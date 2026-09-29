<?php

namespace App\Http\Controllers\UI;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ComponentImportController extends Controller
{
    public function load(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_path' => ['nullable', 'string', 'max:260'],
            'target_path' => ['nullable', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:160'],
        ]);

        $relativeFilePath = $this->resolveRelativeFilePath(
            (string) ($validated['item_path'] ?? ''),
            (string) ($validated['target_path'] ?? ''),
            (string) ($validated['slug'] ?? '')
        );

        if ($relativeFilePath === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nie je urcena cesta ku komponentu.',
            ], 422);
        }

        $fullPath = base_path('config/ui/components/' . $relativeFilePath);
        if (!is_file($fullPath)) {
            return response()->json([
                'success' => true,
                'exists' => false,
                'file' => 'config/ui/components/' . $relativeFilePath,
            ]);
        }

        $raw = (string) file_get_contents($fullPath);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return response()->json([
                'success' => false,
                'message' => 'JSON komponentu je neplatny.',
            ], 422);
        }

        $safeEncoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->json([
            'success' => true,
            'exists' => true,
            'file' => 'config/ui/components/' . $relativeFilePath,
            'content' => $safeEncoded !== false ? $safeEncoded : $raw,
            'name' => (string) ($decoded['name'] ?? ''),
            'slug' => (string) ($decoded['slug'] ?? pathinfo($relativeFilePath, PATHINFO_FILENAME)),
            'target_path' => trim(str_replace('\\', '/', dirname($relativeFilePath)), '/.'),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:160'],
            'html' => ['nullable', 'string'],
            'json_content' => ['nullable', 'string'],
            'tab' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:120'],
            'target_path' => ['nullable', 'string', 'max:200'],
        ]);

        $slug = Str::slug($validated['slug']);
        if ($slug === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nepodarilo sa vygenerovat platny slug.',
            ], 422);
        }

        $objects = [];
        $jsonContent = trim((string) ($validated['json_content'] ?? ''));
        $html = trim((string) ($validated['html'] ?? ''));

        if ($jsonContent !== '') {
            $decodedContent = json_decode($jsonContent, true);
            if (!is_array($decodedContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'JSON obsah nie je validny.',
                ], 422);
            }

            $objectsSource = $decodedContent['objects'] ?? $decodedContent;
            if (is_array($objectsSource) && array_is_list($objectsSource)) {
                $objects = $objectsSource;
            } elseif (is_array($objectsSource)) {
                $objects = [$objectsSource];
            }

            if (empty($objects)) {
                return response()->json([
                    'success' => false,
                    'message' => 'JSON neobsahuje ziadne validne objekty.',
                ], 422);
            }
        } else {
            $objects = $this->htmlToObjects($html);
            if (empty($objects)) {
                return response()->json([
                    'success' => false,
                    'message' => 'HTML neobsahuje ziadne validne elementy na import.',
                ], 422);
            }
        }

        $baseDir = base_path('config/ui/components');
        $relativePath = $this->sanitizeRelativePath((string) ($validated['target_path'] ?? ''));
        $targetDir = $relativePath !== ''
            ? $baseDir . DIRECTORY_SEPARATOR . $relativePath
            : $baseDir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return response()->json([
                'success' => false,
                'message' => 'Nepodarilo sa vytvorit cielovy priecinok.',
            ], 500);
        }

        $payload = [
            'name' => $validated['name'],
            'slug' => $slug,
            'tab' => $validated['tab'] ?? null,
            'objects' => count($objects) === 1 ? $objects[0] : $objects,
        ];

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $slug . '.json';

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return response()->json([
                'success' => false,
                'message' => 'Nepodarilo sa serializovat JSON.',
            ], 500);
        }

        if (file_put_contents($filePath, $encoded) === false) {
            return response()->json([
                'success' => false,
                'message' => 'Nepodarilo sa zapisat subor.',
            ], 500);
        }

        $treePath = ($relativePath !== '' ? $relativePath . '/' : '') . $slug . '.json';
        $this->upsertTreeItem(
            (string) ($validated['tab'] ?? 'other'),
            (string) ($validated['category'] ?? ''),
            (string) $validated['name'],
            $slug,
            $treePath
        );

        return response()->json([
            'success' => true,
            'slug' => $slug,
            'file' => 'config/ui/components/' . $treePath,
        ]);
    }

    private function upsertTreeItem(string $tab, string $category, string $name, string $slug, string $relativeFilePath): void
    {
        $tabKey = Str::slug($tab);
        if ($tabKey === '') {
            $tabKey = 'other';
        }

        $treeFile = base_path('config/ui/content/components.json');
        $tree = [];

        if (is_file($treeFile)) {
            $decoded = json_decode((string) file_get_contents($treeFile), true);
            if (is_array($decoded)) {
                $tree = $decoded;
            }
        }

        if (!isset($tree[$tabKey]) || !is_array($tree[$tabKey])) {
            $tree[$tabKey] = [];
        }

        $categoryName = trim($category);
        if ($categoryName === '' && !empty($tree[$tabKey])) {
            $first = $tree[$tabKey][0] ?? null;
            if (is_array($first) && !empty($first['category'])) {
                $categoryName = (string) $first['category'];
            }
        }
        if ($categoryName === '') {
            $categoryName = 'Menu';
        }

        $categoryIndex = null;
        foreach ($tree[$tabKey] as $index => $group) {
            if (is_array($group) && (($group['category'] ?? null) === $categoryName)) {
                $categoryIndex = $index;
                break;
            }
        }

        if ($categoryIndex === null) {
            if (!empty($tree[$tabKey]) && isset($tree[$tabKey][0]) && is_array($tree[$tabKey][0])) {
                $categoryIndex = 0;
                $categoryName = (string) ($tree[$tabKey][0]['category'] ?? $categoryName);
            } else {
                $tree[$tabKey][] = [
                    'category' => $categoryName,
                    'items' => [],
                ];
                $categoryIndex = array_key_last($tree[$tabKey]);
            }
        }

        if (!isset($tree[$tabKey][$categoryIndex]['items']) || !is_array($tree[$tabKey][$categoryIndex]['items'])) {
            $tree[$tabKey][$categoryIndex]['items'] = [];
        }

        $itemId = 'import-' . $slug;
        $newItem = [
            'id' => $itemId,
            'name' => $name,
            'path' => $relativeFilePath,
        ];

        $updated = false;
        foreach ($tree[$tabKey][$categoryIndex]['items'] as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }

            $sameId = (($item['id'] ?? '') === $itemId);
            $sameSlugName = Str::slug((string) ($item['name'] ?? '')) === $slug;

            if ($sameId || $sameSlugName) {
                $tree[$tabKey][$categoryIndex]['items'][$idx] = array_merge($item, $newItem);
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $tree[$tabKey][$categoryIndex]['items'][] = $newItem;
        }

        $encodedTree = json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedTree !== false) {
            @file_put_contents($treeFile, $encodedTree);
        }
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

        return ($safePath !== '' ? $safePath . '/' : '') . $safeSlug . '.json';
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

        return implode('/', $safe) . '.json';
    }

    private function htmlToObjects(string $html): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        $wrappedHtml = '<div id="__import_root__">' . $html . '</div>';
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $dom->getElementById('__import_root__');
        if (!$root) {
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
                $object[$attribute->name] = $attribute->value;
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

        if (!empty($contentItems)) {
            if (count($contentItems) === 1 && is_string($contentItems[0])) {
                $object['content'] = $contentItems[0];
            } else {
                $object['content'] = $contentItems;
            }
        }

        return $object;
    }
}
