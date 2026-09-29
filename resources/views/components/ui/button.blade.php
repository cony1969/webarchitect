@props(['click' => '', 'style' => ''])

<button type="button" 
        @if($click) @click.prevent="{{ $click }}" @endif
        {{ $attributes }}
        style="background: transparent; border: none; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; padding: 0; line-height: 1; {{ $style }}"
>
    {{ $slot }}
</button>
