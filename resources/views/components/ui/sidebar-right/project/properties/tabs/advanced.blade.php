<div class="d-flex gap-2 w-100">
    <x-ui.form-group-input label="Počet domén" value="selectedProjectObj.domains ? selectedProjectObj.domains.length : 0" readonly />
    <x-ui.form-group-input label="Počet stránok" value="selectedProjectObj.domains ? selectedProjectObj.domains.reduce((sum, d) => sum + (d.pages ? d.pages.length : 0), 0) : 0" readonly />
</div>
