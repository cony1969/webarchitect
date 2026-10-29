<style>
    /* Reset a základná kostra aplikácie */
    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        height: 100vh;
        height: 100dvh;
        overflow: hidden;
        overflow: clip;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background-color: #f4f5f8;
    }

    /* Keep Safari focus/Select2 navigation from scrolling the whole CMS shell. */
    body {
        position: fixed;
        inset: 0;
    }

    /* Hlavný kontajner drží fixnú výšku okna */
    .app-container {
        display: flex;
        flex-direction: column;
        height: 100vh;
        height: 100dvh;
    }

    /* Fixný Topbar */
    .main-header {
        height: 55px;
        background: var(--wa-surface-base);
        color: #f4f6f8;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        border-bottom: 1px solid var(--wa-border);
        z-index: 10;
        flex-shrink: 0;
    }
    .main-header .brand {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        color: #f4f6f8;
        font-weight: bold;
        font-size: 16px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .main-header .brand-logo {
        display: block;
        width: 40px;
        height: 40px;
        object-fit: contain;
        flex-shrink: 0;
    }
    .top-nav { display: flex; gap: 20px; }
    .user-profile { font-size: 13px; color: #6f768e; }

    /* Pracovný priestor pod hlavičkou */
    .workspace-wrapper {
        display: flex;
        flex: 1;
        min-height: 0;
        min-width: 0;
        overflow: hidden;
    }

        /* Pokročilý trojblokový sidebar */
    .advanced-sidebar {
        background: var(--wa-surface-base);
        color: #a6b0cf;
        display: flex;
        flex-direction: column;
        height: 100%;
        flex-shrink: 0;
        width: clamp(240px, 22vw, 320px);
        min-height: 0;
        min-width: 0;
    }


    /* Jednotlivé vertikálne bloky v sidebare */
    .sidebar-block {
        display: flex;
        flex-direction: column;
        flex: 0 0 auto;
        min-height: 0;
        overflow: hidden;
    }
    .block-header {
        background: var(--wa-surface-raised);
        color: #fff;
        padding: 8px 15px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    .block-content {
        padding: 15px;
        flex: 1;
        overflow-y: auto; /* Scrolluje sa len vnútro daného bloku */
        font-size: 13px;
        scrollbar-width: thin;
        scrollbar-color: rgba(138, 148, 173, 0.45) transparent;
    }

    .block-content::-webkit-scrollbar {
        width: 8px;
        background: transparent;
    }

    .block-content::-webkit-scrollbar-track {
        background: transparent;
    }

    .block-content::-webkit-scrollbar-thumb {
        background: rgba(138, 148, 173, 0.45);
        border-radius: 8px;
    }

    .sidebar-scroll-area {
        scrollbar-width: thin;
        scrollbar-color: rgba(138, 148, 173, 0.45) transparent;
    }

    .sidebar-scroll-area::-webkit-scrollbar {
        width: 8px;
        background: transparent;
    }

    .sidebar-scroll-area::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-scroll-area::-webkit-scrollbar-thumb {
        background: rgba(138, 148, 173, 0.45);
        border-radius: 8px;
    }

    /* Left sidebar: keep scrolling, hide native scrollbar rail that appears as white strip. */
    .advanced-sidebar .sidebar-scroll-area,
    .advanced-sidebar .block-content {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .advanced-sidebar .sidebar-scroll-area::-webkit-scrollbar,
    .advanced-sidebar .block-content::-webkit-scrollbar {
        width: 0;
        height: 0;
        background: transparent;
    }

    /* Right sidebar (Properties/Appearance): keep scrolling, hide native scrollbar rail. */
    .advanced-sidebar-right .block-content,
    .advanced-sidebar-right .tab-content {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .advanced-sidebar-right .block-content::-webkit-scrollbar,
    .advanced-sidebar-right .tab-content::-webkit-scrollbar {
        width: 0;
        height: 0;
        background: transparent;
    }

    /* Hlavná pracovná plocha */
    .main-content {
        flex: 1;
        padding: 25px;
        overflow-y: auto;
    }

/* Nastavenie transparentného pozadia pre aktívny prvok Scrollspy */
.nav-link.active, 
.list-group-item.active {
    background-color: transparent !important;
    color: #3bc0c3 !important; /* Voliteľné: zachová farbu textu/linky */
}

/* Vynútenie kurzora pre interaktívne prvky (tabs/tree/add buttons). */
.sidebar-block .nav-link,
.sidebar-block button,
.canvas-tabs-bar button,
.canvas-tabs-bar [role="button"],
.canvas-tabs-bar div,
.user-profile button,
.user-profile span {
    cursor: pointer !important;
}

.tree-item {
    cursor: grab !important;
}

.tree-item:active {
    cursor: grabbing !important;
}

/* Globálne štýly pre form-group-input */
.prop-form-group {
    display: flex;
    flex-direction: column;
    width: 100%;
}
.form-group-label {
    display: block;
    font-family: sans-serif;
    font-size: 11px;
    text-transform: uppercase;
    color: #8a94ad;
    margin-bottom: 4px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.form-group-field {
    width: 100%;
    background: var(--wa-surface-raised);
    border: 1px solid var(--wa-border-strong);
    color: #fff;
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 4px;
    box-sizing: border-box;
    outline: none;
    transition: border-color 0.15s ease;
}
.form-group-field:focus {
    border-color: #3bc0c3;
}
.form-group-field.field-readonly {
    background: var(--wa-surface-deep);
    color: #6c758d;
    border-color: var(--wa-border);
    cursor: not-allowed;
}

</style>
