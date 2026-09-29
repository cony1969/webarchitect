@props(['submit' => ''])

<div x-show="{{ $attributes->get('show') ?? 'show' }}" 
     x-transition.opacity
     @keydown.escape.window="{{ $attributes->get('show') ?? 'show' }} = false"
     style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.6); z-index: 9999;"
>
    <!-- Absolútne poziciovanie do presného stredu obrazovky, ktoré prebije Bootstrap vplyvy -->
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #1e222d; border: 1px solid #2d3345; width: 400px; border-radius: 6px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        
        <!-- Hlavička -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: #242936; border-bottom: 1px solid #2d3345;">
            <h5 class="modal-title" style="font-size: 14px; font-weight: 600; color: #3bc0c3; margin: 0;" x-text="{{ $attributes->get('title') ?? 'title' }}"></h5>
            <button type="button" class="btn-close btn-close-white" style="background: none; border: none; color: #fff; cursor: pointer; font-size: 12px;" @click="{{ $attributes->get('show') ?? 'show' }} = false">✕</button>
        </div>

        <!-- Obsah -->
        <div style="padding: 20px; color: #fff;">
            {{ $slot }}
        </div>

        <!-- Päta -->
        <div style="display: flex; justify-content: flex-end; gap: 10px; padding: 15px 20px; background: #242936; border-top: 1px solid #2d3345;">
            <button type="button" class="btn btn-sm" style="background: #31364a; border: 1px solid #4e566d; font-size: 12px; color: #fff; padding: 5px 15px; cursor: pointer; border-radius: 4px;" @click="{{ $attributes->get('show') ?? 'show' }} = false">Zrušiť</button>
            <button type="button" class="btn btn-sm" style="background: #3bc0c3; border: none; font-size: 12px; color: #1e222d; font-weight: 600; padding: 5px 15px; cursor: pointer; border-radius: 4px;" @click="{{ $submit }}">Uložiť</button>
        </div>

    </div>
</div>
