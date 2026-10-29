<?php

namespace App\Services\Cms;

use App\Models\Cms\Component;
use App\Models\Cms\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LayoutEditor
{
    public function __construct(private SectionLibrary $library, private DocumentStore $store, private PageComposition $composition) {}

    public function catalog(User $user): array
    {
        $personal = DB::table('cms_collections')->where('user_id', $user->id)->where('name', 'layouts')->exists();
        $layouts = Section::where('owner_key', $personal ? 'user:'.$user->id : 'library')->where('kind', 'layout_template')->orderBy('position')->get();
        $components = Component::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))->whereNull('project_id')->orderBy('name')->get()
            ->filter(fn ($c) => ! ($c->settings['placeholder'] ?? false) && count($c->document['nodes'] ?? []) === 1 && $this->isContainer($c->document['nodes'][0]));

        return ['layouts' => $layouts->map(fn ($s) => $s->only(['id', 'legacy_id', 'name', 'revision']))->values()->all(),
            'revision' => $this->library->revision($user),
            'components' => $components->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'revision' => $c->revision, 'content_target' => 'root'])->values()->all()];
    }

    private function isContainer(mixed $node): bool
    {
        return is_array($node) && ($node['type'] ?? '') === 'div' && preg_match('/(?:^|\s)container(?:-(?:fluid|sm|md|lg|xl|xxl))?(?:\s|$)/', $node['attributes']['class'] ?? '');
    }

    public function find(User $user, string $id): Section
    {
        return $this->store->query('sections', $user)->where('kind', 'layout_template')->findOrFail($id);
    }

    public function data(User $user, Section $layout): array
    {
        return array_merge($layout->only(['id', 'legacy_id', 'name', 'revision', 'document']), ['shared' => $layout->user_id === null], $this->composition->resolve($user, $layout->document, null));
    }

    public function save(User $user, array $data, ?string $id = null): Section
    {
        return DB::transaction(function () use ($user, $data, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $original = $id ? $this->find($user, $id) : null;
            if ($original) {
                abort_unless($original->revision === $data['revision'], 409, 'Layout medzitým zmenil iný editor. Otvorte jeho aktuálnu verziu.');
            }
            $doc = DocumentData::validate($data['document']);
            $this->composition->resolve($user, $doc, null);
            // Preserve all visible library templates when creating the personal collection.
            if (! DB::table('cms_collections')->where('user_id', $user->id)->where('name', 'layouts')->exists()) {
                $this->library->replace($user, $this->library->read($user), $this->library->revision($user));
            }
            $layout = $original?->user_id === null && $original ? Section::where('user_id', $user->id)->where('kind', 'layout_template')->where('legacy_id', $original->legacy_id)->firstOrFail() : $original;
            if ($layout) {
                abort_unless($layout->revision === $data['revision'], 409, 'Vlastná verzia layoutu už bola upravená. Otvorte ju zo zoznamu layoutov.');
            }
            $name = trim($data['name']);
            if (Section::where('user_id', $user->id)->where('kind', 'layout_template')->whereRaw('lower(name) = lower(?)', [$name])->when($layout, fn ($q) => $q->whereKeyNot($layout->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'Layout s týmto názvom už existuje.']);
            }
            if ($layout) {
                return $this->store->update('sections', $user, $layout->id, ['name' => $name, 'document' => $doc, 'revision' => $data['revision']]);
            }
            $slug = Str::limit(Str::slug($name) ?: 'layout', 140, '');
            $base = $slug;
            $number = 2;
            while (Section::withTrashed()->where('user_id', $user->id)->where('scope_key', '')->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$number++;
            }
            $layout = new Section(['user_id' => $user->id, 'owner_key' => 'user:'.$user->id, 'legacy_id' => 'layout-'.Str::uuid(), 'name' => $name, 'slug' => $slug,
                'kind' => 'layout_template', 'scope_key' => '', 'document' => $doc, 'settings' => [], 'source_payload' => [],
                'position' => (int) Section::where('user_id', $user->id)->max('position') + 1]);
            $this->store->save($layout, $user->id);
            DB::table('cms_collections')->where('user_id', $user->id)->where('name', 'layouts')->increment('revision', 1, ['updated_at' => now()]);

            return $layout;
        });
    }
}
