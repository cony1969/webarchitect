(() => {
    'use strict';
    const clone = value => JSON.parse(JSON.stringify(value));
    const emit = (name, detail) => window.dispatchEvent(new CustomEvent(name, { detail }));
    window.waProjectSlug = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

    document.addEventListener('alpine:init', () => {
        const config = window.waProjectEditorConfig;
        Alpine.store('projectEditor', {
            drafts: {}, activeKey: null,
            get current() { return this.activeKey ? this.drafts[this.activeKey] || null : null; },
            get active() { return this.current !== null; },
            get isDomain() { return this.current?.kind === 'domain'; },
            defaults(settings = {}) {
                return { ...clone(config.defaults), ...clone(settings), setup: { ...config.defaults.setup, ...(settings.setup || {}) } };
            },
            ensure(key, id = null, kind = 'project', projectId = null) {
                if (!this.drafts[key]) {
                    const form = kind === 'domain' ? { name: '', status: 'draft', settings: this.defaults() } : { name: '' };
                    this.drafts[key] = { id, kind, projectId, projectName: '', overview: {}, revision: 1, form, document: null, baseline: JSON.stringify(form), loaded: !id, loading: false, saving: false, error: '', errors: {}, notice: '', conflict: false, copyOpen: false, copyName: '', copySlug: '', autoSlug: true };
                }
                if (id && !this.drafts[key].id) { this.drafts[key].id = id; this.drafts[key].loaded = false; }
                return this.drafts[key];
            },
            dirty(key) {
                const state = this.drafts[key];
                return !!state && JSON.stringify(state.form) !== state.baseline;
            },
            activate(tab) {
                this.activeKey = ['project', 'domain'].includes(tab?.mode) ? tab.id : null;
                if (!this.activeKey) return;
                const state = this.ensure(tab.id, tab.mode === 'domain' ? tab.domainId : tab.projectId, tab.mode, tab.projectId || null);
                state.projectName ||= tab.projectName || '';
                if (state.id && !state.loaded && !state.loading) this.load(tab.id);
                emit('project-editor-focused', { projectId: state.kind === 'domain' ? state.projectId : state.id, domainId: state.kind === 'domain' ? state.id : null });
            },
            apply(key, document) {
                const state = this.ensure(key, document.id, document.editor_kind || 'project', document.project_id || null);
                state.id = document.id;
                state.revision = document.revision;
                state.kind = document.editor_kind || state.kind;
                state.projectId = document.project_id || null; state.projectName = document.project?.name || '';
                state.overview = document.overview || { pages_count: document.pages_count || 0 };
                state.form = state.kind === 'domain' ? { name: document.name, status: document.status, settings: this.defaults(document.settings) } : { name: document.name };
                state.document = document.document;
                state.baseline = JSON.stringify(state.form);
                state.loaded = true;
                state.error = ''; state.errors = {}; state.conflict = false;
            },
            async load(key, force = false) {
                const state = this.drafts[key];
                if (!state?.id || state.loading) return;
                if (force && this.dirty(key) && !window.confirm('Načítať uloženú verziu a zahodiť neuložené zmeny v tejto karte?')) return;
                state.loading = true; state.error = ''; state.errors = {};
                try {
                    const data = await this.request('/api/cms/' + (state.kind === 'domain' ? 'domains' : 'projects') + '/' + state.id);
                    if (this.drafts[key] !== state) return;
                    this.apply(key, data.data);
                    emit('project-document-loaded', { key, document: data.data });
                } catch (error) { this.failed(state, error); }
                finally { state.loading = false; }
            },
            async request(url, options = {}) {
                const response = await fetch(url, { ...options, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, ...(options.headers || {}) } });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.data && !data.projects) {
                    const error = new Error(response.status === 401 ? 'Prihlásenie vypršalo. Obnovte stránku a prihláste sa.' : (data.message || 'Požiadavku sa nepodarilo dokončiť. Skúste to znova.'));
                    error.fields = data.errors || {}; error.status = response.status;
                    throw error;
                }
                return data;
            },
            failed(state, error) {
                state.error = error.message || 'Server nie je dostupný. Zmeny zostali v editore.';
                state.errors = error.fields || {};
                state.conflict = error.status === 409;
            },
            openCopy(key) {
                const state = this.drafts[key];
                if (!state || state.loading || state.saving || !state.loaded) return;
                state.copyName = state.kind === 'domain' ? 'kopia.' + state.form.name : (state.form.name ? state.form.name + ' – kópia' : 'Nový projekt');
                state.copySlug = waProjectSlug(state.copyName);
                state.errors = {}; state.error = ''; state.copyOpen = true;
            },
            async save(key, asNew = false) {
                const state = this.drafts[key];
                if (!state || !state.loaded || state.loading || state.saving) return;
                state.error = ''; state.errors = {}; state.notice = ''; state.conflict = false;
                const form = clone(state.form);
                form.name = (asNew ? state.copyName : form.name).trim();
                if (asNew && state.kind === 'domain') form.status = 'draft';
                if (!form.name) { state.error = 'Vyplňte názov.'; return; }
                state.saving = true;
                const updating = state.id && !asNew;
                const resource = state.kind === 'domain' ? '/api/cms/domains' : '/api/cms/projects';
                const url = state.id ? resource + '/' + state.id + (asNew ? '/copy' : '') : resource;
                const payload = { ...form, ...(updating ? { revision: state.revision } : {}), ...(state.kind === 'domain' ? { project_id: state.projectId } : {}) };
                try {
                    const data = await this.request(url, { method: updating ? 'PUT' : 'POST', body: JSON.stringify(payload) });
                    if (!asNew) this.apply(key, data.data);
                    state.copyOpen = false;
                    state.notice = asNew ? 'Nový záznam je uložený v samostatnej karte.' : 'Zmeny sú uložené.';
                    if (data.project_tree) emit('cms-project-tree-updated', { ...data.project_tree, focusProject: state.kind === 'domain' ? data.data.project?.legacy_id : data.data.legacy_id });
                    emit('project-document-saved', { key, document: data.data, asNew, kind: state.kind });
                } catch (error) { this.failed(state, error); }
                finally { state.saving = false; }
            },
            async refreshOverviews() {
                for (const state of Object.values(this.drafts)) {
                    if (!state.id || !state.loaded || state.loading || state.saving) continue;
                    try { const result = await this.request('/api/cms/' + (state.kind === 'domain' ? 'domains' : 'projects') + '/' + state.id); state.overview = result.data.overview || { pages_count: result.data.pages_count || 0 }; } catch (_) {}
                }
            },
            typeName(state) { return config.types[state?.form.settings?.site_type] || 'Projekt'; },
            templateName(state) { return config.templates[state?.form.settings.setup.template]?.name || 'Základný web'; },
            databaseName(state) { return config.databases[state?.form.settings.setup.database_engine] || 'PostgreSQL'; },
        });
        window.addEventListener('cms-project-overviews-refresh', () => Alpine.store('projectEditor').refreshOverviews());
        window.addEventListener('cms-project-tree-updated', () => Alpine.store('projectEditor').refreshOverviews());
        window.addEventListener('beforeunload', event => {
            const store = Alpine.store('projectEditor');
            if (Object.keys(store.drafts).some(key => store.dirty(key))) { event.preventDefault(); event.returnValue = ''; }
        });
        Alpine.data('waProjectForm', (key, id, kind = 'project', projectId = null) => ({
            key,
            init() { Alpine.store('projectEditor').ensure(this.key, id, kind, projectId); },
            trapCopy(event) {
                const controls = [...event.currentTarget.querySelectorAll('input, button')].filter(element => !element.disabled);
                const first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
            },
            get draft() { return Alpine.store('projectEditor').ensure(this.key); },
            get store() { return Alpine.store('projectEditor'); },
            updateName() {
                // Project slugs are maintained internally by the server.
            },
        }));
        Alpine.data('waProjectProperties', () => ({
            select: null,
            init() {
                this.$nextTick(() => {
                    this.select = window.jQuery(this.$refs.projectType);
                    this.select.select2({ width: '100%', minimumResultsForSearch: 0, dropdownCssClass: 'wa-project-type-dropdown', language: { noResults: () => 'Žiadny základ webu sa nenašiel.' } });
                    this.select.on('change.waProject', () => {
                        const current = Alpine.store('projectEditor').current;
                        if (current?.kind === 'domain' && !current.loading && !current.saving) current.form.settings.site_type = this.select.val();
                    });
                    this.$watch('$store.projectEditor.activeKey', () => this.sync());
                    this.$watch('$store.projectEditor.current?.form.settings?.site_type', () => this.sync());
                    this.$watch('$store.projectEditor.current?.loading', () => this.sync());
                    this.$watch('$store.projectEditor.current?.saving', () => this.sync());
                    this.sync();
                });
            },
            sync() {
                const current = Alpine.store('projectEditor').current;
                if (!this.select || current?.kind !== 'domain') return;
                this.select.prop('disabled', current.loading || current.saving || !current.loaded);
                this.select.val(current.form.settings.site_type).trigger('change.select2');
            },
            destroy() {
                if (this.select?.data('select2')) this.select.off('.waProject').select2('destroy');
                this.select = null;
            },
        }));
    });

    window.waCanvasTabs = (tabs, activeTab, csrf) => ({
        tabs, activeTab, hoveredElement: 'Pracovná plocha (Canvas)', tabSaveError: '', persistTimer: null,
        get editor() { return Alpine.store('projectEditor'); },
        get currentMode() { return this.tabs.find(tab => tab.id === this.activeTab)?.mode || 'page'; },
        init() {
            if (!this.tabs.some(tab => tab.id === this.activeTab)) this.activeTab = this.tabs[0]?.id;
            this.editor.activate(this.tabs.find(tab => tab.id === this.activeTab));
        },
        setActiveTab(id) {
            this.activeTab = id;
            const mode = this.tabs.find(tab => tab.id === id)?.mode;
            this.hoveredElement = mode === 'domain' ? 'Editor domény' : mode === 'project' ? 'Editor projektu' : 'Pracovná plocha (Canvas)';
            this.editor.activate(this.tabs.find(tab => tab.id === id));
            this.persistTabs();
            this.$nextTick(() => {
                const active = [...this.$el.querySelectorAll('[data-tab-id]')].find(element => element.dataset.tabId === id);
                active?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            });
        },
        stepTab(id, direction) {
            const index = this.tabs.findIndex(tab => tab.id === id);
            this.setActiveTab(this.tabs[(index + direction + this.tabs.length) % this.tabs.length].id);
            this.$nextTick(() => this.$el.querySelector('[role="tab"][aria-selected="true"]')?.focus());
        },
        buildContentKey(mode, route, title, detail) {
            if (mode === 'project') return detail?.projectId ? 'project:' + detail.projectId : 'project-draft:' + crypto.randomUUID();
            if (mode === 'domain') return detail?.domainId ? 'domain:' + detail.domainId : 'domain-draft:' + crypto.randomUUID();
            if (mode === 'profile') return 'profile';
            if (mode === 'page') return 'page:' + [detail?.project?.id || 'project', detail?.domain?.id || 'domain', detail?.page?.id || detail?.page?.route || detail?.page?.name || title].join('|');
            return route ? 'route:' + route : 'mode:' + mode + '|title:' + title;
        },
        addTab(title = 'Nová stránka', mode = 'page', appendNumber = true, route = null, detail = {}) {
            if (mode === 'profile' || ['project', 'domain'].includes(mode)) route = null;
            const contentKey = this.buildContentKey(mode, route, title, detail);
            const existing = this.tabs.find(tab => tab.contentKey === contentKey || (mode === 'project' && tab.mode === 'project' && detail.projectId && tab.projectId === detail.projectId) || (mode === 'profile' && tab.mode === mode) || (route && tab.route === route));
            if (existing) { this.setActiveTab(existing.id); return existing; }
            const tab = { id: 'tab-' + crypto.randomUUID(), title: appendNumber ? title + ' #' + (this.tabs.length + 1) : title, mode, route, contentKey, ...(['project', 'domain'].includes(mode) ? { projectId: detail.projectId || null, domainId: detail.domainId || null, projectName: detail.projectName || '' } : {}) };
            if (['project', 'domain'].includes(mode) && detail.document) this.editor.apply(tab.id, detail.document);
            this.tabs.push(tab); this.setActiveTab(tab.id); return tab;
        },
        handleAddNewTab(event) {
            const detail = event.detail || {};
            this.addTab(detail.title || 'Nový projekt', detail.mode || detail.type || 'page', false, detail.route || detail.page?.route || detail.page?.url || null, detail);
        },
        projectSaved(detail) {
            const document = detail.document;
            let tab = this.tabs.find(tab => tab.id === detail.key);
            if (detail.asNew) {
                tab = this.addTab(document.name, detail.kind || 'project', false, null, { projectId: detail.kind === 'domain' ? document.project_id : document.id, domainId: detail.kind === 'domain' ? document.id : null, document });
                this.editor.apply(tab.id, document);
                this.editor.drafts[tab.id].notice = detail.kind === 'domain' ? 'Nová doména je uložená.' : 'Nový projekt je uložený.';
            }
            if (!tab) return;
            if (detail.kind === 'domain') { tab.projectId = document.project_id; tab.domainId = document.id; } else tab.projectId = document.id;
            tab.title = document.name; tab.contentKey = (detail.kind || 'project') + ':' + document.id;
            this.persistTabs();
        },
        projectLoaded(detail) {
            const tab = this.tabs.find(tab => tab.id === detail.key);
            if (tab) tab.title = detail.document.name;
        },
        closeTab(id, event) {
            event.stopPropagation();
            if (this.tabs.length <= 1) { alert('Editor musí mať otvorenú aspoň jednu pracovnú plochu.'); return; }
            if (this.editor.drafts[id]?.saving) return;
            if (this.editor.dirty(id) && !confirm('Zavrieť kartu a zahodiť jej neuložené zmeny?')) return;
            const index = this.tabs.findIndex(tab => tab.id === id);
            this.tabs = this.tabs.filter(tab => tab.id !== id);
            delete this.editor.drafts[id];
            if (this.activeTab === id) this.setActiveTab(this.tabs[Math.min(index, this.tabs.length - 1)].id);
            this.persistTabs();
        },
        persistTabs() {
            clearTimeout(this.persistTimer);
            this.persistTimer = setTimeout(async () => {
                try {
                    const response = await fetch('/profile/save-tabs', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ tabs: this.tabs, activeTab: this.activeTab }) });
                    if (!response.ok) throw new Error();
                    this.tabSaveError = '';
                } catch (_) { this.tabSaveError = 'Otvorené karty sa nepodarilo zapamätať. Skúste to znova.'; }
            }, 180);
        },
        saveShortcut(event) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's' && ['project', 'domain'].includes(this.currentMode)) { event.preventDefault(); this.editor.save(this.activeTab); }
        },
        updateInspector(event) {
            const target = event.target;
            this.hoveredElement = ['project', 'domain'].includes(this.currentMode) ? (this.currentMode === 'domain' ? 'Editor domény' : 'Editor projektu') : (target?.getAttribute('data-name') || target?.tagName?.toLowerCase() || 'Pracovná plocha (Canvas)');
        },
        destroy() { clearTimeout(this.persistTimer); },
    });
})();
