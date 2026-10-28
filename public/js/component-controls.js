(() => {
    'use strict';
    const tokens = value => [...new Set(String(value || '').trim().split(/\s+/).filter(Boolean))];
    const roots = source => Array.isArray(source.objects) ? source.objects : [source.objects];
    const attribute = (node, name) => node?.attributes?.[name] ?? node?.[name] ?? '';
    const setAttribute = (node, name, value) => {
        if (!value) { delete node[name]; if (node.attributes) delete node.attributes[name]; }
        else if (node.attributes && Object.hasOwn(node.attributes, name)) { node.attributes[name] = value; delete node[name]; }
        else node[name] = value;
    };
    const styleRows = style => {
        const declarations = []; let part = '', quote = '', escaped = false, comment = false, depth = 0;
        for (let i = 0; i < style.length; i++) {
            const char = style[i], next = style[i + 1];
            if (comment) { part += char; if (char === '*' && next === '/') { part += next; i++; comment = false; } continue; }
            if (escaped) { part += char; escaped = false; continue; }
            if (char === '\\') { part += char; escaped = true; continue; }
            if (quote) { part += char; if (char === quote) quote = ''; continue; }
            if (char === '/' && next === '*') { part += char + next; i++; comment = true; continue; }
            if (char === '"' || char === "'") quote = char;
            if ('([{'.includes(char)) depth++;
            if (')]}'.includes(char)) depth--;
            if (char === ';' && depth === 0) { declarations.push(part); part = ''; } else part += char;
        }
        if (part.trim()) declarations.push(part);
        return declarations.map(value => {
            let colon = -1, comment = false;
            for (let i = 0; i < value.length; i++) {
                if (comment) { if (value[i] === '*' && value[i + 1] === '/') { i++; comment = false; } continue; }
                if (value[i] === '/' && value[i + 1] === '*') { i++; comment = true; continue; }
                if (value[i] === ':') { colon = i; break; }
            }
            return colon < 0 ? null : { name: value.slice(0, colon).replace(/\/\*[\s\S]*?\*\//g, '').trim(), value: value.slice(colon + 1).trim() };
        }).filter(row => row?.name);
    };
    const serializeStyles = rows => rows.filter(row => String(row.name || '').trim()).map(row => String(row.name).trim() + ': ' + String(row.value || '').trim() + ';').join(' ');
    const fieldOptions = (field, breakpoint) => field.options[breakpoint || ''] || [];
    const replaceField = (classes, field, breakpoint, value) => {
        const allowed = fieldOptions(field, breakpoint);
        const result = tokens(classes).filter(token => !allowed.includes(token));
        if (value && allowed.includes(value)) result.push(value);
        return tokens(result.join(' '));
    };
    const normalizeSearch = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
    const matchesSearch = (query, ...values) => {
        const haystack = normalizeSearch(values.flat(Infinity).join(' '));
        return normalizeSearch(query).split(' ').filter(Boolean).every(word => haystack.includes(word));
    };
    const bootstrapAliases = {
        container: 'container type responsive fluid', display: 'display visibility visible hidden', padding: 'padding spacing', margin: 'margin spacing',
        width: 'width sizing', background: 'background color', color: 'text color foreground',
        px: 'padding horizontal left right inline', py: 'padding vertical top bottom', pt: 'padding top', pb: 'padding bottom', ps: 'padding start left inline', pe: 'padding end right inline',
        mx: 'margin horizontal left right inline', my: 'margin vertical top bottom', mt: 'margin top', mb: 'margin bottom', ms: 'margin start left inline', me: 'margin end right inline',
        height: 'height sizing', gap: 'gap spacing gutters', direction: 'flex direction', wrap: 'flex wrap', justify: 'justify content alignment', align: 'align items alignment', self: 'align self alignment',
        grow: 'flex grow', shrink: 'flex shrink', order: 'order', 'text-align': 'text align alignment', 'font-size': 'font size typography', 'font-weight': 'font weight typography', 'font-style': 'font style typography',
        border: 'border outline', 'border-color': 'border color', 'border-width': 'border width', rounded: 'border radius rounded', shadow: 'box shadow', overflow: 'overflow', position: 'position', float: 'float', 'user-select': 'user select',
    };
    const visibleFields = (config, ui) => config.fields.filter(field => normalizeSearch(ui.search)
        ? matchesSearch(ui.search, field.label, field.id, bootstrapAliases[field.id], Object.values(field.options))
        : field.common || ui.allFields || ui.extraFields.includes(field.id));
    const cssProperties = [
        ['width', 'Šírka'], ['min-width', 'Minimálna šírka'], ['max-width', 'Maximálna šírka'],
        ['height', 'Výška'], ['min-height', 'Minimálna výška'], ['max-height', 'Maximálna výška'],
        ['padding', 'Vnútorné odsadenie'], ['padding-top', 'Vnútorné odsadenie hore'], ['padding-bottom', 'Vnútorné odsadenie dole'], ['padding-left', 'Vnútorné odsadenie vľavo'], ['padding-right', 'Vnútorné odsadenie vpravo'],
        ['margin', 'Vonkajšie odsadenie'], ['margin-top', 'Vonkajšie odsadenie hore'], ['margin-bottom', 'Vonkajšie odsadenie dole'], ['margin-left', 'Vonkajšie odsadenie vľavo'], ['margin-right', 'Vonkajšie odsadenie vpravo'],
        ['background', 'Pozadie'], ['background-color', 'Farba pozadia'], ['background-image', 'Obrázok pozadia'], ['background-size', 'Veľkosť pozadia'], ['background-position', 'Pozícia pozadia'],
        ['color', 'Farba textu'], ['font-family', 'Rodina písma'], ['font-size', 'Veľkosť písma'], ['font-weight', 'Hrúbka písma'], ['font-style', 'Štýl písma'],
        ['line-height', 'Výška riadka'], ['letter-spacing', 'Rozostup písmen'], ['text-align', 'Zarovnanie textu'], ['text-decoration', 'Dekorácia textu'], ['white-space', 'Biele znaky a zalamovanie textu'],
        ['border', 'Orámovanie'], ['border-color', 'Farba orámovania'], ['border-width', 'Hrúbka orámovania'], ['border-style', 'Štýl orámovania'], ['border-radius', 'Zaoblenie rohov'], ['box-shadow', 'Tieň'],
        ['display', 'Zobrazenie'], ['visibility', 'Viditeľnosť'], ['opacity', 'Priehľadnosť'], ['overflow', 'Pretekanie obsahu'],
        ['position', 'Pozícia'], ['top', 'Horná poloha'], ['bottom', 'Dolná poloha'], ['left', 'Ľavá poloha'], ['right', 'Pravá poloha'], ['z-index', 'Poradie vrstiev'],
        ['gap', 'Medzera medzi prvkami'], ['row-gap', 'Medzera medzi riadkami'], ['column-gap', 'Medzera medzi stĺpcami'],
        ['flex-direction', 'Smer flex'], ['flex-wrap', 'Zalamovanie flex'], ['justify-content', 'Rozloženie obsahu'], ['align-items', 'Zarovnanie prvkov'], ['align-self', 'Zarovnanie samotného prvku'], ['flex-grow', 'Roztiahnutie flex'], ['flex-shrink', 'Zmenšenie flex'], ['order', 'Poradie prvku'],
        ['grid-template-columns', 'Stĺpce mriežky'], ['grid-template-rows', 'Riadky mriežky'], ['cursor', 'Kurzor'], ['transform', 'Transformácia'], ['transition', 'Prechod'], ['object-fit', 'Prispôsobenie obrázka'], ['aspect-ratio', 'Pomer strán'],
    ].map(([name, label]) => ({ name, label }));
    const cssSearchResults = (query, rows) => {
        const known = new Set(cssProperties.map(property => property.name));
        const custom = rows.map(row => String(row.name || '').trim()).filter(name => name && !known.has(name));
        return [...cssProperties, ...[...new Set(custom)].map(name => ({ name, label: 'Vlastná vlastnosť' }))].filter(property => matchesSearch(query, property.name, property.label));
    };
    const state = () => ({ tab: 'basic', search: '', rootIndex: 0, rootOptions: [], editable: false, breakpoint: '', extraFields: [], allFields: false, allClasses: false, classTokens: [], styleRows: [], stylesTouched: false });
    const blankRow = () => ({ id: crypto.randomUUID(), name: '', value: '' });
    const sync = (draft, source) => {
        const ui = draft.controls ||= state();
        const items = roots(source);
        ui.rootOptions = items.flatMap((node, index) => typeof node === 'object' && node && !Array.isArray(node) ? [{ index, label: (index + 1) + '. ' + node.type + (attribute(node, 'class') ? ' · ' + attribute(node, 'class') : '') }] : []);
        if (!ui.rootOptions.some(item => item.index === Number(ui.rootIndex))) ui.rootIndex = ui.rootOptions[0]?.index ?? 0;
        const node = items[ui.rootIndex];
        ui.editable = !!node && typeof node === 'object' && node.type !== 'cms-component';
        ui.classTokens = tokens(attribute(node, 'class'));
        ui.stylesTouched = false;
        ui.styleRows = styleRows(String(attribute(node, 'style'))).map(row => ({ id: crypto.randomUUID(), ...row }));
        if (!ui.styleRows.length) ui.styleRows.push(blankRow());
    };
    window.waComponentControls = { tokens, roots, attribute, setAttribute, styleRows, serializeStyles, fieldOptions, replaceField, state, blankRow, sync, normalizeSearch, matchesSearch, visibleFields, cssSearchResults };

    document.addEventListener('alpine:init', () => {
        Alpine.data('waComponentProperties', () => ({
            extraField: '',
            get store() { return this.$store.componentEditor; },
            get key() { return this.store.activeKey; },
            get draft() { return this.store.current; },
            get ui() { return this.draft.controls; },
            get config() { return window.waComponentPropertyConfig; },
            get fields() { return visibleFields(this.config, this.ui); },
            get cssResults() { return cssSearchResults(this.ui.search, this.ui.styleRows); },
            changePropertyTab(tab) { this.ui.tab = tab; },
            addField() { if (this.extraField && !this.ui.extraFields.includes(this.extraField)) this.ui.extraFields.push(this.extraField); this.extraField = ''; },
            useCssProperty(name) {
                if (!this.ui.editable || this.draft.saving || this.draft.codeError) return;
                let row = this.ui.styleRows.find(row => row.name.trim().toLowerCase() === name.toLowerCase());
                if (!row) {
                    row = this.ui.styleRows.find(row => !row.name.trim() && !row.value.trim());
                    if (row) row.name = name;
                    else { row = { ...blankRow(), name }; this.ui.styleRows.push(row); }
                    this.store.stylesChanged(this.key);
                }
                this.ui.search = '';
                this.$nextTick(() => this.$root.querySelector('[data-css-row="' + row.id + '"] [data-css-value]')?.focus());
            },
            addStyleRow() {
                const row = blankRow(); this.ui.styleRows.push(row);
                this.$nextTick(() => this.$root.querySelector('[data-css-row="' + row.id + '"] input')?.focus());
            },
            colorsFor(row) { return window.waColors.rowColors(row); },
            setRowColor(row, index, value) {
                const token = this.colorsFor(row)[index];
                if (!token) return;
                row.value = window.waColors.replaceColor(row.value, token, value);
                this.store.stylesChanged(this.key);
            },
            removeStyleRow(index) { this.ui.styleRows.splice(index, 1); this.store.stylesChanged(this.key); },
        }));
        Alpine.data('waBootstrapField', field => ({
            field, widget: null,
            get store() { return this.$store.componentEditor; },
            get draft() { return this.store.current; },
            init() {
                this.$nextTick(() => {
                    this.widget = window.jQuery(this.$refs.field);
                    this.widget.select2({ width: '100%', minimumResultsForSearch: 8, dropdownCssClass: 'wa-project-type-dropdown' });
                    this.widget.on('change.waControl', () => this.store.bootstrapChanged(this.store.activeKey, this.field, this.widget.val() || ''));
                    this.$watch('$store.componentEditor.activeKey', () => this.sync());
                    this.$watch('draft?.controls.breakpoint', () => this.sync());
                    this.$watch('draft?.controls.classTokens', () => this.sync());
                    this.$watch('draft?.saving', () => this.sync());
                    this.$watch('draft?.codeError', () => this.sync());
                    this.sync();
                });
            },
            sync() {
                if (!this.widget || !this.draft) return;
                const options = fieldOptions(this.field, this.draft.controls.breakpoint);
                const selected = [...this.draft.controls.classTokens].reverse().find(token => options.includes(token)) || '';
                this.widget.empty().append(new Option('Nenastavené', ''));
                for (const token of options) this.widget.append(new Option(token, token));
                this.widget.prop('disabled', this.draft.saving || !!this.draft.codeError || !this.draft.controls.editable).val(selected).trigger('change.select2');
            },
            destroy() { if (this.widget?.data('select2')) this.widget.off('.waControl').select2('destroy'); },
        }));
        Alpine.data('waAllBootstrapClasses', () => ({
            widget: null,
            get store() { return this.$store.componentEditor; },
            get draft() { return this.store.current; },
            init() {
                this.$nextTick(() => {
                    this.widget = window.jQuery(this.$refs.classes);
                    const names = new Set([...window.waComponentPropertyConfig.classes, ...this.draft.controls.classTokens]);
                    for (const name of names) this.widget.append(new Option(name, name));
                    this.widget.select2({ width: '100%', tags: true, tokenSeparators: [' ', '\n'], closeOnSelect: false, dropdownCssClass: 'wa-project-type-dropdown', language: { noResults: () => 'Zadajte vlastnú triedu.' } });
                    this.widget.on('change.waClasses', () => this.store.classesChanged(this.store.activeKey, this.widget.val() || []));
                    this.$watch('$store.componentEditor.activeKey', () => this.sync());
                    this.$watch('draft?.controls.classTokens', () => this.sync());
                    this.$watch('draft?.saving', () => this.sync());
                    this.sync();
                });
            },
            sync() {
                if (!this.widget || !this.draft) return;
                const values = this.draft.controls.classTokens;
                const known = new Set([...this.$refs.classes.options].map(option => option.value));
                for (const value of values) if (!known.has(value)) this.widget.append(new Option(value, value));
                this.widget.prop('disabled', this.draft.saving || !this.draft.controls.editable || !!this.draft.codeError).val(values).trigger('change.select2');
            },
            destroy() { if (this.widget?.data('select2')) this.widget.off('.waClasses').select2('destroy'); },
        }));
    });
})();
