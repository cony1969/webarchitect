<div class="dropdown d-inline-block user-profile"
     style="font-family: 'Figtree', sans-serif;">
    
    <button type="button"
            id="topbar-profile-dropdown"
            class="btn header-item waves-effect dropdown-toggle d-inline-flex align-items-center gap-2"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            style="color: #d1d8df; font-size: 13px; font-weight: 500; background: transparent; border: none; box-shadow: none;">
        <span>Prihlásený: {{ Auth::user()->name ?? 'Jozef Králik' }}</span>
    </button>

    <ul class="dropdown-menu dropdown-menu-end"
        aria-labelledby="topbar-profile-dropdown"
        style="background: var(--wa-surface-base); border: 1px solid var(--wa-border); border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); min-width: 170px; padding: 4px 0;">
<li>
    <a href="{{ route('profile.edit') }}"
       data-route="{{ route('profile.edit') }}"
       class="dropdown-item"
       onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('add-new-tab', {
           detail: {
               title: 'Môj profil',
               type: 'profile',
               mode: 'profile',
               route: this.getAttribute('data-route')
           }
       }));"
       style="display: flex; align-items: center; gap: 10px; color: #a4b1cd; font-size: 13px;">
        <i class="fas fa-user-cog bg-transparent" style="width: 16px;"></i>
        <span>Môj profil</span>
    </a>
</li>
						
        <li><hr class="dropdown-divider" style="border-color: var(--wa-border);"></li>

        <li>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit"
                        class="dropdown-item"
                        style="display: flex; align-items: center; gap: 10px; color: #ff4d4d; font-size: 13px;">
                    <i class="fas fa-sign-out-alt bg-transparent" style="width: 16px;"></i>
                    <span>Odhlásiť sa</span>
                </button>
            </form>
        </li>
    </ul>
</div>
