(() => {
    const options = [
        {value: '', label: 'Nenastavené', icon: 'fas fa-ban'},
        {value: 'left', label: 'Vľavo', icon: 'fas fa-align-left'},
        {value: 'center', label: 'Na stred', icon: 'fas fa-align-center'},
        {value: 'right', label: 'Vpravo', icon: 'fas fa-align-right'},
        {value: 'justify', label: 'Do bloku', icon: 'fas fa-align-justify'},
    ];
    function write(rows, value, classes = []) {
        if (!options.some(option => option.value === value)) return false;
        const important = rows.some(row => !row.pseudos?.length && row.name.toLowerCase() === 'text-align' && /!important\s*$/i.test(row.value)) || classes.some(token => /^text-(?:(?:sm|md|lg|xl|xxl)-)?(?:start|end|left|right|center|justify)$/.test(token));
        window.waBoxModel.upsert(rows, 'text-align', value, important);
        return true;
    }
    function read(rows) {
        const style = document.createElement('div').style;
        style.cssText = window.waComponentControls.serializeStyles(rows);
        return style.getPropertyValue('text-align');
    }
    window.waAlignment = {options, write, read};
    document.addEventListener('alpine:init', () => Alpine.data('waAlignmentEditor', () => ({
        options, selected: '',
        get store() { return this.$store.componentEditor; },
        get draft() { return this.store.current; },
        get disabled() { return !this.draft?.loaded || this.draft.loading || this.draft.saving || !!this.draft.codeError || !this.draft.controls.editable; },
        init() {
            this.$watch('$store.componentEditor.activeKey', () => this.refresh());
            this.$watch('draft?.controls.styleRows', () => this.refresh());
            this.refresh();
        },
        refresh() { this.selected = read(this.draft?.controls.styleRows || []); },
        choose(value) {
            if (this.disabled || !write(this.draft.controls.styleRows, value, this.draft.controls.classTokens)) return;
            this.store.stylesChanged(this.store.activeKey);
            this.refresh();
        },
        tabIndex(value, index) { return this.selected === value || (index === 0 && !options.some(option => option.value === this.selected)) ? 0 : -1; },
        navigate(event) {
            const keys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
            if (!keys.includes(event.key) || this.disabled) return;
            event.preventDefault();
            const current = [...this.$refs.switch.children].indexOf(event.target);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : (current + (['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1) + options.length) % options.length;
            this.choose(options[next].value);
            this.$refs.switch.children[next].focus();
        },
    })));
})();
