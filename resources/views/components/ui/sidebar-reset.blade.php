<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--wa-border);">
    <h4 style="color: #ffffff; margin: 0 0 5px; font-size: 14px; font-weight: 600;">Rozloženie prostredia</h4>
    <p style="color: #a4b1cd; margin: 0 0 15px; font-size: 12px;">Otvorené sekcie bočných panelov sa pamätajú iba v tomto prehliadači.</p>
    <button type="button"
            onclick="try { localStorage.removeItem('wa.sidebar.left.section'); localStorage.removeItem('wa.sidebar.right.section'); } catch (e) {} window.dispatchEvent(new CustomEvent('sidebar-accordion-reset'));"
            style="padding: 10px 16px; background: var(--wa-surface-raised); color: #fff; border: 1px solid var(--wa-border); border-radius: 6px; font-size: 13px; cursor: pointer;">
        Obnoviť predvolené sekcie
    </button>
</div>
