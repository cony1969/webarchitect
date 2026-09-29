<x-ui.form-group-input label="ID" value="selectedPageObj.id" readonly />
<x-ui.form-group-input label="Typ" value="'page'" readonly />
<x-ui.form-group-input label="Projekt" value="selectedPageProjectObj && selectedPageProjectObj.name ? selectedPageProjectObj.name : ''" readonly />
<x-ui.form-group-input label="Doména" value="selectedPageDomainObj && selectedPageDomainObj.name ? selectedPageDomainObj.name : ''" readonly />
<x-ui.form-group-input label="Názov" model="selectedPageObj.name" />
<x-ui.form-group-input label="Slug" value="selectedPageObj.name ? selectedPageObj.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') : ''" readonly />
