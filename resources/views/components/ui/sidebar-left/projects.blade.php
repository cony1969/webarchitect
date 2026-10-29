@props(['layout' => [], 'projectsData' => []])
{{-- @dd($projectsData, 'Projects data passed to sidebar-left/projects.blade.php'); --}}
<section class="sidebar-block block-bottom" :class="{ 'is-open': activeSection === 'projects' }"

         x-data="{
             projects: window.sidebarProjectsData || [],
             revision: window.sidebarProjectsRevision || 1,
             projectIds: window.sidebarProjectIds || {},
             domainIds: window.sidebarDomainIds || {},
             saving: false,
             saveAgain: false,
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
                 window.dispatchEvent(new CustomEvent('add-new-tab', { detail: { title: 'Nový projekt', mode: 'project' } }));
             },
             openProject(project) {
                 const projectId = this.projectIds[project.id];
                 if (!projectId) { alert('Obnovte zoznam projektov a skúste to znova.'); return; }
                 window.dispatchEvent(new CustomEvent('add-new-tab', { detail: { title: project.name, mode: 'project', projectId } }));
             },
             openDomain(project, domain) {
                 const projectId = this.projectIds[project.id];
                 const domainId = this.domainIds[projectId] && this.domainIds[projectId][domain.id];
                 if (!domainId) { alert('Obnovte zoznam domén a skúste to znova.'); return; }
                 window.dispatchEvent(new CustomEvent('add-new-tab', { detail: { title: domain.name, mode: 'domain', projectId, domainId, projectName: project.name } }));
             },
             triggerNodeModal(mode, project, domain = null) {
                 if (mode === 'domain') {
                     window.dispatchEvent(new CustomEvent('add-new-tab', { detail: { title: 'Nová doména', mode: 'domain', projectId: this.projectIds[project.id], projectName: project.name } }));
                     return;
                 }
                 this.modalMode = mode;
                 this.selectedProject = project.id;
                 this.selectedDomain = domain ? domain.id : null;
                 this.selectedPage = null;
                 this.saveTreePosition();
                 window.dispatchEvent(new CustomEvent('open-engine-modal', { detail: { title: mode === 'domain' ? 'Pridať novú doménu' : 'Pridať novú stránku', callback: 'project-modal-submit' } }));
             },
             replaceTree(data) {
                 this.projects = data.projects;
                 this.revision = data.revision;
                 this.projectIds = data.ids || this.projectIds;
                 this.domainIds = data.domain_ids || this.domainIds;
                 window.sidebarPageIds = data.page_ids || window.sidebarPageIds || {};
                 window.sidebarProjectsData = this.projects;
                 window.sidebarProjectsRevision = this.revision;
                 window.sidebarProjectIds = this.projectIds;
                 window.sidebarDomainIds = this.domainIds;
                 if (data.focusProject && data.focusProject !== this.selectedProject) {
                     this.selectedProject = data.focusProject; this.selectedDomain = null; this.selectedPage = null;
                     this.saveTreePosition();
                 }
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
                 if (this.selectedProject && projectData) this.openProject(projectData);

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

                 if (domainData) this.openDomain(projectData, domainData);
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
                         pageId: (window.sidebarPageIds || {})[page.id] || null,
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

             async deletePage(project, domain, page) {
                 if (this.saving) return;
                 const index = domain.pages.findIndex(item => item.id === page.id);
                 if (index < 0 || !confirm('Odstrániť stránku „' + page.name + '“ z projektu?')) return;
                 const pageId = (window.sidebarPageIds || {})[page.id] || null;
                 domain.pages.splice(index, 1);
                 if (!await this.saveToDb()) {
                     const currentDomain = this.projects.find(item => item.id === project.id)?.domains.find(item => item.id === domain.id);
                     if (currentDomain && !currentDomain.pages.some(item => item.id === page.id)) currentDomain.pages.splice(Math.min(index, currentDomain.pages.length), 0, page);
                     return;
                 }
                 if (this.selectedPage === page.id) {
                     this.selectedPage = null;
                     this.saveTreePosition();
                     window.dispatchEvent(new CustomEvent('page-selected', { detail: null }));
                 }
                 window.dispatchEvent(new CustomEvent('cms-page-deleted', { detail: { pageId, pageLegacyId: page.id, domainLegacyId: domain.id, projectLegacyId: project.id } }));
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

             async saveToDb() {
                 if (this.saving) { this.saveAgain = true; return false; }
                 this.saving = true;
                 this.projects = this.normalizeProjects(this.projects);
                 const submittedTree = JSON.stringify(this.projects);
                 try {
                     const response = await fetch('/api/ui/save-projects', {
                         method: 'POST',
                         headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                         body: JSON.stringify({ projects: this.projects, revision: this.revision })
                     });
                     const data = await response.json();
                     if (!response.ok || !data.success) throw new Error(data.message || 'Uloženie sa nepodarilo.');
                     if (this.saveAgain || JSON.stringify(this.projects) !== submittedTree) {
                         this.revision = data.revision;
                         this.projectIds = data.ids || this.projectIds;
                         this.domainIds = data.domain_ids || this.domainIds;
                         this.saveAgain = true;
                     } else {
                         this.replaceTree(data);
                         window.dispatchEvent(new CustomEvent('cms-project-overviews-refresh'));
                     }
                     return true;
                 } catch (error) {
                     this.saveAgain = false;
                     alert(error.message || 'Uloženie sa nepodarilo.');
                     return false;
                 } finally {
                     this.saving = false;
                     if (this.saveAgain) { this.saveAgain = false; this.saveToDb(); }
                 }
             }
         }"
         @project-modal-submit.window="handleInflow($event.detail.value)"
         @project-properties-save.window="saveToDb()"
         @cms-project-tree-updated.window="replaceTree($event.detail)"
         @project-editor-focused.window="if ($event.detail.projectId) { const legacy = Object.keys(projectIds).find(id => projectIds[id] === $event.detail.projectId); if (legacy) { selectedProject = legacy; selectedDomain = $event.detail.domainId ? Object.keys(domainIds[$event.detail.projectId] || {}).find(id => domainIds[$event.detail.projectId][id] === $event.detail.domainId) || null : null; selectedPage = null; saveTreePosition(); } }"
>
    <!-- Plusko plynule odpáli globálny vycentrovaný modal -->
    <x-ui.accordion-header section="projects" :title="$layout['blocks']['block_3']['title'] ?? 'Projekty'" action-label="Nový projekt" action="triggerModal()" />
    
    <div class="block-content sidebar-accordion-content" id="accordion-projects-content" role="region" aria-labelledby="accordion-projects-toggle" x-show="activeSection === 'projects'" x-cloak style="padding: 10px 15px; flex: 1; overflow-y: auto; color: #fff; font-size: 13px; user-select: none;">
        @if(in_array((int) auth()->id(), config('site-import.user_ids', []), true))
            <button type="button" class="btn wa-project-secondary mb-3" style="width:100%;font-size:12px" @click="$dispatch('add-new-tab', { title: 'Import z ASUS-u', mode: 'import' })">Načítať web z ASUS-u</button>
        @endif
        <template x-for="project in projects" :key="project.id">
            <div style="margin-bottom: 8px;">
                <!-- 1. ÚROVEŇ: PROJEKT -->
                <div @click="selectProject(project.id)" 
                     :style="{ color: selectedProject === project.id ? '#3bc0c3' : '#fff' }"
                     style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: bold; padding: 4px 0;">
                    <span x-text="selectedProject === project.id ? '▼' : '►'" style="font-size: 9px; width: 10px; flex-shrink: 0;"></span>
                    <span>📁</span>
                    <span class="wa-tree-name" :title="project.name" x-text="project.name"></span>
                    <span class="wa-tree-actions">
                        <button type="button" class="wa-tree-action" title="Pridať doménu" aria-label="Pridať doménu" @click.stop="triggerNodeModal('domain', project)">+</button>
                        <button type="button" class="wa-tree-action" title="Otvoriť editor projektu" aria-label="Otvoriť editor projektu" @click.stop="openProject(project)"><i class="fas fa-pen" aria-hidden="true"></i></button>
                        <span class="wa-tree-trash" title="Zmazat" aria-label="Zmazat">
                            <i class="fas fa-trash" style="font-size: 12px;" aria-hidden="true"></i>
                        </span>
                    </span>
                </div>

                <!-- 2. ÚROVEŇ: DOMÉNY -->
                <div x-show="selectedProject === project.id" style="padding-left: 18px; margin-top: 2px;">
                    <template x-for="domain in project.domains" :key="domain.id">
                        <div style="margin-bottom: 4px;">
                            <div @click="selectDomain(domain.id, $event)"
                                 :style="{ color: selectedDomain === domain.id ? '#3bc0c3' : '#8a94ad' }"
                                 style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; padding: 2px 0;">
                                <span x-text="selectedDomain === domain.id ? '▼' : '►'" style="font-size: 9px; width: 10px; flex-shrink: 0;"></span>
                                <span>🌐</span>
                                <span class="wa-tree-name" :title="domain.name" x-text="domain.name"></span>
                                <span class="wa-tree-actions">
                                    <button type="button" class="wa-tree-action" title="Pridať stránku" aria-label="Pridať stránku" @click.stop="triggerNodeModal('page', project, domain)">+</button>
                                    <button type="button" class="wa-tree-action" title="Otvoriť editor domény" aria-label="Otvoriť editor domény" @click.stop="openDomain(project, domain)"><i class="fas fa-pen" aria-hidden="true"></i></button>
                                    <span class="wa-tree-trash" title="Zmazat" aria-label="Zmazat">
                                        <i class="fas fa-trash" style="font-size: 12px;" aria-hidden="true"></i>
                                    </span>
                                </span>
                            </div>

                            <!-- 3. ÚROVEŇ: STRÁNKY -->
                            <div x-show="selectedDomain === domain.id" style="padding-left: 18px; margin-top: 2px; display: flex; flex-direction: column; gap: 4px;">
                                <template x-for="page in domain.pages" :key="page.id">
                                    <div class="wa-tree-page-row" :style="{ background: selectedPage === page.id ? '#3b82f6' : 'var(--wa-surface-raised)' }">
                                        <button type="button" class="wa-tree-page-open" :aria-label="'Otvoriť stránku ' + page.name" @click.stop="selectPage(page, domain, project, $event)">
                                            <span aria-hidden="true">📄</span>
                                            <span class="wa-tree-name" :title="page.name" x-text="page.name"></span>
                                        </button>
                                        <button type="button" class="wa-tree-trash wa-tree-page-delete" :title="'Odstrániť stránku ' + page.name" :aria-label="'Odstrániť stránku ' + page.name" :disabled="saving" @click.stop="deletePage(project, domain, page)">
                                            <i class="fas fa-trash" style="font-size: 12px;" aria-hidden="true"></i>
                                        </button>
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
