(() => {
    'use strict';
    const clone = value => JSON.parse(JSON.stringify(value));
    const empty = () => ({ schema_version: 1, nodes: [], css: '', js: '' });
    const emit = (name, detail) => window.dispatchEvent(new CustomEvent(name, { detail }));
    // The sandbox runs only this editor bridge; component JavaScript stays disabled.
    window.waLayoutPreviewScript = `(() => {
        const token = document.body.dataset.waPreviewToken;
        const select = nodeId => {
            for (const container of document.querySelectorAll('.wa-preview-container')) {
                const selected = container.dataset.waLayoutNode === nodeId;
                container.classList.toggle('wa-preview-selected', selected);
                const caption = [...container.children].find(child => child.classList.contains('wa-preview-caption'));
                if (caption?.tagName === 'BUTTON') caption.setAttribute('aria-pressed', String(selected));
            }
        };
        document.addEventListener('click', event => {
            const caption = event.target.closest('button.wa-preview-caption[data-wa-select-node]');
            if (!caption) return;
            event.preventDefault(); event.stopPropagation();
            parent.postMessage({ type: 'wa-layout-select', token, nodeId: caption.dataset.waSelectNode }, '*');
        });
        window.addEventListener('message', event => {
            if (event.source === parent && event.data?.type === 'wa-layout-selection' && event.data.token === token) select(event.data.nodeId);
        });
        select(document.body.dataset.waSelectedNode);
    })();`;
    document.addEventListener('alpine:init', () => {
        Alpine.store('layoutEditor', {
            drafts: {}, activeKey: null, catalog: { layouts: [], components: [] }, catalogLoaded: false, catalogPending: null, catalogError: '',
            get current() { return this.activeKey ? this.drafts[this.activeKey] || null : null; },
            ensure(key, tab = {}) {
                if (!this.drafts[key]) this.drafts[key] = { id: tab.layoutId || null, form: { name: tab.title || 'Nový layout', document: empty() }, revision: 1,
                    baseline: '', loaded: !tab.layoutId, loading: false, saving: false, shared: false, error: '', notice: '', previewError: '', preview: '',
                    previewToken: crypto.randomUUID(), selectedId: null, componentId: '', sequence: 0, timer: null, reloadOpen: false };
                const state = this.drafts[key];
                if (!state.baseline) state.baseline = JSON.stringify(state.form);
                if (!state.preview) state.preview = window.waComponentPreview({ objects: [], css: '', js: '' }, { nestedContainers: true, selectionToken: state.previewToken, selectedId: state.selectedId });
                return state;
            },
            dirty(key) { const state = this.drafts[key]; return !!state && JSON.stringify(state.form) !== state.baseline; },
            async request(url, options = {}) {
                const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.waProjectEditorConfig.csrf } });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(Object.values(body.errors || {}).flat().join(' ') || body.message || 'Požiadavku sa nepodarilo dokončiť. Skúste to znova.');
                return body;
            },
            async loadCatalog() {
                if (this.catalogPending) return this.catalogPending;
                this.catalogPending = (async () => {
                    try { const result = await this.request('/api/cms/layouts'); this.catalog = result.data; this.catalogLoaded = true; this.catalogError = ''; }
                    catch (error) { this.catalogError = error.message; }
                    finally { this.catalogPending = null; }
                })();
                return this.catalogPending;
            },
            async activate(tab) {
                this.activeKey = tab?.mode === 'layout' ? tab.id : null;
                if (!this.activeKey) return;
                const key = tab.id, state = this.ensure(key, tab);
                emit('project-editor-focused', {});
                if (!this.catalogLoaded) await this.loadCatalog();
                if (this.drafts[key] !== state) return;
                // Reopen older saved cards that only stored a title.
                if (!state.id && !tab.layoutNew && !this.dirty(key)) {
                    const match = this.catalog.layouts.find(layout => layout.name === tab.title);
                    if (match) { state.id = match.id; state.loaded = false; }
                }
                if (state.id && !state.loaded && !state.loading) await this.load(key);
            },
            apply(key, data) {
                const state = this.ensure(key);
                state.sequence++; clearTimeout(state.timer);
                state.id = data.id; state.revision = data.revision; state.shared = data.shared;
                state.form = { name: data.name, document: clone(data.document) };
                state.baseline = JSON.stringify(state.form); state.loaded = true; state.error = ''; state.previewError = '';
                state.preview = window.waComponentPreview(data.preview, { nestedContainers: true, selectionToken: state.previewToken, selectedId: state.selectedId });
                if (!this.find(state, state.selectedId)) state.selectedId = null;
            },
            async load(key) {
                const state = this.drafts[key];
                if (!state?.id || state.loading || state.saving) return;
                state.loading = true; state.error = ''; state.reloadOpen = false;
                try { const result = await this.request('/api/cms/layouts/' + state.id); if (this.drafts[key] !== state) return; this.apply(key, result.data); emit('layout-document-loaded', { key, document: result.data }); }
                catch (error) { state.error = error.message; }
                finally { state.loading = false; }
            },
            changed(key) {
                const state = this.drafts[key]; if (!state || state.loading || state.saving) return;
                state.notice = ''; clearTimeout(state.timer); const sequence = ++state.sequence;
                state.timer = setTimeout(() => this.preview(key, sequence), 220);
            },
            async preview(key, sequence) {
                const state = this.drafts[key]; if (!state || state.sequence !== sequence) return;
                try {
                    const result = await this.request('/api/cms/layouts/preview', { method: 'POST', body: JSON.stringify({ document: state.form.document }) });
                    if (this.drafts[key] !== state || state.sequence !== sequence) return;
                    state.preview = window.waComponentPreview(result.data.preview, { nestedContainers: true, selectionToken: state.previewToken, selectedId: state.selectedId }); state.previewError = '';
                } catch (error) { if (this.drafts[key] === state && state.sequence === sequence) state.previewError = error.message; }
            },
            find(state, id) {
                const walk = nodes => { for (const node of nodes) { if (typeof node === 'string') continue; if (node.id === id) return node; const found = walk(node.children); if (found) return found; } return null; };
                return state ? walk(state.form.document.nodes) : null;
            },
            select(key, id) {
                const state = this.drafts[key];
                if (!state || key !== this.activeKey || state.loading || state.saving || !this.find(state, id)) return false;
                state.selectedId = id;
                emit('project-editor-focused', {});
                return true;
            },
            previewSelected(key, message) {
                const state = this.drafts[key];
                if (!state || message?.type !== 'wa-layout-select' || message.token !== state.previewToken || typeof message.nodeId !== 'string') return false;
                return this.select(key, message.nodeId);
            },
            selected(key) { const state = this.drafts[key]; return this.find(state, state?.selectedId); },
            rows(key) {
                const state = this.drafts[key], rows = [];
                const walk = (nodes, depth = 0) => { for (const node of nodes) { if (typeof node === 'string') continue;
                    rows.push({ id: node.id, depth, label: node.type === 'cms-component' ? (this.catalog.components.find(c => c.id === node.attributes.component_id)?.name || 'Komponent') : node.type === 'p' ? 'Text' : node.type }); walk(node.children, depth + 1); } };
                if (state) walk(state.form.document.nodes); return rows;
            },
            addContainer(key, inside = false) {
                const state = this.drafts[key]; if (!state?.loaded || state.loading || state.saving) return;
                const source = this.catalog.components.find(c => c.id === state.componentId) || this.catalog.components[0];
                if (!source) { state.error = 'V knižnici zatiaľ nie je dostupný Kontajner.'; return; }
                const parent = inside ? this.selected(key) : null;
                if (inside && parent?.type !== 'cms-component') return;
                const node = { id: crypto.randomUUID(), type: 'cms-component', attributes: { component_id: source.id, content_target: source.content_target, full_width: false }, children: [] };
                (parent ? parent.children : state.form.document.nodes).push(node); state.selectedId = node.id; this.changed(key);
            },
            addText(key) {
                const state = this.drafts[key], parent = this.selected(key);
                if (!state || state.saving || parent?.type !== 'cms-component') return;
                const node = { id: crypto.randomUUID(), type: 'p', attributes: {}, children: ['Text layoutu'] };
                parent.children.push(node); state.selectedId = node.id; this.changed(key);
            },
            text(key) { return this.selected(key)?.children.filter(child => typeof child === 'string').join('') || ''; },
            setText(key, value) { const node = this.selected(key); if (node?.type !== 'p') return; node.children = [value]; this.changed(key); },
            toggleWidth(key) { const state = this.drafts[key], node = this.selected(key); if (state?.saving || node?.type !== 'cms-component') return; node.attributes.full_width = !node.attributes.full_width; this.changed(key); },
            remove(key) {
                const state = this.drafts[key]; if (!state || state.saving) return;
                const id = state.selectedId;
                const walk = nodes => { const index = nodes.findIndex(n => typeof n !== 'string' && n.id === id); if (index >= 0) { nodes.splice(index, 1); return true; } return nodes.some(node => typeof node !== 'string' && walk(node.children)); };
                walk(state.form.document.nodes); state.selectedId = null; this.changed(key);
            },
            async save(key) {
                const state = this.drafts[key]; if (!state?.loaded || state.loading || state.saving) return;
                if (!state.form.name.trim()) { state.error = 'Vyplňte názov layoutu.'; return; }
                state.saving = true; state.error = ''; state.notice = ''; clearTimeout(state.timer); state.sequence++;
                try {
                    const result = await this.request('/api/cms/layouts' + (state.id ? '/' + state.id : ''), { method: state.id ? 'PUT' : 'POST', body: JSON.stringify({ ...state.form, revision: state.revision }) });
                    this.apply(key, result.data); this.catalog = result.catalog; this.catalogLoaded = true; state.notice = 'Layout je uložený.';
                    emit('layout-document-saved', { key, document: result.data });
                    emit('layout-catalog-updated', { catalog: result.catalog });
                } catch (error) { state.error = error.message; }
                finally { state.saving = false; }
            },
            discard(key) { const state = this.drafts[key]; if (state) { clearTimeout(state.timer); state.sequence++; delete this.drafts[key]; } },
        });
        Alpine.data('waLayoutForm', (key, tab) => ({
            key, messageHandler: null,
            get store() { return this.$store.layoutEditor; },
            get draft() { return this.store.ensure(key, tab); },
            init() {
                this.messageHandler = event => {
                    if (event.source !== this.$refs.preview?.contentWindow || event.origin !== 'null') return;
                    this.store.previewSelected(key, event.data);
                };
                window.addEventListener('message', this.messageHandler);
                this.$watch('draft.selectedId', () => this.syncSelection());
            },
            syncSelection() {
                this.$refs.preview?.contentWindow?.postMessage({ type: 'wa-layout-selection', token: this.draft.previewToken, nodeId: this.draft.selectedId }, '*');
            },
            destroy() { window.removeEventListener('message', this.messageHandler); },
        }));
        Alpine.data('waLayoutProperties', () => ({ get store() { return this.$store.layoutEditor; }, get draft() { return this.store.current; }, get node() { return this.store.selected(this.store.activeKey); } }));
        window.addEventListener('component-document-saved', () => Alpine.store('layoutEditor').loadCatalog());
    });
})();
