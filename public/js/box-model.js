(() => {
    const sides = ['top', 'right', 'bottom', 'left'];
    const property = (group, side) => group === 'border' ? `border-${side}-width` : `${group}-${side}`;
    function normalize(name, input) {
        let value = String(input).trim();
        if (/^-?(?:\d+\.?\d*|\.\d+)$/.test(value)) value += 'px';
        if (value && !CSS.supports(name, value.replace(/\s*!important\s*$/i, ''))) throw new Error('Neplatná CSS hodnota. Použite napr. 12px, 1rem alebo 5%.');
        return value;
    }
    function important(rows, name, classes = []) {
        const family = name.split('-')[0];
        const inherited = rows.some(row => !row.pseudos?.length && (row.name.toLowerCase() === family || row.name.toLowerCase().startsWith(family + '-')) && /!important\s*$/i.test(row.value));
        const utility = classes.some(token => family === 'border' ? /^border(?:-|$)/.test(token) : new RegExp('^' + family[0] + '[trblxyse]?-(?:(?:sm|md|lg|xl|xxl)-)?(?:[0-5]|auto)$').test(token));
        return inherited || utility;
    }
    function upsert(rows, name, value, priority = false) {
        const existing = rows.find(row => !row.pseudos?.length && row.name.toLowerCase() === name);
        const retained = rows.filter(row => row.pseudos?.length || row.name.toLowerCase() !== name);
        if (value) retained.push({id: existing?.id || crypto.randomUUID(), name, value: value + (priority && !/!important\s*$/i.test(value) ? ' !important' : '')});
        rows.splice(0, rows.length, ...retained);
    }
    function hasBorderStyle(rows, side) {
        return rows.some(row => !row.pseudos?.length && ['border', 'border-style', `border-${side}`, `border-${side}-style`].includes(row.name.toLowerCase()) && row.value.trim());
    }
    function read(rows) {
        const style = document.createElement('div').style;
        style.cssText = window.waComponentControls.serializeStyles(rows);
        const values = {};
        for (const side of sides) for (const name of [`margin-${side}`, `padding-${side}`, `border-${side}-width`, `border-${side}-style`, `border-${side}-color`]) values[name] = style.getPropertyValue(name);
        return values;
    }
    window.waBoxModel = {sides, property, normalize, important, upsert, read, hasBorderStyle};
    document.addEventListener('alpine:init', () => Alpine.data('waBoxModelEditor', () => ({
        values: {}, linked: {margin: false, padding: false, border: false}, borderSide: 'all', error: '',
        get store() { return this.$store.componentEditor; },
        get draft() { return this.store.current; },
        get controls() { return this.draft?.controls; },
        get disabled() { return !this.draft?.loaded || this.draft.loading || this.draft.saving || !!this.draft.codeError || !this.controls?.editable; },
        init() {
            this.$watch('$store.componentEditor.activeKey', () => { this.error = ''; this.refresh(); });
            this.$watch('draft?.controls.styleRows', () => this.refresh());
            this.refresh();
        },
        refresh() { this.values = read(this.controls?.styleRows || []); },
        value(group, side) { return this.values[property(group, side)] || ''; },
        borderValue(kind) {
            const selected = this.borderSide === 'all' ? sides : [this.borderSide];
            const values = selected.map(side => this.values[`border-${side}-${kind}`] || '');
            return values.every(value => value === values[0]) ? values[0] : '';
        },
        apply(entries) {
            if (this.disabled) return;
            try {
                const normalized = entries.map(([name, value]) => [name, normalize(name, value)]);
                const rows = this.controls.styleRows;
                for (const [name, value] of normalized) upsert(rows, name, value, important(rows, name, this.controls.classTokens));
                this.error = '';
                this.store.stylesChanged(this.store.activeKey);
                this.refresh();
            } catch (error) { this.error = error.message; }
        },
        changeEdge(group, side, input) {
            const selected = this.linked[group] ? sides : [side];
            const entries = selected.map(edge => [property(group, edge), input]);
            // A newly added border needs a style to become visible. Preserve an explicit 'none'.
            if (group === 'border' && input.trim() && !/^0(?:px)?$/.test(input.trim())) {
                for (const edge of selected) if (!this.values[`border-${edge}-style`] && !hasBorderStyle(this.controls.styleRows, edge)) entries.push([`border-${edge}-style`, 'solid']);
            }
            this.apply(entries);
        },
        changeBorder(kind, value) { this.apply((this.borderSide === 'all' ? sides : [this.borderSide]).map(side => [`border-${side}-${kind}`, value])); },
    })));
})();
