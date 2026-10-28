<?php

namespace App\Http\Controllers\UI;

use App\Http\Controllers\Controller;
use App\Services\Cms\ComponentLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ComponentImportController extends Controller
{
    public function __construct(private ComponentLibrary $library) {}

    public function load(Request $request): JsonResponse
    {
        $data = $request->validate(['item_path' => ['nullable', 'string', 'max:400'], 'target_path' => ['nullable', 'string', 'max:300'], 'slug' => ['nullable', 'string', 'max:180']]);
        $path = $this->resolveRelativeFilePath($data['item_path'] ?? '', $data['target_path'] ?? '', $data['slug'] ?? '');
        abort_if($path === '', 422, 'Nie je určený komponent.');
        $component = $this->library->find($request->user(), $path);
        if (! $component || ($component->settings['placeholder'] ?? false)) {
            return response()->json(['success' => true, 'exists' => false, 'file' => 'config/ui/components/'.$path]);
        }

        return response()->json([
            'success' => true, 'exists' => true, 'component_id' => $component->id, 'revision' => $component->revision,
            'file' => 'config/ui/components/'.$path, 'name' => $component->name, 'slug' => $component->slug,
            'target_path' => trim(dirname($path), '/.'),
            'content' => json_encode($this->library->legacy($component), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['prohibited'], 'name' => ['required', 'string', 'max:120'], 'slug' => ['required', 'string', 'max:160'],
            'component_id' => ['nullable', 'uuid'], 'revision' => ['nullable', 'integer', 'min:1'],
            'html' => ['nullable', 'string', 'max:1000000'], 'json_content' => ['nullable', 'string', 'max:2000000'],
            'tab' => ['nullable', 'string', 'max:80'], 'category' => ['nullable', 'string', 'max:120'], 'target_path' => ['nullable', 'string', 'max:300'],
        ]);
        $slug = Str::slug($data['slug']);
        abort_if($slug === '', 422, 'Slug nie je platný.');
        if (trim($data['json_content'] ?? '') !== '') {
            $decoded = json_decode($data['json_content'], true);
            abort_unless(is_array($decoded), 422, 'JSON obsah nie je platný.');
            $objects = $decoded['objects'] ?? $decoded;
            $data['source_payload'] = $decoded;
            $data['document_css'] = is_string($decoded['css'] ?? null) ? $decoded['css'] : '';
            $data['document_js'] = is_string($decoded['js'] ?? null) ? $decoded['js'] : '';
        } else {
            $objects = $this->htmlToObjects($data['html'] ?? '');
            $data['source_payload'] = ['html_source' => $data['html'] ?? ''];
        }
        abort_unless(is_array($objects) && count($objects), 422, 'Obsah neobsahuje objekty komponentu.');
        $directory = $this->sanitizeRelativePath($data['target_path'] ?? '');
        $path = ($directory ? $directory.'/' : '').$slug.'.json';
        abort_if(strlen($path) > 400, 422, 'Umiestnenie komponentu je príliš dlhé.');
        $component = $this->library->saveImport($request->user(), $data, $objects, $path);

        return response()->json(['success' => true, 'component_id' => $component->id, 'revision' => $component->revision, 'slug' => $component->slug, 'file' => 'config/ui/components/'.$component->source_key]);
    }

    private function sanitizeRelativePath(string $path): string
    {
        $segments = preg_split('#[\\/]+#', $path) ?: [];
        $safe = [];

        foreach ($segments as $segment) {
            $slug = Str::slug($segment);
            if ($slug !== '') {
                $safe[] = $slug;
            }
        }

        return implode('/', $safe);
    }

    private function resolveRelativeFilePath(string $itemPath, string $targetPath, string $slug): string
    {
        $relativeFromItem = $this->sanitizeRelativeFilePath($itemPath);
        if ($relativeFromItem !== '') {
            return $relativeFromItem;
        }

        $safePath = $this->sanitizeRelativePath($targetPath);
        $safeSlug = Str::slug($slug);
        if ($safeSlug === '') {
            return '';
        }

        return ($safePath !== '' ? $safePath.'/' : '').$safeSlug.'.json';
    }

    private function sanitizeRelativeFilePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '') {
            return '';
        }

        $segments = preg_split('#/+#', $normalized) ?: [];
        $safe = [];
        foreach ($segments as $segment) {
            $namePart = preg_replace('/\.json$/i', '', $segment);
            $slug = Str::slug((string) $namePart);
            if ($slug !== '') {
                $safe[] = $slug;
            }
        }

        if (empty($safe)) {
            return '';
        }

        return implode('/', $safe).'.json';
    }

    private function htmlToObjects(string $html): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        $wrappedHtml = '<div id="__import_root__">'.$html.'</div>';
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementById('__import_root__');
        if (! $root) {
            return [];
        }

        $result = [];
        foreach ($root->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                $result[] = $this->elementToObject($node);
            }
        }

        return $result;
    }

    private function elementToObject(\DOMElement $element): array
    {
        $object = [
            'type' => strtolower($element->tagName),
        ];

        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attribute) {
                if (in_array($attribute->name, ['type', 'content', 'attributes'])) {
                    $object['attributes'][$attribute->name] = $attribute->value;
                } else {
                    $object[$attribute->name] = $attribute->value;
                }
            }
        }

        $contentItems = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $contentItems[] = $this->elementToObject($child);

                continue;
            }

            if ($child instanceof \DOMText) {
                $text = trim($child->wholeText);
                if ($text !== '') {
                    $contentItems[] = $text;
                }
            }
        }

        if (! empty($contentItems)) {
            if (count($contentItems) === 1 && is_string($contentItems[0])) {
                $object['content'] = $contentItems[0];
            } else {
                $object['content'] = $contentItems;
            }
        }

        return $object;
    }
}
