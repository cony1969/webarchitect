<template x-if="selectedObjectType === 'project' && selectedProjectObj !== null">
    <div class="d-flex flex-column w-100">
        <x-ui.tabs
            :tabs="[
                ['id' => 'basic', 'title' => 'ZÁKLADNÉ'],
                ['id' => 'appearance', 'title' => 'VZHĽAD'],
                ['id' => 'bootstrap', 'title' => 'BOOTSTRAP'],
                ['id' => 'advanced', 'title' => 'ROZŠÍRENÉ'],
            ]"
            active-var="projectTab"
            change-handler="setProjectTab"
            :scrollable="true"
            scroll-ref="projectTabsWrap"
            :wheel-scroll="true"
            data-attr-name="data-project-tab"
            item-min-width="110px"
        />

        <div x-show="projectTab === 'basic'" class="w-100">
            <x-ui.sidebar-right.project.properties.tabs.basic />
        </div>

        <div x-show="projectTab === 'appearance'" class="w-100">
            <x-ui.sidebar-right.project.properties.tabs.appearance />
        </div>

        <div x-show="projectTab === 'bootstrap'" class="w-100">
            <x-ui.sidebar-right.project.properties.tabs.bootstrap />
        </div>

        <div x-show="projectTab === 'advanced'" class="w-100">
            <x-ui.sidebar-right.project.properties.tabs.advanced />
        </div>

        <div class="mt-3 w-100">
            <button type="button" class="btn btn-success btn-sm fw-bold" @click="saveToDb()">Uložiť</button>
        </div>
    </div>
</template>
