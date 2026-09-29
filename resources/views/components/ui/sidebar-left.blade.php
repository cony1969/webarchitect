@props(['layout' => [], 'components' => [], 'savedTab' => 'native'])

@php
    $baseDir = '/data/d/4/d4f1af97-73d7-4842-9f8a-d4679c7e0428/pokerclub.sk/sub/war/config/ui';
    $projectsFilePath = $baseDir . '/content/projects.json';
    $layoutsFilePath = $baseDir . '/content/layouts.json';
    
    $projectsData = [];
    if (file_exists($projectsFilePath)) {
        $allData = json_decode(file_get_contents($projectsFilePath), true) ?: [];
        
        $userId = auth()->id();
        $userKey = 'user_' . $userId;
        
        if ($userId && isset($allData[$userKey]) && is_array($allData[$userKey])) {
            $projectsData = $allData[$userKey];
        }
    }

    $layoutsData = [];
    if (file_exists($layoutsFilePath)) {
        $decodedLayouts = json_decode(file_get_contents($layoutsFilePath), true) ?: [];
        if (is_array($decodedLayouts)) {
            $layoutsData = $decodedLayouts;
        }
    }

    $projectsTreeState = [
        'selectedProject' => null,
        'selectedDomain' => null,
        'selectedPage' => null,
    ];

    if (auth()->check()) {
        $savedTreeState = \DB::table('user_settings')
            ->where('user_id', auth()->id())
            ->where('name', 'sidebar_projects_tree_position')
            ->value('value');

        if (is_string($savedTreeState) && $savedTreeState !== '') {
            $decodedTreeState = json_decode($savedTreeState, true);
            if (is_array($decodedTreeState)) {
                $projectsTreeState['selectedProject'] = $decodedTreeState['selectedProject'] ?? null;
                $projectsTreeState['selectedDomain'] = $decodedTreeState['selectedDomain'] ?? null;
                $projectsTreeState['selectedPage'] = $decodedTreeState['selectedPage'] ?? null;
            }
        }
    }
@endphp

{{-- Globálny JavaScript objekt mimo PHP bloku --}}
<script>
    window.sidebarProjectsData = {!! json_encode($projectsData) !!};
    window.sidebarLayoutsData = {!! json_encode($layoutsData) !!};
    window.sidebarProjectsTreeState = {!! json_encode($projectsTreeState) !!};
</script>

<aside class="advanced-sidebar" :style="{ width: sidebarWidth + 'px' }" style="display: flex; flex-direction: column; height: 100%; overflow: hidden; background: #1e222d;">
    
    <!-- 1. HORNÝ BLOK (Komponenty) -->
    <div :style="{ height: blockTopHeight + 'px' }" style="flex: none; display: flex; flex-direction: column; overflow: hidden;">
        @if(view()->exists('components.ui.sidebar-left.components'))
            <x-ui.sidebar-left.components :layout="$layout" :components="$components" :saved-tab="$savedTab" />
        @else
            <div style="color: red; padding: 10px;">Chyba: Súbor ui/sidebar-left/components.blade.php neexistuje!</div>
        @endif
    </div>

    {{-- Resizer pre horný blok --}}
    <div class="sidebar-resizer-horizontal" @mousedown="isResizingHeight1 = true; document.body.style.cursor = 'row-resize'; document.body.style.userSelect = 'none';"></div>

    <!-- 2. STREDNÝ BLOK (Layouts) -->
    <div :style="{ height: blockMiddleHeight + 'px' }" style="flex: none; display: flex; flex-direction: column; overflow: hidden;">
        @if(view()->exists('components.ui.sidebar-left.layouts'))
            <x-ui.sidebar-left.layouts :layout="$layout" />
        @else
            <div style="color: red; padding: 10px;">Chyba: Súbor ui/sidebar-left/layouts.blade.php neexistuje!</div>
        @endif
    </div>
    
    {{-- Resizer pre spodný blok --}}
    <div class="sidebar-resizer-horizontal" @mousedown="isResizingHeight2 = true; document.body.style.cursor = 'row-resize'; document.body.style.userSelect = 'none';"></div>

    <!-- 3. SPODNÝ BLOK (Projects) - vyplní celý zvyšný priestor a umožní skrolovanie obsahu -->
    <div class="sidebar-scroll-area" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
        @if(view()->exists('components.ui.sidebar-left.projects'))
						<x-ui.sidebar-left.projects :layout="$layout" :projects-data="$projectsData" />

        @else
            <div style="color: red; padding: 10px;">Chyba: Súbor ui/sidebar-left/projects.blade.php neexistuje!</div>
        @endif
    </div>
</aside>
