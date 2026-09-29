@props([
    'label', 
    'readonly' => false, 
    'type' => 'text', 
    'value' => null, 
    'model' => null,
    'groupClass' => 'prop-form-group'
])

<div class="{{ $groupClass }} mb-2 w-100">
    <label class="form-group-label">
        {{ $label }}
    </label>
    
    @if($type === 'select')
        <select class="form-group-field" {{ $attributes }}>
            {{ $slot }}
        </select>
    @elseif($type === 'textarea')
        <textarea class="form-group-field" style="height: 50px; resize: none;" {{ $attributes }}></textarea>
    @else
        <input type="{{ $type }}" 
               class="form-group-field {{ $readonly ? 'field-readonly' : '' }}"
               @if($readonly) :value="{{ $value }}" readonly @endif
               @if($model) x-model="{{ $model }}" @endif
               @if(!$readonly && !$model && $value) value="{{ $value }}" @endif
               {{ $attributes }} />
    @endif
</div>
