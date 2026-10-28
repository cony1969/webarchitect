@props(['layout' => [], 'components' => [], 'savedTab' => 'native'])

@php
    $projects = app(\App\Services\Cms\ProjectTree::class);
    $sections = app(\App\Services\Cms\SectionLibrary::class);
    $projectsData = $projects->read(auth()->user());
    $layoutsData = $sections->read(auth()->user());
    $projectsRevision = $projects->revision(auth()->user());
    $layoutsRevision = $sections->revision(auth()->user());

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
    window.sidebarProjectsRevision = @js($projectsRevision);
    window.sidebarLayoutsRevision = @js($layoutsRevision);
    window.sidebarProjectIds = @js(\App\Models\Cms\Project::where('user_id', auth()->id())->pluck('id', 'legacy_id'));
    window.sidebarProjectsData = @js($projectsData);
    window.sidebarLayoutsData = @js($layoutsData);
    window.sidebarProjectsTreeState = @js($projectsTreeState);
</script>

<aside class="advanced-sidebar" aria-label="Nástroje a projekty">
    <x-ui.sidebar-accordion id="left" initial="components" :sections="['components', 'layouts', 'projects']">
        <x-ui.sidebar-left.components :layout="$layout" :components="$components" :saved-tab="$savedTab" />
        <x-ui.sidebar-left.layouts :layout="$layout" />
        <x-ui.sidebar-left.projects :layout="$layout" :projects-data="$projectsData" />
    </x-ui.sidebar-accordion>
</aside>
