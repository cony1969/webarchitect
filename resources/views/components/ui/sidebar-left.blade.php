@props(['layout' => [], 'components' => [], 'savedTab' => 'native'])

@php
    $baseDir = config_path('ui');
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

<aside class="advanced-sidebar" aria-label="Nástroje a projekty">
    <x-ui.sidebar-accordion id="left" initial="components" :sections="['components', 'layouts', 'projects']">
        <x-ui.sidebar-left.components :layout="$layout" :components="$components" :saved-tab="$savedTab" />
        <x-ui.sidebar-left.layouts :layout="$layout" />
        <x-ui.sidebar-left.projects :layout="$layout" :projects-data="$projectsData" />
    </x-ui.sidebar-accordion>
</aside>
