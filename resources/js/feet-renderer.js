import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

// Draws a shoe's GLB on the feet DeepAR tracks, on a transparent canvas above
// DeepAR's camera view. Shoes built in DeepAR Studio don't need this; it is
// how shoes made automatically from photos (Tripo GLB, no .deepar) are worn.

// DeepAR's foot tracker works in units where a foot is about 24 long: the
// shoe effects place a GLB measured in metres (a ~0.27 m shoe) at scale ~90.
export const FOOT_LENGTH = 24;

export const DEFAULT_FIT = { scale: 1, rotation: [0, 0, 0], position: [0, 0, 0] };

// A rough ankle and lower leg, drawn only into the depth buffer, so the back
// of the shoe's collar disappears behind the wearer's leg as it would in real
// life. Toes point towards +z on the tracked foot.
const LEG = { radius: 4.2, height: 40, bottom: 2, back: -6 };

const DEG = Math.PI / 180;

function makeLegMask() {
    const material = new THREE.MeshBasicMaterial({ colorWrite: false });
    const leg = new THREE.Mesh(new THREE.CylinderGeometry(LEG.radius, LEG.radius * 1.15, LEG.height, 24), material);
    leg.position.set(0, LEG.bottom + LEG.height / 2, LEG.back);
    leg.renderOrder = -1;
    leg.visible = false;
    return leg;
}

// Centres the model and scales it so its longest side is a foot long, with
// that side along the foot's length (z), whatever units and facing Tripo used.
function normalise(model) {
    const box = new THREE.Box3().setFromObject(model);
    const size = box.getSize(new THREE.Vector3());
    model.position.sub(box.getCenter(new THREE.Vector3()));

    const holder = new THREE.Group();
    holder.add(model);
    if (size.x > size.z) {
        holder.rotation.y = Math.PI / 2;
    }
    holder.scale.setScalar(FOOT_LENGTH / (Math.max(size.x, size.z) || 1));

    return holder;
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

        this.camera = new THREE.PerspectiveCamera(60, 1, 0.5, 10000);
        this.loader = new GLTFLoader();
        this.models = new Map();
        this.modelUrl = null;
        this.fit = { ...DEFAULT_FIT };
        this.legMask = false;
        this.active = false;

        // One anchor per foot follows the tracker. Inside it, `fitted` carries
        // the shoe's fit; the left foot is mirrored across the foot's x axis,
        // the same way DeepAR Studio's template builds the left shoe.
        this.feet = ['right', 'left'].map((side) => {
            const anchor = new THREE.Group();
            const mirror = new THREE.Group();
            const fitted = new THREE.Group();
            const leg = makeLegMask();
            mirror.scale.x = side === 'left' ? -1 : 1;
            mirror.add(fitted);
            anchor.add(mirror, leg);
            anchor.visible = false;
            this.scene.add(anchor);
            return { side, anchor, fitted, leg };
        });

        new ResizeObserver(() => this.resize()).observe(stage);
        this.resize();
    }

    resize() {
        const { clientWidth: width, clientHeight: height } = this.stage;
        if (!width || !height) return;
        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
    }

    setFieldOfView(fovY) {
        if (!fovY) return;
        this.camera.fov = fovY;
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
        this.setFit(fit, legMask);
        this.active = true;
        this.canvas.hidden = false;
        this.renderer.setAnimationLoop(() => this.renderer.render(this.scene, this.camera));
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
            foot.leg.visible = this.legMask;
        }
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
        const lines = [`camera fov ${this.camera.fov.toFixed(1)}°, canvas ${this.renderer.domElement.width}×${this.renderer.domElement.height}, drawing ${this.active ? 'on' : 'off'}, canvas hidden ${this.canvas.hidden}`];
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
    // each foot in its camera space.
    update(left, right) {
        for (const [foot, data] of [[this.feet[1], left], [this.feet[0], right]]) {
            foot.anchor.visible = this.active && data.detected;
            if (!data.detected) continue;
            const [px, py, pz] = data.position;
            const [qx, qy, qz, qw] = data.rotation;
            const [sx, sy, sz] = data.scale;
            foot.anchor.position.set(px, py, pz);
            foot.anchor.quaternion.set(qx, qy, qz, qw);
            foot.anchor.scale.set(sx || 1, sy || 1, sz || 1);
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
