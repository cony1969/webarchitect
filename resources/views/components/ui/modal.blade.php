@props(['submit' => '', 'submitLabel' => 'Uložiť', 'danger' => false, 'busy' => 'false', 'modalId' => 'engine-input-modal', 'cancel' => null])
@php($closeAction = $cancel ?? (($attributes->get('show') ?? 'show').' = false'))

<div id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title" x-cloak
     x-show="{{ $attributes->get('show') ?? 'show' }}"
     x-transition.opacity
     @keydown.escape.window="if ({{ $attributes->get('show') ?? 'show' }} && !({{ $busy }})) { {{ $closeAction }} }"
     @keydown.tab="const items = [...$el.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex=\'0\']')].filter(el => el.getClientRects().length); if (items.length) { $event.preventDefault(); const current = items.indexOf(document.activeElement); items[(current + ($event.shiftKey ? -1 : 1) + items.length) % items.length].focus(); }"
     style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.6); z-index: 9999;"
>
    <!-- Absolútne poziciovanie do presného stredu obrazovky, ktoré prebije Bootstrap vplyvy -->
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--wa-surface-base); border: 1px solid var(--wa-border); width: min(400px, calc(100vw - 32px)); border-radius: 6px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">

        <!-- Hlavička -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: var(--wa-surface-raised); border-bottom: 1px solid var(--wa-border);">
            <h5 id="{{ $modalId }}-title" class="modal-title" style="font-size: 14px; font-weight: 600; color: #3bc0c3; margin: 0;" x-text="{{ $attributes->get('title') ?? 'title' }}"></h5>
            <button type="button" class="btn-close btn-close-white" aria-label="Zavrieť dialóg" style="filter: none; background: none; border: none; color: #fff; cursor: pointer; font-size: 12px;" @click="{{ $closeAction }}" :disabled="{{ $busy }}">✕</button>
        </div>

        <!-- Obsah -->
        <div style="padding: 20px; color: #fff;">
            {{ $slot }}
        </div>

        <!-- Päta -->
        <div style="display: flex; justify-content: flex-end; gap: 10px; padding: 15px 20px; background: var(--wa-surface-raised); border-top: 1px solid var(--wa-border);">
            <button type="button" class="btn btn-sm" style="background: var(--wa-surface-hover); border: 1px solid var(--wa-border-strong); font-size: 12px; color: #fff; padding: 5px 15px; cursor: pointer; border-radius: 4px;" @click="{{ $closeAction }}" :disabled="{{ $busy }}" data-modal-cancel>Zrušiť</button>
            <button type="button" class="btn btn-sm" style="background: {{ $danger ? '#c73e42' : '#3bc0c3' }}; border: none; font-size: 12px; color: {{ $danger ? '#fff' : 'var(--wa-surface-base)' }}; font-weight: 600; padding: 5px 15px; cursor: pointer; border-radius: 4px;" @click="{{ $submit }}" :disabled="{{ $busy }}" :aria-busy="{{ $busy }}">{{ $submitLabel }}</button>
        </div>

    </div>
</div>
