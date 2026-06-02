<div
    class="space-y-4 p-1"
    x-data="{
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
    }"
>

    @if (! $competition->isPublicScheduleAvailable())
        <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
            This page will become publicly accessible once enrolments are closed.
        </div>
    @endif

    {{-- QR code --}}
    <div x-ref="qrcode" class="flex justify-center">
        <x-qr-code :value="$url" :size="220" />
    </div>

    {{-- URL --}}
    <div class="rounded-lg bg-gray-100 border border-gray-200 px-3 py-2 text-center">
        <a href="{{ $url }}" target="_blank" style="color: #2563eb; font-size: 0.875rem; word-break: break-all;" class="hover:underline">
            {{ $url }}
        </a>
    </div>

    {{-- Copy QR button --}}
    <div class="flex justify-center">
        <button
            type="button"
            x-on:click="copyQr()"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
        >
            <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-4 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span x-text="copied ? 'Copied!' : 'Copy QR code'"></span>
        </button>
    </div>
</div>
