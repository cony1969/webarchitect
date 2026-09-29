@props(['app' => [], 'layout' => []])

@php
    // Načítanie dát z configu, kam ich uložil Provider
    $components = config('ui.components', []);

    $userSettings = [];
    if (auth()->check()) {
        $userSettings = \DB::table('user_settings')
            ->where('user_id', auth()->id())
            ->whereIn('name', ['sidebar_active_tab'])
            ->pluck('value', 'name')
            ->toArray();
    }

    // Načítanie rozmerov zo session s fallbackom na JSON
    $sidebarWidth = session('sidebar_width', $layout['sidebar']['width'] ?? 320);
    $blockTopHeight = session('block_top_height', $layout['blocks']['block_1']['height'] ?? 200);
    $blockMiddleHeight = session('block_middle_height', $layout['blocks']['block_2']['height'] ?? 200);
    $sidebarRightWidth = session('sidebar_right_width', 340);
    $blockRightTopHeight = session('block_right_top_height', 380);
    
    // Načítanie uloženej záložky primárne z user_settings, fallback session
    $savedTab = (string) ($userSettings['sidebar_active_tab'] ?? session('sidebar_active_tab', 'native'));
@endphp

<div class="app-container">
    <!-- 1. HORNÁ LIŠTA (TOPBAR) -->
    <header class="main-header">
        <div class="brand">{{ $app['appName'] ?? 'Web Architect' }}</div>
        <nav class="top-nav">
            @foreach($topMenu ?? [] as $item)
                <x-ui.menu-item :item="$item" />
            @endforeach
        </nav>
        <x-ui.topbar.profile />
    </header>

    <!-- 2. HLAVNÝ WORKSPACE S NAČÍTANÍM ROZMEROV ZO SESSION / JSON -->
    <div class="workspace-wrapper" 
         x-data="{ 
             sidebarWidth: {{ $sidebarWidth }}, 
             blockTopHeight: {{ $blockTopHeight }},
             blockMiddleHeight: {{ $blockMiddleHeight }},
             sidebarRightWidth: {{ $sidebarRightWidth }},
             blockRightTopHeight: {{ $blockRightTopHeight }},
             isResizingWidth: false,
             isResizingRightWidth: false,
             isResizingHeight1: false,
             isResizingHeight2: false,
             isResizingRightHeight: false,
             resizeTimeout: null,

             saveSizes() {
                clearTimeout(this.resizeTimeout);
                this.resizeTimeout = setTimeout(() => {
                    fetch('/sidebar/resize', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            width: this.sidebarWidth,
                            top_height: this.blockTopHeight,
                            middle_height: this.blockMiddleHeight,
                            right_width: this.sidebarRightWidth,
                            right_top_height: this.blockRightTopHeight
                        })
                    })
                    .then(response => response.json())
                    .then(payload => {
                        window.dispatchEvent(new CustomEvent('resizer-db-debug', {
                            detail: payload || {}
                        }));
                    })
                    .catch(() => {});
                }, 300);
            },
             
             init() {
                 window.addEventListener('mousemove', (e) => {
                     let maxPanelHeight = window.innerHeight * 0.33;

                     if (this.isResizingWidth) {
                         let newWidth = e.clientX;
                         if (newWidth > 200 && newWidth < 600) {
                             this.sidebarWidth = newWidth;
                             this.saveSizes();
                         }
                     }

                     if (this.isResizingRightWidth) {
                         let newRightWidth = window.innerWidth - e.clientX;
                         if (newRightWidth > 240 && newRightWidth < 700) {
                             this.sidebarRightWidth = newRightWidth;
                             this.saveSizes();
                         }
                     }
                     if (this.isResizingHeight1) {
                         let containerTop = document.querySelector('.advanced-sidebar').getBoundingClientRect().top;
                         let newHeight = e.clientY - containerTop;
                         if (newHeight > 80 && newHeight <= maxPanelHeight) {
                             this.blockTopHeight = newHeight;
                             this.saveSizes();
                         }
                     }
                     if (this.isResizingHeight2) {
                         let resizer1Bottom = document.querySelector('.block-middle').getBoundingClientRect().top;
                         let newHeight = e.clientY - resizer1Bottom;
                         if (newHeight > 80 && newHeight <= maxPanelHeight) {
                             this.blockMiddleHeight = newHeight;
                             this.saveSizes();
                         }
                     }

                     if (this.isResizingRightHeight) {
                         let rightSidebar = document.querySelector('.advanced-sidebar-right');
                         if (rightSidebar) {
                             let rightTop = rightSidebar.getBoundingClientRect().top;
                             let rightHeight = e.clientY - rightTop;
                             if (rightHeight > 80 && rightHeight <= maxPanelHeight) {
                                 this.blockRightTopHeight = rightHeight;
                                 this.saveSizes();
                             }
                         }
                     }
                 });

                 window.addEventListener('mouseup', () => {
                     this.isResizingWidth = false;
                     this.isResizingRightWidth = false;
                     this.isResizingHeight1 = false;
                     this.isResizingHeight2 = false;
                     this.isResizingRightHeight = false;
                     document.body.style.cursor = 'default';
                     document.body.style.userSelect = 'auto';
                 });
             }
         }"
    >

        <x-ui.sidebar-left :layout="$layout" :components="$components" :saved-tab="$savedTab" />

        <div class="sidebar-resizer-vertical" @mousedown="isResizingWidth = true; document.body.style.cursor = 'col-resize'; document.body.style.userSelect = 'none';"></div>

        <main class="main-content" style="padding: 0;">
            <x-ui.canvas-tabs>
                {{ $slot }}
            </x-ui.canvas-tabs>
        </main>

        <div class="sidebar-resizer-vertical" @mousedown="isResizingRightWidth = true; document.body.style.cursor = 'col-resize'; document.body.style.userSelect = 'none';"></div>

        <x-ui.sidebar-right :layout="$layout" :block-right-top-height="$blockRightTopHeight" />
    </div>
</div>
