@props(['layout' => [], 'components' => [], 'savedTab' => 'native'])

@php $b1 = $layout['blocks']['block_1'] ?? []; @endphp

<section class="sidebar-block" :class="{ 'is-open': activeSection === 'components' }"

         x-data="{ 
             activeTab: '{{ $savedTab }}',
             changeTab(tabId) {
                 this.activeTab = tabId;
                 fetch('/sidebar/save-setting', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': '{{ csrf_token() }}'
                     },
                     body: JSON.stringify({
                         name: 'sidebar_active_tab',
                         value: tabId
                     })
                 });
             }
         }"

>
    <x-ui.accordion-header section="components" :title="$b1['title'] ?? 'Komponenty'" action-label="Pridať komponent" action="window.dispatchEvent(new CustomEvent('open-modal-import', { detail: { tab: activeTab, ...(window.sidebarComponentsImportContext || {}) } }))" />
    <div class="block-content sidebar-accordion-content" id="accordion-components-content" role="region" aria-labelledby="accordion-components-toggle" x-show="activeSection === 'components'" x-cloak style="padding: 0 15px; flex: 1; overflow-y: auto;">
        @if(!empty($b1['tabs']))
            <x-ui.tabs :tabs="$b1['tabs']" active-var="activeTab" change-handler="changeTab" />
        @endif
				{{-- @dd($components) --}}
        @if(!empty($b1['component']))
            <x-dynamic-component :component="$b1['component']" :components="$components" />
        @endif
    </div>

    <x-ui.sidebar-left.modal-import />
</section>
