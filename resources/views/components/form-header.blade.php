@props(['title', 'subtitle' => null])
<div style="text-align: center; margin-bottom: 30px;">
    <h2 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">{{ $title }}</h2>
    @if($subtitle)
        <p style="color: #7885a0; margin: 5px 0 0 0; font-size: 14px;">{{ $subtitle }}</p>
    @endif
</div>
