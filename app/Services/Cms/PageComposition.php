<?php

namespace App\Services\Cms;

use App\Models\Cms\Component;
use App\Models\Cms\Page;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PageComposition
{
    public function component(User $user, string $id, ?string $projectId): Component
    {
        abort_unless(Str::isUuid($id), 422, 'Neplatný odkaz na komponent.');

        return Component::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
            ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $projectId))->findOrFail($id);
    }

    public function resolve(User $user, array $document, ?string $projectId, bool $forEditor = false): array
    {
        $css = [];
        $js = [];
        $used = [];
        $count = 0;
        $bytes = 0;
        $expand = function (array $doc, array $stack = [], int $depth = 0, ?string $slotId = null, array $slotContent = []) use (&$expand, &$css, &$js, &$used, &$count, &$bytes, $user, $projectId, $forEditor) {
            DocumentData::validate($doc);
            foreach (['css', 'js'] as $field) {
                $hash = hash('sha256', $doc[$field]);
                if (! isset(${$field}[$hash])) {
                    $bytes += strlen($doc[$field]);
                    if ($bytes > 2_000_000) {
                        throw ValidationException::withMessages(['document' => 'Stránka prekračuje limit štýlov a skriptov.']);
                    }
                    ${$field}[$hash] = $doc[$field];
                }
            }
            $walk = function ($node, int $level) use (&$walk, &$expand, &$used, &$count, $user, $projectId, $stack, $slotId, $slotContent, $forEditor) {
                if (++$count > 5000 || $level > 48) {
                    throw ValidationException::withMessages(['document' => 'Stránka prekračuje limit obsahu alebo vnorenia.']);
                }
                if (is_string($node)) {
                    return [$node];
                }
                if ($node['type'] === 'cms-component') {
                    $id = $node['attributes']['component_id'] ?? '';
                    if (in_array($id, $stack, true)) {
                        throw ValidationException::withMessages(['document' => 'Komponenty obsahujú kruhový odkaz.']);
                    }
                    $component = $this->component($user, $id, $projectId);
                    abort_if($component->settings['placeholder'] ?? false, 422, 'Komponent zatiaľ nemá definíciu.');
                    $used[$id] = ['id' => $id, 'name' => $component->name, 'revision' => $component->revision, 'project_id' => $component->project_id];

                    $template = $component->document;
                    $target = $node['attributes']['content_target'] ?? null;
                    $targetId = $target === 'root' && count($template['nodes']) === 1 ? ($template['nodes'][0]['id'] ?? null) : null;
                    $fullWidth = $node['attributes']['full_width'] ?? false;
                    $fullHeight = $node['attributes']['full_height'] ?? false;
                    if (! is_bool($fullWidth) || ! is_bool($fullHeight) || ($target !== null && $target !== 'root')) {
                        throw ValidationException::withMessages(['document' => 'Neplatné nastavenie inštancie komponentu.']);
                    }
                    $targetFound = false;
                    $prepare = function (array &$nodes) use (&$prepare, $targetId, $fullWidth, $fullHeight, &$targetFound) {
                        foreach ($nodes as &$item) {
                            if (! is_array($item)) {
                                continue;
                            }
                            if ($item['id'] === $targetId) {
                                // Explicit insertion target avoids guessing with multi-root definitions.
                                if (! in_array($item['type'], ['div', 'section', 'main', 'header', 'footer', 'nav', 'article', 'aside'])) {
                                    throw ValidationException::withMessages(['document' => 'Tento prvok nemôže prijímať vnorený obsah.']);
                                }
                                $targetFound = true;
                                if ($fullHeight) {
                                    // Height belongs to this instance; disabling restores the definition unchanged.
                                    $class = $item['attributes']['class'] ?? '';
                                    $class = preg_replace('/(?<!\S)(?:h-(?:25|50|75|100|auto)|vh-100)(?!\S)/', '', $class);
                                    $item['attributes']['class'] = trim(preg_replace('/\s+/', ' ', $class).' h-100 wa-fill-height');
                                }
                                if ($fullWidth) {
                                    $class = $item['attributes']['class'] ?? '';
                                    if (! preg_match('/(?:^|\s)container(?:-(?:fluid|sm|md|lg|xl|xxl))?(?:\s|$)/', $class)) {
                                        throw ValidationException::withMessages(['document' => 'Celú šírku možno nastaviť len kontajneru.']);
                                    }
                                    $item['attributes']['class'] = trim(preg_replace('/(?<!\S)container(?:-(?:fluid|sm|md|lg|xl|xxl))?(?!\S)/', 'container-fluid', $class));
                                }
                            }
                            $prepare($item['children']);
                        }
                    };
                    $prepare($template['nodes']);
                    if (($target !== null || $node['children'] || $fullWidth || $fullHeight) && ! $targetFound) {
                        throw ValidationException::withMessages(['document' => 'Cieľ vnoreného obsahu už v komponente neexistuje. Vyberte aktuálny kontajner.']);
                    }
                    $content = [];
                    // Sibling uses of the same definition can be nested; only definition dependencies form cycles.
                    foreach ($node['children'] as $child) {
                        array_push($content, ...$walk($child, $level + 1));
                    }

                    $expanded = $expand($template, [...$stack, $id], $level + 1, $targetId, $content);
                    // Preview-only identity refers to this placement, never to the shared definition.
                    if ($forEditor && count($expanded) === 1 && is_array($expanded[0])) {
                        $expanded[0]['attributes']['data-wa-layout-node'] = $node['id'];
                        $expanded[0]['attributes']['data-wa-layout-label'] = $component->name;
                    }

                    return $expanded;
                }
                $originalId = $node['id'];
                $node['id'] = (string) Str::uuid();
                $children = [];
                foreach ($node['children'] as $child) {
                    array_push($children, ...$walk($child, $level + 1));
                }
                if ($originalId === $slotId) {
                    array_push($children, ...$slotContent);
                }
                $node['children'] = $children;
                if (array_any($children, fn ($child) => is_array($child) && preg_match('/(?:^|\s)wa-fill-height(?:\s|$)/', $child['attributes']['class'] ?? ''))) {
                    $node['attributes']['class'] = trim(($node['attributes']['class'] ?? '').' wa-height-parent');
                }

                return [$node];
            };
            $nodes = [];
            foreach ($doc['nodes'] as $node) {
                array_push($nodes, ...$walk($node, $depth));
            }

            return $nodes;
        };
        $nodes = $expand($document);
        $heightCss = '.wa-height-parent{display:flex!important;flex-direction:column!important}.wa-height-parent>.wa-fill-height{flex:1 1 0!important;height:auto!important;min-height:0}';
        $hasHeight = function (array $nodes) use (&$hasHeight): bool {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                if (preg_match('/(?:^|\s)wa-fill-height(?:\s|$)/', $node['attributes']['class'] ?? '') || $hasHeight($node['children'])) {
                    return true;
                }
            }

            return false;
        };
        if ($hasHeight($nodes)) {
            $css[hash('sha256', $heightCss)] = $heightCss;
        }
        $resolved = DocumentData::validate(['schema_version' => 1, 'nodes' => $nodes, 'css' => implode("\n", array_unique($css)), 'js' => implode("\n", array_unique($js))]);

        return ['preview' => ['objects' => DocumentData::toLegacy($resolved), 'css' => $resolved['css'], 'js' => $resolved['js']], 'components' => array_values($used)];
    }

    public function page(User $user, Page $page): array
    {
        $page->load('project', 'domain');

        $composition = $this->resolve($user, $page->document, $page->project_id);
        $palette = app(BootstrapPalette::class)->css($page->domain?->settings['bootstrap']['colors'] ?? []);
        if ($palette !== '') {
            $composition['preview']['css'] = $palette."\n".$composition['preview']['css'];
        }

        return array_merge([
            'id' => $page->id, 'legacy_id' => $page->legacy_id, 'name' => $page->name, 'slug' => $page->slug,
            'revision' => $page->revision, 'project_id' => $page->project_id, 'project_name' => $page->project?->name,
            'project_legacy_id' => $page->project?->legacy_id, 'domain_legacy_id' => $page->domain?->legacy_id,
            'domain_id' => $page->domain_id, 'domain_name' => $page->domain?->name,
            'placements_count' => count(array_filter($page->document['nodes'], fn ($node) => is_array($node) && $node['type'] === 'cms-component')),
        ], $composition);
    }
}
