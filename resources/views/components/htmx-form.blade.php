@props(['config', 'inputs' => []])

<form hx-post="{{ $config['action'] }}" 
      hx-target="{{ $config['target'] ?? '#form-container' }}" 
      hx-swap="{{ $config['swap'] ?? 'innerHTML' }}"
      hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'
      hx-on::before-swap="if(event.detail.xhr.status === 204) event.preventDefault();"
      hx-indicator="this">
    @csrf
    
    @foreach($inputs as $input)
        <x-input-group 
            :id="$input['id']"
            :name="$input['name'] ?? $input['id']"
            :type="$input['type'] ?? 'text'"
            :label="$input['label']"
            :value="$input['value'] ?? ''"
            :required="$input['required'] ?? false"
            :autofocus="$input['autofocus'] ?? false"
            :autocomplete="$input['autocomplete'] ?? null"
        />
    @endforeach

    {{ $slot }}
</form>
