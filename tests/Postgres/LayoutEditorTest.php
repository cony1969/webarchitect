<?php

use App\Models\Cms\Component;
use App\Models\Cms\Section;
use App\Models\User;
use App\Services\Cms\DocumentData;
use App\Services\Cms\DocumentStore;
use App\Services\Cms\PageComposition;
use App\Services\Cms\SectionLibrary;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
    $this->store = app(DocumentStore::class);
    $this->artisan('cms:install-bootstrap', ['--user' => $this->user->email])->assertSuccessful();
    $this->container = Component::where('user_id', $this->user->id)->sole();
    $this->placement = fn ($children = [], $full = false) => ['id' => (string) Str::uuid(), 'type' => 'cms-component', 'attributes' => ['component_id' => $this->container->id, 'content_target' => 'root', 'full_width' => $full], 'children' => $children];
    $this->actingAs($this->user);
});

it('saves and reopens nested instances without changing the library container', function () {
    $text = ['id' => (string) Str::uuid(), 'type' => 'p', 'attributes' => [], 'children' => ['Obsah layoutu']];
    $document = DocumentData::empty();
    $document['nodes'][] = ($this->placement)([($this->placement)([$text])], true);
    $response = $this->postJson('/api/cms/layouts', ['name' => 'Základ webu', 'document' => $document])->assertOk()
        ->assertJsonPath('data.preview.objects.class', 'container-fluid')->assertJsonPath('data.preview.objects.content.0.class', 'container')
        ->assertJsonPath('data.preview.objects.content.0.content.0.content', 'Obsah layoutu');
    $id = $response->json('data.id');
    $this->getJson('/api/cms/layouts/'.$id)->assertOk()->assertJsonPath('data.document', fn ($stored) => $stored == $document)->assertJsonPath('data.revision', 1);
    expect(Section::find($id)->kind)->toBe('layout_template')->and($this->container->fresh()->revision)->toBe(1)
        ->and($this->container->fresh()->document['nodes'][0]['attributes']['class'])->toBe('container');
    $this->putJson('/api/cms/layouts/'.$id, ['name' => 'Nový názov', 'document' => $document, 'revision' => 1])->assertOk()->assertJsonPath('data.revision', 2);
    $this->putJson('/api/cms/layouts/'.$id, ['name' => 'Stará karta', 'document' => $document, 'revision' => 1])->assertConflict();
    expect(Section::find($id)->name)->toBe('Nový názov');
});

it('keeps root content attached when a definition is saved with regenerated node ids', function () {
    $document = DocumentData::empty();
    $document['nodes'][] = ($this->placement)(['Vlastný obsah']);
    $id = $this->postJson('/api/cms/layouts', ['name' => 'Stabilný obsah', 'document' => $document])->assertOk()->json('data.id');
    $changed = $this->container->document;
    $changed['nodes'][0]['id'] = (string) Str::uuid();
    $changed['nodes'][0]['attributes']['class'] = 'container py-4';
    $this->store->update('components', $this->user, $this->container->id, ['revision' => 1, 'document' => $changed]);
    $this->getJson('/api/cms/layouts/'.$id)->assertOk()->assertJsonPath('data.preview.objects.class', 'container py-4')->assertJsonPath('data.preview.objects.content', 'Vlastný obsah');
});

it('creates a personal layout from a shared template and preserves the other templates', function () {
    foreach (['Prázdny', 'Ďalší'] as $position => $name) {
        $section = new Section(['user_id' => null, 'owner_key' => 'library', 'legacy_id' => 'layout-'.$position, 'name' => $name, 'slug' => 'layout-'.$position,
            'kind' => 'layout_template', 'scope_key' => '', 'position' => $position, 'settings' => [], 'source_payload' => [], 'document' => DocumentData::empty()]);
        $this->store->save($section, null);
    }
    $shared = Section::whereNull('user_id')->orderBy('position')->first();
    $response = $this->putJson('/api/cms/layouts/'.$shared->id, ['name' => 'Moja predloha', 'document' => DocumentData::empty(), 'revision' => 1])->assertOk()->assertJsonPath('data.shared', false);
    expect($response->json('data.id'))->not->toBe($shared->id);
    expect($shared->fresh()->name)->toBe('Prázdny')->and($shared->fresh()->revision)->toBe(1);
    expect(app(SectionLibrary::class)->read($this->user))->toHaveCount(2);
    $this->getJson('/api/cms/layouts')->assertOk()->assertJsonCount(2, 'data.layouts')->assertJsonPath('data.layouts.0.name', 'Moja predloha');
    $this->putJson('/api/cms/layouts/'.$shared->id, ['name' => 'Stará predloha', 'document' => DocumentData::empty(), 'revision' => 1])->assertConflict();
});

it('rejects foreign layouts and components without writing anything', function () {
    $document = DocumentData::empty();
    $document['nodes'][] = ($this->placement)();
    $id = $this->postJson('/api/cms/layouts', ['name' => 'Súkromný', 'document' => $document])->assertOk()->json('data.id');
    $this->actingAs($this->other)->getJson('/api/cms/layouts/'.$id)->assertNotFound();
    $this->putJson('/api/cms/layouts/'.$id, ['name' => 'Cudzí', 'document' => DocumentData::empty(), 'revision' => 1])->assertNotFound();
    $this->postJson('/api/cms/layouts', ['name' => 'Cudzí kontajner', 'document' => $document])->assertNotFound();
    $this->getJson('/api/cms/layouts')->assertOk()->assertJsonCount(0, 'data.components');
    expect(Section::where('user_id', $this->other->id)->count())->toBe(0);
});

it('validates slots and dependency cycles while allowing nested uses of the same container', function () {
    $document = DocumentData::empty();
    $document['nodes'][] = ($this->placement)([($this->placement)(['Text'])]);
    $this->postJson('/api/cms/layouts/preview', ['document' => $document])->assertOk()->assertJsonPath('data.preview.objects.content.0.content', 'Text');
    unset($document['nodes'][0]['attributes']['content_target']);
    $this->postJson('/api/cms/layouts', ['name' => 'Neplatný slot', 'document' => $document])->assertUnprocessable();
    $cycle = DocumentData::empty();
    $cycle['nodes'][] = ($this->placement)();
    $this->container->update(['document' => $cycle]);
    $this->postJson('/api/cms/layouts/preview', ['document' => $cycle])->assertUnprocessable();
    expect(Section::where('user_id', $this->user->id)->count())->toBe(0);
});

it('deduplicates component styles across instances and keeps contents separate', function () {
    $source = $this->container->document;
    $source['css'] = '.container { color: teal; }';
    $this->container->update(['document' => $source]);
    $document = DocumentData::empty();
    $document['nodes'] = [($this->placement)(['Prvý']), ($this->placement)(['Druhý'])];
    $result = $this->postJson('/api/cms/layouts/preview', ['document' => $document])->assertOk()->assertJsonPath('data.preview.objects.0.content', 'Prvý')->assertJsonPath('data.preview.objects.1.content', 'Druhý');
    expect(substr_count($result->json('data.preview.css'), '.container { color: teal; }'))->toBe(1);
});

it('maps repeated and nested preview containers to placement ids without saving editor markers', function () {
    $inner = ($this->placement)();
    $outer = ($this->placement)([$inner]);
    $second = ($this->placement)();
    $document = DocumentData::empty();
    $document['nodes'] = [$outer, $second];
    $result = $this->postJson('/api/cms/layouts', ['name' => 'Výber inštancií', 'document' => $document])->assertOk()
        ->assertJsonPath('data.preview.objects.0.data-wa-layout-node', $outer['id'])
        ->assertJsonPath('data.preview.objects.0.content.0.data-wa-layout-node', $inner['id'])
        ->assertJsonPath('data.preview.objects.1.data-wa-layout-node', $second['id']);
    expect(Section::find($result->json('data.id'))->document)->toEqual($document);
    $rendered = app(PageComposition::class)->resolve($this->user, $document, null);
    expect(json_encode($rendered))->not->toContain('data-wa-layout-node');
});
