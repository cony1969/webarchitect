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
            defaults(settings = {}) {
                return { ...clone(config.defaults), ...clone(settings), setup: { ...config.defaults.setup, ...(settings.setup || {}) } };
            },
            ensure(key, id = null) {
                if (!this.drafts[key]) {
                    const form = { name: '', slug: '', status: 'draft', settings: this.defaults() };
                    this.drafts[key] = { id, revision: 1, form, document: null, baseline: JSON.stringify(form), loaded: !id, loading: false, saving: false, error: '', errors: {}, notice: '', conflict: false, copyOpen: false, copyName: '', copySlug: '', autoSlug: true };
                }
                if (id && !this.drafts[key].id) { this.drafts[key].id = id; this.drafts[key].loaded = false; }
                return this.drafts[key];
            },
            dirty(key) {
                const state = this.drafts[key];
                return !!state && JSON.stringify(state.form) !== state.baseline;
            },
            activate(tab) {
                this.activeKey = tab?.mode === 'project' ? tab.id : null;
                if (!this.activeKey) return;
                const state = this.ensure(tab.id, tab.projectId || null);
                if (state.id && !state.loaded && !state.loading) this.load(tab.id);
                emit('project-editor-focused', { projectId: state.id });
            },
            apply(key, document) {
                const state = this.ensure(key, document.id);
                state.id = document.id;
                state.revision = document.revision;
                state.form = { name: document.name, slug: document.slug, status: document.status, settings: this.defaults(document.settings) };
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
                    const data = await this.request('/api/cms/projects/' + state.id);
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
                state.copyName = state.form.name ? state.form.name + ' – kópia' : 'Nový projekt';
                state.copySlug = waProjectSlug(state.copyName);
                state.errors = {}; state.error = ''; state.copyOpen = true;
            },
            async save(key, asNew = false) {
                const state = this.drafts[key];
                if (!state || !state.loaded || state.loading || state.saving) return;
                state.error = ''; state.errors = {}; state.notice = ''; state.conflict = false;
                const form = clone(state.form);
                if (asNew) { form.name = state.copyName.trim(); form.slug = state.copySlug.trim(); form.status = 'draft'; }
                else { form.name = form.name.trim(); form.slug = form.slug.trim(); }
                if (!form.name || !form.slug) { state.error = 'Vyplňte názov a slug projektu.'; return; }
                state.saving = true;
                const updating = state.id && !asNew;
                const url = state.id ? '/api/cms/projects/' + state.id + (asNew ? '/copy' : '') : '/api/cms/projects';
                const payload = { ...form, ...(updating ? { revision: state.revision } : {}), ...(state.document ? { document: state.document } : {}) };
                try {
                    const data = await this.request(url, { method: updating ? 'PUT' : 'POST', body: JSON.stringify(payload) });
                    if (!asNew) this.apply(key, data.data);
                    state.copyOpen = false;
                    state.notice = asNew ? 'Projekt bol uložený ako nový v samostatnej karte.' : 'Zmeny projektu sú uložené.';
                    if (data.project_tree) emit('cms-project-tree-updated', { ...data.project_tree, focusProject: data.data.legacy_id });
                    emit('project-document-saved', { key, document: data.data, asNew });
                } catch (error) { this.failed(state, error); }
                finally { state.saving = false; }
            },
            typeName(state) { return config.types[state?.form.settings.project_type] || 'Projekt'; },
            templateName(state) { return config.templates[state?.form.settings.setup.template]?.name || 'Základný web'; },
            databaseName(state) { return config.databases[state?.form.settings.setup.database_engine] || 'PostgreSQL'; },
        });
        window.addEventListener('beforeunload', event => {
            const store = Alpine.store('projectEditor');
            if (Object.keys(store.drafts).some(key => store.dirty(key))) { event.preventDefault(); event.returnValue = ''; }
        });
        Alpine.data('waProjectForm', (key, id) => ({
            key,
            init() { Alpine.store('projectEditor').ensure(this.key, id); },
            trapCopy(event) {
                const controls = [...event.currentTarget.querySelectorAll('input, button')].filter(element => !element.disabled);
                const first = controls[0], last = controls[controls.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
            },
            get draft() { return Alpine.store('projectEditor').ensure(this.key); },
            get store() { return Alpine.store('projectEditor'); },
            updateName() {
                if (!this.draft.id && this.draft.autoSlug) this.draft.form.slug = waProjectSlug(this.draft.form.name);
            },
        }));
        Alpine.data('waProjectProperties', () => ({
            select: null,
            init() {
                this.$nextTick(() => {
                    this.select = window.jQuery(this.$refs.projectType);
                    this.select.select2({ width: '100%', minimumResultsForSearch: 0, dropdownCssClass: 'wa-project-type-dropdown', language: { noResults: () => 'Žiadny typ projektu sa nenašiel.' } });
                    this.select.on('change.waProject', () => {
                        const current = Alpine.store('projectEditor').current;
                        if (current && !current.loading && !current.saving) current.form.settings.project_type = this.select.val();
                    });
                    this.$watch('$store.projectEditor.activeKey', () => this.sync());
                    this.$watch('$store.projectEditor.current?.form.settings.project_type', () => this.sync());
                    this.$watch('$store.projectEditor.current?.loading', () => this.sync());
                    this.$watch('$store.projectEditor.current?.saving', () => this.sync());
                    this.sync();
                });
            },
            sync() {
                const current = Alpine.store('projectEditor').current;
                if (!this.select || !current) return;
                this.select.prop('disabled', current.loading || current.saving || !current.loaded);
                this.select.val(current.form.settings.project_type).trigger('change.select2');
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
            if (mode === 'profile') return 'profile';
            if (mode === 'page') return 'page:' + [detail?.project?.id || 'project', detail?.domain?.id || 'domain', detail?.page?.id || detail?.page?.route || detail?.page?.name || title].join('|');
            return route ? 'route:' + route : 'mode:' + mode + '|title:' + title;
        },
        addTab(title = 'Nová stránka', mode = 'page', appendNumber = true, route = null, detail = {}) {
            if (mode === 'profile' || mode === 'project') route = null;
            const contentKey = this.buildContentKey(mode, route, title, detail);
            const existing = this.tabs.find(tab => tab.contentKey === contentKey || (mode === 'project' && detail.projectId && tab.projectId === detail.projectId) || (mode === 'profile' && tab.mode === mode) || (route && tab.route === route));
            if (existing) { this.setActiveTab(existing.id); return existing; }
            const tab = { id: 'tab-' + crypto.randomUUID(), title: appendNumber ? title + ' #' + (this.tabs.length + 1) : title, mode, route, contentKey, ...(mode === 'project' ? { projectId: detail.projectId || null } : {}) };
            if (mode === 'project' && detail.document) this.editor.apply(tab.id, detail.document);
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
                tab = this.addTab(document.name, 'project', false, null, { projectId: document.id, document });
                this.editor.apply(tab.id, document);
                this.editor.drafts[tab.id].notice = 'Nový projekt je uložený.';
            }
            if (!tab) return;
            tab.projectId = document.id; tab.title = document.name; tab.contentKey = 'project:' + document.id;
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
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's' && this.currentMode === 'project') { event.preventDefault(); this.editor.save(this.activeTab); }
        },
        updateInspector(event) {
            const target = event.target;
            this.hoveredElement = this.currentMode === 'project' ? 'Editor projektu' : (target?.getAttribute('data-name') || target?.tagName?.toLowerCase() || 'Pracovná plocha (Canvas)');
        },
        destroy() { clearTimeout(this.persistTimer); },
    });
})();
