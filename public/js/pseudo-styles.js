(() => {
    const catalog = window.waCssPseudos || [];
    const allowed = new Set(catalog.map(item => item.value));
    function states(row) {
        const values = row.pseudos || [];
        if (!Array.isArray(values) || values.some(value => !allowed.has(value))) throw new Error('Neznámy alebo neplatný pseudo stav.');
        return [...new Set(values)];
    }
    function rules(node) {
        const rows = window.waComponentControls.attribute(node, 'style_rules') || [];
        if (!Array.isArray(rows) || rows.length > 200) throw new Error('Neplatné pseudo pravidlá.');
        return rows.map(row => {
            if (!row || typeof row.name !== 'string' || typeof row.value !== 'string') throw new Error('Neplatné pseudo pravidlo.');
            return {name: row.name, value: row.value, pseudos: states(row)};
        }).filter(row => row.name.trim() && row.pseudos.length);
    }
    function css(selector, base, rows) {
        const declaration = row => {
            if (!/^(?:--[a-zA-Z0-9_-]+|-?[a-zA-Z][a-zA-Z0-9-]*)$/.test(row.name)) throw new Error('Neplatný názov CSS vlastnosti.');
            const style = document.createElement('div').style;
            const important = /\s*!important\s*$/i.test(row.value);
            style.setProperty(row.name, row.value.replace(/\s*!important\s*$/i, ''), important ? 'important' : '');
            return style.cssText;
        };
        let output = base ? selector + '{' + base + '}\n' : '';
        for (const row of rows) {
            const selected = states(row);
            if (!selected.length) continue;
            const selectors = selected.map(state => selector + state).join(',');
            output += selectors + '{' + declaration(row) + '}\n';
        }
        return output;
    }
    window.waPseudoStyles = {catalog, states, rules, css};
})();
