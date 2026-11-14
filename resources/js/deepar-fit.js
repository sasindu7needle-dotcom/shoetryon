// Places a shoe inside a DeepAR shoe effect (Studio's template, or one built
// by scripts/build-deepar-effect.py) from its fit, for the try-on and the
// admin's feet preview.

// Shoe model nodes of DeepAR Studio's shoe try-on template, one per foot. The
// template builds the left foot as a mirror image of the right one across the
// foot's x axis (its left foot occluder has a -1 x scale), so the left shoe
// needs the same mirror or it renders as a second right shoe.
const MODEL_NODES = { model_right: 1, model_left: -1 };

// DeepAR's tracked foot occluder sits about 0.6 units (7 mm) behind real feet.
// Phone tests showed shoes fitted to it sitting too far back, and shoes moved
// forward to match the foot losing their heel counter inside the occluder, so
// the occluder itself is moved forward. The fits in config/shoes.php are
// measured against the moved occluder.
const OCCLUDER_NODES = ['foot_occluder_right', 'foot_occluder_left'];
const OCCLUDER_SHIFT = 0.6;

// The parents of the model nodes. They have no rotation, so a scale set on them
// works along the foot's own axes (width, height, length), whatever rotation
// the shoe's model node needs.
const STRETCH_NODES = ['shopar_shoe_right_origin_compensator', 'shopar_shoe_left_origin_compensator'];

// Studio exports the GLB at its original size (metres) and with inconsistent
// axes, which leaves the shoe a few millimetres long inside the foot. Place it
// on the foot using the per-shoe values from config/shoes.php. They are for the
// right foot; the left foot gets their mirror image (x scale and x position
// flipped, y and z rotation negated), the same way Studio exports it. An
// optional `stretch` widens (or lengthens) a shoe that is a different shape
// from DeepAR's foot.
export function applyFit(ar, fit) {
    for (const node of OCCLUDER_NODES) {
        ar.changeParameterVector(node, '', 'position', 0, 0, OCCLUDER_SHIFT, 0);
    }
    if (!fit) return;
    const s = fit.scale;
    const [sx, sy, sz] = fit.stretch ?? [1, 1, 1];
    const [rx, ry, rz] = fit.rotation;
    const [px, py, pz] = fit.position;
    for (const node of STRETCH_NODES) {
        ar.changeParameterVector(node, '', 'scale', sx, sy, sz, 0);
    }
    for (const [node, m] of Object.entries(MODEL_NODES)) {
        ar.changeParameterVector(node, '', 'scale', m * s, s, s, 0);
        ar.changeParameterVector(node, '', 'rotation', rx, m * ry, m * rz, 0);
        // The parent's stretch scales this position too; undo it so `position`
        // stays in foot units.
        ar.changeParameterVector(node, '', 'position', (m * px) / sx, py / sy, pz / sz, 0);
    }
}
