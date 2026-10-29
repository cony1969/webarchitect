@props(['layout' => []])

<section class="sidebar-block" :class="{ 'is-open': activeSection === 'properties' }"

         x-data="{
             selectedObjectType: null,
             selectedProjectObj: null,
             selectedDomainObj: null,
             selectedDomainProjectObj: null,
             selectedPageObj: null,
             selectedPageDomainObj: null,
             selectedPageProjectObj: null,
             projectTab: window.waSessionTabs.get('legacy_properties', 'basic'),

             headerTitle() {
                 if (this.$store.pagePreview.current) return 'VLASTNOSTI STRÁNKY {' + (this.$store.pagePreview.current.data.name || 'Stránka') + '}';
                 if (this.$store.componentEditor.current) return 'VLASTNOSTI KOMPONENTU {' + (this.$store.componentEditor.current.form.name || 'Komponent') + '}';
                 if (this.$store.projectEditor.activeMode === 'import') return 'IMPORT Z ASUS-U';
                 if (this.$store.projectEditor.active) return (this.$store.projectEditor.isDomain ? 'VLASTNOSTI DOMÉNY {' : 'VLASTNOSTI PROJEKTU {') + (this.$store.projectEditor.current.form.name || 'Nový projekt') + '}';
                 if (this.selectedPageObj && this.selectedPageObj.name) {
                     return 'VLASTNOSTI STRÁNKY {' + this.selectedPageObj.name + '}';
                 }

                 if (this.selectedDomainObj && this.selectedDomainObj.name) {
                     return 'VLASTNOSTI DOMENY {' + this.selectedDomainObj.name + '}';
                 }

                 if (this.selectedProjectObj && this.selectedProjectObj.name) {
                     return (this.$store.projectEditor.isDomain ? 'VLASTNOSTI DOMÉNY {' : 'VLASTNOSTI PROJEKTU {') + this.selectedProjectObj.name + '}';
                 }

                 return 'VLASTNOSTI PROJEKTU';
             },

             hydrateFromTreeState() {
                 const tree = window.sidebarProjectsTreeState || {};
                 const projects = window.sidebarProjectsData || [];

                 const selectedProjectId = tree.selectedProject ?? null;
                 const selectedDomainId = tree.selectedDomain ?? null;
                 const selectedPageId = tree.selectedPage ?? null;

                 if (selectedProjectId === null && selectedDomainId === null && selectedPageId === null) {
                     return;
                 }

                 const sameId = (a, b) => String(a) === String(b);
                 const project = projects.find((p) => sameId(p.id, selectedProjectId)) || null;
                 const domain = project && Array.isArray(project.domains)
                     ? project.domains.find((d) => sameId(d.id, selectedDomainId)) || null
                     : null;
                 const page = domain && Array.isArray(domain.pages)
                     ? domain.pages.find((pg) => sameId(pg.id, selectedPageId)) || null
                     : null;

                 this.selectedProjectObj = project;

                 if (page) {
                     this.selectedPageObj = page;
                     this.selectedPageDomainObj = domain;
                     this.selectedPageProjectObj = project;
                     this.selectedDomainObj = domain;
                     this.selectedDomainProjectObj = project;
                     this.selectedObjectType = 'page';
                     return;
                 }

                 if (domain) {
                     this.selectedPageObj = null;
                     this.selectedPageDomainObj = null;
                     this.selectedPageProjectObj = null;
                     this.selectedDomainObj = domain;
                     this.selectedDomainProjectObj = project;
                     this.selectedObjectType = 'domain';
                     return;
                 }

                 this.selectedPageObj = null;
                 this.selectedPageDomainObj = null;
                 this.selectedPageProjectObj = null;
                 this.selectedDomainObj = null;
                 this.selectedDomainProjectObj = null;
                 this.selectedObjectType = project ? 'project' : null;
             },

             setProjectTab(tab) {
                 const allowedTabs = ['basic', 'appearance', 'bootstrap', 'advanced'];
                 const nextTab = allowedTabs.includes(String(tab)) ? String(tab) : 'basic';
                 this.projectTab = nextTab;
                 window.waSessionTabs.set('legacy_properties', nextTab);
                 this.$nextTick(() => this.scrollProjectTabIntoView());
             },

             scrollProjectTabIntoView() {
                 const wrap = this.$refs.projectTabsWrap;
                 if (!wrap) return;
                 const candidates = Array.from(wrap.querySelectorAll('[data-project-tab]'));
                 const active = candidates.find((el) => String(el.getAttribute('data-project-tab')) === String(this.projectTab));
                 if (!active) return;
                 active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
             },

             init() {
                 this.hydrateFromTreeState();
                 this.$nextTick(() => this.scrollProjectTabIntoView());

                 window.addEventListener('project-selected', (e) => {
                     const detail = e.detail || null;
                     this.selectedProjectObj = detail;
                     this.selectedDomainObj = null;
                     this.selectedDomainProjectObj = null;
                     this.selectedPageObj = null;
                     this.selectedPageDomainObj = null;
                     this.selectedPageProjectObj = null;
                     this.selectedObjectType = detail ? 'project' : null;
                     this.$nextTick(() => this.scrollProjectTabIntoView());
                 });

                 window.addEventListener('domain-selected', (e) => {
                     const detail = e.detail || null;

                     if (detail && detail.domain) {
                         this.selectedDomainObj = detail.domain;
                         this.selectedDomainProjectObj = detail.project || null;
                         this.selectedProjectObj = detail.project || this.selectedProjectObj;
                         this.selectedPageObj = null;
                         this.selectedPageDomainObj = null;
                         this.selectedPageProjectObj = null;
                         this.selectedObjectType = 'domain';
                         return;
                     }

                     this.selectedDomainObj = null;
                     this.selectedDomainProjectObj = null;
                     this.selectedPageObj = null;
                     this.selectedPageDomainObj = null;
                     this.selectedPageProjectObj = null;
                     this.selectedObjectType = this.selectedProjectObj ? 'project' : null;
                 });

                 window.addEventListener('page-selected', (e) => {
                     const detail = e.detail || null;

                     if (detail && detail.page) {
                         this.selectedPageObj = detail.page;
                         this.selectedPageDomainObj = detail.domain || null;
                         this.selectedPageProjectObj = detail.project || null;
                         this.selectedDomainObj = detail.domain || null;
                         this.selectedDomainProjectObj = detail.project || null;
                         this.selectedProjectObj = detail.project || this.selectedProjectObj;
                         this.selectedObjectType = 'page';
                         return;
                     }

                     this.selectedPageObj = null;
                     this.selectedPageDomainObj = null;
                     this.selectedPageProjectObj = null;
                     if (this.selectedDomainObj) {
                         this.selectedObjectType = 'domain';
                     } else if (this.selectedProjectObj) {
                         this.selectedObjectType = 'project';
                     } else {
                         this.selectedObjectType = null;
                     }
                 });
             },

             saveToDb() {
                 window.dispatchEvent(new CustomEvent('project-properties-save'));
             }
         }">
    <x-ui.accordion-header section="properties" title="Vlastnosti" dynamic-title="headerTitle()" />
    <div class="block-content sidebar-accordion-content" id="accordion-properties-content" role="region" aria-labelledby="accordion-properties-toggle" x-show="activeSection === 'properties'" x-cloak style="padding: 0 15px; overflow-y: auto; color: #fff;">
        <x-ui.page-editor.properties />
        <x-ui.component-editor.properties />
        <x-ui.project-editor.properties />
        <x-ui.domain-editor.properties />
        <div x-show="$store.projectEditor.activeMode === 'import'" class="wa-project-properties" style="padding:20px 0">
            <h3 class="wa-project-properties-title">Analýza webu</h3><p class="wa-project-muted">Vyberte zdroj v strednej karte a spustite analýzu. Zobrazí stránky, sekcie, súbory a dostupné subdomény.</p><p class="wa-project-muted">Tento krok iba číta. Pôvodný web ani projekty sa nemenia.</p>
        </div>
        <div x-show="!$store.pagePreview.current && !$store.componentEditor.current && !$store.projectEditor.active && $store.projectEditor.activeMode !== 'import'">
        <x-ui.sidebar-right.properties.project />
        <x-ui.sidebar-right.properties.domain />
        <x-ui.sidebar-right.properties.page />

        <template x-if="selectedObjectType === null">
            <div style="color: var(--wa-border-strong); text-align: center; padding-top: 20px; font-style: italic;">
                Klikni na objekt v ľavom paneli pre zobrazenie vlastností.
            </div>
        </template>
        </div>
    </div>
</section>
