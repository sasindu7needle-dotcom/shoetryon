<?php

/*
| Shoe catalog for the DeepAR try-on page.
|
| `glb` / `thumb` are your source assets (public/models/samples).
| `effect` is the DeepAR effect file (public/effects/shoes/*.deepar). DeepAR cannot
| load .glb at runtime: import each GLB into DeepAR Studio (shoe try-on template),
| export the .deepar file and drop it in public/effects/shoes using the name below.
|
| `fit` places the shoe on the tracked foot. It is applied at runtime to the
| template's `model_right` node and mirrored onto `model_left`, so it overrides
| whatever transform Studio exported. Units are DeepAR foot space of the right
| foot: the foot occluder runs from the heel at z = -14.8 to the toes at
| z = 5.9 (after the 0.6 forward shift tryon.js gives it, see below), its sole
| is at y = -1.9 with up along +Y, and the big toe side is +X.
| Rotation is Euler degrees. Studio imports GLBs inconsistently (it dropped the
| canvas sneaker's root rotation and scale but kept the street runner's), so
| each effect needs its own values. The ballet flat and rain boot effects come
| from scripts/build-deepar-effect.py, which keeps the GLB's metres and axes.
| The optional `stretch` multiplies the size along the foot's own axes
| [width, height, length], for a shoe whose shape differs from DeepAR's foot.
|
| The values line up the heel and toe anchors in the GLB's `tryOnFit` extras
| with the occluder's heel and toes (scaled up to 6% for tracking jitter),
| centre the shoe across the foot (the occluder sits 0.5-0.75 units towards the
| big toe) and put the outsole on the occluder's sole. Checked against the
| occluder mesh, the foot then stays inside the shoe everywhere except where
| the leg leaves through the collar.
|
| Phone tests showed real feet sitting about 0.6 units further forward than
| DeepAR's occluder: shoes fitted to it sat too far back (the flat's heel stuck
| out about 2 cm, the big toe showed beside the boot), and moving only the
| shoes forward put the flat's heel counter inside the occluder, which hid it.
| tryon.js therefore moves the occluder forward 0.6 and all fits are measured
| against the moved occluder. The boot is scaled up 6% so the big toe can't
| poke through its toe cap.
|
| DeepAR's foot is wider and blunter than the slim, pointed ballet flat. Scaled
| to its anchors, the foot comes out through the flat's side walls, its heel
| counter and the inner front corner of the toe box (the big toe). An earlier
| fit scaled the whole flat up 7% instead, which pushed its toe 3 units past
| the occluder's toes and its opening to z = 3.6, well over DeepAR's toes
| (z = 0 to 5.9), so phones showed bare toes inside the flat. The flat is now
| at its anchor scale and stretched 26% wider and 6% taller: its toe ends at
| z = 7.5 and its opening at z = 2.6, and rendered from 23 phone-like views the
| shoe pixels where the foot shows through drop from 557 to 4.
|
| The rain boot's shaft sat off-centre on DeepAR's leg column, which came up to
| 0.5 units out through the outer back of the shaft above the ankle and showed
| the camera image there. The boot is stretched 5% wider and moved 0.2 towards
| the outside, which keeps the leg inside the shaft from every side.
*/
return [
    [
        'id' => 'ballet-flat',
        'name' => 'Ballet Flat',
        'glb' => 'models/samples/ballet-flat.glb',
        'thumb' => 'models/samples/ballet-flat.webp',
        'effect' => 'effects/shoes/ballet-flat.deepar',
        'fit' => ['scale' => 90.9, 'stretch' => [1.26, 1.06, 1], 'rotation' => [0, -92, 0], 'position' => [0.77, -1.78, -3.89]],
    ],
    [
        'id' => 'canvas-sneaker',
        'name' => 'Canvas Sneaker',
        'glb' => 'models/samples/canvas-sneaker.glb',
        'thumb' => 'models/samples/canvas-sneaker.webp',
        'effect' => 'effects/shoes/canvas-sneaker.deepar',
        'fit' => ['scale' => 17.7, 'rotation' => [-90, 180, 0], 'position' => [0.5, 3.5, -5.1]],
    ],
    [
        'id' => 'rain-boot',
        'name' => 'Rain Boot',
        'glb' => 'models/samples/rain-boot.glb',
        'thumb' => 'models/samples/rain-boot.webp',
        'effect' => 'effects/shoes/rain-boot.deepar',
        'fit' => ['scale' => 95.4, 'stretch' => [1.05, 1, 1], 'rotation' => [0, -90, 0], 'position' => [0.55, -1.87, -4.84]],
    ],
    [
        'id' => 'street-runner',
        'name' => 'Street Runner',
        'glb' => 'models/samples/street-runner.glb',
        'thumb' => 'models/samples/street-runner.webp',
        'effect' => 'effects/shoes/street-runner.deepar',
        'fit' => ['scale' => 88.4, 'rotation' => [0, -90, 0], 'position' => [0.75, -1.8, -4.85]],
    ],
];
