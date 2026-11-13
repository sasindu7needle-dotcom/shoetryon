import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

// Shows a GLB from Tripo so the admin can turn it around before building its
// DeepAR effect. The model is centred and scaled to fit, whatever units it uses.
for (const canvas of document.querySelectorAll('[data-model-preview]')) {
    const status = canvas.parentElement.querySelector('[data-model-status]');
    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;

    const scene = new THREE.Scene();
    scene.environment = new THREE.PMREMGenerator(renderer).fromScene(new RoomEnvironment(), 0.04).texture;

    const camera = new THREE.PerspectiveCamera(35, 1, 0.01, 100);
    camera.position.set(1.6, 1, 2.2);
    const controls = new OrbitControls(camera, canvas);
    controls.enableDamping = true;
    controls.autoRotate = !matchMedia('(prefers-reduced-motion: reduce)').matches;
    controls.autoRotateSpeed = 1.5;
    canvas.addEventListener('pointerdown', () => { controls.autoRotate = false; }, { once: true });

    const resize = () => {
        const { clientWidth: width, clientHeight: height } = canvas;
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    };
    new ResizeObserver(resize).observe(canvas);
    resize();

    new GLTFLoader().load(
        canvas.dataset.modelPreview,
        (gltf) => {
            const model = gltf.scene;
            const box = new THREE.Box3().setFromObject(model);
            const size = box.getSize(new THREE.Vector3()).length() || 1;
            model.position.sub(box.getCenter(new THREE.Vector3()));
            const holder = new THREE.Group();
            holder.add(model);
            holder.scale.setScalar(2 / size);
            scene.add(holder);
            status.hidden = true;
        },
        undefined,
        () => { status.textContent = 'The 3D model could not be loaded.'; },
    );

    renderer.setAnimationLoop(() => {
        controls.update();
        renderer.render(scene, camera);
    });
}
