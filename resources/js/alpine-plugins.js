export function qrCopy() {
    return {
        copied: false,
        async copyQr() {
            const svg = this.$refs.qrcode.querySelector('svg');
            const svgData = new XMLSerializer().serializeToString(svg);
            const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const img = new Image();
            await new Promise(resolve => { img.onload = resolve; img.src = url; });
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            canvas.getContext('2d').drawImage(img, 0, 0);
            URL.revokeObjectURL(url);
            canvas.toBlob(async png => {
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': png })]);
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            }, 'image/png');
        }
    };
}
