<style>
    /* Reset a základná kostra aplikácie */
    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        height: 100vh;
        overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background-color: #f4f5f8;
    }

    /* Hlavný kontajner drží fixnú výšku okna */
    .app-container {
        display: flex;
        flex-direction: column;
        height: 100vh;
    }

    /* Fixný Topbar */
    .main-header {
        height: 55px;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        border-bottom: 1px solid #e9e9ef;
        z-index: 10;
        flex-shrink: 0;
    }
    .brand { font-weight: bold; font-size: 16px; }
    .top-nav { display: flex; gap: 20px; }
    .user-profile { font-size: 13px; color: #6f768e; }

    /* Pracovný priestor pod hlavičkou */
    .workspace-wrapper {
        display: flex;
        flex: 1;
        height: calc(100vh - 55px);
        overflow: hidden;
    }

        /* Pokročilý trojblokový sidebar */
    .advanced-sidebar {
        background: #2a3042;
        color: #a6b0cf;
        display: flex;
        flex-direction: column;
        height: 100%;
        flex-shrink: 0;
        /* Uvoľníme šírku pre plynulé ťahanie JavaScriptom */
        min-width: 0px !important; 
        max-width: 800px;
    }


    /* Jednotlivé vertikálne bloky v sidebare */
    .sidebar-block {
        display: flex;
        flex-direction: column;
        flex: 1; /* Bloky berú rovnaký podiel, kým nenasadíme JS resizer */
        overflow: hidden;
    }
    .block-header {
        background: #32394e;
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

    /* Resizery (Línie pripravené na drag & drop) */
    .sidebar-resizer-horizontal {
        height: 4px;
        background: #232938;
        cursor: row-resize;
        transition: background 0.2s;
    }
    .sidebar-resizer-horizontal:hover { background: #3bc0c3; }

    .sidebar-resizer-vertical {
        width: 4px;
        background: #e9e9ef;
        cursor: col-resize;
        transition: background 0.2s;
        flex-shrink: 0;
    }
    .sidebar-resizer-vertical:hover { background: #3bc0c3; }

    /* Hlavná pracovná plocha */
    .main-content {
        flex: 1;
        padding: 25px;
        overflow-y: auto;
    }
    .sidebar-resizer-vertical.is-dragging {
        background: #3bc0c3 !important;
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
    background: #2a2e3d;
    border: 1px solid #4e566d;
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
    background: #202430;
    color: #6c758d;
    border-color: #383e52;
    cursor: not-allowed;
}

</style>
