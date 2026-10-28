@props(['layout' => []])

<section class="sidebar-block" :class="{ 'is-open': activeSection === 'appearance' }"  x-data="{ rightTab: 'templates' }">
    <x-ui.accordion-header section="appearance" title="Vzhľad" action-label="Možnosti vzhľadu" action="alert('Možnosti vzhľadu')" />
    
    <div class="block-content sidebar-accordion-content" id="accordion-appearance-content" role="region" aria-labelledby="accordion-appearance-toggle" x-show="activeSection === 'appearance'" x-cloak style="padding: 0 15px; flex: 1; display: flex; flex-direction: column; overflow: hidden;">
        <ul class="nav" style="border-bottom: 2px solid var(--wa-border-strong); margin-bottom: 15px; display: flex; padding-left: 0; list-style: none; width: 100%; flex: 0 0 auto;">
            <li class="nav-item" style="flex: 1; text-align: center; margin-bottom: -2px;">
                <button class="nav-link" 
                        @click.prevent="rightTab = 'templates'"
                        :style="{ 
                            color: rightTab === 'templates' ? '#fff' : '#8a94ad',
                            backgroundColor: 'transparent',
                            border: 'none',
                            borderBottom: rightTab === 'templates' ? '2px solid #3bc0c3' : '2px solid transparent',
                            fontSize: '12px',
                            padding: '8px 0',
                            fontWeight: rightTab === 'templates' ? '600' : '400',
                            cursor: 'pointer',
                            width: '100%'
                        }">
                    Templates
                </button>
            </li>
            <li class="nav-item" style="flex: 1; text-align: center; margin-bottom: -2px;">
                <button class="nav-link" 
                        @click.prevent="rightTab = 'other'"
                        :style="{ 
                            color: rightTab === 'other' ? '#fff' : '#8a94ad',
                            backgroundColor: 'transparent',
                            border: 'none',
                            borderBottom: rightTab === 'other' ? '2px solid #3bc0c3' : '2px solid transparent',
                            fontSize: '12px',
                            padding: '8px 0',
                            fontWeight: rightTab === 'other' ? '600' : '400',
                            cursor: 'pointer',
                            width: '100%'
                        }">
                    Iné
                </button>
            </li>
        </ul>

        <div class="tab-content" style="flex: 1; overflow-y: auto; color: #fff;">
            <div x-show="rightTab === 'templates'" x-transition>
                [ Obsah pre šablóny (Templates) ]
            </div>
            <div x-show="rightTab === 'other'" x-transition>
                [ Obsah pre Iné nastavenia vzhľadu ]
            </div>
        </div>
    </div>
</section>
