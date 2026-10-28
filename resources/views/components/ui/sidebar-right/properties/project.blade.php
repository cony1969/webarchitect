<template x-if="selectedObjectType === 'project' && selectedProjectObj !== null">
    <div class="wa-project-properties">
        <p class="wa-project-muted">Projekt</p><strong x-text="selectedProjectObj.name"></strong>
        <button type="button" class="btn wa-project-secondary mt-3" @click="$dispatch('add-new-tab', {title: selectedProjectObj.name, mode: 'project', projectId: window.sidebarProjectIds[selectedProjectObj.id]})">Otvoriť editor projektu</button>
    </div>
</template>
