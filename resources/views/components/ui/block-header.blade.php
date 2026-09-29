@props(['title' => '', 'click' => ''])

<div class="custom-panel-title" style="all: unset; display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100% !important; box-sizing: border-box !important; padding: 10px 15px !important; background: #242936 !important; border-bottom: 1px solid #2d3345 !important; min-height: 38px !important;">
    <span style="all: unset; font-family: sans-serif !important; font-size: 11px !important; text-transform: uppercase !important; font-weight: 600 !important; letter-spacing: 0.5px !important; color: #fff !important; display: inline-block !important;">
        {{ $title }}
    </span>
    
    <span @if($click) @click.prevent="{{ $click }}" @endif 
          style="font-family: sans-serif !important; font-size: 14px !important; font-weight: bold !important; color: #3bc0c3 !important; cursor: pointer !important; padding: 0 5px !important; display: inline-block !important; user-select: none !important;"
          onmouseover="this.style.color='#fff'"
          onmouseout="this.style.color='#3bc0c3'">
        +
    </span>
</div>
