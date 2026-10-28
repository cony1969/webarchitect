@props(['section', 'title' => '', 'dynamicTitle' => null, 'action' => '', 'actionLabel' => ''])

<div class="sidebar-accordion-header">
    <h2 class="sidebar-accordion-heading">
        <button type="button"
                id="accordion-{{ $section }}-toggle"
                class="sidebar-accordion-toggle"
                aria-controls="accordion-{{ $section }}-content"
                :aria-expanded="activeSection === '{{ $section }}'"
                @click="toggleSection('{{ $section }}')">
            <svg class="sidebar-accordion-chevron" width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M5.5 3.5 10 8l-4.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span class="sidebar-accordion-title" @if($dynamicTitle) x-text="{{ $dynamicTitle }}" @endif>{{ $title }}</span>
        </button>
    </h2>
    @if($action)
        <button type="button" class="sidebar-accordion-action" aria-label="{{ $actionLabel }}" title="{{ $actionLabel }}"
                @click.stop="openSection('{{ $section }}'); {{ $action }}">+</button>
    @endif
</div>
