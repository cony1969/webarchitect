<x-ui.form-group-input label="ID" value="selectedProjectObj.id" readonly />
<x-ui.form-group-input label="Typ" value="'project'" readonly />
<x-ui.form-group-input label="Názov" model="selectedProjectObj.name" @input="window.dispatchEvent(new CustomEvent('project-data-updated'))" />
<x-ui.form-group-input label="Slug" value="selectedProjectObj.name ? selectedProjectObj.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') : ''" readonly />
