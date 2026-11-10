"""Build a DeepAR shoe effect (.deepar) from a single-mesh PBR GLB, using a Studio-exported
shoe effect as the template (street-runner.deepar: one mesh, one pbr_gltf material with
albedo, normal, metal-roughness and AO maps, plus the foot occluders and template nodes).

The template is copied unchanged and the new data is appended at the end of the file;
FlatBuffer offsets point forward, so the template's tables are repointed at it:

- both shoe meshes get the GLB geometry as raw vertices (the layout Studio decodes its
  Draco meshes into: position, RGBA8 colour, uv0, uv1, normal, tangent; 56 bytes) with
  u16 indices, and an empty Draco blob
- the albedo, normal and metal-roughness textures get 10-level WebP mip chains
- the material gets the GLB's metallic/roughness factors and no AO
- the template's inner shoe nodes are reset to identity, because the GLB's node
  transforms are baked into the vertices (metres, toe +X, up +Y, sole at y = 0)

Usage (needs Pillow: pip install pillow):

    python scripts/build-deepar-effect.py public/effects/shoes/street-runner.deepar \n        public/models/samples/<shoe>.glb public/effects/shoes/<shoe>.deepar <shoe> \n        [--leg-occluder] [--set u_name=value ...]

--leg-occluder adds an occluder for the leg inside a tall shaft (boots), built from the
shaft's inner wall and the GLB's tryOnFit occlusion sections. --set overrides a material
uniform, e.g. --set u_specularIntensity=0.4 to stop a glossy finish looking like chrome
when seen edge-on.
"""
import io
import json
import math
import struct
import sys

from PIL import Image


# Schema-less FlatBuffer access, enough to walk the .deepar tables.
class FB:
    def __init__(s,d): s.d=d; s.N=len(d)
    def u32(s,o): return struct.unpack_from('<I',s.d,o)[0]
    def i32(s,o): return struct.unpack_from('<i',s.d,o)[0]
    def u16(s,o): return struct.unpack_from('<H',s.d,o)[0]
    def f32(s,o): return struct.unpack_from('<f',s.d,o)[0]
    def string(s,o):
        if o<0 or o+4>s.N: return None
        n=s.u32(o)
        if n==0 or n>400 or o+4+n>=s.N or s.d[o+4+n]!=0: return None
        b=s.d[o+4:o+4+n]
        return b.decode() if all(32<=c<127 for c in b) else None
    def table(s,t):
        if t<0 or t+4>s.N or t%4: return None
        vt=t-s.i32(t)
        if vt<0 or vt+4>s.N or vt%2: return None
        vs=s.u16(vt); ts=s.u16(vt+2)
        if vs<4 or vs>120 or vs%2 or ts<4 or ts>2000: return None
        f=[s.u16(vt+4+2*i) for i in range((vs-4)//2)]
        if any(x>=ts or (x and x<4) for x in f): return None
        return f,ts
    def field(s,t,i):
        f,_=s.table(t)
        return t+f[i] if i<len(f) and f[i] else None
    def deref(s,o): return o+s.u32(o)
    def classify(s,o):
        """Guess what the 4 bytes at o reference."""
        v=s.u32(o); t=o+v
        if v==0 or t>=s.N: return ('scalar',v)
        st=s.string(t)
        if st is not None: return ('string',st)
        if s.table(t): return ('table',t)
        n=s.u32(t)
        if 0<n and t+4+n<=s.N: return ('vector',t)
        return ('scalar',v)


# Minimal GLB reader: accessors and embedded images.
def read(path):
    d=open(path,'rb').read()
    L=struct.unpack_from('<I',d,12)[0]; j=json.loads(d[20:20+L])
    bin_off=20+L+8
    def acc(i):
        a=j['accessors'][i]; bv=j['bufferViews'][a['bufferView']]
        comp={'SCALAR':1,'VEC2':2,'VEC3':3,'VEC4':4}[a['type']]
        fmt,sz={5126:('f',4),5123:('H',2),5125:('I',4),5121:('B',1)}[a['componentType']]
        off=bin_off+bv.get('byteOffset',0)+a.get('byteOffset',0); st=bv.get('byteStride',sz*comp)
        return [struct.unpack_from('<%d%s'%(comp,fmt),d,off+k*st) for k in range(a['count'])]
    def image(i):
        im=j['images'][i]; bv=j['bufferViews'][im['bufferView']]
        o=bin_off+bv.get('byteOffset',0); return d[o:o+bv['byteLength']]
    return j,acc,image


VERTEX = struct.Struct('<3f4B2f2f3f3f')
assert VERTEX.size == 56
WEBP_TEXTURE_FORMAT = 20231031


def quat_matrix(q):
    x, y, z, w = q
    return [[1 - 2 * (y * y + z * z), 2 * (x * y - z * w), 2 * (x * z + y * w)],
            [2 * (x * y + z * w), 1 - 2 * (x * x + z * z), 2 * (y * z - x * w)],
            [2 * (x * z - y * w), 2 * (y * z + x * w), 1 - 2 * (x * x + y * y)]]


def node_matrix(n):
    r = quat_matrix(n.get('rotation', [0, 0, 0, 1]))
    s = n.get('scale', [1, 1, 1])
    t = n.get('translation', [0, 0, 0])
    return [[r[i][0] * s[0], r[i][1] * s[1], r[i][2] * s[2], t[i]] for i in range(3)] + [[0, 0, 0, 1]]


def mul(a, b):
    return [[sum(a[i][k] * b[k][j] for k in range(4)) for j in range(4)] for i in range(4)]


def normalize(v):
    length = math.sqrt(sum(c * c for c in v)) or 1.0
    return [c / length for c in v]


def load_glb(path):
    """The GLB's mesh in scene space, plus its material and textures."""
    j, acc, image = read(path)
    found = []

    def walk(i, m):
        n = j['nodes'][i]
        m = mul(m, node_matrix(n))
        if 'mesh' in n:
            found.append((n['mesh'], m))
        for c in n.get('children', []):
            walk(c, m)

    identity = [[1, 0, 0, 0], [0, 1, 0, 0], [0, 0, 1, 0], [0, 0, 0, 1]]
    for root in j['scenes'][0]['nodes']:
        walk(root, identity)
    assert len(found) == 1, 'expected one mesh node'
    mesh_index, m = found[0]
    prims = j['meshes'][mesh_index]['primitives']
    assert len(prims) == 1, 'expected one primitive'
    prim = prims[0]

    positions = [[sum(m[i][k] * p[k] for k in range(3)) + m[i][3] for i in range(3)]
                 for p in acc(prim['attributes']['POSITION'])]
    linear = [[m[i][k] for k in range(3)] for i in range(3)]
    det = (linear[0][0] * (linear[1][1] * linear[2][2] - linear[1][2] * linear[2][1])
           - linear[0][1] * (linear[1][0] * linear[2][2] - linear[1][2] * linear[2][0])
           + linear[0][2] * (linear[1][0] * linear[2][1] - linear[1][1] * linear[2][0]))
    assert det > 0, 'mirrored node transforms are not supported'
    # Uniform scale and rotation only, so the linear part also transforms normals.
    normals = [normalize([sum(linear[i][k] * n[k] for k in range(3)) for i in range(3)])
               for n in acc(prim['attributes']['NORMAL'])]
    uvs = acc(prim['attributes']['TEXCOORD_0'])
    indices = [i[0] for i in acc(prim['indices'])]
    material = j['materials'][prim.get('material', 0)]

    def texture_bytes(ref):
        tex = j['textures'][ref['index']]
        src = tex.get('extensions', {}).get('EXT_texture_webp', {}).get('source', tex.get('source'))
        return image(src)

    pbr = material.get('pbrMetallicRoughness', {})
    textures = {
        'albedo': texture_bytes(pbr['baseColorTexture']),
        'normal': texture_bytes(material['normalTexture']),
        'metal_roughness': texture_bytes(pbr['metallicRoughnessTexture']),
    }
    factors = {
        'diffuse': pbr.get('baseColorFactor', [1, 1, 1, 1]),
        'metalness': pbr.get('metallicFactor', 1.0),
        'roughness': pbr.get('roughnessFactor', 1.0),
        'normal_scale': material['normalTexture'].get('scale', 1.0),
    }
    return positions, normals, uvs, indices, textures, factors


def tangents(positions, normals, uvs, indices):
    """Per-vertex dP/du, orthogonalised against the normal (matches Studio's tangents)."""
    acc = [[0.0, 0.0, 0.0] for _ in positions]
    for f in range(0, len(indices), 3):
        a, b, c = indices[f:f + 3]
        e1 = [positions[b][q] - positions[a][q] for q in range(3)]
        e2 = [positions[c][q] - positions[a][q] for q in range(3)]
        du1, dv1 = uvs[b][0] - uvs[a][0], uvs[b][1] - uvs[a][1]
        du2, dv2 = uvs[c][0] - uvs[a][0], uvs[c][1] - uvs[a][1]
        r = du1 * dv2 - du2 * dv1
        if abs(r) < 1e-12:
            continue
        t = [(e1[q] * dv2 - e2[q] * dv1) / r for q in range(3)]
        for v in (a, b, c):
            for q in range(3):
                acc[v][q] += t[q]
    out = []
    for t, n in zip(acc, normals):
        d = sum(t[q] * n[q] for q in range(3))
        t = [t[q] - n[q] * d for q in range(3)]
        if sum(c * c for c in t) < 1e-20:
            # No UV gradient: any vector perpendicular to the normal.
            t = [n[1], -n[0], 0.0] if abs(n[2]) < 0.9 else [0.0, n[2], -n[1]]
        out.append(normalize(t))
    return out


def slice_points(positions, indices, y, spacing=0.002):
    """Points where the mesh surface crosses the plane at height y."""
    out = []
    for f in range(0, len(indices), 3):
        tri = [positions[i] for i in indices[f:f + 3]]
        crossings = []
        for a, b in ((0, 1), (1, 2), (2, 0)):
            pa, pb = tri[a], tri[b]
            if (pa[1] - y) * (pb[1] - y) < 0:
                t = (y - pa[1]) / (pb[1] - pa[1])
                crossings.append([pa[q] + (pb[q] - pa[q]) * t for q in range(3)])
        if len(crossings) == 2:
            pa, pb = crossings
            n = max(1, int(math.dist(pa, pb) / spacing))
            out += [[pa[q] + (pb[q] - pa[q]) * i / n for q in range(3)] for i in range(n + 1)]
    return out


def leg_occluder(positions, indices, sections, start=0.12, step=0.03, inset=0.88, bins=32):
    """A closed tube for the leg inside a boot shaft and above its cuff, in shoe space.

    DeepAR's own foot occluder stops about 0.2 m above the sole, so in a tall boot the
    camera would see the shaft's lining where the leg should be. Inside the shaft each
    ring follows the shaft's inner wall (scaled by `inset`, so it can't poke through the
    boot); above the cuff it uses the GLB's tryOnFit occlusion sections (the calf).
    """

    first = min(sections, key=lambda s: s['center'][1])
    cx, cz = first['center'][0], first['center'][2]

    def inner_profile(y):
        band = [p for p in slice_points(positions, indices, y) if math.hypot(p[0] - cx, p[2] - cz) < 0.09]
        radii = [None] * bins
        for p in band:
            a = math.atan2(p[2] - cz, p[0] - cx)
            k = int((a + math.pi) / (2 * math.pi) * bins) % bins
            r = math.hypot(p[0] - cx, p[2] - cz)
            radii[k] = r if radii[k] is None else min(radii[k], r)
        known = [r for r in radii if r is not None]
        assert len(known) > bins // 2, f'shaft wall not found at y={y:.3f}'
        for k in range(bins):
            if radii[k] is None:
                # Nearest known neighbour on either side.
                for d in range(1, bins):
                    r = radii[(k - d) % bins] or radii[(k + d) % bins]
                    if r:
                        radii[k] = r
                        break
        return [inset * r for r in radii]

    def angle(k):
        return -math.pi + (k + 0.5) * 2 * math.pi / bins

    def section_profile(s):
        rx, rz = s['radius']
        scale = s.get('radialScale') or [1.0]
        return [math.hypot(rx * math.cos(angle(k)), rz * math.sin(angle(k))) * scale[k * len(scale) // bins]
                for k in range(bins)]

    meta = sorted(sections, key=lambda s: s['center'][1])
    rings = []
    y = start
    while y < meta[0]['center'][1] - 1e-6:
        rings.append((y, cx, cz, inner_profile(y)))
        y += step
    for s in meta:
        profile = section_profile(s)
        try:
            # Where the section is still inside the shaft, keep it off the wall.
            profile = [min(a, b) for a, b in zip(profile, inner_profile(s['center'][1]))]
        except AssertionError:
            pass  # above the cuff
        rings.append((s['center'][1], s['center'][0], s['center'][2], profile))

    verts, normals, faces = [], [], []
    for y, rcx, rcz, radii in rings:
        for k in range(bins):
            a = angle(k)
            verts.append([rcx + radii[k] * math.cos(a), y, rcz + radii[k] * math.sin(a)])
            normals.append([math.cos(a), 0.0, math.sin(a)])
    for i in range(len(rings) - 1):
        for k in range(bins):
            a, b = i * bins + k, i * bins + (k + 1) % bins
            c, d = a + bins, b + bins
            # Counter-clockwise seen from outside (angle grows from +x towards +z).
            faces += [a, c, b, b, c, d]
    for ring, up in ((0, -1.0), (len(rings) - 1, 1.0)):
        y, rcx, rcz, _ = rings[ring]
        centre = len(verts)
        verts.append([rcx, y, rcz])
        normals.append([0.0, up, 0.0])
        for k in range(bins):
            a, b = ring * bins + k, ring * bins + (k + 1) % bins
            faces += [centre, b, a] if up > 0 else [centre, a, b]
    return verts, normals, faces


def encode_webp(img, lossless):
    buf = io.BytesIO()
    if lossless:
        img.save(buf, 'WEBP', lossless=True, quality=100, method=6)
    else:
        img.save(buf, 'WEBP', quality=90, method=6)
    return buf.getvalue()


def mip_chain(webp, lossless, linear=False):
    """Level 0 is the original image; smaller levels are box-filtered and re-encoded.

    `linear` is for data textures (normals, metal-roughness). The pbr_gltf shader
    decodes every texture with pow(x, 2.2), so Studio stores these gamma-encoded
    (a flat normal's 0.5 becomes 0.73). Stored as they are in the GLB, a flat
    normal decodes to 0.22 and tilts every shading normal by about 38 degrees,
    and a roughness of 0.25 becomes a mirror-like 0.05.
    """
    img = Image.open(io.BytesIO(webp))
    img.load()
    w, h = img.size
    assert w == h and w & (w - 1) == 0, f'texture must be square power of two, got {w}x{h}'
    mode = 'RGBA' if 'A' in img.getbands() and not linear else 'RGB'
    img = img.convert(mode)
    if linear:
        img = img.point([round(255 * (v / 255) ** (1 / 2.2)) for v in range(256)] * 3)
        webp = encode_webp(img, lossless)
    levels = [webp]
    size = w
    while size > 1:
        size //= 2
        levels.append(encode_webp(img.resize((size, size), Image.Resampling.BOX), lossless))
    offsets, data = [], bytearray()
    for level in levels:
        offsets.append(len(data))
        data += level
    return bytes(data), offsets, w, h


class Patcher:
    def __init__(self, data):
        self.fb = FB(bytes(data))
        self.out = bytearray(data)

    def _pad(self, align):
        while len(self.out) % align:
            self.out += b'\0'

    def vector(self, payload, count, align=4):
        """Appends a vector; returns the offset of its length field."""
        while (len(self.out) + 4) % max(align, 4):
            self.out += b'\0'
        at = len(self.out)
        self.out += struct.pack('<I', count) + payload
        self._pad(4)
        return at

    def string(self, s):
        self._pad(4)
        at = len(self.out)
        raw = s.encode()
        self.out += struct.pack('<I', len(raw)) + raw + b'\0'
        self._pad(4)
        return at

    def clone_table(self, t):
        """Appends a copy of table t that shares its vtable; returns the copy's offset.
        Its offset fields still need repointing, since their targets are behind it."""
        _, size = self.fb.table(t)
        self._pad(8)
        at = len(self.out)
        self.out += self.fb.d[t:t + size]
        struct.pack_into('<i', self.out, at, at - (t - self.fb.i32(t)))
        return at

    def point(self, field_at, target):
        assert target > field_at, 'offsets must point forward'
        struct.pack_into('<I', self.out, field_at, target - field_at)

    def field(self, table, i):
        at = self.fb.field(table, i)
        assert at is not None, f'table {table:#x} has no field {i}'
        return at

    def set_u32(self, table, i, value):
        struct.pack_into('<I', self.out, self.field(table, i), value)

    def set_floats(self, table, i, values):
        vec = self.fb.deref(self.field(table, i))
        n = self.fb.u32(vec)
        assert len(values) <= n, (len(values), n)
        struct.pack_into('<%df' % len(values), self.out, vec + 4, *values)


def tables(fb, vec):
    return [fb.deref(vec + 4 + 4 * k) for k in range(fb.u32(vec))]


def name_of(fb, t):
    return fb.string(fb.deref(fb.field(t, 0)))


def walk_nodes(fb, t, out):
    out.append(t)
    ch = fb.field(t, 1)
    if ch:
        for c in tables(fb, fb.deref(ch)):
            walk_nodes(fb, c, out)


def build(template_path, glb_path, out_path, prefix, with_leg_occluder=False, uniforms=None):
    positions, normals, uvs, indices, textures, factors = load_glb(glb_path)
    assert len(positions) < 65536, 'u16 indices only'
    tans = tangents(positions, normals, uvs, indices)
    vertices = b''.join(VERTEX.pack(*p, 0, 0, 0, 0, *uv, 0.0, 0.0, *n, *t)
                        for p, uv, n, t in zip(positions, uvs, normals, tans))

    p = Patcher(open(template_path, 'rb').read())
    fb = p.fb
    root = fb.deref(0)
    meshes = tables(fb, fb.deref(fb.field(root, 2)))
    materials = tables(fb, fb.deref(fb.field(root, 3)))
    texs = tables(fb, fb.deref(fb.field(root, 4)))

    # Meshes. DeepAR wants one mesh per node (Studio even duplicates the foot
    # occluder per foot), so a fresh mesh list is appended: the template's four
    # meshes with the shoe geometry swapped in, plus a leg occluder per foot for
    # boots. Old tables are left unused; offsets only point forward, so the new
    # list, then its tables, then their data are written in that order.
    names = [name_of(fb, m) for m in meshes]
    assert names[0] == names[2] == 'Scene/shoe.armesh' and 'occluder' in names[1] and 'occluder' in names[3], names
    shoe_fields = {
        0: ('string', f'{prefix}/shoe.armesh'),
        1: ('vector', vertices, len(positions)),
        2: ('vector', struct.pack('<%dH' % len(indices), *indices), len(indices)),
        8: ('vector', b'', 0),
    }
    plan = [(meshes[0], shoe_fields), (meshes[1], {}), (meshes[2], shoe_fields), (meshes[3], {})]
    if with_leg_occluder:
        j, _, _ = read(glb_path)
        volumes = j['scenes'][0]['extras']['tryOnFit']['occlusion']['volumes']
        verts, nrms, faces = leg_occluder(positions, indices, volumes[0]['sections'])
        assert len(verts) < 65536
        leg_fields = {
            0: ('string', f'{prefix}/leg-occluder.armesh'),
            1: ('vector', b''.join(VERTEX.pack(*v, 0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, *n, 0.0, 0.0, 0.0)
                                   for v, n in zip(verts, nrms)), len(verts)),
            2: ('vector', struct.pack('<%dH' % len(faces), *faces), len(faces)),
        }
        plan += [(meshes[1], leg_fields), (meshes[3], leg_fields)]
        print(f'leg occluder: {len(verts)} vertices, {len(faces) // 3} triangles')

    element_size = {1: VERTEX.size, 2: 2, 3: 4, 4: 4, 5: 4, 8: 1}
    mesh_list = p.vector(b'\0' * 4 * len(plan), len(plan))
    clones = [p.clone_table(t) for t, _ in plan]
    for k, c in enumerate(clones):
        p.point(mesh_list + 4 + 4 * k, c)
    # Tables may share data (the shoe geometry is the same for both feet); only the
    # mesh tables themselves must be one per node.
    written = {}
    for (t, fields), c in zip(plan, clones):
        for i, off in enumerate(fb.table(t)[0]):
            if not off:
                continue
            spec = fields.get(i)
            if spec is None:
                src = fb.deref(fb.field(t, i))
                if i == 0:
                    spec = ('string', fb.string(src))
                else:
                    n = fb.u32(src)
                    assert n == 0 or i in element_size, (i, n)
                    spec = ('vector', fb.d[src + 4:src + 4 + n * element_size.get(i, 0)], n)
            key = (spec[0], spec[1])
            if key not in written:
                written[key] = p.string(spec[1]) if spec[0] == 'string' else p.vector(spec[1], spec[2])
            p.point(c + off, written[key])
    p.point(p.field(root, 2), mesh_list)
    p.point(p.field(root, 7), mesh_list)

    # Textures, matched to the template's slots by name.
    slots = {
        'Scene/diffuseMidnight.jpg.webp': ('albedo', False),
        'Scene/normal.jpg.webp': ('normal', True),
        'Scene/occlusionRougnessMetalness.jpg.webp': ('metal_roughness', True),
    }
    seen = set()
    for t in texs:
        slot = slots.get(name_of(fb, t))
        if not slot:
            continue
        role, lossless = slot
        assert fb.u32(fb.field(t, 1)) == WEBP_TEXTURE_FORMAT
        data, offsets, w, h = mip_chain(textures[role], lossless, linear=role != 'albedo')
        p.point(p.field(t, 0), p.string(f'{prefix}/{role}.webp'))
        p.point(p.field(t, 2), p.vector(data, len(data)))
        p.point(p.field(t, 3), p.vector(struct.pack('<%dQ' % len(offsets), *offsets), len(offsets), align=8))
        p.set_u32(t, 6, w)
        p.set_u32(t, 7, h)
        p.set_u32(t, 8, len(offsets))
        seen.add(role)
    assert seen == {'albedo', 'normal', 'metal_roughness'}, seen

    # Material: the GLB's factors. The GLB has no AO map, so AO is switched off
    # (the template samples AO from the metal-roughness texture).
    pbr = [m for m in materials if name_of(fb, m) == 'Scene/phong1SG.mat']
    assert len(pbr) == 1
    mat = pbr[0]
    p.point(p.field(mat, 0), p.string(f'{prefix}/shoe.mat'))
    values = {
        'u_diffuse': factors['diffuse'],
        'u_metalness': [factors['metalness']],
        'u_roughness': [factors['roughness']],
        'u_aoMapIntensity': [0.0],
        'u_normalScale': [factors['normal_scale'], factors['normal_scale']],
    }
    # Per-shoe look tweaks from --set, e.g. u_specularIntensity for glossy rubber.
    values.update({k: [float(x) for x in v.split(',')] for k, v in (uniforms or {}).items()})
    done = set()
    for u in tables(fb, fb.deref(fb.field(mat, 4))):
        nm = name_of(fb, u)
        if nm in values:
            p.set_floats(u, 1, values[nm])
            done.add(nm)
    assert done == set(values), set(values) - done

    # Nodes: the GLB transforms are baked into the vertices, so the template's
    # Shoeobj (which held the runner's own scale and offset) becomes identity.
    all_nodes = []
    scene = fb.deref(fb.field(root, 1))
    walk_nodes(fb, scene, all_nodes)
    identity = [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]
    reset = 0
    for n in all_nodes:
        if name_of(fb, n) in ('Shoeobj', 'Shoeobj_2', 'g_Shoe', 'g_Shoe_2', 'Shoe', 'Shoe_2'):
            p.set_floats(n, 2, identity)
            tr = fb.deref(p.field(n, 11))
            p.set_floats(tr, 0, [0, 0, 0])
            p.set_floats(tr, 2, [1, 1, 1])
            rot = fb.deref(p.field(tr, 1))
            p.set_floats(tr, 1, [0, 0, 0, 1] if fb.u32(rot) == 4 else [0, 0, 0])
            reset += 1
    assert reset == 6, reset

    if with_leg_occluder:
        # The leg occluders hang off the (otherwise empty) g_Shoe group nodes, so
        # they follow the shoe's fit and the left foot's mirror.
        occluder = [k for k, m in enumerate(materials) if name_of(fb, m) == 'occluder']
        assert len(occluder) == 1
        for n in all_nodes:
            mesh = {'g_Shoe': 4, 'g_Shoe_2': 5}.get(name_of(fb, n))
            if mesh is not None:
                struct.pack_into('<i', p.out, p.field(n, 3), mesh)
                struct.pack_into('<i', p.out, p.field(n, 6), occluder[0])

    open(out_path, 'wb').write(p.out)
    print(f'{out_path}: {len(positions)} vertices, {len(indices) // 3} triangles, {len(p.out)} bytes')


if __name__ == '__main__':
    argv = sys.argv[1:]
    sets = dict(argv[i + 1].split('=', 1) for i, a in enumerate(argv) if a == '--set')
    args = [a for i, a in enumerate(argv) if not a.startswith('--') and (i == 0 or argv[i - 1] != '--set')]
    build(*args[:4], with_leg_occluder='--leg-occluder' in argv, uniforms=sets)
