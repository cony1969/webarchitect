@props(['hoveredElement' => ''])

<div class="canvas-footer-inspector"
     style="background: #242936; border-top: 1px solid #2d3345; padding: 6px 15px; display: flex; align-items: center; justify-content: space-between; font-family: monospace; font-size: 11px; color: #8a94ad; flex: 0 0 auto; user-select: none; gap: 10px;">
    <div style="display: flex; align-items: center; gap: 12px; min-width: 0; flex-wrap: wrap;">
        <div>
            <span style="color: #3bc0c3; font-weight: bold;">[ REZIM: </span>
            <span style="color: #fff; font-weight: bold;" x-text="currentMode.toUpperCase()"></span>
            <span style="color: #3bc0c3; font-weight: bold;"> ]</span>
        </div>
        <div style="color: #4e566d;">|</div>
        <div>
            <span>Objekt pod kurzorom:</span>
            <span style="color: #fff; font-weight: bold;" x-text="{{ $hoveredElement }}"></span>
        </div>
    </div>
</div>
