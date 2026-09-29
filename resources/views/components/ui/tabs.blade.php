@props([
    'tabs' => [],
    'activeVar' => 'activeTab',
    'changeHandler' => 'changeTab',
    'scrollable' => false,
    'scrollRef' => null,
    'wheelScroll' => false,
    'dataAttrName' => null,
    'itemMinWidth' => null,
])

<style>
    .ui-tabs-scroll::-webkit-scrollbar {
        display: none;
    }
</style>

@if($scrollable)
    <div
        @if($scrollRef) x-ref="{{ $scrollRef }}" @endif
        @if($wheelScroll) @wheel.prevent="(() => { const primaryDelta = Math.abs($event.deltaX) > Math.abs($event.deltaY) ? $event.deltaX : $event.deltaY; $el.scrollLeft += primaryDelta; })()" @endif
        class="ui-tabs-scroll"
        style="overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none; margin-bottom: 12px;"
    >
@endif

<ul class="nav" style="border-bottom: 2px solid #4e566d; margin-bottom: {{ $scrollable ? '0' : '15px' }}; display: flex; flex-wrap: {{ $scrollable ? 'nowrap' : 'wrap' }}; padding-left: 0; list-style: none; width: {{ $scrollable ? 'max-content' : '100%' }}; min-width: {{ $scrollable ? '100%' : '0' }}; gap: 5px;">
    @foreach($tabs as $tab)
        @php
            $tabId = $tab['id'] ?? '';
            $tabTitle = $tab['title'] ?? $tabId;
        @endphp
        <li class="nav-item" style="{{ $itemMinWidth ? 'flex: 0 0 auto; min-width: ' . $itemMinWidth . ';' : 'flex: 1;' }} text-align: center; margin-bottom: -2px;">
            <button class="nav-link"
                    type="button"
                    @if($dataAttrName) {{ $dataAttrName }}="{{ $tabId }}" @endif
                    @click.prevent="if (typeof {{ $changeHandler }} === 'function') { {{ $changeHandler }}('{{ $tabId }}') } else { {{ $activeVar }} = '{{ $tabId }}' }"
                    :style="{
                        color: {{ $activeVar }} === '{{ $tabId }}' ? '#fff' : '#8a94ad',
                        borderBottom: {{ $activeVar }} === '{{ $tabId }}' ? '2px solid #3bc0c3' : '2px solid transparent',
                        fontWeight: {{ $activeVar }} === '{{ $tabId }}' ? '600' : '400'
                    }"
                    style="background: transparent; border: none; font-size: 12px; padding: 8px 0; cursor: pointer; width: 100%; text-align: center; border-radius: 0; transition: all 0.2s ease;">
                {{ $tabTitle }}
            </button>
        </li>
    @endforeach
</ul>

@if($scrollable)
    </div>
@endif
