@props(['layout' => [], 'projectsData' => []])
{{-- @dd($projectsData, 'Projects data passed to sidebar-left/projects.blade.php'); --}}
<section class="sidebar-block block-bottom" :class="{ 'is-open': activeSection === 'projects' }"

         x-data="{
             projects: window.sidebarProjectsData || [],
             selectedProject: window.sidebarProjectsTreeState && window.sidebarProjectsTreeState.selectedProject ? window.sidebarProjectsTreeState.selectedProject : null,
             selectedDomain: window.sidebarProjectsTreeState && window.sidebarProjectsTreeState.selectedDomain ? window.sidebarProjectsTreeState.selectedDomain : null,
             selectedPage: window.sidebarProjectsTreeState && window.sidebarProjectsTreeState.selectedPage ? window.sidebarProjectsTreeState.selectedPage : null,
             modalMode: 'project',

             normalizeId(prefix, value) {
                 const raw = String(value ?? '').trim();
                 if (raw === '') {
                     return prefix + '-' + Date.now();
                 }
                 if (raw.startsWith(prefix + '-')) {
                     return raw;
                 }
                 const digits = raw.replace(/[^0-9]/g, '');
                 return prefix + '-' + (digits !== '' ? digits : Date.now());
             },

             normalizeProjects(items) {
                 return (items || []).map((project) => ({
                     ...project,
                     id: this.normalizeId('project', project && project.id),
                     domains: (project && project.domains ? project.domains : []).map((domain) => ({
                         ...domain,
                         id: this.normalizeId('domain', domain && domain.id),
                         pages: (domain && domain.pages ? domain.pages : []).map((page) => ({
                             ...page,
                             id: this.normalizeId('page', page && page.id)
                         }))
                     }))
                 }));
             },

             // Zavolá globálny modal z engine.blade.php
             triggerModal() {
                 let title = 'Pridat nový projekt';
                 this.modalMode = 'project';

                 if (this.selectedDomain !== null && this.selectedProject !== null) {
                     title = 'Pridať novú stránku';
                     this.modalMode = 'page';
                 } else if (this.selectedProject !== null) {
                     title = 'Pridať novú doménu';
                     this.modalMode = 'domain';
                 }

                 // Vyvolanie globálnej udalosti, ktorú modal zachytí
                 window.dispatchEvent(new CustomEvent('open-engine-modal', {
                     detail: { title: title, callback: 'project-modal-submit' }
                 }));
             },

             // Spracovanie dát po kliknutí na Uložiť v modale
             handleInflow(name) {
                 if (this.modalMode === 'page') {
                     let proj = this.projects.find(p => p.id === this.selectedProject);
                     if (proj) {
                         let dom = proj.domains.find(d => d.id === this.selectedDomain);
                         if (dom) dom.pages.push({ id: this.normalizeId('page', Date.now()), name: name });
                     }
                 } else if (this.modalMode === 'domain') {
                     let proj = this.projects.find(p => p.id === this.selectedProject);
                     if (proj) proj.domains.push({ id: this.normalizeId('domain', Date.now()), name: name, pages: [] });
                 } else if (this.modalMode === 'project') {
                     this.projects.push({ id: this.normalizeId('project', Date.now()), name: name, domains: [] });
                 }

                 this.saveToDb();
             },

             selectProject(id) {
                 this.selectedProject = (this.selectedProject === id) ? null : id;
                 this.selectedDomain = null;
                 this.selectedPage = null;
                 this.saveTreePosition();

                 // Nájdeme vybraný projekt podľa ID
                 const projectData = this.projects.find(p => p.id === id);

                 // Odoslanie udalosti pre panel vlastností (ak sa projekt zrušil, posielame null)
                 window.dispatchEvent(new CustomEvent('project-selected', {
                     detail: this.selectedProject ? projectData : null
                 }));

                 window.dispatchEvent(new CustomEvent('domain-selected', {
                     detail: null
                 }));

                 window.dispatchEvent(new CustomEvent('page-selected', {
                     detail: null
                 }));
             },

             selectDomain(id, e) {
                 e.stopPropagation();
                 this.selectedDomain = (this.selectedDomain === id) ? null : id;
                 this.selectedPage = null;
                 this.saveTreePosition();

                 let projectData = this.projects.find(p => p.id === this.selectedProject);
                 let domainData = null;

                 if (projectData && this.selectedDomain) {
                     domainData = projectData.domains.find(d => d.id === this.selectedDomain) || null;
                 }

                 window.dispatchEvent(new CustomEvent('domain-selected', {
                     detail: domainData ? {
                         domain: domainData,
                         project: projectData
                     } : null
                 }));

                 window.dispatchEvent(new CustomEvent('page-selected', {
                     detail: null
                 }));
             },

             selectPage(page, domain, project, e) {
                 e.stopPropagation();
                 this.selectedProject = project && project.id ? project.id : this.selectedProject;
                 this.selectedDomain = domain && domain.id ? domain.id : this.selectedDomain;
                 this.selectedPage = page && page.id ? page.id : null;
                 this.saveTreePosition();

                 window.dispatchEvent(new CustomEvent('page-selected', {
                     detail: {
                         page: page,
                         domain: domain,
                         project: project
                     }
                 }));

                 window.dispatchEvent(new CustomEvent('add-new-tab', {
                     detail: {
                         title: page.name,
                         type: 'page',
                         mode: 'page',
                         route: page && (page.route || page.url || page.path) ? (page.route || page.url || page.path) : null,
                         page: page,
                         domain: domain,
                         project: project
                     }
                 }));
             },

             saveTreePosition() {
                 fetch('/sidebar/save-setting', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': '{{ csrf_token() }}'
                     },
                     body: JSON.stringify({
                         name: 'sidebar_projects_tree_position',
                         value: {
                             selectedProject: this.selectedProject,
                             selectedDomain: this.selectedDomain,
                             selectedPage: this.selectedPage
                         }
                     })
                 }).catch(() => {
                     // Keep UI responsive even if persistence fails.
                 });
             },

             saveToDb() {
                 this.projects = this.normalizeProjects(this.projects);

                 fetch('/api/ui/save-projects', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': '{{ csrf_token() }}'
                     },
                     body: JSON.stringify({
                         projects: this.projects
                     })
                 }).catch(() => {
                     // Keep UI responsive even if persistence fails.
                 });
             }
         }"
         @project-modal-submit.window="handleInflow($event.detail.value)"
         @project-properties-save.window="saveToDb()"
>
    <!-- Plusko plynule odpáli globálny vycentrovaný modal -->
    <x-ui.accordion-header section="projects" :title="$layout['blocks']['block_3']['title'] ?? 'Projekty'" action-label="Pridať projekt, doménu alebo stránku" action="triggerModal()" />
    
    <div class="block-content sidebar-accordion-content" id="accordion-projects-content" role="region" aria-labelledby="accordion-projects-toggle" x-show="activeSection === 'projects'" x-cloak style="padding: 10px 15px; flex: 1; overflow-y: auto; color: #fff; font-size: 13px; user-select: none;">
        <template x-for="project in projects" :key="project.id">
            <div style="margin-bottom: 8px;">
                <!-- 1. ÚROVEŇ: PROJEKT -->
                <div @click="selectProject(project.id)" 
                     :style="{ color: selectedProject === project.id ? '#3bc0c3' : '#fff' }"
                     style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: bold; padding: 4px 0;">
                    <span x-text="selectedProject === project.id ? '▼' : '►'" style="font-size: 9px; width: 10px;"></span>
                    <span>📁</span>
                    <span x-text="project.name"></span>
                    <span title="Zmazat"
                          aria-label="Zmazat"
                          style="display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; opacity: 0.9; margin-left: auto; pointer-events: none; color: #ef4444;">
                        <svg viewBox="0 0 16 16" width="12" height="12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M2.5 4H13.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                            <path d="M6 2.8H10" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                            <path d="M4 4.5L4.6 13H11.4L12 4.5" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>

                <!-- 2. ÚROVEŇ: DOMÉNY -->
                <div x-show="selectedProject === project.id" style="padding-left: 18px; margin-top: 2px;">
                    <template x-for="domain in project.domains" :key="domain.id">
                        <div style="margin-bottom: 4px;">
                            <div @click="selectDomain(domain.id, $event)"
                                 :style="{ color: selectedDomain === domain.id ? '#3bc0c3' : '#8a94ad' }"
                                 style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; padding: 2px 0;">
                                <span x-text="selectedDomain === domain.id ? '▼' : '►'" style="font-size: 9px; width: 10px;"></span>
                                <span>🌐</span>
                                <span x-text="domain.name"></span>
                                <span title="Zmazat"
                                      aria-label="Zmazat"
                                      style="display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; opacity: 0.9; margin-left: auto; pointer-events: none; color: #ef4444;">
                                    <svg viewBox="0 0 16 16" width="12" height="12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M2.5 4H13.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                        <path d="M6 2.8H10" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                        <path d="M4 4.5L4.6 13H11.4L12 4.5" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </div>

                            <!-- 3. ÚROVEŇ: STRÁNKY -->
                            <div x-show="selectedDomain === domain.id" style="padding-left: 18px; margin-top: 2px; display: flex; flex-direction: column; gap: 4px;">
                                <template x-for="page in domain.pages" :key="page.id">
                                    <div :style="{ background: selectedPage === page.id ? '#3b82f6' : 'var(--wa-surface-raised)' }"
                                         style="display: flex; align-items: center; gap: 8px; padding: 3px 6px; border-radius: 3px; font-size: 12px; color: #fff; cursor: pointer;"
                                         @click="selectPage(page, domain, project, $event)"
                                    >
                                        <span>📄</span>
                                        <span x-text="page.name"></span>
                                        <span title="Zmazat"
                                              aria-label="Zmazat"
                                                style="display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; opacity: 0.9; margin-left: auto; pointer-events: none; color: #ef4444;">
                                            <svg viewBox="0 0 16 16" width="12" height="12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                <path d="M2.5 4H13.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                                <path d="M6 2.8H10" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                                                <path d="M4 4.5L4.6 13H11.4L12 4.5" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="projects.length === 0">
            <div style="color: var(--wa-border-strong); text-align: center; padding-top: 20px; font-style: italic;">
                Kliknite na + pre vytvorenie projektu.
            </div>
        </template>
    </div>
</section>
