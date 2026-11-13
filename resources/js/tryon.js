import * as deepar from 'deepar';
import { FeetRenderer, startFootTrackingOnly } from './feet-renderer.js';

const { licenseKey, rootPath, fits, trackerEffect } = window.TRYON;
const stage = document.getElementById('ar-stage');
const status = document.getElementById('ar-status');
const statusText = status.querySelector('[data-status-text]');
const statusAction = status.querySelector('[data-status-action]');
const busyIndicators = status.querySelectorAll('[data-busy]');
const hint = document.getElementById('ar-hint');
const buttons = [...document.querySelectorAll('.shoe-btn')];
const canWear = (btn) => Boolean(btn.dataset.effect || btn.dataset.glb);

// Shoes made from photos have no DeepAR effect; their GLB is drawn on the
// tracked feet above DeepAR's camera view.
const feetRenderer = new FeetRenderer(stage, document.getElementById('ar-shoes'));
// What DeepAR has loaded: a shoe's effect URL, or 'tracker' while a GLB shoe
// is worn and DeepAR only tracks the feet.
let deeparEffect = null;

const ua = navigator.userAgent;
const isIOS = /iPad|iPhone|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
const isAndroid = /Android/.test(ua);
// Social and messaging apps open links in their own web view, which often
// can't use the camera.
const isInAppBrowser = /FBAN|FBAV|FB_IAB|Instagram|Line\/|Snapchat|musical_ly|Bytedance|TikTok|LinkedInApp|Pinterest|Twitter|; wv\)/.test(ua);

// The back camera at the size DeepAR asks for by default. The foot tracker
// works on small frames, and bigger ones slow older phones down.
const CAMERA = { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 360 }, frameRate: { ideal: 30 } };

const reload = { label: 'Reload', run: () => location.reload() };
const retryCamera = { label: 'Try again', run: () => startCamera() };

let ar = null;
let stream = null;
let cameraStarting = null;
let cameraStartedAt = 0;
let wakeLock = null;
// Set once something unrecoverable happened; the camera then stays off.
let stopped = false;

const video = document.createElement('video');
video.muted = true;
video.playsInline = true;
video.setAttribute('playsinline', '');

function setHint(message, isError = false) {
    hint.textContent = message;
    hint.classList.toggle('text-red-600', isError);
}

// Overlay on the camera view: a spinner while busy, or a message with an
// optional button when the user has to do something.
function showStatus(message, { busy = true, action = null } = {}) {
    status.hidden = false;
    hint.hidden = true;
    statusText.textContent = message;
    busyIndicators.forEach((el) => (el.hidden = !busy));
    statusAction.hidden = !action;
    if (action) {
        statusAction.textContent = action.label;
        statusAction.onclick = action.run;
    }
}

function hideStatus() {
    status.hidden = true;
    hint.hidden = false;
}

function fail(message, action = null) {
    stopped = true;
    stopCamera();
    showStatus(message, { busy: false, action });
}

// The SDK reports license and tracking problems through console.error;
// mirror them on screen so they are visible on phones.
const consoleError = console.error.bind(console);
console.error = (...args) => {
    consoleError(...args);
    setHint(args.map((a) => a?.message ?? String(a)).join(' ').slice(0, 200), true);
};

// A rejected license is only logged, not thrown.
const consoleLog = console.log.bind(console);
console.log = (...args) => {
    consoleLog(...args);
    if (/license key not valid/i.test(String(args[0]))) {
        fail(`DeepAR license key not valid for ${location.hostname}. Add this domain to the Web app on developer.deepar.ai.`);
    }
};

// DeepAR renders with WebGL 2 and tracks feet with WebAssembly.
function unsupportedReason() {
    if (typeof WebAssembly !== 'object') {
        return "This browser can't run the try-on. Open the page in an up-to-date Safari or Chrome.";
    }
    let gl = null;
    try {
        gl = document.createElement('canvas').getContext('webgl2');
    } catch {
        // Treated as unsupported below.
    }
    if (!gl) {
        return "This browser can't show the 3D shoes because WebGL 2 is missing or turned off. Update the browser (on iPhone, iOS 15 or later) and try again.";
    }
    gl.getExtension('WEBGL_lose_context')?.loseContext();
    return null;
}

const openInBrowser = {
    label: 'Copy link',
    run: async () => {
        try {
            await navigator.clipboard.writeText(location.href);
            statusText.textContent = 'Link copied. Paste it into Safari or Chrome.';
        } catch {
            statusText.textContent = `Open ${location.href} in Safari or Chrome.`;
        }
        statusAction.hidden = true;
    },
};

const IN_APP_MESSAGE = "This app's built-in browser can't use the camera. Open the page in Safari or Chrome: tap ⋯ or ⋮ and choose Open in browser.";

// Why the camera can't be used at all on this page, if that is the case.
function cameraUnavailable() {
    if (!window.isSecureContext) {
        return { message: 'The camera only works over HTTPS. Open the https:// address of this page.' };
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        return isInAppBrowser
            ? { message: IN_APP_MESSAGE, action: openInBrowser }
            : { message: "This browser can't open the camera. Open the page in Safari (iPhone) or Chrome (Android)." };
    }
    return null;
}

function showCameraError(e) {
    switch (e?.name) {
        case 'NotAllowedError':
        case 'PermissionDeniedError':
        case 'SecurityError':
            if (isInAppBrowser) {
                showStatus(IN_APP_MESSAGE, { busy: false, action: openInBrowser });
            } else if (isIOS) {
                showStatus('Camera access is blocked. In Safari, tap aA in the address bar, then Website Settings › Camera › Allow. In other browsers, turn on Camera for the browser in the Settings app.', { busy: false, action: retryCamera });
            } else if (isAndroid) {
                showStatus('Camera access is blocked. Tap the icon next to the address bar, then Permissions › Camera › Allow.', { busy: false, action: retryCamera });
            } else {
                showStatus('Camera access is blocked. Allow the camera for this site in your browser settings.', { busy: false, action: retryCamera });
            }
            break;
        case 'NotReadableError':
        case 'TrackStartError':
        case 'AbortError':
            showStatus('The camera is busy. Close other apps that are using it, then try again.', { busy: false, action: retryCamera });
            break;
        case 'NotFoundError':
        case 'DevicesNotFoundError':
        case 'OverconstrainedError':
            showStatus('No camera was found on this device.', { busy: false, action: retryCamera });
            break;
        default:
            showStatus(`Could not start the camera${e?.message ? `: ${e.message}` : '.'}`, { busy: false, action: retryCamera });
    }
}

async function openCamera() {
    try {
        return await navigator.mediaDevices.getUserMedia({ video: CAMERA, audio: false });
    } catch (e) {
        // A few phones reject the size or frame rate; any back camera will do.
        if (e.name !== 'OverconstrainedError') throw e;
        return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
    }
}

// Standing still while looking at your feet lets the screen dim and lock, which
// stops the camera. Keep it on while the camera runs (Chrome 84+, iOS 16.4+).
async function keepScreenOn() {
    if (wakeLock || !('wakeLock' in navigator)) return;
    try {
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLock.addEventListener('release', () => (wakeLock = null));
    } catch {
        // Refused, e.g. in battery saver mode; the screen just dims as usual.
    }
}

async function showVideo() {
    await video.play();
    ar.setVideoElement(video, false);
    feetRenderer.setVideo(video);
    hideStatus();
    keepScreenOn();
}

// Stops a camera that was asked for but won't be used.
function release(request) {
    request?.then((s) => s.getTracks().forEach((track) => track.stop()), () => {});
}

function startCamera(request = null) {
    if (stopped) {
        release(request);
        return Promise.resolve();
    }
    cameraStarting ??= (async () => {
        stopCamera();
        showStatus('Starting camera… Allow camera access if your browser asks.');
        try {
            stream = await (request ?? openCamera());
        } catch (e) {
            consoleError('Could not start the camera:', e);
            showCameraError(e);
            return;
        }
        // The page went to the background (or failed) while the permission prompt was open.
        if (document.hidden || stopped) {
            stopCamera();
            return;
        }
        cameraStartedAt = Date.now();
        stream.getVideoTracks().forEach((track) => track.addEventListener('ended', onCameraEnded));
        video.srcObject = stream;
        try {
            await showVideo();
        } catch (e) {
            // Autoplay refused; playing from a tap is always allowed.
            consoleError('Could not play the camera:', e);
            showStatus('Tap to start the camera.', { busy: false, action: { label: 'Start camera', run: () => showVideo().catch(showCameraError) } });
        }
    })().finally(() => (cameraStarting = null));
    return cameraStarting;
}

function stopCamera() {
    stream?.getTracks().forEach((track) => {
        track.removeEventListener('ended', onCameraEnded);
        track.stop();
    });
    stream = null;
    video.srcObject = null;
    ar?.stopVideo();
}

// The camera can stop while the page is open, e.g. when another app takes it
// or a call comes in. Restart it, but don't loop if it keeps stopping.
function onCameraEnded() {
    stopCamera();
    if (document.hidden || stopped) return;
    if (Date.now() - cameraStartedAt > 5000) {
        startCamera();
    } else {
        showStatus('The camera stopped.', { busy: false, action: retryCamera });
    }
}

// Phones stop the camera when the page goes to the background (switching apps,
// locking the screen) and don't restart it, which leaves a frozen view. Release
// it here and start it again when the page is back.
document.addEventListener('visibilitychange', () => {
    if (!ar || stopped) return;
    if (document.hidden) {
        stopCamera();
        ar.setPaused(true);
    } else {
        ar.setPaused(false);
        startCamera();
    }
});
window.addEventListener('pagehide', () => stopCamera());
window.addEventListener('pageshow', (e) => {
    if (e.persisted && ar && !document.hidden) startCamera();
});

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
function applyFit(ar, fit) {
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

let trackingReady = false;
let feetVisible = false;
let loadedShoe = null;
let wantedShoe = null;
let loadingShoe = false;

function showTrackingHint() {
    if (!trackingReady) setHint('Starting foot tracking…');
    else if (feetVisible) setHint('Feet detected');
    else setHint('Looking for feet… stand 1–2 m back, both feet in frame');
}

function markSelected(id) {
    buttons.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.id === id)));
}

// Taps while a shoe is loading only change which shoe loads next, so a slow
// download can't replace a newer choice.
async function selectShoe(id) {
    wantedShoe = id;
    markSelected(id);
    if (loadingShoe) return;
    loadingShoe = true;
    let failedName = null;
    while (wantedShoe !== loadedShoe) {
        const shoeId = wantedShoe;
        const btn = buttons.find((b) => b.dataset.id === shoeId);
        setHint(`Loading ${btn.dataset.name}…`);
        try {
            if (btn.dataset.effect) {
                feetRenderer.hide();
                await ar.switchEffect(btn.dataset.effect, { trackingInit: { foot: true } });
                deeparEffect = btn.dataset.effect;
                applyFit(ar, fits[shoeId]);
            } else {
                if (deeparEffect !== 'tracker') {
                    await startFootTrackingOnly(ar, trackerEffect);
                    deeparEffect = 'tracker';
                }
                await feetRenderer.show(btn.dataset.glb, fits[shoeId], btn.dataset.legMask === '1');
            }
            loadedShoe = shoeId;
        } catch (e) {
            consoleError('Could not load shoe:', e);
            failedName = btn.dataset.name;
            wantedShoe = loadedShoe;
            markSelected(loadedShoe);
        }
    }
    loadingShoe = false;
    if (failedName) setHint(`Couldn't load the ${failedName}. Check your connection and tap it again.`, true);
    else showTrackingHint();
}

async function main() {
    if (!licenseKey) {
        fail('Missing DeepAR license key.');
        return;
    }
    const unsupported = unsupportedReason();
    if (unsupported) {
        fail(unsupported);
        return;
    }
    const noCamera = cameraUnavailable();
    if (noCamera) {
        fail(noCamera.message, noCamera.action);
        return;
    }

    showStatus('Starting camera… Allow camera access if your browser asks.');
    // Ask for the camera while the SDK downloads; the result is used below.
    const firstCamera = openCamera();
    firstCamera.catch(() => {});

    try {
        ar = await deepar.initialize({
            licenseKey,
            previewElement: stage,
            rootPath,
            additionalOptions: {
                cameraConfig: { disableDefaultCamera: true },
                hint: ['footInit'],
            },
        });
    } catch (e) {
        release(firstCamera);
        throw e;
    }

    // Phones can drop the GPU context under memory pressure; DeepAR can't recover.
    ar.getCanvas().addEventListener('webglcontextlost', (e) => {
        e.preventDefault();
        fail('The 3D view was interrupted by the phone.', reload);
    });

    ar.callbacks.onFootTrackingInitialized = (fovY) => {
        feetRenderer.setFieldOfView(fovY);
        trackingReady = true;
        if (!loadingShoe) showTrackingHint();
    };
    ar.callbacks.onFeetTracked = (left, right) => {
        feetRenderer.update(left, right);
        const visible = left.detected || right.detected;
        if (visible === feetVisible) return;
        feetVisible = visible;
        if (!loadingShoe) showTrackingHint();
    };
    ar.initializeFootTracking();

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            if (canWear(btn)) selectShoe(btn.dataset.id);
        });
    });

    // ?preview=<id> is the phone-test link n8n emails for a shoe that is still hidden.
    const params = new URLSearchParams(location.search);
    const wanted = params.get('preview') ?? params.get('shoe');
    const ready = buttons.filter(canWear);
    const first = ready.find((b) => b.dataset.id === wanted) ?? ready[0];
    if (first) selectShoe(first.dataset.id);

    await startCamera(firstCamera);
}

main().catch((e) => {
    consoleError(e);
    fail(`Could not start AR: ${e.message ?? e}`, reload);
});
