<?php

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    expect(DB::connection()->getDatabaseName())->toBe('webarchitect_test');
    $this->user = User::factory()->create();
});

it('remembers property tabs in the authenticated user session and renders them on reload', function () {
    $key = 'wa.tabs.'.$this->user->id;
    $this->actingAs($this->user)->postJson('/api/ui/session-tab', ['panel' => 'component_properties', 'tab' => 'css'])
        ->assertOk()->assertSessionHas($key.'.component_properties', 'css');
    $this->postJson('/api/ui/session-tab', ['panel' => 'domain_properties', 'tab' => 'bootstrap'])
        ->assertOk()->assertSessionHas($key.'.domain_properties', 'bootstrap')->assertSessionHas($key.'.component_properties', 'css');
    $this->postJson('/api/ui/session-tab', ['panel' => 'appearance', 'tab' => 'other'])->assertOk()->assertSessionHas($key.'.appearance', 'other');
    $this->postJson('/api/ui/session-tab', ['panel' => 'component_view', 'tab' => 'json'])->assertOk()->assertSessionHas($key.'.component_view', 'json');
    $this->get('/dashboard')->assertOk()->assertSee('window.waSessionTabsConfig', false)->assertSee('component_properties', false)->assertSee('domain_properties', false);
});

it('rejects unrecognized panels invalid tab names and owner overrides', function ($payload) {
    $this->actingAs($this->user)->postJson('/api/ui/session-tab', $payload)->assertUnprocessable();
    expect(session('wa.tabs.'.$this->user->id))->toBeNull();
})->with([
    [['panel' => 'component_properties', 'tab' => 'wrong']],
    [['panel' => 'domain_properties', 'tab' => 'css']],
    [['panel' => 'appearance', 'tab' => 'bootstrap']],
    [['panel' => 'arbitrary.session.key', 'tab' => 'css']],
    [['panel' => ['component_properties'], 'tab' => 'css']],
    [['panel' => 'component_properties', 'tab' => 'css', 'user_id' => 99]],
]);

it('keeps different user selections separate and requires authentication', function () {
    $other = User::factory()->create();
    $this->postJson('/api/ui/session-tab', ['panel' => 'component_properties', 'tab' => 'css'])->assertUnauthorized();
    $this->actingAs($this->user)->postJson('/api/ui/session-tab', ['panel' => 'component_properties', 'tab' => 'css'])->assertOk();
    $this->actingAs($other)->postJson('/api/ui/session-tab', ['panel' => 'component_properties', 'tab' => 'bootstrap'])->assertOk();
    expect(session('wa.tabs.'.$this->user->id.'.component_properties'))->toBe('css');
    expect(session('wa.tabs.'.$other->id.'.component_properties'))->toBe('bootstrap');
});

it('retains database persistence for the left component tab tree selection and open center cards', function () {
    $this->actingAs($this->user)->postJson('/sidebar/save-setting', ['name' => 'sidebar_active_tab', 'value' => 'bootstrap'])->assertOk();
    $tree = ['selectedProject' => 'project-1', 'selectedDomain' => 'domain-1', 'selectedPage' => 'page-1'];
    $this->postJson('/sidebar/save-setting', ['name' => 'sidebar_projects_tree_position', 'value' => $tree])->assertOk();
    $tabs = [['id' => 'tab-layout', 'title' => 'Layout', 'mode' => 'layout'], ['id' => 'tab-component', 'title' => 'Component', 'mode' => 'component']];
    $this->postJson('/profile/save-tabs', ['tabs' => $tabs, 'activeTab' => 'tab-component'])->assertOk();
    expect(UserSetting::get('sidebar_active_tab'))->toBe('bootstrap');
    expect(json_decode(UserSetting::get('sidebar_projects_tree_position'), true))->toBe($tree);
    expect(json_decode(UserSetting::get('open_canvas_tabs'), true))->toBe(['tabs' => $tabs, 'activeTab' => 'tab-component']);
    $this->get('/dashboard')->assertOk()->assertSee('tab-component');
});
