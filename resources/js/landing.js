import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const shoes = window.SHOES;
const canvas = document.getElementById('hero-canvas');
const loading = document.getElementById('hero-loading');
const nameEl = document.getElementById('shoe-name');
const nav = document.getElementById('site-nav');
const chips = [...document.querySelectorAll('[data-shoe]')];
const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

// Header turns solid once the hero scrolls away.
const updateNav = () => nav.setAttribute('data-solid', String(scrollY > 40));
addEventListener('scroll', updateNav, { passive: true });
updateNav();

const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.15;

const scene = new THREE.Scene();
const pmrem = new THREE.PMREMGenerator(renderer);
scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 100);
camera.position.set(0, 0.6, 6.4);

const key = new THREE.DirectionalLight(0xffffff, 2.4);
key.position.set(3, 5, 4);
const rim = new THREE.PointLight(0x9fd0ff, 40, 14);
rim.position.set(-3, 1.5, -3);
scene.add(key, rim);

const stage = new THREE.Group();
scene.add(stage);

const floor = -0.95;

function radialTexture(inner, outer) {
    const c = document.createElement('canvas');
    c.width = c.height = 256;
    const g = c.getContext('2d');
    const grad = g.createRadialGradient(128, 128, 0, 128, 128, 128);
    grad.addColorStop(0, inner);
    grad.addColorStop(1, outer);
    g.fillStyle = grad;
    g.fillRect(0, 0, 256, 256);
    return new THREE.CanvasTexture(c);
}

// Soft contact shadow under the shoe.
const shadow = new THREE.Mesh(
    new THREE.PlaneGeometry(3.4, 1.4),
    new THREE.MeshBasicMaterial({ map: radialTexture('rgba(0,20,60,0.55)', 'rgba(0,20,60,0)'), transparent: true, depthWrite: false }),
);
shadow.rotation.x = -Math.PI / 2;
shadow.position.y = floor;
stage.add(shadow);

const rings = [1.8, 2.4].map((radius, i) => {
    const ring = new THREE.Mesh(
        new THREE.TorusGeometry(radius, 0.006, 16, 160),
        new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.35 - i * 0.15, depthWrite: false }),
    );
    ring.rotation.x = Math.PI / 2;
    ring.position.y = floor;
    stage.add(ring);
    return ring;
});

const loader = new GLTFLoader();
const cache = new Map();

function loadShoe(shoe) {
    if (!cache.has(shoe.id)) {
        cache.set(shoe.id, loader.loadAsync(shoe.glb).then((gltf) => {
            const model = gltf.scene;
            const box = new THREE.Box3().setFromObject(model);
            const size = box.getSize(new THREE.Vector3());
            const center = box.getCenter(new THREE.Vector3());
            model.position.sub(center);
            const holder = new THREE.Group();
            holder.add(model);
            holder.userData.fit = 3.2 / Math.max(size.x, size.y, size.z);
            return holder;
        }));
    }
    return cache.get(shoe.id);
}

// The thumbnail column scrolls when there are more shoes than fit; keep the
// selected one in view without scrolling the page.
function revealChip(chip) {
    const list = chip?.closest('ul');
    if (!list) return;
    const behavior = reduceMotion ? 'auto' : 'smooth';
    const listBox = list.getBoundingClientRect();
    const chipBox = chip.getBoundingClientRect();

    // A row on phones, a column on larger screens.
    if (list.scrollWidth > list.clientWidth && (chipBox.left < listBox.left || chipBox.right > listBox.right)) {
        list.scrollBy({ left: chipBox.left - listBox.left - (listBox.width - chipBox.width) / 2, behavior });
    } else if (list.scrollHeight > list.clientHeight && (chipBox.top < listBox.top || chipBox.bottom > listBox.bottom)) {
        list.scrollBy({ top: chipBox.top - listBox.top - (listBox.height - chipBox.height) / 2, behavior });
    }
}

// Load the shoes either side of the one shown so arrow keys and the next tap
// switch instantly, without downloading every model in a large collection.
function warmNeighbours(index) {
    [index - 1, index + 1].forEach((i) => {
        const shoe = shoes[(i + shoes.length) % shoes.length];
        loadShoe(shoe).catch(() => cache.delete(shoe.id));
    });
}

let current = null;
let leaving = null;
let currentIndex = 0;
let enterStart = 0;
let leaveStart = 0;
let spin = 0.6;
let spinVelocity = 0;
let dragging = false;
let lastX = 0;

const easeOutBack = (t) => 1 + 2.7 * Math.pow(t - 1, 3) + 1.7 * Math.pow(t - 1, 2);

async function show(index) {
    // Every shoe can be hidden from the site in the admin shoe library.
    if (!shoes.length) return;
    currentIndex = (index + shoes.length) % shoes.length;
    const shoe = shoes[currentIndex];

    chips.forEach((c, i) => c.setAttribute('aria-pressed', String(i === currentIndex)));
    revealChip(chips[currentIndex]);
    warmNeighbours(currentIndex);
    nameEl.textContent = shoe.name;
    document.querySelectorAll('[data-tryon-link]').forEach((a) => {
        a.href = `${a.href.split('?')[0]}?shoe=${shoe.id}`;
    });

    const holder = (await loadShoe(shoe)).clone(true);
    if (currentIndex !== shoes.indexOf(shoe)) return;

    if (current) {
        if (leaving) stage.remove(leaving);
        leaving = current;
        leaveStart = performance.now();
    }
    current = holder;
    holder.scale.setScalar(0.0001);
    stage.add(holder);
    enterStart = performance.now();
}

// Input: drag to spin with inertia, mouse parallax.
const pointer = { x: 0, y: 0 };
canvas.addEventListener('pointerdown', (e) => {
    dragging = true;
    lastX = e.clientX;
    canvas.setPointerCapture(e.pointerId);
});
canvas.addEventListener('pointermove', (e) => {
    pointer.x = (e.clientX / innerWidth) * 2 - 1;
    pointer.y = (e.clientY / innerHeight) * 2 - 1;
    if (dragging) {
        spinVelocity = (e.clientX - lastX) * 0.012;
        lastX = e.clientX;
    }
});
const release = () => { dragging = false; };
canvas.addEventListener('pointerup', release);
canvas.addEventListener('pointercancel', release);

chips.forEach((chip, i) => chip.addEventListener('click', () => i !== currentIndex && show(i)));
addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') show(currentIndex + 1);
    if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') show(currentIndex - 1);
});

function resize() {
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    // Pull back on narrow screens so the whole shoe fits.
    camera.position.z = w < 640 ? 8.5 : 5.8;
    camera.updateProjectionMatrix();
}
new ResizeObserver(resize).observe(canvas);
resize();

const clock = new THREE.Clock();
let t = 0;

function frame() {
    requestAnimationFrame(frame);
    const dt = Math.min(clock.getDelta(), 0.05);
    t += dt;
    const now = performance.now();

    rings.forEach((r, i) => {
        r.rotation.z += dt * (0.15 + i * 0.1) * (i % 2 ? -1 : 1);
        r.scale.setScalar(1 + Math.sin(t * 1.2 + i) * 0.015);
    });

    spin += spinVelocity;
    spinVelocity *= dragging ? 1 : 0.94;
    spin += reduceMotion || dragging ? 0 : dt * 0.5;

    const bob = reduceMotion ? 0 : Math.sin(t * 1.2) * 0.08;
    shadow.scale.setScalar(1 - bob * 0.8);

    if (current) {
        const k = Math.min((now - enterStart) / 900, 1);
        current.scale.setScalar(current.userData.fit * Math.max(easeOutBack(k), 0.0001));
        current.rotation.y = spin + (1 - k) * 2.5;
        current.rotation.x = 0.1 + Math.sin(t * 0.8) * 0.03;
        current.position.y = bob;
    }
    if (leaving) {
        const k = Math.min((now - leaveStart) / 350, 1);
        leaving.scale.setScalar(Math.max(leaving.userData.fit * (1 - k), 0.0001));
        leaving.rotation.y += dt * 4;
        if (k >= 1) {
            stage.remove(leaving);
            leaving = null;
        }
    }

    camera.position.x += ((reduceMotion ? 0 : pointer.x * 0.4) - camera.position.x) * 0.04;
    camera.position.y += ((reduceMotion ? 0.6 : 0.6 - pointer.y * 0.25) - camera.position.y) * 0.04;
    camera.lookAt(0, -0.1, 0);

    renderer.render(scene, camera);
}

const first = new URLSearchParams(location.search).get('shoe');
const start = Math.max(0, shoes.findIndex((s) => s.id === first));

show(start)
    .catch((e) => console.error(e))
    .finally(() => {
        loading.style.opacity = '0';
        setTimeout(() => loading.remove(), 800);
    });
frame();
