@props(['layout' => []])

<aside class="advanced-sidebar-right" aria-label="Vlastnosti a vzhľad">
    <x-ui.sidebar-accordion id="right" initial="properties" :sections="['properties', 'appearance']">
        <x-ui.sidebar-right.properties :layout="$layout" />
        <x-ui.sidebar-right.apperance :layout="$layout" />
    </x-ui.sidebar-accordion>
</aside>
