@props(['group', 'title'])
<div class="wa-box-layer wa-box-{{ $group }}">
    <span class="wa-box-label">{{ $title }}</span>
    @foreach (['top'=>'hore', 'right'=>'vpravo', 'bottom'=>'dole', 'left'=>'vľavo'] as $side => $label)
        <input type="text" class="wa-box-edge wa-box-{{ $side }}" aria-label="{{ $group }} {{ $label }}" :value="value('{{ $group }}', '{{ $side }}')" placeholder="—" autocomplete="off" spellcheck="false"
            @change.stop="changeEdge('{{ $group }}', '{{ $side }}', $event.target.value)"
            @keydown.enter.prevent="$event.target.blur()" @keydown.escape.stop="$event.target.value = value('{{ $group }}', '{{ $side }}'); $event.target.blur()"
            :title="'{{ $group }} {{ $label }}: ' + (value('{{ $group }}', '{{ $side }}') || 'nenastavené')">
    @endforeach
    {{ $slot }}
</div>
