<template x-if="selectedObjectType === 'domain' && selectedDomainObj !== null">
    <div class="d-flex flex-column w-100">
        <x-ui.sidebar-right.domain.properties.tabs.basic />

        <div class="mt-3 w-100">
            <button type="button" class="btn btn-success btn-sm fw-bold" @click="saveToDb()">Uložiť</button>
        </div>
    </div>
</template>
