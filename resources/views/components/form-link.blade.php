@props(['href', 'center' => false, 'muted' => false])
<a href="{{ $href }}" 
   style="text-decoration: none; font-size: 13px; 
          {{ $center ? 'display: block; text-align: center; margin-top: 20px;' : '' }} 
          {{ $muted ? 'color: #4e566d;' : 'color: #3b82f6;' }}"
   @if($muted) onmouseover="this.style.color='#a4b1cd'" onmouseout="this.style.color='#4e566d'" @endif>
    {{ $slot }}
</a>
