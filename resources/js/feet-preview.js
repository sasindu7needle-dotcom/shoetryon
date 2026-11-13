import * as deepar from 'deepar';
import { DEFAULT_FIT, FeetRenderer, startFootTrackingOnly } from './feet-renderer.js';

// The admin's "preview on my feet" page: Tripo's GLB on the tracked feet,
// before the shoe is approved. The admin lines it up with the buttons; the
// fit is saved to the submission and becomes the shoe's starting fit.

const config = window.FEET_PREVIEW;
const stage = document.getElementById('feet-stage');
const status = document.getElementById('feet-status');
const statusText = status.querySelector('[data-status-text]');
const statusAction = status.querySelector('[data-status-action]');
const busy = status.querySelector('[data-busy]');
const hint = document.getElementById('feet-hint');
const savedNote = document.getElementById('feet-saved');
const legMaskButton = document.querySelector('[data-adjust="leg"]');

const reload = { label: 'Reload', run: () => location.reload() };
const feetRenderer = new FeetRenderer(stage, document.getElementById('feet-shoes'));

const copyFit = (fit) => ({ scale: fit.scale, rotation: [...fit.rotation], position: [...fit.position] });
let fit = copyFit({ ...DEFAULT_FIT, ...config.fit });
let legMask = config.legMask;

function showStatus(message, { isBusy = true, action = null } = {}) {
    status.hidden = false;
    statusText.textContent = message;
    busy.hidden = !isBusy;
    statusAction.hidden = !action;
    if (action) {
        statusAction.textContent = action.label;
        statusAction.onclick = action.run;
    }
}

// ---- saving the fit ---------------------------------------------------------------

let saveTimer = null;

function scheduleSave() {
    if (!config.saveUrl) return;
    savedNote.textContent = 'Saving…';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 600);
}

async function save() {
    try {
        const response = await fetch(config.saveUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ fit, leg_mask: legMask }),
        });
        savedNote.textContent = response.ok ? 'Fit saved' : `Could not save the fit (error ${response.status}). Reload and try again.`;
    } catch {
        savedNote.textContent = 'Could not save the fit. Check your connection.';
    }
}

// ---- controls ---------------------------------------------------------------------

const wrap = (degrees) => ((((degrees + 180) % 360) + 360) % 360) - 180;
const round = (value) => Math.round(value * 1000) / 1000;

const adjusters = {
    smaller: () => (fit.scale = round(fit.scale / 1.05)),
    bigger: () => (fit.scale = round(fit.scale * 1.05)),
    turn: () => (fit.rotation[1] = wrap(fit.rotation[1] + 90)),
    flip: () => (fit.rotation[2] = wrap(fit.rotation[2] + 180)),
    up: () => (fit.position[1] = round(fit.position[1] + 0.5)),
    down: () => (fit.position[1] = round(fit.position[1] - 0.5)),
    forward: () => (fit.position[2] = round(fit.position[2] + 0.5)),
    back: () => (fit.position[2] = round(fit.position[2] - 0.5)),
    leg: () => (legMask = !legMask),
    reset: () => {
        fit = copyFit(DEFAULT_FIT);
        legMask = false;
    },
};

function showLegMaskState() {
    legMaskButton.setAttribute('aria-pressed', String(legMask));
    legMaskButton.textContent = legMask ? 'Leg mask: on' : 'Leg mask: off';
}

for (const button of document.querySelectorAll('[data-adjust]')) {
    button.disabled = !config.saveUrl && button.dataset.adjust !== 'leg';
    button.addEventListener('click', () => {
        adjusters[button.dataset.adjust]();
        feetRenderer.setFit(fit, legMask);
        showLegMaskState();
        scheduleSave();
    });
}
showLegMaskState();

// ---- camera -----------------------------------------------------------------------

async function openCamera() {
    try {
        return await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 360 } }, audio: false });
    } catch (error) {
        if (error.name !== 'OverconstrainedError') throw error;
        return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
    }
}

function describeCameraError(error) {
    if (!window.isSecureContext) {
        return 'The camera only works over HTTPS. Open the https:// address of this page.';
    }
    switch (error?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Camera access is blocked. Allow the camera for this site in your browser settings, then reload.';
        case 'NotReadableError':
            return 'The camera is busy. Close other apps that are using it, then reload.';
        case 'NotFoundError':
            return 'No camera was found on this device. Open this page on your phone.';
        default:
            return `Could not start the camera${error?.message ? `: ${error.message}` : '.'}`;
    }
}

// ---- debug readout (add ?debug=1 to the URL) --------------------------------------

const debug = new URLSearchParams(location.search).has('debug');

function startDebugReadout(getFeet) {
    feetRenderer.showMarkers();
    const readout = document.createElement('pre');
    readout.className = 'pointer-events-none absolute inset-x-2 bottom-2 z-10 overflow-hidden rounded-lg bg-black/70 p-2 text-[10px] leading-tight whitespace-pre-wrap text-white';
    stage.append(readout);
    const numbers = (values) => (values ? Array.from(values, (value) => Number(value).toFixed(2)).join(', ') : '–');
    setInterval(() => {
        const feet = getFeet();
        const raw = feet
            ? ['left', 'right'].map((side) => {
                const data = feet[side];
                return `${side} raw: ${data.detected ? 'detected' : 'not detected'} pos (${numbers(data.position)}) scale (${numbers(data.scale)}) rot (${numbers(data.rotation)})`;
            })
            : ['no foot data from DeepAR yet'];
        readout.textContent = [...raw, ...feetRenderer.describe()].join('\n');
    }, 400);
}

// ---- start ------------------------------------------------------------------------

async function main() {
    if (!config.licenseKey) {
        showStatus('Set DEEPAR_LICENSE_KEY in the environment, then reload.', { isBusy: false });
        return;
    }
    if (!config.trackerEffect) {
        showStatus('Foot tracking needs a shoe effect (.deepar) on the server, and none was found. Upload one to a shoe, then reload.', { isBusy: false });
        return;
    }
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        showStatus(describeCameraError(null), { isBusy: false });
        return;
    }

    const cameraRequest = openCamera();
    cameraRequest.catch(() => {});

    showStatus('Loading the 3D model…');
    await feetRenderer.show(config.modelUrl, fit, legMask);

    showStatus('Starting foot tracking…');
    const ar = await deepar.initialize({
        licenseKey: config.licenseKey,
        previewElement: stage,
        rootPath: config.rootPath,
        additionalOptions: {
            cameraConfig: { disableDefaultCamera: true },
            hint: ['footInit'],
        },
    });

    ar.callbacks.onFootTrackingInitialized = (fovY) => feetRenderer.setFieldOfView(fovY);
    let feetVisible = null;
    let lastFeet = null;
    if (debug) {
        startDebugReadout(() => lastFeet);
    }
    ar.callbacks.onFeetTracked = (left, right) => {
        lastFeet = { left, right };
        feetRenderer.update(left, right);
        const visible = left.detected || right.detected;
        if (visible !== feetVisible) {
            feetVisible = visible;
            hint.textContent = visible ? 'Feet found. Line the shoe up with the buttons if it is off.' : 'Looking for feet… stand 1–2 m back, both feet in frame';
        }
    };
    ar.initializeFootTracking();
    await startFootTrackingOnly(ar, config.trackerEffect);

    showStatus('Starting camera… Allow camera access if your browser asks.');
    let stream;
    try {
        stream = await cameraRequest;
    } catch (error) {
        showStatus(describeCameraError(error), { isBusy: false, action: reload });
        return;
    }
    const video = document.createElement('video');
    video.muted = true;
    video.playsInline = true;
    video.setAttribute('playsinline', '');
    video.srcObject = stream;
    try {
        await video.play();
    } catch {
        await new Promise((resolve) => showStatus('Tap to start the camera.', { isBusy: false, action: { label: 'Start camera', run: () => video.play().then(resolve) } }));
    }
    ar.setVideoElement(video, false);
    status.hidden = true;

    document.addEventListener('visibilitychange', () => ar.setPaused(document.hidden));
    window.addEventListener('pagehide', () => stream.getTracks().forEach((track) => track.stop()));
}

main().catch((error) => {
    console.error(error);
    const message = String(error?.message ?? error);
    showStatus(/(401|403|419)/.test(message)
        ? 'Your admin session has ended. Sign in again on this phone, then reload.'
        : `Could not start the preview: ${message}`, { isBusy: false, action: reload });
});
