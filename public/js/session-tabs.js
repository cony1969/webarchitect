(() => {
    'use strict';
    const config = window.waSessionTabsConfig || {};
    const values = {...(config.tabs || {})};
    const pending = new Map();
    let saving = false;
    const report = message => window.dispatchEvent(new CustomEvent('wa-session-tabs-status', {detail: {message}}));
    const flush = async () => {
        if (saving) return;
        saving = true;
        try {
            while (pending.size) {
                const [panel, tab] = pending.entries().next().value;
                const response = await fetch('/api/ui/session-tab', {
                    method: 'POST', keepalive: true,
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf},
                    body: JSON.stringify({panel, tab}),
                });
                if (!response.ok) throw new Error();
                if (pending.get(panel) === tab) pending.delete(panel);
            }
            report('');
        } catch (_) {
            report('Vybranú záložku sa nepodarilo zapamätať.');
        } finally { saving = false; }
    };
    window.waSessionTabs = {
        get(panel, fallback) { return values[panel] || fallback; },
        set(panel, tab) { values[panel] = tab; pending.set(panel, tab); return flush(); },
        retry: flush,
    };
})();
