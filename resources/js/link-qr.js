import QRCode from 'qrcode';

// Draws a QR code of each [data-link-qr] element's link, so the admin can
// open the page on a phone by scanning it instead of copying the link.
document.querySelectorAll('[data-link-qr]').forEach(async (container) => {
    try {
        container.innerHTML = await QRCode.toString(container.dataset.linkQr, {
            type: 'svg',
            margin: 1,
            errorCorrectionLevel: 'M',
        });
    } catch {
        container.textContent = 'The QR code could not be drawn. Use the link below.';
    }
});
