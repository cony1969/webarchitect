<section class="wa-alignment-editor" x-data="waAlignmentEditor()" aria-label="Grafické zarovnanie textu">
    <div class="wa-alignment-heading"><h3>Zarovnanie textu</h3><code>text-align</code></div>
    <div class="wa-alignment-switch" role="radiogroup" aria-label="Zarovnanie textu" x-ref="switch" @keydown="navigate($event)">
        @foreach ([['','Nenastavené','ban'],['left','Vľavo','align-left'],['center','Na stred','align-center'],['right','Vpravo','align-right'],['justify','Do bloku','align-justify']] as $index => [$value, $label, $icon])
            <button type="button" role="radio" aria-label="Zarovnanie textu: {{ $label }}" title="{{ $label }}{{ $value ? ' · text-align: ' . $value : ' · odstrániť text-align' }}"
                :aria-checked="selected === '{{ $value }}'" :tabindex="tabIndex('{{ $value }}', {{ $index }})" :disabled="disabled" @click="choose('{{ $value }}')">
                <i class="fas fa-{{ $icon }}" aria-hidden="true"></i>
            </button>
        @endforeach
    </div>
    <p class="wa-alignment-current" x-text="options.find(option => option.value === selected)?.label || selected"></p>
</section>
