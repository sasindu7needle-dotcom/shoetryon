import * as THREE from 'three';

// Works out which way a shoe model faces from its shape alone, so a GLB from
// Tripo (whose axes vary from shoe to shoe) can be stood on the tracked foot
// without anyone turning it by hand:
//  - down: the sole is the one large flat face at an extreme of the model;
//  - toes: the forefoot (ball of the foot) is wider than the heel;
//  - big toe side: the toe tip leans towards the big toe and the arch cuts
//    in on the same side, so a left shoe is told from a right one.
// Only the six axis directions are considered: generators export shoes
// axis-aligned, just not consistently which axis is which.

const SAMPLE_COUNT = 24000;
const AXES = [new THREE.Vector3(1, 0, 0), new THREE.Vector3(0, 1, 0), new THREE.Vector3(0, 0, 1)];

/**
 * Points spread evenly over the model's surface (by triangle area), in the
 * model's own space, so dense and sparse parts of a mesh count the same.
 *
 * @returns {{ points: THREE.Vector3[], normals: THREE.Vector3[], area: number }}
 */
export function sampleSurface(object, count = SAMPLE_COUNT) {
    object.updateMatrixWorld(true);
    const triangles = [];
    let totalArea = 0;
    const a = new THREE.Vector3();
    const b = new THREE.Vector3();
    const c = new THREE.Vector3();
    object.traverse((node) => {
        const position = node.isMesh ? node.geometry?.attributes?.position : null;
        if (!position) return;
        const index = node.geometry.index;
        const triangleCount = (index ? index.count : position.count) / 3;
        for (let i = 0; i < triangleCount; i++) {
            const [ia, ib, ic] = index ? [index.getX(3 * i), index.getX(3 * i + 1), index.getX(3 * i + 2)] : [3 * i, 3 * i + 1, 3 * i + 2];
            a.fromBufferAttribute(position, ia).applyMatrix4(node.matrixWorld);
            b.fromBufferAttribute(position, ib).applyMatrix4(node.matrixWorld);
            c.fromBufferAttribute(position, ic).applyMatrix4(node.matrixWorld);
            const normal = new THREE.Vector3().subVectors(c, b).cross(new THREE.Vector3().subVectors(a, b));
            const area = normal.length() / 2;
            if (!(area > 0)) continue;
            totalArea += area;
            triangles.push({ a: a.clone(), b: b.clone(), c: c.clone(), normal: normal.normalize(), cumulative: totalArea });
        }
    });

    const points = [];
    const normals = [];
    if (!triangles.length) return { points, normals, area: 0 };
    // A fixed sequence instead of Math.random(), so a shoe always gets the same answer.
    let seed = 12345;
    const random = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;
        return seed / 4294967296;
    };
    for (let i = 0; i < count; i++) {
        const target = random() * totalArea;
        let low = 0;
        let high = triangles.length - 1;
        while (low < high) {
            const middle = (low + high) >> 1;
            if (triangles[middle].cumulative < target) low = middle + 1;
            else high = middle;
        }
        const triangle = triangles[low];
        let u = random();
        let v = random();
        if (u + v > 1) {
            u = 1 - u;
            v = 1 - v;
        }
        points.push(new THREE.Vector3()
            .copy(triangle.a)
            .addScaledVector(new THREE.Vector3().subVectors(triangle.b, triangle.a), u)
            .addScaledVector(new THREE.Vector3().subVectors(triangle.c, triangle.a), v));
        normals.push(triangle.normal);
    }
    return { points, normals, area: totalArea };
}

function extentAlong(points, axis) {
    let min = Infinity;
    let max = -Infinity;
    for (const point of points) {
        const value = point.dot(axis);
        if (value < min) min = value;
        if (value > max) max = value;
    }
    return { min, max, size: max - min };
}

// How much of the face at the model's far end in this direction is flat, as a
// share of that face's bounding rectangle: the sole fills most of its
// footprint, while the open top or a side wall (even a boot's tall shaft)
// leaves most of its rectangle empty.
function flatEndScore({ points, normals, area }, direction) {
    const { max, size } = extentAlong(points, direction);
    const [first, second] = AXES.filter((axis) => Math.abs(axis.dot(direction)) < 0.5).map((axis) => extentAlong(points, axis).size);
    if (!size || !first || !second) return 0;
    const edge = max - 0.06 * size;
    let flat = 0;
    for (let i = 0; i < points.length; i++) {
        if (points[i].dot(direction) >= edge && Math.abs(normals[i].dot(direction)) > 0.85) flat++;
    }
    return ((flat / points.length) * area) / (first * second);
}

// How much surface near the model's far end in this direction faces that way:
// a sole covers the whole footprint, a heel's back only a small patch.
function facingCount({ points, normals }, direction) {
    const { max, size } = extentAlong(points, direction);
    const edge = max - 0.1 * size;
    let count = 0;
    for (let i = 0; i < points.length; i++) {
        if (points[i].dot(direction) >= edge && normals[i].dot(direction) > 0.7) count++;
    }
    return count;
}

// glTF's up is +y, and Tripo and the sample shoes follow it. Shape alone is
// not enough: on a smooth generated shoe the flat back of the heel scores as
// flat as the sole, and a boot's side wall has more surface facing out than
// its sole. So the sole stays at -y unless another end beats it on both
// counts, flatness and twice the surface facing out (a model exported on its
// side or upside down). Keep in step with scripts/build-deepar-effect.py.
const GLTF_DOWN = new THREE.Vector3(0, -1, 0);
const DOWN_OVERRIDE_RATIO = 2;

/**
 * The model's down, toe and big-toe directions, each one of its ±x/±y/±z axes,
 * with how sure each guess is (0 = a coin toss).
 *
 * @returns {{ down: THREE.Vector3, toe: THREE.Vector3, bigToe: THREE.Vector3, isLeftShoe: boolean, confidence: { down: number, toe: number, side: number } }}
 */
export function detectShoeAxes(surface) {
    const { points } = surface;
    const candidates = AXES.flatMap((axis) => [axis.clone(), axis.clone().negate()])
        .map((direction) => ({ direction, flat: flatEndScore(surface, direction), facing: facingCount(surface, direction) }));
    const gltf = candidates.find((candidate) => candidate.direction.equals(GLTF_DOWN));
    const override = candidates
        .filter((candidate) => candidate !== gltf && candidate.flat > gltf.flat && candidate.facing >= DOWN_OVERRIDE_RATIO * Math.max(gltf.facing, 1))
        .sort((first, second) => second.flat - first.flat)[0];
    const chosen = override ?? gltf;
    const down = chosen.direction;
    const runnerUp = Math.max(...candidates.filter((candidate) => candidate !== chosen).map((candidate) => candidate.facing));
    const downConfidence = chosen.facing ? Math.max(0, 1 - runnerUp / chosen.facing) : 0;

    // Length: the longer of the two axes across the sole.
    const across = AXES.filter((axis) => Math.abs(axis.dot(down)) < 0.5)
        .map((axis) => ({ axis, size: extentAlong(points, axis).size }))
        .sort((first, second) => second.size - first.size);
    const lengthAxis = across[0].axis.clone();
    const widthAxis = across[1].axis.clone();

    // The footprint: the bottom of the shoe (sole and welt, at most a tenth of
    // its length up, so a boot's ankle doesn't count), where straps, laces and
    // the collar don't change the outline.
    const height = extentAlong(points, down);
    const fullLength = extentAlong(points, lengthAxis);
    const soleDepth = Math.min(0.25 * height.size, 0.1 * fullLength.size);
    const sole = points.filter((point) => point.dot(down) >= height.max - soleDepth);
    const length = extentAlong(sole, lengthAxis);
    const width = extentAlong(sole, widthAxis);
    const midline = (width.min + width.max) / 2;
    const BINS = 20;
    const bins = Array.from({ length: BINS }, () => ({ min: Infinity, max: -Infinity, sum: 0, count: 0 }));
    for (const point of sole) {
        const along = (point.dot(lengthAxis) - length.min) / (length.size || 1);
        const bin = bins[Math.min(BINS - 1, Math.max(0, Math.floor(along * BINS)))];
        const sideways = point.dot(widthAxis);
        bin.min = Math.min(bin.min, sideways);
        bin.max = Math.max(bin.max, sideways);
        bin.sum += sideways - midline;
        bin.count++;
    }
    const widthOf = (bin) => (bin.count ? bin.max - bin.min : 0);
    const widestIn = (from, to) => Math.max(...bins.slice(from, to).map(widthOf));

    // Toes: the forefoot is wider than the heel. When the outline can't tell
    // (a boot's sole can be as wide at both ends), the collar or shaft decides:
    // the top of a shoe sits over the heel.
    const widestHigh = widestIn(BINS / 2, BINS - 1);
    const widestLow = widestIn(1, BINS / 2);
    const widthLean = (widestHigh - widestLow) / Math.max(widestHigh, widestLow, 1e-9);
    const top = points.filter((point) => point.dot(down) <= height.min + 0.3 * height.size);
    const topMiddle = top.reduce((total, point) => total + point.dot(lengthAxis), 0) / (top.length || 1);
    const collarLean = (fullLength.min + fullLength.max) / 2 - topMiddle;
    const collarConfidence = Math.abs(collarLean) / (fullLength.size || 1);
    const isWidthClear = Math.abs(widthLean) >= 0.05 || Math.abs(widthLean) >= collarConfidence;
    const toeLean = isWidthClear ? widthLean : collarLean;
    const toe = toeLean >= 0 ? lengthAxis.clone() : lengthAxis.clone().negate();
    const toeConfidence = isWidthClear ? Math.abs(widthLean) : collarConfidence;

    // Big toe: towards the side the toe tip leans and the arch is cut away from.
    const isToeHigh = toe.dot(lengthAxis) > 0;
    const region = (from, to) => {
        const [start, end] = isToeHigh ? [from, to] : [BINS - to, BINS - from];
        const picked = bins.slice(start, end);
        const count = picked.reduce((total, bin) => total + bin.count, 0);
        return count ? picked.reduce((total, bin) => total + bin.sum, 0) / count : 0;
    };
    const toeTip = region(16, 20);
    const arch = region(7, 11);
    const lean = (toeTip - arch) / (width.size || 1);
    const bigToe = widthAxis.clone().multiplyScalar(lean >= 0 ? 1 : -1);

    // A right shoe has its big toe on the right-handed x of (up, toe), i.e. up × toe.
    const up = down.clone().negate();
    const isLeftShoe = bigToe.dot(new THREE.Vector3().crossVectors(up, toe)) < 0;

    return {
        down,
        toe,
        bigToe,
        isLeftShoe,
        confidence: { down: downConfidence, toe: toeConfidence, side: Math.abs(lean) },
    };
}

/**
 * The rotation (and, for a left shoe, mirror) that stands the model on the
 * foot: sole down (-y), toes forward (+z), big toe on the +x side, which is
 * how DeepAR's right foot is laid out. A left shoe is only mirrored when the
 * outline says so clearly; otherwise it is left as it is.
 */
export function orientationMatrix(axes, { minimumSideConfidence = 0.03 } = {}) {
    const up = axes.down.clone().negate();
    const toe = axes.toe;
    const rightHandedX = new THREE.Vector3().crossVectors(up, toe);
    const mirror = axes.isLeftShoe && axes.confidence.side >= minimumSideConfidence;
    const x = mirror ? rightHandedX.negate() : rightHandedX;
    return new THREE.Matrix4().set(
        x.x, x.y, x.z, 0,
        up.x, up.y, up.z, 0,
        toe.x, toe.y, toe.z, 0,
        0, 0, 0, 1,
    );
}
