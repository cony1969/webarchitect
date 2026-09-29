<x-ui.form-group-input label="ID" value="selectedDomainObj.id" readonly />
<x-ui.form-group-input label="Typ" value="'domain'" readonly />
<x-ui.form-group-input label="Projekt" value="selectedDomainProjectObj && selectedDomainProjectObj.name ? selectedDomainProjectObj.name : ''" readonly />
<x-ui.form-group-input label="Názov" model="selectedDomainObj.name" />
<x-ui.form-group-input label="Slug" value="selectedDomainObj.name ? selectedDomainObj.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') : ''" readonly />
<x-ui.form-group-input label="Počet stránok" value="selectedDomainObj.pages ? selectedDomainObj.pages.length : 0" readonly />
