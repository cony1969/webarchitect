<template x-if="$store.projectEditor.active && !$store.projectEditor.isDomain">
    <div class="wa-project-properties">
        <h3 class="wa-project-properties-title">Prehľad projektu</h3>
        <div class="wa-project-properties-info"><span>Domény / subdomény</span><strong x-text="$store.projectEditor.current.overview.domains_count || 0"></strong></div>
        <div class="wa-project-properties-info"><span>Stránky</span><strong x-text="$store.projectEditor.current.overview.pages_count || 0"></strong></div>
        <div class="wa-project-properties-info"><span>Komponenty</span><strong x-text="$store.projectEditor.current.overview.components_count || 0"></strong></div>
        <div class="wa-project-properties-info"><span>Sekcie</span><strong x-text="$store.projectEditor.current.overview.sections_count || 0"></strong></div>
        <p class="wa-project-note">Technické nastavenia patria jednotlivým doménam a subdoménam.</p>
    </div>
</template>
