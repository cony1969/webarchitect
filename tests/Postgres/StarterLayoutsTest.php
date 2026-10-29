<?php

use App\Models\Cms\Component;
use App\Models\Cms\ComponentEntry;
use App\Models\Cms\Section;
use App\Models\User;
use App\Services\Cms\BootstrapComponents;
use App\Services\Cms\ComponentLibrary;
use App\Services\Cms\DocumentData;
use App\Services\Cms\DocumentStore;
use App\Services\Cms\LayoutEditor;
use App\Services\Cms\PageComposition;
use App\Services\Cms\StarterLayouts;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
});

it('builds five real layouts from reusable owned components and renders their intended content', function () {
    $this->artisan('cms:install-layouts', ['--user' => $this->user->email])->assertSuccessful();
    $layouts = Section::where('user_id', $this->user->id)->where('kind', 'layout_template')->get()->keyBy('name');
    expect($layouts)->toHaveCount(5)->and(Component::where('user_id', $this->user->id)->count())->toBe(6);
    foreach (StarterLayouts::NAMES as $name) {
        $layout = $layouts[$name];
        expect($layout->document['nodes'][0]['type'])->toBe('cms-component');
        $rendered = app(PageComposition::class)->resolve($this->user, $layout->document, null);
        expect($rendered['components'])->not->toBeEmpty();
        foreach ($rendered['components'] as $component) {
            expect(Component::find($component['id'])->user_id)->toBe($this->user->id);
        }
        $serialized = json_encode($rendered, JSON_UNESCAPED_UNICODE);
        if ($name === 'table') {
            expect($serialized)->toContain('Prehľad záznamov', 'tbody', 'Stránkovanie', 'text-nowrap text-end');
        }
        if ($name === 'form') {
            expect($serialized)->toContain('textarea', 'E-mail', 'Nový záznam');
        }
        if (str_contains($name, 'sidebar')) {
            expect($serialized)->toContain('Bočná navigácia', 'col-12 col-sm-4 col-lg-3', 'col-12 col-sm-8 col-lg-9');
        }
        if (str_contains($name, 'topbar')) {
            expect($serialized)->toContain('Hlavná navigácia', 'Názov webu');
        }
    }
    $sidebar = app(PageComposition::class)->resolve($this->user, $layouts['sidebar']->document, null);
    $combined = app(PageComposition::class)->resolve($this->user, $layouts['topbar + sidebar']->document, null);
    $sidebarComponent = Component::where('user_id', $this->user->id)->where('slug', 'bootstrap-sidebar')->sole();
    expect(array_column($sidebar['components'], 'id'))->toContain($sidebarComponent->id);
    expect(array_column($combined['components'], 'id'))->toContain($sidebarComponent->id);
    $catalog = app(LayoutEditor::class)->catalog($this->user);
    expect(array_filter($catalog['components'], fn ($component) => $component['is_container']))->toHaveCount(1);
    expect(array_column($catalog['components'], 'name'))->toContain('Tabuľka (Table)', 'Hlavná lišta (Navbar)', 'Formulár (Form)');
});

it('preserves blank custom layouts and customized components and is safe to run again', function () {
    $store = app(DocumentStore::class);
    $document = DocumentData::fromLegacy(['type' => 'p', 'content' => 'Moja rozpracovaná práca']);
    $blank = app(LayoutEditor::class)->save($this->user, ['name' => 'blank', 'document' => $document]);
    $custom = app(LayoutEditor::class)->save($this->user, ['name' => 'table', 'document' => $document]);
    $container = app(BootstrapComponents::class)->install($this->user, 'container');
    $changed = $container->document;
    $changed['nodes'][0]['attributes']['class'] = 'container-fluid px-4';
    $store->update('components', $this->user, $container->id, ['revision' => 1, 'document' => $changed]);
    $this->artisan('cms:install-layouts', ['--user' => $this->user->email])->assertSuccessful();
    $snapshot = Section::where('user_id', $this->user->id)->orderBy('id')->get()->toArray();
    $revisions = DB::table('cms_document_revisions')->count();
    $this->artisan('cms:install-layouts', ['--user' => $this->user->email])->assertSuccessful();
    expect(Section::where('user_id', $this->user->id)->orderBy('id')->get()->toArray())->toEqual($snapshot);
    expect(DB::table('cms_document_revisions')->count())->toBe($revisions);
    expect($blank->fresh()->document)->toEqual($document)->and($custom->fresh()->document)->toEqual($document);
    expect($container->fresh()->revision)->toBe(2)->and($container->fresh()->document)->toEqual($changed);
    expect(Component::where('user_id', $this->user->id)->count())->toBe(6);
});

it('replaces the shared table catalog placeholder only for the selected account', function () {
    $placeholder = new Component(['user_id' => null, 'owner_key' => 'library', 'source_key' => 'catalog:bootstrap:bs-table', 'slug' => 'shared-table', 'name' => 'Tabuľka (Table)', 'kind' => 'component', 'scope_key' => '', 'settings' => ['tab' => 'bootstrap', 'placeholder' => true], 'document' => DocumentData::empty()]);
    app(DocumentStore::class)->save($placeholder, null);
    ComponentEntry::create(['user_id' => null, 'owner_key' => 'library', 'component_id' => $placeholder->id, 'entry_key' => 'table-entry', 'tab' => 'bootstrap', 'category' => 'Zobrazenie dát a Prvky', 'category_position' => 3, 'position' => 1, 'source_payload' => ['id' => 'bs-table', 'name' => 'Tabuľka (Table)']]);
    $this->artisan('cms:install-layouts', ['--user' => $this->user->email])->assertSuccessful();
    $catalog = app(ComponentLibrary::class)->catalog($this->user, true);
    $items = collect($catalog['bootstrap'])->flatMap(fn ($category) => $category['items'])->where('name', 'Tabuľka (Table)');
    expect($items)->toHaveCount(1)->and($items->first()['placeholder'])->toBeFalse();
    expect($placeholder->fresh()->settings['placeholder'])->toBeTrue();
    $otherCatalog = app(ComponentLibrary::class)->catalog($this->other, true);
    expect($otherCatalog['bootstrap'][0]['items'][0]['placeholder'])->toBeTrue();
    expect(Section::where('user_id', $this->other->id)->count())->toBe(0);
    $this->actingAs($this->other)->getJson('/api/cms/layouts')->assertOk()->assertJsonCount(0, 'data.components');
});

it('corrects the empty combined layout name and rejects a missing owner', function () {
    $layout = app(LayoutEditor::class)->save($this->user, ['name' => 'tobbar + sidebar', 'document' => DocumentData::empty()]);
    $this->artisan('cms:install-layouts', ['--user' => $this->user->email])->assertSuccessful();
    expect($layout->fresh()->name)->toBe('topbar + sidebar')->and($layout->fresh()->document['nodes'])->not->toBeEmpty();
    $this->artisan('cms:install-layouts', ['--user' => 'missing@example.test'])->assertFailed();
    expect(Section::where('user_id', $this->user->id)->where('kind', 'layout_template')->count())->toBe(5);
});
