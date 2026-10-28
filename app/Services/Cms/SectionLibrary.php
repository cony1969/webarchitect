<?php

namespace App\Services\Cms;

use App\Models\Cms\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SectionLibrary
{
    public function __construct(private DocumentStore $store) {}

    public function revision(User $user): int
    {
        return (int) (DB::table('cms_collections')->where('user_id', $user->id)->where('name', 'layouts')->value('revision') ?? 1);
    }

    public function read(User $user): array
    {
        $personal = DB::table('cms_collections')->where('user_id', $user->id)->where('name', 'layouts')->exists();

        return Section::where('owner_key', $personal ? 'user:'.$user->id : 'library')->where('kind', 'layout_template')->orderBy('position')->get()->map(fn ($s) => array_merge($s->source_payload ?? [], ['id' => $s->legacy_id, 'name' => $s->name]))->all();
    }

    public function replace(User $user, array $layouts, int $revision): int
    {
        return DB::transaction(function () use ($user, $layouts, $revision) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->revision($user) === $revision, 409, 'Layouty medzitým zmenilo iné okno. Obnovte stránku pred uložením.');
            $ids = [];
            foreach ($layouts as $position => $data) {
                $section = Section::withTrashed()->firstOrNew(['owner_key' => 'user:'.$user->id, 'legacy_id' => $data['id']]);
                $section->fill(['user_id' => $user->id, 'name' => $data['name'], 'slug' => $section->exists && $section->name === $data['name'] ? $section->slug : (Str::slug($data['name']) ?: 'layout'), 'kind' => 'layout_template', 'position' => $position, 'source_payload' => $data, 'scope_key' => '', 'deleted_at' => null]);
                $template = $section->exists ? null : Section::where('owner_key', 'library')->where('legacy_id', $data['id'])->first();
                $section->document ??= $template?->document ?? DocumentData::empty();
                $section->settings ??= $template?->settings ?? [];
                $this->store->save($section, $user->id);
                $ids[] = $section->id;
            }
            Section::where('user_id', $user->id)->where('kind', 'layout_template')->whereNotIn('id', $ids)->delete();
            DB::table('cms_collections')->updateOrInsert(['user_id' => $user->id, 'name' => 'layouts'], ['revision' => $revision + 1, 'created_at' => now(), 'updated_at' => now()]);

            return $revision + 1;
        });
    }
}
