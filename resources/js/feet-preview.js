import * as deepar from 'deepar';
import { DEFAULT_FIT, FeetRenderer, startFootTrackingOnly } from './feet-renderer.js';

// The admin's "preview on my feet" page: Tripo's GLB on the tracked feet,
// before the shoe is approved. The shoe is fitted automatically (centred on
// each foot, one foot long, along the foot); that fit is saved to the
// submission and becomes the shoe's starting fit. A photo of the try-on can
// be sent to Claude, whose better fit replaces the automatic one.

const config = window.FEET_PREVIEW;
const stage = document.getElementById('feet-stage');
const status = document.getElementById('feet-status');
const statusText = status.querySelector('[data-status-text]');
const statusAction = status.querySelector('[data-status-action]');
const busy = status.querySelector('[data-busy]');
const hint = document.getElementById('feet-hint');
const savedNote = document.getElementById('feet-saved');
const legMaskButton = document.querySelector('[data-leg-mask]');

const reload = { label: 'Reload', run: () => location.reload() };
const feetRenderer = new FeetRenderer(stage, document.getElementById('feet-shoes'));

let fit = config.isClaudeFit
    ? structuredClone(config.fit)
    : { scale: DEFAULT_FIT.scale, rotation: [...DEFAULT_FIT.rotation], position: [...DEFAULT_FIT.position] };
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
        savedNote.textContent = response.ok ? describeFit() : `Could not save the fit (error ${response.status}). Reload and try again.`;
    } catch {
        savedNote.textContent = 'Could not save the fit. Check your connection.';
    }
}

// ---- controls ---------------------------------------------------------------------

function describeFit() {
    return config.isClaudeFit ? 'Using the fit Claude suggested.' : 'Fitted automatically to your feet.';
}

// "Feet inside" hides the shoe's footbed and collar where the wearer's foot
// and leg are, so the real foot shows inside the shoe.
function showLegMaskState() {
    legMaskButton.setAttribute('aria-pressed', String(legMask));
    legMaskButton.textContent = legMask ? 'Feet inside: on' : 'Feet inside: off';
}

legMaskButton.addEventListener('click', () => {
    legMask = !legMask;
    feetRenderer.setFit(fit, legMask);
    showLegMaskState();
    scheduleSave();
});
showLegMaskState();

// A fit saved by hand before the shoe was fitted automatically is replaced;
// a fit Claude answered with is kept.
const isSavedFitCurrent = JSON.stringify(config.fit) === JSON.stringify(fit);
if (!isSavedFitCurrent) {
    scheduleSave();
}
if (config.isClaudeFit && config.saveUrl) {
    savedNote.textContent = describeFit();
}

// ---- photo, and asking Claude for a better fit ------------------------------------

const photoButton = document.querySelector('[data-take-photo]');
const photoDialog = document.getElementById('feet-photo');
const photoImage = photoDialog.querySelector('[data-photo-image]');
const photoSave = photoDialog.querySelector('[data-photo-save]');
const photoComment = photoDialog.querySelector('[data-photo-comment]');
const photoSend = photoDialog.querySelector('[data-photo-send]');
const photoError = photoDialog.querySelector('[data-photo-error]');
let photoBlob = null;
let isClaudeWorking = config.isClaudeWorking;

function showPhotoError(message) {
    photoError.hidden = !message;
    photoError.textContent = message ?? '';
}

function showClaudeState() {
    if (!photoSend) return;
    photoSend.disabled = isClaudeWorking;
    photoSend.textContent = isClaudeWorking ? 'Claude is working on the last photo…' : 'Send to Claude to optimize the fit';
}

function toBlob(canvas, quality) {
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
}

photoButton.addEventListener('click', async () => {
    const canvas = feetRenderer.snapshot();
    let blob = await toBlob(canvas, 0.9);
    if (blob && blob.size > config.maxPhotoBytes) {
        blob = await toBlob(canvas, 0.6);
    }
    if (!blob) {
        savedNote.textContent = 'Could not take the photo. Try again.';
        return;
    }
    if (photoImage.src) URL.revokeObjectURL(photoImage.src);
    photoBlob = blob;
    photoImage.src = URL.createObjectURL(blob);
    photoSave.href = photoImage.src;
    photoSave.download = `${config.photoName}-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.jpg`;
    showPhotoError(null);
    showClaudeState();
    photoDialog.showModal();
});

photoSend?.addEventListener('click', async () => {
    if (!photoBlob || isClaudeWorking) return;
    const comment = photoComment.value.trim();
    if (comment && comment.length < 5) {
        showPhotoError('Write a little more (at least 5 characters), or leave it empty.');
        return;
    }
    const body = new FormData();
    body.append('photo', photoBlob, 'try-on.jpg');
    if (comment) body.append('comment', comment);

    photoSend.disabled = true;
    photoSend.textContent = 'Sending…';
    showPhotoError(null);
    try {
        const response = await fetch(config.claudeUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body,
        });
        const answer = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = Object.values(answer.errors ?? {})[0]?.[0];
            showPhotoError(firstError ?? answer.message ?? `Could not send the photo (error ${response.status}).`);
            showClaudeState();
            return;
        }
        photoComment.value = '';
        photoDialog.close();
        isClaudeWorking = true;
        savedNote.textContent = 'Sent to Claude. The new fit shows here in about a minute; keep the camera open.';
        watchClaude(answer.round?.id);
    } catch {
        showPhotoError('Could not send the photo. Check your connection.');
        showClaudeState();
    }
});

// Asks every few seconds whether Claude has answered, and puts the new fit
// on the feet as soon as it is saved.
function watchClaude(roundId = null) {
    if (!config.claudeStatusUrl) return;
    const check = async () => {
        try {
            const response = await fetch(config.claudeStatusUrl, { headers: { Accept: 'application/json' } });
            const round = response.ok ? (await response.json()).round : null;
            if (round && (roundId === null || round.id === roundId) && round.status !== 'processing') {
                isClaudeWorking = false;
                showClaudeState();
                if (round.status === 'applied' && round.fit) {
                    fit = structuredClone(round.fit);
                    config.isClaudeFit = true;
                    feetRenderer.setFit(fit, legMask);
                    savedNote.textContent = `Claude's new fit is on${round.message ? `: ${round.message}` : '.'}`;
                } else {
                    savedNote.textContent = round.status === 'no_change'
                        ? `Claude changed nothing${round.message ? `: ${round.message}` : '.'}`
                        : `Claude could not finish${round.message ? `: ${round.message}` : '.'}`;
                }
                return;
            }
        } catch {
            // Try again on the next tick.
        }
        setTimeout(check, 5000);
    };
    setTimeout(check, 5000);
}

if (isClaudeWorking) {
    savedNote.textContent = 'Claude is working on your last photo…';
    watchClaude();
}

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
            hint.textContent = visible ? 'Feet found. The shoe is fitted automatically.' : 'Looking for feet… stand 1–2 m back, both feet in frame';
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
    feetRenderer.setVideo(video);
    status.hidden = true;
    photoButton.disabled = false;

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
