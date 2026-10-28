(() => {
    'use strict';
    document.addEventListener('alpine:init', () => {
        Alpine.store('pagePreview', {
            drafts: {}, activeKey: null,
            get current() { return this.drafts[this.activeKey] || null; },
            ensure(key, id = null) {
                if (!this.drafts[key]) this.drafts[key] = { id, loading: false, loaded: false, error: '', data: { name: '', components: [] }, preview: '' };
                if (id) this.drafts[key].id = id;
                return this.drafts[key];
            },
            activate(tab) {
                this.activeKey = tab?.mode === 'page' && tab.pageId ? tab.id : null;
                if (this.activeKey) { this.ensure(tab.id, tab.pageId); this.load(tab.id); window.dispatchEvent(new CustomEvent('project-editor-focused')); }
            },
            apply(key, data) {
                const draft = this.ensure(key, data.id);
                draft.data = data; draft.preview = window.waComponentPreview(data.preview); draft.loaded = true; draft.error = '';
            },
            async load(key) {
                const draft = this.drafts[key];
                if (!draft?.id || draft.loading) return;
                draft.loading = true;
                try { const result = await Alpine.store('componentEditor').request('/api/cms/page-composition/' + draft.id); if (this.drafts[key] === draft) this.apply(key, result.data); }
                catch (error) { draft.error = error.message; } finally { draft.loading = false; }
            },
        });
    });
})();
