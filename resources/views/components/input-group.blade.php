@props(['id', 'name', 'type' => 'text', 'label', 'value' => '', 'required' => false, 'autocomplete' => null, 'autofocus' => false])
<div style="margin-bottom: 20px;">
    <label for="{{ $id }}" style="color: #a4b1cd; font-size: 13px; margin-bottom: 8px; display: block; font-weight: 500;">{{ $label }}</label>
    <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" {{ $required ? 'required' : '' }} {{ $autofocus ? 'autofocus' : '' }} autocomplete="{{ $autocomplete }}" 
           style="width: 100%; padding: 12px; background: #151922; border: 1px solid #2d3446; border-radius: 6px; color: #ffffff; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; outline: none;"
           onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#2d3446'" />
    @error($name)
        <span style="color: #ff4d4d; font-size: 12px; margin-top: 6px; display: block;">{{ $message }}</span>
    @enderror
</div>
