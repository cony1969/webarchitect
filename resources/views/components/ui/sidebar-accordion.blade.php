@props(['id', 'initial', 'sections' => []])

<div class="sidebar-accordion"
     x-data="{
         activeSection: @js($initial),
         storageKey: @js('wa.sidebar.' . $id . '.section'),
         allowedSections: @js($sections),
         init() {
             try {
                 const saved = window.localStorage.getItem(this.storageKey);
                 if (saved === '' || this.allowedSections.includes(saved)) {
                     this.activeSection = saved || null;
                 }
             } catch (e) {}
             this.$nextTick(() => {
                 if (@js($id) === 'right' && (this.$store.projectEditor.active || this.$store.componentEditor.current || this.$store.pagePreview.current)) this.openSection('properties');
             });
         },
         rememberSection() {
             try { window.localStorage.setItem(this.storageKey, this.activeSection || ''); } catch (e) {}
         },
         openSection(section) {
             if (!this.allowedSections.includes(section)) return;
             this.activeSection = section;
             this.rememberSection();
         },
         toggleSection(section) {
             this.activeSection = this.activeSection === section ? null : section;
             this.rememberSection();
         }
     }"
     @project-editor-focused.window="if (@js($id) === 'right') openSection('properties')"
     @sidebar-accordion-reset.window="openSection(@js($initial))">
    {{ $slot }}
</div>
