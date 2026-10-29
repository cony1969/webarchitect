@props(['layout' => []])
<section class="sidebar-block block-middle" :class="{ 'is-open': activeSection === 'layouts' }"
    x-data="{
        layouts: [],
        async init() { await this.$store.layoutEditor.loadCatalog(); this.layouts = this.$store.layoutEditor.catalog.layouts; },
        openLayout(item) { this.$dispatch('add-new-tab', { title: item.name, mode: 'layout', layoutId: item.id }); },
        triggerModal() { this.$dispatch('add-new-tab', { title: 'Nový layout', mode: 'layout', layoutNew: true }); }
    }"
    @layout-catalog-updated.window="layouts = $event.detail.catalog.layouts">
    <x-ui.accordion-header section="layouts" :title="$layout['blocks']['block_2']['title'] ?? 'Layouts'" action-label="Pridať layout" action="triggerModal()" />
    <div class="block-content sidebar-accordion-content" id="accordion-layouts-content" role="region" aria-labelledby="accordion-layouts-toggle" x-show="activeSection === 'layouts'" x-cloak style="padding:0 15px;flex:1;overflow-y:auto;color:#fff">
        <p x-show="$store.layoutEditor.catalogError" class="wa-project-message is-error"><span x-text="$store.layoutEditor.catalogError"></span> <button type="button" @click="$store.layoutEditor.loadCatalog().then(() => layouts = $store.layoutEditor.catalog.layouts)">Skúsiť znova</button></p>
        <template x-for="item in layouts" :key="item.id"><button type="button" class="wa-layout-library-item" :aria-label="'Otvoriť layout ' + item.name" @click="openLayout(item)"><span aria-hidden="true">📐</span> <span x-text="item.name"></span></button></template>
        <p x-show="!layouts.length && !$store.layoutEditor.catalogError" class="wa-project-muted mt-3">Kliknite na + pre vytvorenie layoutu.</p>
    </div>
</section>
