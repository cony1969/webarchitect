<?php

use App\Models\Cms\Domain;
use App\Models\User;
use App\Services\Cms\BootstrapPalette;
use App\Services\Cms\DocumentStore;
use App\Services\Cms\DomainStore;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = app(DocumentStore::class)->create('projects', $this->user, ['name' => 'Palette project']);
    $this->actingAs($this->user);
});

it('normalizes and saves a domain palette without changing another domain', function () {
    $other = app(DomainStore::class)->save($this->user, ['name' => 'other.example.com', 'project_id' => $this->project->id]);
    $palette = ['bootstrap' => ['colors' => ['primary' => '#AbC', 'danger' => '#123456']]];
    $data = $this->postJson('/api/cms/domains', ['name' => 'web.example.com', 'project_id' => $this->project->id, 'settings' => $palette])->assertCreated()->assertJsonPath('data.settings.bootstrap.colors.primary', '#aabbcc')->assertJsonPath('data.settings.bootstrap.colors.danger', '#123456')->assertJsonPath('data.settings.bootstrap.colors.success', '#198754')->json('data');
    $this->getJson('/api/cms/domains/'.$data['id'])->assertOk()->assertJsonPath('data.settings.bootstrap.colors.primary', '#aabbcc');
    $this->postJson('/api/cms/domains/'.$data['id'].'/copy', ['name' => 'copy.example.com'])->assertCreated()->assertJsonPath('data.settings.bootstrap.colors.primary', '#aabbcc');
    expect($other->fresh()->settings['bootstrap']['colors']['primary'])->toBe('#0d6efd');
    $this->putJson('/api/cms/domains/'.$data['id'], ['name' => 'web.example.com', 'revision' => 1, 'settings' => ['bootstrap' => ['colors' => ['primary' => '#008080']]]])->assertOk()->assertJsonPath('data.revision', 2);
    $this->putJson('/api/cms/domains/'.$data['id'], ['name' => 'web.example.com', 'revision' => 1, 'settings' => $palette])->assertConflict();
});

it('rejects invalid palettes without saving a domain', function ($settings) {
    $this->postJson('/api/cms/domains', ['name' => 'invalid.example.com', 'project_id' => $this->project->id, 'settings' => $settings])->assertUnprocessable();
    expect(Domain::count())->toBe(0);
})->with([
    [['bootstrap' => 'invalid']],
    [['bootstrap' => ['colors' => ['unknown' => '#abcdef']]]],
    [['bootstrap' => ['colors' => ['primary' => '#123456;display:none']]]],
    [['bootstrap' => ['colors' => ['primary' => 'red']]]],
    [['bootstrap' => ['colors' => ['primary' => null]]]],
]);

it('uses only the page domain palette in its isolated preview', function () {
    $domain = app(DomainStore::class)->save($this->user, ['name' => 'themed.example.com', 'project_id' => $this->project->id, 'settings' => ['bootstrap' => ['colors' => ['primary' => '#008080']]]]);
    $page = app(DocumentStore::class)->create('pages', $this->user, ['name' => 'Home', 'slug' => 'home', 'domain_id' => $domain->id]);
    $this->getJson('/api/cms/page-composition/'.$page->id)->assertOk()->assertJson(fn ($json) => $json->where('data.domain_id', $domain->id)->where('data.preview.css', fn ($css) => str_contains($css, '.bg-primary{background-color:#008080!important}') && str_contains($css, '.btn-primary'))->etc());
    $this->actingAs(User::factory()->create())->getJson('/api/cms/page-composition/'.$page->id)->assertNotFound();
});

it('keeps the default stylesheet untouched and refuses invalid CSS color injection', function () {
    $palette = app(BootstrapPalette::class);
    expect($palette->css([]))->toBe('')->and($palette->css(['primary' => '#123456;bad']))->toBe('');
    expect($palette->css(['primary' => '#008080']))->toContain('--bs-primary:#008080', 'color:#ffffff', '.btn-outline-primary');
});
