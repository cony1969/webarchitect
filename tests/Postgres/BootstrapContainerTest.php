<?php

use App\Models\Cms\Component;
use App\Models\Cms\ComponentEntry;
use App\Models\User;
use App\Services\Cms\ComponentLibrary;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('installs a real empty Bootstrap container and a catalog entry for the selected owner', function () {
    $this->artisan('cms:install-bootstrap', ['component' => 'container', '--user' => $this->user->email])->assertSuccessful();
    $component = Component::where('user_id', $this->user->id)->sole();
    expect($component->document['nodes'][0])->toMatchArray(['type' => 'div', 'attributes' => ['class' => 'container'], 'children' => []]);
    expect($component->document['css'])->toBe('')->and($component->document['js'])->toBe('')->and($component->revision)->toBe(1)->and($component->settings['placeholder'])->toBeFalse();
    $entry = ComponentEntry::where('component_id', $component->id)->sole();
    expect($entry->user_id)->toBe($this->user->id)->and($entry->tab)->toBe('bootstrap')->and($entry->category)->toBe('Základné rozloženie')->and($entry->category_position)->toBe(0);
    expect(app(ComponentLibrary::class)->catalog($this->user)['bootstrap'][0]['items'][0]['name'])->toBe('Kontajner (Container)');
    expect(DB::table('cms_document_revisions')->where('document_id', $component->id)->count())->toBe(1);
});

it('loads and saves the installed container through the existing component editor', function () {
    $this->artisan('cms:install-bootstrap', ['--user' => $this->user->email])->assertSuccessful();
    $component = Component::where('user_id', $this->user->id)->sole();
    $this->actingAs($this->user)->postJson('/ui/components/load', ['item_path' => $component->source_key])->assertOk()->assertJsonPath('exists', true)->assertJsonPath('component_id', $component->id);
    $source = app(ComponentLibrary::class)->legacy($component);
    $source['objects']['class'] = 'container-fluid';
    $this->postJson('/ui/components/import-html', [
        'name' => $component->name, 'slug' => $component->slug, 'tab' => 'bootstrap', 'category' => 'Základné rozloženie',
        'component_id' => $component->id, 'revision' => 1, 'target_path' => dirname($component->source_key),
        'json_content' => json_encode($source),
    ])->assertOk()->assertJsonPath('component_id', $component->id)->assertJsonPath('revision', 2);
    expect($component->fresh()->document['nodes'][0]['attributes']['class'])->toBe('container-fluid');
    $this->artisan('cms:install-bootstrap', ['--user' => $this->user->email])->assertSuccessful();
    expect($component->fresh()->revision)->toBe(2)->and($component->fresh()->document['nodes'][0]['attributes']['class'])->toBe('container-fluid')->and(Component::count())->toBe(1)->and(ComponentEntry::count())->toBe(1);
});

it('does not duplicate a component or its revision on repeated installation', function () {
    $this->artisan('cms:install-bootstrap', ['--user' => $this->user->email])->assertSuccessful();
    $first = Component::where('user_id', $this->user->id)->sole();
    $this->artisan('cms:install-bootstrap', ['--user' => $this->user->email])->assertSuccessful();
    expect(Component::count())->toBe(1)->and(ComponentEntry::count())->toBe(1)->and($first->fresh()->revision)->toBe(1)->and(DB::table('cms_document_revisions')->where('document_id', $first->id)->count())->toBe(1);
});

it('keeps personal library content isolated from other users', function () {
    $this->artisan('cms:install-bootstrap', ['--user' => $this->user->email])->assertSuccessful();
    $other = User::factory()->create();
    $component = Component::where('user_id', $this->user->id)->sole();
    expect(app(ComponentLibrary::class)->catalog($other)['bootstrap'])->toBe([]);
    $this->actingAs($other)->postJson('/ui/components/load', ['item_path' => $component->source_key])->assertOk()->assertJsonPath('exists', false);
});

it('rejects unknown definitions and missing users without creating content', function () {
    $this->artisan('cms:install-bootstrap', ['component' => '../container', '--user' => $this->user->email])->assertFailed();
    $this->artisan('cms:install-bootstrap', ['--user' => 'missing@example.test'])->assertFailed();
    expect(Component::count())->toBe(0)->and(ComponentEntry::count())->toBe(0);
});
