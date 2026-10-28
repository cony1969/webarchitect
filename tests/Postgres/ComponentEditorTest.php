<?php

use App\Models\Cms\Component;
use App\Models\Cms\ComponentEntry;
use App\Models\User;
use App\Services\Cms\ComponentLibrary;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->artisan('cms:install-bootstrap', ['--user' => $this->user->email])->assertSuccessful();
    $this->component = Component::where('user_id', $this->user->id)->sole();
    $this->payload = [
        'component_id' => $this->component->id, 'revision' => 1, 'as_new' => false,
        'name' => $this->component->name, 'slug' => $this->component->slug,
        'target_path' => dirname($this->component->source_key), 'tab' => 'bootstrap', 'category' => 'Základné rozloženie',
        'json_content' => json_encode(app(ComponentLibrary::class)->legacy($this->component)),
    ];
});

it('loads component properties and restores the existing definition for a central editor', function () {
    $this->actingAs($this->user)->postJson('/ui/components/load', ['item_path' => $this->component->source_key])->assertOk()
        ->assertJsonPath('data.id', $this->component->id)->assertJsonPath('data.category', 'Základné rozloženie')
        ->assertJsonPath('data.tab', 'bootstrap')->assertJsonPath('data.shared', false);
    $this->getJson('/ui/components/catalog')->assertOk()->assertJsonPath('catalog.bootstrap.0.items.0.name', $this->component->name);
    $this->get('/dashboard')->assertOk()->assertSee('component-editor.js')->assertSee('Otvoriť JSON v Ace')->assertSee('sandbox=""', false);
});

it('updates the real document and library name while retaining metadata', function () {
    $source = json_decode($this->payload['json_content'], true);
    $source['objects']['class'] = 'container-fluid';
    $this->actingAs($this->user)->postJson('/ui/components/editor', array_replace($this->payload, ['name' => 'Upravený kontajner', 'json_content' => json_encode($source)]))
        ->assertOk()->assertJsonPath('data.id', $this->component->id)->assertJsonPath('data.revision', 2)->assertJsonPath('catalog.bootstrap.0.items.0.name', 'Upravený kontajner');
    $saved = $this->component->fresh();
    expect($saved->name)->toBe('Upravený kontajner')->and($saved->document['nodes'][0]['attributes']['class'])->toBe('container-fluid')
        ->and($saved->source_payload['framework'])->toBe('bootstrap-5')->and(ComponentEntry::count())->toBe(1);
});

it('saves a new editable component from the current draft without changing the original', function () {
    $source = json_decode($this->payload['json_content'], true);
    $source['objects']['class'] = 'container-fluid';
    $this->actingAs($this->user)->postJson('/ui/components/editor', array_replace($this->payload, ['as_new' => true, 'name' => 'Široký kontajner', 'slug' => 'siroky-kontajner', 'json_content' => json_encode($source)]))
        ->assertOk()->assertJsonPath('data.slug', 'siroky-kontajner')->assertJsonPath('data.revision', 1);
    expect(Component::count())->toBe(2)->and(ComponentEntry::count())->toBe(2)->and($this->component->fresh()->revision)->toBe(1)
        ->and($this->component->fresh()->document['nodes'][0]['attributes']['class'])->toBe('container');
    $copy = Component::where('slug', 'siroky-kontajner')->sole();
    expect($copy->source_payload['framework'])->toBe('bootstrap-5')->and($copy->document['nodes'][0]['attributes']['class'])->toBe('container-fluid');
});

it('rejects stale revisions and duplicate copy slugs without replacing existing data', function () {
    $this->actingAs($this->user)->postJson('/ui/components/editor', array_replace($this->payload, ['revision' => 7]))->assertConflict();
    $this->postJson('/ui/components/editor', array_replace($this->payload, ['as_new' => true]))->assertUnprocessable()->assertJsonValidationErrors('slug');
    expect(Component::count())->toBe(1)->and($this->component->fresh()->revision)->toBe(1)->and(DB::table('cms_document_revisions')->count())->toBe(1);
});

it('rejects invalid JSON and invalid object structures without writing a revision', function () {
    $this->actingAs($this->user);
    foreach (['{broken', '{"objects":[]}', '{"objects":{"class":"container"}}', '{"objects":{"type":"div"},"css":[]}'] as $json) {
        $this->postJson('/ui/components/editor', array_replace($this->payload, ['json_content' => $json]))->assertUnprocessable();
    }
    expect($this->component->fresh()->revision)->toBe(1)->and(DB::table('cms_document_revisions')->count())->toBe(1);
});

it('scopes load save and copy to the authenticated owner', function () {
    $other = User::factory()->create();
    $this->actingAs($other)->postJson('/ui/components/load', ['item_path' => 'cms/'.$this->component->id.'.json'])->assertOk()->assertJsonPath('exists', false);
    $this->postJson('/ui/components/editor', $this->payload)->assertNotFound();
    $this->postJson('/ui/components/editor', array_replace($this->payload, ['as_new' => true, 'slug' => 'copy']))->assertNotFound();
    $this->getJson('/ui/components/catalog')->assertOk()->assertJsonPath('catalog.bootstrap', []);
});

it('requires copying a shared definition and preserves the shared original', function () {
    $this->component->update(['user_id' => null, 'owner_key' => 'shared']);
    $this->actingAs($this->user)->postJson('/ui/components/editor', $this->payload)->assertForbidden();
    $this->postJson('/ui/components/editor', array_replace($this->payload, ['as_new' => true, 'name' => 'Moja kópia', 'slug' => 'moja-kopia']))->assertOk()->assertJsonPath('data.shared', false);
    expect($this->component->fresh()->revision)->toBe(1)->and(Component::where('user_id', $this->user->id)->count())->toBe(1);
});
