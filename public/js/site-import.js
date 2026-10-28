(() => {
    'use strict';
    document.addEventListener('alpine:init', () => {
        Alpine.data('waSiteImport', () => ({
            sources: [], sourceId: '', loading: false, analyzing: false, error: '', report: null,
            async init() {
                this.loading = true;
                try {
                    this.sources = await this.request('/api/cms/site-import/sources');
                    this.sourceId = this.sources[0]?.id || '';
                } catch (error) { this.error = error.message; }
                finally { this.loading = false; }
            },
            async request(url, payload = null) {
                const response = await fetch(url, { method: payload ? 'POST' : 'GET', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.waProjectEditorConfig.csrf }, ...(payload ? { body: JSON.stringify(payload) } : {}) });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(response.status === 401 ? 'Prihlásenie vypršalo. Obnovte stránku.' : result.message || 'Analýzu sa nepodarilo načítať.');
                return result.data;
            },
            async analyze() {
                if (!this.sourceId || this.analyzing) return;
                this.error = ''; this.report = null; this.analyzing = true;
                try { this.report = await this.request('/api/cms/site-import/analyze', { source_id: this.sourceId }); }
                catch (error) { this.error = error.message; }
                finally { this.analyzing = false; }
            },
            size(bytes) { return (bytes / 1024 / 1024).toLocaleString('sk-SK', { maximumFractionDigits: 1 }) + ' MB'; },
        }));
    });
})();
