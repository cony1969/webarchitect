<template x-if="$store.pagePreview.current">
    <div class="wa-project-properties" style="padding:20px 0" x-data="{ get page() { return $store.pagePreview.current.data; } }">
        <h3 class="wa-project-properties-title" x-text="page.name || 'Stránka'"></h3><div class="wa-project-properties-info"><span>Projekt</span><strong x-text="page.project_name"></strong></div><div class="wa-project-properties-info"><span>Doména</span><strong x-text="page.domain_name"></strong></div><div class="wa-project-properties-info"><span>Slug</span><strong x-text="page.slug"></strong></div><div class="wa-project-properties-info"><span>Použitia komponentov</span><strong x-text="page.placements_count || 0"></strong></div><div class="wa-project-properties-info"><span>Verzia stránky</span><strong x-text="page.revision"></strong></div><p class="wa-project-note">Stránka sa vykresľuje z vlastných komponentov projektu. Ich úpravy sa zobrazia pri obnovení náhľadu.</p>
    </div>
</template>
