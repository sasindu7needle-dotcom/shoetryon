import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { detectShoeAxes, orientationMatrix, sampleSurface } from './shoe-orientation.js';

// Draws a shoe's GLB on the feet DeepAR tracks, on a transparent canvas above
// DeepAR's camera view. Shoes built in DeepAR Studio don't need this; it is
// how shoes made automatically from photos (Tripo GLB, no .deepar) are worn.

// DeepAR's foot tracker works in units where a foot is about 24 long: the
// shoe effects place a GLB measured in metres (a ~0.27 m shoe) at scale ~90.
export const FOOT_LENGTH = 24;

export const DEFAULT_FIT = { scale: 1, rotation: [0, 0, 0], position: [0, 0, 0] };

// A rough foot, ankle and lower leg, drawn only into the depth buffer, so the
// wearer's own foot shows inside the shoe: the footbed and the inside of the
// shoe are hidden where the foot is, and the back of the collar disappears
// behind the leg, while straps and the upper in front of the foot still
// cover it. Sized from the shoe as it sits on the foot, as fractions of its
// length (L), width (W) and height (H). Toes point towards +z on the foot.
const WEARER = {
    soleTop: 0.2, // the footbed, up from the bottom of the shoe (× H)
    footHalfWidth: 0.37, // × W
    footHalfHeight: 0.45, // above the footbed (× H); stays under the straps
    footHalfLength: 0.46, // × L
    legRadius: 0.17, // × L
    legHeight: 2, // × L
    legFromHeel: 0.25, // the leg's centre, forward of the back of the shoe (× L)
};

const DEG = Math.PI / 180;

// A real foot is about this long. Used when the tracker reports in metres
// instead of its own "24 per foot" units.
const FOOT_LENGTH_METRES = 0.26;

// The field of view DeepAR assumes across the long side of the camera frame
// (see nb() in the SDK): phones 85°, Macs 54°, everything else 90°.
function trackerLongSideFov() {
    const ua = navigator.userAgent;
    if (/Android|iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)) return 85;
    if (/Macintosh|Mac OS X/.test(ua)) return 54;
    return 90;
}

// Turns a camera space whose z axis points into the scene (computer-vision
// style: y down, z forward) into three.js's (y up, looking down -z): a half
// turn about x.
const VISION_TO_GL = new THREE.Quaternion(1, 0, 0, 0);

// The foot and leg occluders, laid out by layoutWearer() once the shoe is known.
function makeWearer() {
    const material = new THREE.MeshBasicMaterial({ colorWrite: false });
    const foot = new THREE.Mesh(new THREE.SphereGeometry(1, 32, 16), material);
    // Unit height, standing on y = 0, wider at the ankle than higher up.
    const leg = new THREE.Mesh(new THREE.CylinderGeometry(1 / 1.15, 1, 1, 24).translate(0, 0.5, 0), material);
    const wearer = new THREE.Group();
    wearer.add(foot, leg);
    for (const mesh of [foot, leg]) {
        mesh.renderOrder = -1;
    }
    wearer.visible = false;
    return { wearer, foot, leg };
}

// Where a shoe stands on DeepAR's right foot, in tracker units: the middle of
// its sole goes here. The foot occluder runs from the heel at z = -14.8 to the
// toes at z = 5.9 with its sole at y = -1.9, sitting a little towards the big
// toe (+x). Measured from the sample shoes whose DeepAR effects were fitted on
// phones (config/shoes.php): their soles sit at y = -1.8, their middles at
// z = -3.9 to -4.9 and x = 0.55 to 0.77, and they are 23 to 26 units long.
export const SHOE_SEAT = [0.7, -1.82, -4.6];
export const SHOE_LENGTH = 25;

// Stands the model on the foot whatever axes, facing, side and units the
// generator used: its shape says which way is down, where the toes are and
// whether it is a left or a right shoe (see shoe-orientation.js). It is then
// turned to sole down (-y), toes forward (+z) and big toe on +x, scaled to
// SHOE_LENGTH, and moved so the middle of its sole is at the origin, so a fit's
// scale and rotation work around the sole instead of lifting the shoe off it.
// Remembers the resulting bounds, which the foot and leg occluders are sized
// from, and what was detected, for the debug readout.
function normalise(model) {
    const holder = new THREE.Group();
    holder.add(model);
    const axes = detectShoeAxes(sampleSurface(model));
    holder.applyMatrix4(orientationMatrix(axes));
    holder.updateMatrixWorld(true);

    const box = new THREE.Box3().setFromObject(holder);
    const size = box.getSize(new THREE.Vector3());
    const scale = SHOE_LENGTH / (size.z || 1);
    const soleMiddle = new THREE.Vector3((box.min.x + box.max.x) / 2, box.min.y, (box.min.z + box.max.z) / 2);
    const placed = new THREE.Group();
    placed.add(holder);
    placed.scale.setScalar(scale);
    placed.position.copy(soleMiddle).multiplyScalar(-scale);

    const shoe = new THREE.Group();
    shoe.add(placed);
    shoe.updateMatrixWorld(true);
    const bounds = new THREE.Box3().setFromObject(shoe);
    shoe.userData.bounds = { min: bounds.min.toArray(), max: bounds.max.toArray() };
    shoe.userData.orientation = axes;

    return shoe;
}

export class FeetRenderer {
    constructor(stage, canvas) {
        this.stage = stage;
        this.canvas = canvas;
        this.renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
        this.renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.setClearColor(0x000000, 0);

        this.scene = new THREE.Scene();
        this.scene.environment = new THREE.PMREMGenerator(this.renderer).fromScene(new RoomEnvironment(), 0.04).texture;
        this.scene.add(new THREE.HemisphereLight(0xffffff, 0x444444, 1.2));

        // A near plane small enough for a tracker that reports in metres.
        this.camera = new THREE.PerspectiveCamera(60, 1, 0.001, 10000);
        // The tan of half the vertical field of view the foot tracker assumes.
        // DeepAR starts the tracker with the long-side angle squeezed onto the
        // short side of a 640×360 frame (see initializeDeepAR in the SDK);
        // onFootTrackingInitialized reports the same angle.
        this.trackerVerticalTan = Math.tan((trackerLongSideFov() / 2) * DEG) / (640 / 360);
        this.video = null;
        this.viewKey = '';
        this.convention = null;
        this.loader = new GLTFLoader();
        this.models = new Map();
        this.modelUrl = null;
        this.modelBounds = null;
        this.fit = { ...DEFAULT_FIT };
        this.legMask = false;
        this.active = false;

        // One anchor per foot follows the tracker. Inside it, `seat` puts the
        // middle of the shoe's sole on the foot and `fitted` carries the
        // shoe's fit around that point; the left foot is mirrored across the
        // foot's x axis, the same way DeepAR Studio's template builds the left shoe.
        this.feet = ['right', 'left'].map((side) => {
            const anchor = new THREE.Group();
            // Converts the shoe from tracker units (24 per foot) to whatever
            // units the tracker actually reports in.
            const units = new THREE.Group();
            const mirror = new THREE.Group();
            const seat = new THREE.Group();
            const fitted = new THREE.Group();
            const { wearer, foot, leg } = makeWearer();
            mirror.scale.x = side === 'left' ? -1 : 1;
            seat.position.set(...SHOE_SEAT);
            seat.add(fitted);
            mirror.add(seat);
            units.add(mirror, wearer);
            anchor.add(units);
            anchor.visible = false;
            this.scene.add(anchor);
            return { side, anchor, units, mirror, seat, fitted, wearer, foot, leg };
        });

        new ResizeObserver(() => this.resize()).observe(stage);
        this.resize();
    }

    resize() {
        const { clientWidth: width, clientHeight: height } = this.stage;
        if (!width || !height) return;
        this.renderer.setSize(width, height, false);
        this.viewKey = '';
        this.matchCameraView();
    }

    // The camera video DeepAR shows, so the 3D camera can see exactly the
    // part of the frame that is on screen.
    setVideo(video) {
        this.video = video;
        this.viewKey = '';
    }

    // The vertical field of view DeepAR started the foot tracker with.
    setFieldOfView(fovY) {
        if (!fovY) return;
        this.trackerVerticalTan = Math.tan((fovY / 2) * DEG);
        this.viewKey = '';
        this.matchCameraView();
    }

    // DeepAR draws the camera frame to cover the stage, cropping whatever
    // sticks out. The 3D camera gets the field of view of the visible part,
    // so a point the tracker puts on the foot lands on the foot on screen.
    // The tracker applies its field of view to the height of the frame it is
    // given, whichever way round that frame is: on a phone held upright the
    // frame is portrait, so the angle spans the long side. Treating it as the
    // short side made the camera ~1.8× too wide, drawing the shoes too small
    // and pulled towards the middle of the screen.
    matchCameraView() {
        const { clientWidth: width, clientHeight: height } = this.stage;
        const videoWidth = this.video?.videoWidth || 0;
        const videoHeight = this.video?.videoHeight || 0;
        const key = `${width}x${height}:${videoWidth}x${videoHeight}:${this.trackerVerticalTan}`;
        if (key === this.viewKey || !width || !height) return;
        this.viewKey = key;

        this.camera.aspect = width / height;
        if (videoWidth && videoHeight) {
            const cover = Math.max(width / videoWidth, height / videoHeight);
            const visibleHeight = height / (videoHeight * cover);
            this.camera.fov = (2 * Math.atan(this.trackerVerticalTan * visibleHeight)) / DEG;
        }
        this.camera.updateProjectionMatrix();
    }

    // Loads (or reuses) the GLB and shows it on both feet.
    async show(url, fit, legMask = false) {
        this.modelUrl = url;
        if (!this.models.has(url)) {
            this.models.set(url, this.loader.loadAsync(url).then((gltf) => normalise(gltf.scene)));
        }
        let model;
        try {
            model = await this.models.get(url);
        } catch (error) {
            this.models.delete(url);
            throw error;
        }
        // A newer shoe was chosen while this one downloaded.
        if (this.modelUrl !== url) return;

        for (const foot of this.feet) {
            foot.fitted.clear();
            foot.fitted.add(foot.side === 'right' ? model : model.clone());
        }
        this.modelBounds = model.userData.bounds;
        this.modelOrientation = model.userData.orientation;
        this.setFit(fit, legMask);
        this.active = true;
        this.canvas.hidden = false;
        this.renderer.setAnimationLoop(() => {
            this.matchCameraView();
            this.renderer.render(this.scene, this.camera);
        });
    }

    hide() {
        this.active = false;
        this.modelUrl = null;
        this.canvas.hidden = true;
        this.renderer.setAnimationLoop(null);
    }

    setFit(fit, legMask = this.legMask) {
        this.fit = { ...DEFAULT_FIT, ...(fit ?? {}) };
        this.legMask = Boolean(legMask);
        const { scale, rotation: [rx, ry, rz], position: [px, py, pz] } = this.fit;
        const [sx, sy, sz] = this.fit.stretch ?? [1, 1, 1];
        for (const foot of this.feet) {
            foot.fitted.scale.set(scale * sx, scale * sy, scale * sz);
            foot.fitted.rotation.set(rx * DEG, ry * DEG, rz * DEG);
            foot.fitted.position.set(px, py, pz);
            foot.wearer.visible = this.legMask;
            this.layoutWearer(foot);
        }
    }

    // Puts the foot on the shoe's footbed and the leg above its heel, using
    // the shoe's bounds as it sits on the tracked foot (after the fit).
    layoutWearer(foot) {
        if (!this.modelBounds) return;
        foot.fitted.updateMatrix();
        foot.seat.updateMatrix();
        foot.mirror.updateMatrix();
        const box = new THREE.Box3(new THREE.Vector3(...this.modelBounds.min), new THREE.Vector3(...this.modelBounds.max))
            .applyMatrix4(foot.fitted.matrix)
            .applyMatrix4(foot.seat.matrix)
            .applyMatrix4(foot.mirror.matrix);
        const size = box.getSize(new THREE.Vector3());
        const centre = box.getCenter(new THREE.Vector3());
        const soleTop = box.min.y + WEARER.soleTop * size.y;

        foot.foot.position.set(centre.x, soleTop, centre.z);
        foot.foot.scale.set(WEARER.footHalfWidth * size.x, WEARER.footHalfHeight * size.y, WEARER.footHalfLength * size.z);

        foot.leg.position.set(centre.x, soleTop, box.min.z + WEARER.legFromHeel * size.z);
        foot.leg.scale.set(WEARER.legRadius * size.z, WEARER.legHeight * size.z, WEARER.legRadius * size.z);
    }

    // The camera frame with the shoes drawn on it, as the stage shows it, for
    // a photo of the try-on. The shoes are drawn again right before they are
    // copied, since WebGL clears its canvas after each frame.
    snapshot({ maxSide = 1600 } = {}) {
        const { clientWidth: width, clientHeight: height } = this.stage;
        const ratio = Math.min(this.renderer.getPixelRatio(), maxSide / Math.max(width, height));
        const photo = document.createElement('canvas');
        photo.width = Math.round(width * ratio);
        photo.height = Math.round(height * ratio);
        const context = photo.getContext('2d');

        const video = this.video;
        if (video?.videoWidth && video.videoHeight) {
            const cover = Math.max(photo.width / video.videoWidth, photo.height / video.videoHeight);
            const drawnWidth = video.videoWidth * cover;
            const drawnHeight = video.videoHeight * cover;
            context.drawImage(video, (photo.width - drawnWidth) / 2, (photo.height - drawnHeight) / 2, drawnWidth, drawnHeight);
        }
        if (this.active) {
            this.matchCameraView();
            this.renderer.render(this.scene, this.camera);
            context.drawImage(this.renderer.domElement, 0, 0, photo.width, photo.height);
        }
        return photo;
    }

    // Coloured axes on each tracked foot, so a debug session can see where the
    // tracker puts the feet even when the shoe itself is out of view.
    showMarkers() {
        for (const foot of this.feet) {
            const axes = new THREE.AxesHelper(12);
            axes.material.depthTest = false;
            axes.renderOrder = 10;
            foot.anchor.add(axes);
        }
    }

    // One line per foot: where its shoe ends up and how big it is, in the
    // camera's view, for the debug readout.
    describe() {
        const numbers = (vector) => vector.toArray().map((value) => value.toFixed(2)).join(', ');
        this.scene.updateMatrixWorld();
        const lines = [`tracker space: ${this.convention ?? 'not seen yet'}, video ${this.video?.videoWidth ?? 0}×${this.video?.videoHeight ?? 0}`, `camera fov ${this.camera.fov.toFixed(1)}°, canvas ${this.renderer.domElement.width}×${this.renderer.domElement.height}, drawing ${this.active ? 'on' : 'off'}, canvas hidden ${this.canvas.hidden}`];
        const orientation = this.modelOrientation;
        if (orientation) {
            const axis = (vector) => ['x', 'y', 'z'].map((name, i) => (Math.round(vector.getComponent(i)) ? `${vector.getComponent(i) > 0 ? '+' : '-'}${name}` : '')).join('');
            const sure = Object.entries(orientation.confidence).map(([name, value]) => `${name} ${value.toFixed(2)}`).join(', ');
            lines.push(`model: down ${axis(orientation.down)}, toes ${axis(orientation.toe)}, big toe ${axis(orientation.bigToe)}, ${orientation.isLeftShoe ? 'left' : 'right'} shoe (sure: ${sure})`);
        }
        for (const foot of this.feet) {
            if (!foot.anchor.visible) {
                lines.push(`${foot.side} shoe: not shown (foot not tracked)`);
                continue;
            }
            const box = new THREE.Box3().setFromObject(foot.fitted);
            if (box.isEmpty()) {
                lines.push(`${foot.side} shoe: no model loaded`);
                continue;
            }
            const centre = box.getCenter(new THREE.Vector3());
            const screen = centre.clone().project(this.camera);
            lines.push(`${foot.side} shoe: size (${numbers(box.getSize(new THREE.Vector3()))}) at (${numbers(centre)}), distance ${centre.length().toFixed(1)}, screen (${screen.x.toFixed(2)}, ${screen.y.toFixed(2)}) depth ${screen.z.toFixed(2)}`);
        }
        return lines;
    }

    // DeepAR's onFeetTracked data: position, quaternion rotation and scale of
    // each foot in its camera space. A tracked foot is always in front of the
    // camera, so the sign of z tells which way that space faces, and its
    // distance tells whether it is in metres or in tracker units.
    update(left, right) {
        for (const [foot, data] of [[this.feet[1], left], [this.feet[0], right]]) {
            foot.anchor.visible = this.active && data.detected;
            if (!data.detected) continue;
            const [px, py, pz] = data.position;
            const [qx, qy, qz, qw] = data.rotation;
            const [sx, sy, sz] = data.scale;
            const rotation = new THREE.Quaternion(qx, qy, qz, qw).normalize();
            const isVisionSpace = pz > 0;
            if (isVisionSpace) {
                foot.anchor.position.set(px, -py, -pz);
                foot.anchor.quaternion.copy(VISION_TO_GL).multiply(rotation);
            } else {
                foot.anchor.position.set(px, py, pz);
                foot.anchor.quaternion.copy(rotation);
            }
            foot.anchor.scale.set(Math.abs(sx) || 1, Math.abs(sy) || 1, Math.abs(sz) || 1);

            // A foot 24 units long can't be under 10 units from the camera:
            // such a small distance means the tracker reports metres.
            const isMetres = Math.hypot(px, py, pz) < 10;
            const scaleSize = (Math.abs(sx || 1) + Math.abs(sy || 1) + Math.abs(sz || 1)) / 3;
            foot.units.scale.setScalar(isMetres ? FOOT_LENGTH_METRES / FOOT_LENGTH / scaleSize : 1);
            this.convention = `${isVisionSpace ? 'z forward (converted)' : 'z backward (three.js)'}, ${isMetres ? 'metres' : 'tracker units'}`;
        }
    }
}

// Loads a shoe effect only for DeepAR's foot tracking and hides its own shoe,
// so just the GLB shows. Without such an effect, foot tracking runs on its own.
export async function startFootTrackingOnly(ar, trackerEffect) {
    if (!trackerEffect) {
        ar.clearEffect();
        return;
    }
    await ar.switchEffect(trackerEffect, { trackingInit: { foot: true } });
    for (const node of ['model_right', 'model_left']) {
        ar.changeParameterBool(node, '', 'enabled', false);
    }
}
