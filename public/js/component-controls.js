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
    const serializeStyles = rows => rows.filter(row => String(row.name || '').trim() && !row.pseudos?.length).map(row => String(row.name).trim() + ': ' + String(row.value || '').trim() + ';').join(' ');
    const fieldOptions = (field, breakpoint) => field.options[breakpoint || ''] || [];
    const replaceField = (classes, field, breakpoint, value) => {
        const allowed = fieldOptions(field, breakpoint);
        const result = tokens(classes).filter(token => !allowed.includes(token));
        if (value && allowed.includes(value)) result.push(value);
        return tokens(result.join(' '));
    };
    const isContainerClass = token => /^container(?:-(?:fluid|sm|md|lg|xl|xxl))?$/.test(token);
    const isColumnClass = token => /^col(?:-(?:sm|md|lg|xl|xxl))?(?:-(?:auto|[1-9]|1[0-2]))?$/.test(token);
    const fullWidthClasses = (classes, type = 'div') => {
        const current = tokens(classes);
        const container = current.some(isContainerClass);
        const column = current.some(isColumnClass);
        const needsBlock = current.some(token => /^d-(?:(?:sm|md|lg|xl|xxl)-)?inline$/.test(token)) || (['a','span','strong','em','b','i','small','label','code'].includes(type.toLowerCase()) && !current.some(token => /^d-(?:none|block|inline-block|grid|table|table-cell|table-row|flex|inline-flex)$/.test(token)));
        const result = current.filter(token => !/^d-(?:(?:sm|md|lg|xl|xxl)-)?inline$/.test(token) && !isContainerClass(token) && !isColumnClass(token) && !/^w-(?:25|50|75|100|auto)$/.test(token));
        if (container) result.push('container-fluid');
        if (column) result.push('col-12');
        result.push('w-100');
        if (needsBlock && !result.includes('d-block')) result.push('d-block');
        return result;
    };
    const isFullWidth = (classes, type = 'div') => {
        const current = tokens(classes);
        const expanded = fullWidthClasses(classes, type);
        return current.includes('w-100') && current.length === expanded.length && expanded.every(token => current.includes(token));
    };
    // Keep the reversible class delta with the node so save/reload and project copies retain it.
    const setFullWidth = (node, enabled) => {
        const current = tokens(attribute(node, 'class'));
        const active = isFullWidth(current.join(' '), node.type);
        if (enabled === active) return;
        if (enabled) {
            const applied = fullWidthClasses(current.join(' '), node.type);
            setAttribute(node, 'editor_full_width', {before: current, applied});
            setAttribute(node, 'class', applied.join(' '));
        } else {
            const saved = attribute(node, 'editor_full_width');
            let result;
            if (Array.isArray(saved?.before) && Array.isArray(saved?.applied) && [...saved.before, ...saved.applied].every(token => typeof token === 'string' && token && !/\s/.test(token))) {
                const added = saved.applied.filter(token => !saved.before.includes(token));
                const removed = saved.before.filter(token => !saved.applied.includes(token));
                result = [...saved.before.filter(token => current.includes(token) || removed.includes(token)), ...current.filter(token => !saved.before.includes(token) && !added.includes(token))];
            } else {
                // Older definitions have no restore snapshot: return to natural Bootstrap sizing.
                result = current.filter(token => token !== 'w-100').map(token => token === 'container-fluid' ? 'container' : token === 'col-12' ? 'col' : token);
            }
            setAttribute(node, 'class', tokens(result.join(' ')).join(' '));
            setAttribute(node, 'editor_full_width', '');
        }
    };
    const fieldOptionLabel = (field, token) => {
        if (field.id === 'width') return ({'w-25':'25 %', 'w-50':'50 %', 'w-75':'75 %', 'w-100':'100 % · celá šírka rodiča', 'w-auto':'Automatická šírka'})[token] + ' (' + token + ')';
        if (field.id === 'columns') {
            const value = token.split('-').at(-1);
            return (/^\d+$/.test(value) ? value + ' / 12' + (value === '12' ? ' · celý riadok' : '') : value === 'auto' ? 'Podľa obsahu' : 'Rovnomerne') + ' (' + token + ')';
        }
        if (field.id === 'container' && token === 'container-fluid') return 'Celá šírka rodiča (container-fluid)';
        return token;
    };
    const normalizeSearch = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
    const matchesSearch = (query, ...values) => {
        const haystack = normalizeSearch(values.flat(Infinity).join(' '));
        return normalizeSearch(query).split(' ').filter(Boolean).every(word => haystack.includes(word));
    };
    const bootstrapAliases = {
        container: 'container type responsive fluid', display: 'display visibility visible hidden', padding: 'padding spacing', margin: 'margin spacing',
        columns: 'stlpec stlpce mriezka grid column columns col col-md-12', width: 'width sizing cela sirka full width roztiahnut vyplnit', background: 'background color', color: 'text color foreground',
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
    const cssWords = { color: 'farba', background: 'pozadie', border: 'oramovanie', width: 'sirka', height: 'vyska', min: 'minimalna', max: 'maximalna', top: 'hore horny', bottom: 'dole dolny', left: 'vlavo lavy', right: 'vpravo pravy', start: 'zaciatok', end: 'koniec', inline: 'riadkovy', block: 'blokovy', padding: 'vnutorne odsadenie', margin: 'vonkajsie odsadenie', text: 'text', font: 'pismo', size: 'velkost', weight: 'hrubka', style: 'styl', align: 'zarovnanie', shadow: 'tien', radius: 'zaoblenie rohov', gap: 'medzera', row: 'riadok', column: 'stlpec', grid: 'mriezka', flex: 'flex', wrap: 'zalamovanie', image: 'obrazok', position: 'pozicia', opacity: 'priehladnost', display: 'zobrazenie', overflow: 'pretekanie', animation: 'animacia', duration: 'trvanie', delay: 'oneskorenie', transform: 'transformacia', transition: 'prechod', scroll: 'rolovanie', outline: 'obrys', decoration: 'dekoracia', line: 'ciara riadok', spacing: 'rozostup', letter: 'pismeno', word: 'slovo', content: 'obsah', justify: 'rozlozenie', items: 'prvky', self: 'samotny prvok', cursor: 'kurzor', visibility: 'viditelnost', fill: 'vypln', stroke: 'tah', accent: 'zvyraznenie', caret: 'kurzor', scrollbar: 'rolovacia lista', selection: 'vyber', scheme: 'schema' };
    const cssAliases = name => name.split('-').map(word => cssWords[word] || '').join(' ');
    const cssCatalog = (rows = []) => {
        const labels = new Map(cssProperties.map(property => [property.name, property.label]));
        const names = new Set([...(window.waComponentPropertyConfig?.cssProperties || []), ...labels.keys(), ...rows.map(row => row.name.trim()).filter(Boolean)]);
        return [...names].sort((a, b) => Number(a.startsWith('-')) - Number(b.startsWith('-')) || a.localeCompare(b)).map(name => ({ name, label: labels.get(name) || name, aliases: cssAliases(name) }));
    };
    const cssMatches = (query, property) => matchesSearch(query, property.name, property.label, property.aliases);
    const cssSearchResults = (query, rows) => cssCatalog(rows).filter(property => cssMatches(query, property));
    const cssCustomProperty = value => /^--[a-zA-Z0-9_-]+$/.test(value);
    const state = () => ({ tab: window.waSessionTabs?.get('component_properties', 'basic') || 'basic', search: '', rootIndex: 0, rootOptions: [], editable: false, breakpoint: '', extraFields: [], allFields: false, allClasses: false, classTokens: [], styleRows: [], stylesTouched: false });
    const blankRow = () => ({ id: crypto.randomUUID(), name: '', value: '', pseudos: [] });
    const sync = (draft, source) => {
        const ui = draft.controls ||= state();
        const items = roots(source);
        ui.rootOptions = items.flatMap((node, index) => typeof node === 'object' && node && !Array.isArray(node) ? [{ index, label: (index + 1) + '. ' + node.type + (attribute(node, 'class') ? ' · ' + attribute(node, 'class') : '') }] : []);
        if (!ui.rootOptions.some(item => item.index === Number(ui.rootIndex))) ui.rootIndex = ui.rootOptions[0]?.index ?? 0;
        const node = items[ui.rootIndex];
        ui.editable = !!node && typeof node === 'object' && node.type !== 'cms-component';
        ui.classTokens = tokens(attribute(node, 'class'));
        ui.stylesTouched = false;
        ui.styleRows = [...styleRows(String(attribute(node, 'style'))), ...(window.waPseudoStyles?.rules(node) || []).flatMap(row => row.pseudos.map(pseudo => ({...row, pseudos: [pseudo]})))].map(row => ({ id: crypto.randomUUID(), pseudos: [], ...row }));
        if (!ui.styleRows.length) ui.styleRows.push(blankRow());
    };
    window.waComponentControls = { tokens, roots, attribute, setAttribute, styleRows, serializeStyles, fieldOptions, replaceField, state, blankRow, sync, normalizeSearch, matchesSearch, visibleFields, cssSearchResults, cssCatalog, cssMatches, cssCustomProperty, fullWidthClasses, isFullWidth, setFullWidth, fieldOptionLabel };

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
            changePropertyTab(tab) { if (!['basic', 'bootstrap', 'css'].includes(tab)) return; this.ui.tab = tab; window.waSessionTabs?.set('component_properties', tab); },
            addField() { if (this.extraField && !this.ui.extraFields.includes(this.extraField)) this.ui.extraFields.push(this.extraField); this.extraField = ''; },
            addStyleRow() {
                const row = blankRow(); this.ui.styleRows.push(row);
                this.$nextTick(() => requestAnimationFrame(() => {
                    const select = this.$root.querySelector('[data-css-row="' + row.id + '"] [data-css-property]');
                    if (select && window.jQuery(select).data('select2')) window.jQuery(select).select2('open');
                }));
            },
            copyStyleRow(row) { this.ui.styleRows.push({ ...row, id: crypto.randomUUID(), pseudos: [...(row.pseudos || [])] }); this.store.stylesChanged(this.key); },
            colorOnly(row) { return window.waColors.colorOnly(row); },
            colorsFor(row) { return window.waColors.rowColors(row); },
            setRowColor(row, index, value) {
                const token = this.colorsFor(row)[index];
                if (!token) return;
                row.value = window.waColors.replaceColor(row.value, token, value);
                this.store.stylesChanged(this.key);
            },
            removeStyleRow(index) { this.ui.styleRows.splice(index, 1); this.store.stylesChanged(this.key); },
        }));
        Alpine.data('waCssPseudo', row => ({
            row, widget: null,
            get draft() { return this.$store.componentEditor.current; },
            init() {
                this.$nextTick(() => {
                    const select = this.$refs.pseudo;
                    select.append(new Option('Bežný stav', ''));
                    for (const [label, common] of [['Bežné stavy', true], ['Ďalšie stavy a pseudo prvky', false]]) {
                        const group = document.createElement('optgroup'); group.label = label;
                        for (const item of window.waCssPseudos.filter(item => item.common === common)) group.append(new Option(item.value + ' — ' + item.label, item.value));
                        select.append(group);
                    }
                    this.widget = window.jQuery(select).select2({width: '100%', minimumResultsForSearch: 0, dropdownCssClass: 'wa-project-type-dropdown wa-pseudo-dropdown', language: {noResults: () => 'Žiadny stav nezodpovedá vyhľadávaniu.'}});
                    this.widget.on('change.waPseudo', () => {
                        if (!this.draft?.controls.editable || this.draft.loading || this.draft.saving || this.draft.codeError) return;
                        const value = this.widget.val() || '';
                        this.row.pseudos = value ? [value] : [];
                        this.$store.componentEditor.stylesChanged(this.$store.componentEditor.activeKey);
                    });
                    for (const expression of ['row.pseudos', 'row.name', 'draft?.saving', 'draft?.loading', 'draft?.codeError']) this.$watch(expression, () => this.sync());
                    this.sync();
                });
            },
            sync() {
                if (!this.widget || !this.draft) return;
                this.widget.prop('disabled', !this.draft.loaded || this.draft.loading || this.draft.saving || !!this.draft.codeError || !this.draft.controls.editable).val(this.row.pseudos?.[0] || '').trigger('change.select2');
                this.widget.next('.select2').find('[role=combobox]').attr('aria-label', 'Pseudo stav pre ' + (this.row.name || 'novú vlastnosť'));
            },
            destroy() { if (this.widget?.data('select2')) this.widget.off('.waPseudo').select2('destroy'); this.widget = null; },
        }));
        Alpine.data('waCssProperty', row => ({
            row, widget: null,
            get draft() { return this.$store.componentEditor.current; },
            get query() { return this.draft?.controls.search || ''; },
            init() {
                this.$nextTick(() => {
                    this.widget = window.jQuery(this.$refs.property);
                    this.widget.append(new Option('', ''));
                    for (const property of cssCatalog(this.draft?.controls.styleRows || [])) this.widget.append(new Option(property.name, property.name));
                    this.widget.select2({
                        width: '100%', placeholder: 'Vyberte vlastnosť…', allowClear: true, tags: true,
                        dropdownCssClass: 'wa-project-type-dropdown wa-css-property-dropdown',
                        matcher: (params, data) => {
                            if (!data.id) return null;
                            const property = { name: data.id, label: cssProperties.find(p => p.name === data.id)?.label || data.id, aliases: cssAliases(data.id) };
                            return cssMatches(this.query, property) && cssMatches(params.term || '', property) ? data : null;
                        },
                        createTag: params => {
                            const name = params.term.trim();
                            return cssCustomProperty(name) && cssMatches(this.query, { name, label: name, aliases: cssAliases(name) }) ? { id: name, text: name } : null;
                        },
                        language: { noResults: () => 'Žiadna vlastnosť nezodpovedá filtru.', searching: () => 'Vyhľadávam…' },
                    });
                    this.widget.on('change.waCssProperty', () => {
                        if (!this.draft?.controls.editable || this.draft.saving || this.draft.codeError) return;
                        this.row.name = this.widget.val() || '';
                        this.$store.componentEditor.stylesChanged(this.$store.componentEditor.activeKey);
                    });
                    this.$watch('row.name', () => this.sync());
                    this.$watch('draft?.controls.styleRows.map(item => item.id).join(",")', () => this.sync());
                    this.$watch('draft?.saving', () => this.sync());
                    this.$watch('draft?.loading', () => this.sync());
                    this.$watch('draft?.codeError', () => this.sync());
                    this.sync();
                });
            },
            sync() {
                if (!this.widget || !this.draft) return;
                if (this.row.name && ![...this.$refs.property.options].some(option => option.value === this.row.name)) this.widget.append(new Option(this.row.name, this.row.name));
                this.widget.prop('disabled', !this.draft.loaded || this.draft.loading || this.draft.saving || !!this.draft.codeError || !this.draft.controls.editable).val(this.row.name).trigger('change.select2');
                this.widget.next('.select2').find('[role=combobox]').attr('aria-label', 'CSS vlastnosť ' + (this.draft.controls.styleRows.indexOf(this.row) + 1));
            },
            destroy() { if (this.widget?.data('select2')) this.widget.off('.waCssProperty').select2('destroy'); this.widget = null; },
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
                for (const token of options) this.widget.append(new Option(fieldOptionLabel(this.field, token), token));
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
