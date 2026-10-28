<?php

use App\Models\Cms\Component;
use App\Models\User;
use App\Services\Cms\ComponentLibrary;
use App\Services\Cms\ComponentPropertyCatalog;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->artisan('cms:install-bootstrap', ['--user' => $this->user->email])->assertSuccessful();
    $this->component = Component::where('user_id', $this->user->id)->sole();
    $this->source = app(ComponentLibrary::class)->legacy($this->component);
    $this->payload = ['component_id' => $this->component->id, 'revision' => 1, 'as_new' => false, 'name' => $this->component->name, 'slug' => $this->component->slug, 'target_path' => dirname($this->component->source_key), 'tab' => 'bootstrap', 'category' => 'Základné rozloženie', 'json_content' => json_encode($this->source), 'style_root' => 0];
    $this->actingAs($this->user);
});

it('saves CSS values while omitting every unnamed row', function () {
    $this->postJson('/ui/components/editor', array_replace($this->payload, ['style_rows' => [['name' => '', 'value' => 'red'], ['name' => ' ', 'value' => 'not saved'], ['name' => 'padding', 'value' => '20px'], ['name' => '--brand', 'value' => '#008080']]]))->assertOk();
    expect($this->component->fresh()->document['nodes'][0]['attributes']['style'])->toBe('padding: 20px; --brand: #008080;');
});

it('targets the selected root and retains existing Bootstrap classes external CSS and metadata', function () {
    $this->source['objects'] = [['type' => 'div', 'class' => 'first', 'style' => 'color: red;'], ['type' => 'section', 'attributes' => ['class' => 'container p-md-3 custom', 'style' => 'margin: 1px;']]];
    $this->source['css'] = '.custom { display: block; }';
    $this->postJson('/ui/components/editor', array_replace($this->payload, ['json_content' => json_encode($this->source), 'style_root' => 1, 'style_rows' => [['name' => 'background-color', 'value' => 'teal']]]))->assertOk();
    $saved = $this->component->fresh();
    expect($saved->document['nodes'][0]['attributes']['style'])->toBe('color: red;')->and($saved->document['nodes'][1]['attributes']['style'])->toBe('background-color: teal;')->and($saved->document['nodes'][1]['attributes']['class'])->toBe('container p-md-3 custom')->and($saved->document['css'])->toBe('.custom { display: block; }')->and($saved->source_payload['framework'])->toBe('bootstrap-5');
});

it('removes the style attribute when all rows have been cleared or are unnamed', function () {
    $this->source['objects']['style'] = 'color: red;';
    $this->postJson('/ui/components/editor', array_replace($this->payload, ['json_content' => json_encode($this->source), 'style_rows' => [['name' => '', 'value' => 'blue']]]))->assertOk();
    expect($this->component->fresh()->document['nodes'][0]['attributes'])->not->toHaveKey('style');
});

it('accepts quoted CSS values data URLs and important declarations', function () {
    $this->postJson('/ui/components/editor', array_replace($this->payload, ['style_rows' => [['name' => 'background-image', 'value' => 'url("data:image/svg+xml;a;b")'], ['name' => '--caption', 'value' => '"a;b:c"'], ['name' => 'color', 'value' => 'red !important']]]))->assertOk();
    expect($this->component->fresh()->document['nodes'][0]['attributes']['style'])->toContain('url("data:image/svg+xml;a;b")')->toContain('--caption: "a;b:c";')->toContain('color: red !important;');
});

it('rejects invalid property names values and targets without writing changes', function () {
    foreach ([['style_rows' => [['name' => 'background color', 'value' => 'red']]], ['style_rows' => [['name' => 'color', 'value' => 'red; display:none']]], ['style_rows' => [['name' => 'padding', 'value' => 'calc(20px']]], ['style_root' => 5, 'style_rows' => []]] as $data) {
        $this->postJson('/ui/components/editor', array_replace($this->payload, $data))->assertUnprocessable();
    }
    expect($this->component->fresh()->revision)->toBe(1);
});

it('requires a root even when the submitted rows array is empty', function () {
    $data = array_replace($this->payload, ['style_rows' => []]);
    unset($data['style_root']);
    $this->postJson('/ui/components/editor', $data)->assertUnprocessable()->assertJsonValidationErrors('style_root');
});

it('offers utility choices available in the installed Bootstrap CSS and renders all property tabs', function () {
    $config = app(ComponentPropertyCatalog::class)->data();
    expect($config['cssProperties'])->toHaveCount(671)->toContain('background-color', 'container-type', '-webkit-text-fill-color')->not->toContain('--*');
    expect($config['classes'])->toContain('container', 'container-fluid', 'p-md-3', 'd-md-flex')->not->toContain('opacity-50');
    foreach ($config['fields'] as $field) {
        foreach ($field['options'] as $values) {
            expect(array_diff($values, $config['classes']))->toBe([]);
        }
    }
    $this->get('/dashboard')->assertOk()->assertSee('component-controls.js')->assertSee('Základné nastavenia')->assertSee('Pridať CSS riadok')->assertSee('Zobraziť všetky nastavenia')->assertSee('waCssProperty(row)', false)->assertSee('Filter Select2:');
});


it('persists box model longhands alongside shorthands and Bootstrap classes', function () {
    $this->source['objects']['class'] = 'container p-3';
    $rows = [['name'=>'padding','value'=>'10px 20px'], ['name'=>'padding-top','value'=>'24px !important'], ['name'=>'border-left-width','value'=>'2px'], ['name'=>'border-left-style','value'=>'solid'], ['name'=>'border-left-color','value'=>'#008080']];
    $this->postJson('/ui/components/editor', array_replace($this->payload, ['json_content'=>json_encode($this->source), 'style_rows'=>$rows]))->assertOk();
    $saved = $this->component->fresh()->document['nodes'][0]['attributes'];
    expect($saved['class'])->toBe('container p-3')->and($saved['style'])->toBe('padding: 10px 20px; padding-top: 24px !important; border-left-width: 2px; border-left-style: solid; border-left-color: #008080;');
    $this->get('/dashboard')->assertOk()->assertSee('js/box-model.js', false)->assertSee('Box model')->assertSee('margin hore')->assertSee('padding vľavo')->assertSee('Farba okraja');
});

it('persists icon alignment choices without changing Bootstrap classes or other styles', function () {
    $this->source['objects']['class'] = 'container text-md-start';
    $rows = [['name'=>'padding-top','value'=>'16px'], ['name'=>'text-align','value'=>'center !important']];
    $this->postJson('/ui/components/editor', array_replace($this->payload, ['json_content'=>json_encode($this->source), 'style_rows'=>$rows]))->assertOk();
    $saved = $this->component->fresh()->document['nodes'][0]['attributes'];
    expect($saved['class'])->toBe('container text-md-start')->and($saved['style'])->toBe('padding-top: 16px; text-align: center !important;');
    $this->get('/dashboard')->assertOk()->assertSee('js/alignment-controls.js', false)->assertSee('Grafické zarovnanie textu')->assertSee('Zarovnanie textu: Na stred')->assertSee('fa-align-justify');
});
