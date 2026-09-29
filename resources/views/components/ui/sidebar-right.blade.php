@props(['layout' => [], 'blockRightTopHeight' => 300])

<aside class="advanced-sidebar-right" :style="{ width: sidebarRightWidth + 'px' }" style="display: flex; flex-direction: column; height: 100%; border-left: 1px solid #4e566d; background: #1e222d; overflow: hidden;">
    <!-- 1. VLASTNOSTI (Flexibilná výška podľa resizeru) -->
    <div :style="{ height: blockRightTopHeight + 'px' }" style="flex: none; display: flex; flex-direction: column; overflow: hidden;">
        <x-ui.sidebar-right.properties :layout="$layout" :block-right-top-height="$blockRightTopHeight" />
    </div>

    <!-- HORIZONTÁLNY RESIZER -->
    <div class="sidebar-resizer-horizontal" @mousedown="isResizingRightHeight = true; document.body.style.cursor = 'row-resize'; document.body.style.userSelect = 'none';"></div>

    <!-- 2. VZHĽAD (Automatické roztiahnutie do zvyšku obrazovky) -->
    <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
        <x-ui.sidebar-right.apperance :layout="$layout" />
    </div>
</aside>
