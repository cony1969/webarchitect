(() => {
    'use strict';
    const emit = (name, detail) => window.dispatchEvent(new CustomEvent(name, { detail }));
    const instances = new Map();
    const pretty = value => JSON.stringify(value, null, 4);
    const escape = value => String(value).replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');

    // Preview documents never run component JavaScript or access the parent CMS.
    window.waComponentPreview = source => {
        const root = document.createElement('main');
        const allowed = new Set('div section article main header footer nav aside span p h1 h2 h3 h4 h5 h6 a button ul ol li dl dt dd strong em b i small label input select option textarea form table thead tbody tfoot tr td th img figure figcaption hr br pre code blockquote'.split(' '));
        let count = 0;
        const pseudoCss = [];
        const render = (value, depth = 0) => {
            if (++count > 5000 || depth > 48) throw new Error('Komponent prekračuje limit náhľadu.');
            if (typeof value === 'string') return document.createTextNode(value);
            if (!value || typeof value !== 'object' || Array.isArray(value) || typeof value.type !== 'string') throw new Error('Objekt komponentu nemá platný typ.');
            const type = value.type.toLowerCase();
            if (!allowed.has(type)) return document.createTextNode('');
            const element = document.createElement(type);
            const attributes = { ...value, ...(value.attributes || {}) };
            const pseudoRules = window.waPseudoStyles.rules(value);
            if (pseudoRules.length) {
                const scope = 'wa-state-' + count;
                attributes.class = String(attributes.class || '') + ' ' + scope + (pseudoRules.some(row => row.pseudos.includes('::before') || row.pseudos.includes('::after')) ? ' wa-custom-pseudo' : '');
                // Base styles must share the stylesheet cascade so pseudo rules can override them.
                const base = document.createElement('div').style; base.cssText = String(attributes.style || '');
                pseudoCss.push(window.waPseudoStyles.css('.' + scope, base.cssText, pseudoRules));
                delete attributes.style;
            }
            delete attributes.style_rules;
            delete attributes.content; delete attributes.type; delete attributes.attributes;
            for (const [key, attr] of Object.entries(attributes)) {
                if (/^(class|id|style|title|role|placeholder|value|name|alt|colspan|rowspan|width|height|aria-[a-z-]+|data-[a-z-]+)$/.test(key) && ['string', 'number', 'boolean'].includes(typeof attr)) element.setAttribute(key, String(attr));
            }
            if (['input', 'button', 'select', 'textarea'].includes(type)) element.setAttribute('disabled', '');
            const children = value.content === undefined ? [] : (Array.isArray(value.content) ? value.content : [value.content]);
            for (const child of children) element.append(render(child, depth + 1));
            return element;
        };
        const objects = Array.isArray(source.objects) ? source.objects : [source.objects];
        for (const object of objects) root.append(render(object));
        for (const child of root.children) {
            child.classList.add('wa-preview-root');
            child.setAttribute('data-wa-label', child.classList.contains('container') ? 'Kontajner · .container' : child.classList.contains('container-fluid') ? 'Kontajner · .container-fluid' : child.tagName.toLowerCase());
        }
        const css = (String(source.css || '') + '\n' + pseudoCss.join('\n')).replaceAll('<', '\\3c ');
        const bootstrap = location.origin + '/css/bootstrap.min.css';
        return '<!doctype html><html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="' + escape("default-src 'none'; style-src 'unsafe-inline' " + bootstrap + "; img-src data:; base-uri 'none'; form-action 'none'; script-src 'none'") + '"><link rel="stylesheet" href="' + escape(bootstrap) + '"><style>body{margin:0;padding:42px 20px;background:#fff;background-image:linear-gradient(#3bc0c31c 1px,transparent 1px),linear-gradient(90deg,#3bc0c31c 1px,transparent 1px);background-size:20px 20px} .wa-preview-root{position:relative;min-height:200px;outline:2px dashed #0a969b;outline-offset:-2px} .wa-preview-root:not(.wa-custom-pseudo):empty:before{content:attr(data-wa-label);position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#08777c;font:14px system-ui} ' + css + '</style></head><body>' + root.outerHTML + '</body></html>';
    };

    document.addEventListener('alpine:init', () => {
        Alpine.store('componentEditor', {
            drafts: {}, activeKey: null,
            get current() { return this.drafts[this.activeKey] || null; },
            ensure(key, path = null) {
                if (!this.drafts[key]) this.drafts[key] = { id: null, path, revision: 1, shared: false, loaded: false, loading: false, saving: false, form: { name: '', slug: '', target_path: '', tab: 'bootstrap', category: '' }, code: '', baseline: '', view: 'preview', preview: '', codeError: '', error: '', errors: {}, notice: '', projectId: null, projectName: '', placement: null, controls: window.waComponentControls.state() };
                if (path && !this.drafts[key].path) this.drafts[key].path = path;
                return this.drafts[key];
            },
            snapshot(draft) { return JSON.stringify([draft.form, draft.code]); },
            dirty(key) { const draft = this.drafts[key]; return !!draft?.loaded && this.snapshot(draft) !== draft.baseline; },
            activate(tab) {
                this.activeKey = tab?.mode === 'component' ? tab.id : null;
                if (!this.activeKey) return;
                const draft = this.ensure(tab.id, tab.componentPath || (tab.componentId ? 'cms/' + tab.componentId + '.json' : null));
                if (!draft.loaded && !draft.loading) this.load(tab.id);
                emit('project-editor-focused', {});
            },
            async request(url, payload = null) {
                const response = await fetch(url, { method: payload ? 'POST' : 'GET', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.waProjectEditorConfig.csrf }, ...(payload ? { body: JSON.stringify(payload) } : {}) });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) { const error = new Error(data.message || 'Požiadavku sa nepodarilo dokončiť. Zmeny zostali v editore.'); error.fields = data.errors || {}; throw error; }
                return data;
            },
            apply(key, data) {
                const draft = this.ensure(key);
                Object.assign(draft, { id: data.id, path: data.item_path, revision: data.revision, shared: data.shared, projectId: data.project_id || null, projectName: data.project_name || '', loaded: true, error: '', errors: {}, code: data.content, codeError: '' });
                draft.form = { name: data.name, slug: data.slug, target_path: data.target_path, tab: data.tab, category: data.category };
                this.updateDocument(key, false);
                draft.baseline = this.snapshot(draft);
                emit('component-document-loaded', { key, document: data });
            },
            async load(key, force = false) {
                const draft = this.ensure(key);
                if (force && this.dirty(key) && !confirm('Načítať uložený komponent a zahodiť neuložené zmeny?')) return;
                if (!draft.path) { draft.error = 'Otvorte komponent ceruzkou v ľavej knižnici.'; return; }
                draft.loading = true; draft.error = ''; draft.notice = '';
                try {
                    const result = await this.request('/ui/components/load', { item_path: draft.path });
                    if (!result.exists) throw new Error('Komponent zatiaľ nemá definíciu. Najprv ho vytvorte cez import v knižnici.');
                    if (this.drafts[key] === draft) this.apply(key, result.data);
                } catch (error) { draft.error = error.message; } finally { draft.loading = false; }
            },
            parsed(draft) {
                const value = JSON.parse(draft.code);
                if (!value || typeof value !== 'object' || Array.isArray(value) || !value.objects || typeof value.objects !== 'object' || (Array.isArray(value.objects) && !value.objects.length)) throw new Error('JSON musí obsahovať objekty komponentu v položke objects.');
                for (const key of ['css', 'js']) if (value[key] !== undefined && typeof value[key] !== 'string') throw new Error('Položky css a js musia byť text.');
                return value;
            },
            updateDocument(key, syncMetadata = true, syncControls = true) {
                const draft = this.ensure(key);
                try {
                    const source = this.parsed(draft);
                    const preview = window.waComponentPreview(source);
                    if (syncMetadata) for (const field of ['name', 'slug', 'tab']) if (typeof source[field] === 'string') draft.form[field] = source[field];
                    draft.preview = preview; draft.codeError = '';
                    if (syncControls) window.waComponentControls.sync(draft, source);
                } catch (error) { draft.codeError = 'Neplatný JSON: ' + error.message; }
            },
            writeControlSource(key, source) {
                const draft = this.drafts[key]; draft.code = pretty(source); this.updateDocument(key, false, false);
            },
            selectPropertyRoot(key) { const draft = this.drafts[key]; window.waComponentControls.sync(draft, this.parsed(draft)); },
            bootstrapChanged(key, field, value) {
                const draft = this.drafts[key], controls = window.waComponentControls;
                if (!draft?.controls.editable || draft.saving || draft.codeError) return;
                if (!field.common && !draft.controls.extraFields.includes(field.id)) draft.controls.extraFields.push(field.id);
                const classes = controls.replaceField(draft.controls.classTokens.join(' '), field, draft.controls.breakpoint, value);
                this.classesChanged(key, classes);
            },
            classesChanged(key, classes) {
                const draft = this.drafts[key], controls = window.waComponentControls;
                if (!draft?.controls.editable || draft.saving || draft.codeError) return;
                const source = this.parsed(draft), node = controls.roots(source)[draft.controls.rootIndex];
                draft.controls.classTokens = controls.tokens(classes.join(' '));
                controls.setAttribute(node, 'class', draft.controls.classTokens.join(' '));
                this.writeControlSource(key, source);
            },
            stylesChanged(key) {
                const draft = this.drafts[key], controls = window.waComponentControls;
                if (!draft?.controls.editable || draft.saving || draft.codeError) return;
                const source = this.parsed(draft), node = controls.roots(source)[draft.controls.rootIndex];
                draft.controls.stylesTouched = true;
                controls.setAttribute(node, 'style', controls.serializeStyles(draft.controls.styleRows));
                const rules = draft.controls.styleRows.filter(row => row.name.trim() && row.pseudos?.length).map(row => ({name: row.name, value: row.value, pseudos: window.waPseudoStyles.states(row)}));
                controls.setAttribute(node, 'style_rules', rules.length ? rules : '');
                this.writeControlSource(key, source);
            },
            propertiesChanged(key) {
                const draft = this.drafts[key];
                try { const source = this.parsed(draft); Object.assign(source, { name: draft.form.name, slug: draft.form.slug, tab: draft.form.tab }); draft.code = pretty(source); this.updateDocument(key, false); } catch (_) {}
            },
            format(key) {
                const draft = this.drafts[key];
                try { draft.code = pretty(this.parsed(draft)); this.updateDocument(key); } catch (error) { draft.codeError = 'Neplatný JSON: ' + error.message; }
            },
            async openPlacement(key) {
                const draft = this.drafts[key];
                draft.error = ''; draft.errors = {};
                const previous = draft.placement;
                draft.placement = { loading: true, saving: false, sources: [], projects: [], sourceId: previous?.sourceId || draft.id, projectId: previous?.projectId || '', pageId: previous?.pageId || '', id: previous?.id || crypto.randomUUID() };
                try {
                    const options = await this.request('/api/cms/component-placement/options');
                    Object.assign(draft.placement, options);
                    draft.placement.projectId ||= options.projects.find(p => p.legacy_id === window.sidebarProjectsTreeState?.selectedProject)?.id || options.projects[0]?.id || '';
                    this.placementProjectChanged(key);
                } catch (error) { draft.error = error.message; } finally { draft.placement.loading = false; }
            },
            placementProjectChanged(key) {
                const selection = this.drafts[key].placement;
                const pages = selection.projects.find(p => p.id === selection.projectId)?.pages || [];
                if (!pages.some(page => page.id === selection.pageId)) selection.pageId = pages.find(p => p.legacy_id === window.sidebarProjectsTreeState?.selectedPage)?.id || pages[0]?.id || '';
            },
            async insert(key) {
                const draft = this.drafts[key], selection = draft.placement;
                if (!selection || selection.loading || selection.saving) return;
                if (selection.sourceId === draft.id && this.dirty(key)) { draft.error = 'Najprv uložte zmeny zdrojového komponentu.'; return; }
                const source = selection.sources.find(c => c.id === selection.sourceId);
                const page = selection.projects.find(p => p.id === selection.projectId)?.pages.find(p => p.id === selection.pageId);
                if (!source || !page) { draft.error = 'Vyberte zdroj, cieľový projekt a stránku.'; return; }
                selection.saving = true; draft.error = ''; draft.errors = {};
                try {
                    const result = await this.request('/api/cms/component-placement', { component_id: source.id, component_revision: source.id === draft.id ? draft.revision : source.revision, project_id: selection.projectId, page_id: page.id, page_revision: page.revision, placement_id: selection.id });
                    draft.placement = null; draft.notice = 'Komponent bol vložený do stránky ' + result.data.name + '.';
                    emit('cms-project-tree-updated', result.project_tree);
                    emit('cms-page-composed', { document: result.data });
                } catch (error) { draft.error = error.message; draft.errors = error.fields || {}; } finally { selection.saving = false; }
            },
            async save(key) {
                const draft = this.drafts[key];
                if (!draft?.loaded || draft.saving || draft.loading) return;
                this.updateDocument(key, true, false);
                if (draft.codeError) { draft.view = 'json'; return; }
                draft.saving = true; draft.error = ''; draft.errors = {}; draft.notice = '';
                try {
                    const result = await this.request('/ui/components/editor', { ...draft.form, component_id: draft.id, revision: draft.revision, as_new: false, name: draft.form.name, slug: draft.form.slug, json_content: draft.code, ...(draft.controls.editable && draft.controls.stylesTouched ? { style_root: Number(draft.controls.rootIndex), style_rows: draft.controls.styleRows.map(row => ({ name: row.name, value: row.value, pseudos: row.pseudos || [] })) } : {}) });
                    this.apply(key, result.data);
                    draft.notice = 'Zmeny sú uložené.';
                    emit('component-document-saved', { key, document: result.data, asNew: false });
                    emit('component-catalog-updated', { catalog: result.catalog });
                } catch (error) { draft.error = error.message; draft.errors = error.fields || {}; } finally { draft.saving = false; }
            },
        });
        window.addEventListener('beforeunload', event => {
            const store = Alpine.store('componentEditor');
            if (Object.keys(store.drafts).some(key => store.dirty(key))) { event.preventDefault(); event.returnValue = ''; }
        });
        Alpine.data('waComponentPlacement', key => ({
            key, widgets: [],
            get selection() { return Alpine.store('componentEditor').drafts[this.key].placement; },
            init() {
                this.$nextTick(() => {
                    for (const field of ['sourceId', 'projectId', 'pageId']) {
                        const widget = window.jQuery(this.$refs[field]);
                        widget.select2({ width: '100%', minimumResultsForSearch: 0, dropdownCssClass: 'wa-project-type-dropdown', language: { noResults: () => 'Žiadna položka sa nenašla.' } });
                        widget.on('change.waPlacement', () => {
                            if (!this.selection || this.selection.loading || this.selection.saving) return;
                            this.selection[field] = widget.val() || '';
                            if (field === 'projectId') Alpine.store('componentEditor').placementProjectChanged(this.key);
                        });
                        this.widgets.push([field, widget]);
                    }
                    this.$watch('selection?.projectId', () => this.sync());
                    this.$watch('selection?.pageId', () => this.sync());
                    this.$watch('selection?.loading', () => this.sync());
                    this.$watch('selection?.saving', () => this.sync());
                    this.sync();
                });
            },
            sync() { this.$nextTick(() => { if (this.selection) for (const [field, widget] of this.widgets) widget.prop('disabled', this.selection.loading || this.selection.saving).val(this.selection[field]).trigger('change.select2'); }); },
            destroy() { for (const [, widget] of this.widgets) if (widget.data('select2')) widget.off('.waPlacement').select2('destroy'); },
        }));
        Alpine.data('waComponentForm', key => ({
            key, observer: null,
            get store() { return Alpine.store('componentEditor'); },
            get draft() { return this.store.ensure(this.key); },
            init() {
                this.$watch('draft.code', () => { const ace = instances.get(this.key); if (ace && ace.getValue() !== this.draft.code) ace.setValue(this.draft.code, -1); });
                this.$watch('draft.saving', () => instances.get(this.key)?.setReadOnly(this.draft.saving));
                this.$watch('draft.view', () => this.$nextTick(() => { if (this.draft.view === 'json') this.openAce(); }));
            },
            openAce() {
                if (instances.has(this.key)) { instances.get(this.key).resize(); instances.get(this.key).focus(); return; }
                if (!window.ace) { this.draft.error = 'Ace sa nepodarilo načítať. Obnovte stránku.'; return; }
                const editor = window.ace.edit(this.$refs.ace);
                instances.set(this.key, editor);
                editor.setTheme('ace/theme/tomorrow_night'); editor.session.setMode('ace/mode/json');
                editor.setOptions({ fontSize: 14, showPrintMargin: false, tabSize: 4, useSoftTabs: true, wrap: true });
                editor.setValue(this.draft.code, -1);
                editor.session.on('change', () => { if (this.draft.code !== editor.getValue()) { this.draft.code = editor.getValue(); this.store.updateDocument(this.key); } });
                editor.commands.addCommand({ name: 'saveComponent', bindKey: { win: 'Ctrl-S', mac: 'Command-S' }, exec: () => this.store.save(this.key) });
                this.observer = new ResizeObserver(() => editor.resize()); this.observer.observe(this.$refs.ace);
                editor.resize(); editor.focus();
            },
            destroy() { this.observer?.disconnect(); instances.get(this.key)?.destroy(); instances.delete(this.key); },
        }));
    });
})();
