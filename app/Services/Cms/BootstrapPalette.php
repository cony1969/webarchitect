<?php

namespace App\Services\Cms;

class BootstrapPalette
{
    public function css(array $colors): string
    {
        $rules = [];
        foreach (config('domain-editor.defaults.bootstrap.colors') as $name => $default) {
            $color = strtolower($colors[$name] ?? $default);
            if (! preg_match('/^#[0-9a-f]{6}$/', $color) || $color === $default) {
                continue;
            }
            $rgb = $this->rgb($color);
            $foreground = $this->foreground($rgb);
            $hover = $this->mix($rgb, $foreground === '#ffffff' ? 0 : 255, .15);
            $tint = $this->mix($rgb, 255, .8);
            $border = $this->mix($rgb, 255, .6);
            $text = $this->mix($rgb, 0, .6);
            $rules[] = ':root{--bs-'.$name.':'.$color.';--bs-'.$name.'-rgb:'.implode(',', $rgb).'}';
            $rules[] = '.bg-'.$name.'{background-color:'.$color.'!important}.text-'.$name.'{color:'.$color.'!important}.border-'.$name.'{border-color:'.$color.'!important}';
            $rules[] = '.link-'.$name.'{color:'.$color.'}.link-'.$name.':hover,.link-'.$name.':focus{color:'.$hover.'}';
            $rules[] = '.btn-'.$name.'{--bs-btn-bg:'.$color.';--bs-btn-border-color:'.$color.';--bs-btn-color:'.$foreground.';--bs-btn-hover-bg:'.$hover.';--bs-btn-hover-color:'.$foreground.';--bs-btn-hover-border-color:'.$hover.';color:'.$foreground.';background-color:'.$color.';border-color:'.$color.'}';
            $rules[] = '.btn-'.$name.':hover,.btn-'.$name.':focus,.btn-'.$name.':active,.btn-'.$name.'.active,.btn-check:checked+.btn-'.$name.',.btn-check:active+.btn-'.$name.'{color:'.$foreground.';background-color:'.$hover.';border-color:'.$hover.';box-shadow:0 0 0 .25rem rgba('.implode(',', $rgb).',.25)}';
            $rules[] = '.btn-'.$name.':disabled,.btn-'.$name.'.disabled{color:'.$foreground.';background-color:'.$color.';border-color:'.$color.'}';
            $rules[] = '.btn-outline-'.$name.'{color:'.$color.';border-color:'.$color.'}.btn-outline-'.$name.':hover,.btn-outline-'.$name.':active,.btn-outline-'.$name.'.active,.btn-check:checked+.btn-outline-'.$name.'{color:'.$foreground.';background-color:'.$color.';border-color:'.$color.'}';
            $rules[] = '.alert-'.$name.'{color:'.$text.';background-color:'.$tint.';border-color:'.$border.'}.alert-'.$name.' .alert-link{color:'.$text.'}';
            if ($name === 'primary') {
                $rules[] = 'a:not([class]){color:'.$color.'}.nav-pills .nav-link.active,.progress-bar,.page-item.active .page-link,.form-check-input:checked{background-color:'.$color.';border-color:'.$color.'}';
            }
        }

        return implode("\n", $rules);
    }

    private function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private function mix(array $rgb, int $target, float $weight): string
    {
        return '#'.implode('', array_map(fn ($value) => str_pad(dechex((int) round($value * (1 - $weight) + $target * $weight)), 2, '0', STR_PAD_LEFT), $rgb));
    }

    private function foreground(array $rgb): string
    {
        $linear = array_map(fn ($value) => $value / 255 <= .04045 ? $value / 255 / 12.92 : (($value / 255 + .055) / 1.055) ** 2.4, $rgb);
        $luminance = .2126 * $linear[0] + .7152 * $linear[1] + .0722 * $linear[2];

        return 1.05 / ($luminance + .05) >= ($luminance + .05) / .05 ? '#ffffff' : '#000000';
    }
}
