@props(['layout' => [], 'blockRightTopHeight' => 400])

<section class="sidebar-block"
         :style="{ height: blockRightTopHeight + 'px', flex: 'none' }"
         x-data="{
             selectedObjectType: null,
             selectedProjectObj: null,
             selectedDomainObj: null,
             selectedDomainProjectObj: null,
             selectedPageObj: null,
             selectedPageDomainObj: null,
             selectedPageProjectObj: null,
             projectTab: 'basic',

             headerTitle() {
                 if (this.selectedPageObj && this.selectedPageObj.name) {
                     return 'VLASTNOSTI STRÁNKY {' + this.selectedPageObj.name + '}';
                 }

                 if (this.selectedDomainObj && this.selectedDomainObj.name) {
                     return 'VLASTNOSTI DOMENY {' + this.selectedDomainObj.name + '}';
                 }

                 if (this.selectedProjectObj && this.selectedProjectObj.name) {
                     return 'VLASTNOSTI PROJEKTU {' + this.selectedProjectObj.name + '}';
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
                 try {
                     window.sessionStorage.setItem('warProjectPropertiesActiveTab', nextTab);
                 } catch (e) {
                     // ignore storage errors
                 }
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
                 try {
                     const storedTab = window.sessionStorage.getItem('warProjectPropertiesActiveTab');
                     const allowedTabs = ['basic', 'appearance', 'bootstrap', 'advanced'];
                     this.projectTab = allowedTabs.includes(String(storedTab)) ? String(storedTab) : 'basic';
                 } catch (e) {
                     this.projectTab = 'basic';
                 }

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
    <div class="block-header" x-text="headerTitle()"></div>
    <div class="block-content" style="padding: 0 15px; height: calc(100% - 40px); overflow-y: auto; color: #fff;">
        <x-ui.sidebar-right.properties.project />
        <x-ui.sidebar-right.properties.domain />
        <x-ui.sidebar-right.properties.page />

        <template x-if="selectedObjectType === null">
            <div style="color: #4e566d; text-align: center; padding-top: 20px; font-style: italic;">
                Klikni na objekt v ľavom paneli pre zobrazenie vlastností.
            </div>
        </template>
    </div>
</section>
