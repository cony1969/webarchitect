<section class="wa-box-editor" x-data="waBoxModelEditor()" aria-label="Grafický editor rozmerov a odsadenia">
    <h3>Box model</h3>
    <div class="wa-box-diagram">
        <x-ui.component-editor.box-layer group="margin" title="margin">
            <x-ui.component-editor.box-layer group="border" title="border">
                <x-ui.component-editor.box-layer group="padding" title="padding">
                    <div class="wa-box-content">obsah</div>
                </x-ui.component-editor.box-layer>
            </x-ui.component-editor.box-layer>
        </x-ui.component-editor.box-layer>
    </div>
    <div class="wa-box-links"><span>Spojiť strany:</span><label><input type="checkbox" x-model="linked.margin"> margin</label><label><input type="checkbox" x-model="linked.padding"> padding</label><label><input type="checkbox" x-model="linked.border"> border</label></div>
    <div class="wa-box-border-options">
        <label class="wa-project-field">Okraj<select aria-label="Strana okraja" x-model="borderSide"><option value="all">Všetky strany</option><option value="top">Horný</option><option value="right">Pravý</option><option value="bottom">Dolný</option><option value="left">Ľavý</option></select></label>
        <label class="wa-project-field">Štýl<select aria-label="Štýl okraja" :value="borderValue('style')" @change="changeBorder('style', $event.target.value)"><option value="">Nenastavené / rôzne</option>@foreach(['none','solid','dashed','dotted','double','groove','ridge','inset','outset','hidden'] as $style)<option value="{{ $style }}">{{ $style }}</option>@endforeach</select></label>
        <div class="wa-project-field"><span>Farba</span><span x-data="waColorPicker({get: () => borderValue('color'), set: value => changeBorder('color', value), disabled: () => disabled, showValue: false, label: 'Farba okraja'})"><button type="button" class="wa-color-trigger" x-ref="color" aria-label="Farba okraja"><span aria-hidden="true">▾</span></button></span></div>
    </div>
    <p class="wa-color-error" role="alert" x-show="error" x-text="error"></p>
    <p class="wa-project-muted">Číslo bez jednotky = px. — = nenastavené CSS. Zmeny vytvárajú vlastnosti nižšie; vymazanie odstráni konkrétnu vlastnosť. Triedy Bootstrap ostávajú zachované.</p>
</section>
