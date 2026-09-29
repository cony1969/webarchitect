@props(['items' => []])
<nav class="top-nav">
    @foreach($items as $item)
        <x-ui.menu-item :item="$item" />
    @endforeach
</nav>
