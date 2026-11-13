import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const HOME_POSITION = new THREE.Vector3(1.6, 1, 2.2);

// Says why a GLB failed to load, so the admin knows whether the file is
// missing on the server or broken.
function describeLoadError(error) {
    const message = String(error?.message ?? error ?? '');
    const status = message.match(/responded with (\d{3})/)?.[1];

    if (status === '404') {
        return 'The 3D model file is no longer on the server. Send the shoe again from the Add new shoe page.';
    }

    if (status === '401' || status === '403' || status === '419') {
        return 'Your admin session has ended. Reload the page and sign in again.';
    }

    if (status) {
        return `The server answered with an error (HTTP ${status}) while sending the 3D model.`;
    }

    return 'The 3D model could not be read. The GLB may be damaged; download it and open it in another viewer to check.';
}

// Shows a GLB from Tripo so the admin can turn it around before approving it
// and building its DeepAR effect. The model is centred and scaled to fit,
// whatever units it uses.
for (const canvas of document.querySelectorAll('[data-model-preview]')) {
    const status = canvas.parentElement.querySelector('[data-model-status]');
    const resetButton = canvas.parentElement.querySelector('[data-model-reset]');
    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;

    const scene = new THREE.Scene();
    scene.environment = new THREE.PMREMGenerator(renderer).fromScene(new RoomEnvironment(), 0.04).texture;

    const camera = new THREE.PerspectiveCamera(35, 1, 0.01, 100);
    camera.position.copy(HOME_POSITION);
    const controls = new OrbitControls(camera, canvas);
    controls.enableDamping = true;
    controls.minDistance = 0.5;
    controls.maxDistance = 8;
    controls.autoRotate = !matchMedia('(prefers-reduced-motion: reduce)').matches;
    controls.autoRotateSpeed = 1.5;
    canvas.addEventListener('pointerdown', () => { controls.autoRotate = false; }, { once: true });

    resetButton?.addEventListener('click', () => {
        camera.position.copy(HOME_POSITION);
        controls.target.set(0, 0, 0);
        controls.update();
    });

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
        (error) => {
            console.error('Could not load the 3D model preview.', error);
            status.textContent = describeLoadError(error);
            status.classList.replace('text-slate-500', 'text-rose-700');
            if (resetButton) {
                resetButton.hidden = true;
            }
        },
    );

    renderer.setAnimationLoop(() => {
        controls.update();
        renderer.render(scene, camera);
    });
}
