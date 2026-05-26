<?php

namespace App\View\Components;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\View\Component;

class QrCode extends Component
{
    public string $svg;

    public function __construct(string $value, int $size = 200)
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $raw = (new Writer($renderer))->writeString($value);

        // Strip XML declaration for safe inline embedding
        $this->svg = preg_replace('/^<\?xml[^?]*\?>\s*/i', '', $raw);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('components.qr-code');
    }
}
