<?php

namespace App\Services\Cms;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocumentData
{
    public static function empty(): array
    {
        return ['schema_version' => 1, 'nodes' => [], 'css' => '', 'js' => ''];
    }

    public static function fromLegacy(array $objects): array
    {
        $objects = array_is_list($objects) ? $objects : [$objects];
        $map = function ($value) use (&$map) {
            if (is_string($value)) {
                return $value;
            }
            if (! is_array($value) || ! isset($value['type']) || (isset($value['attributes']) && ! is_array($value['attributes']))) {
                throw ValidationException::withMessages(['document' => 'Objekt komponentu nemá platný typ.']);
            }
            $attributes = $value;
            unset($attributes['type'], $attributes['content'], $attributes['attributes']);
            $attributes = array_merge($attributes, $value['attributes'] ?? []);
            $children = $value['content'] ?? [];
            if (! is_array($children) || ! array_is_list($children)) {
                $children = [$children];
            }

            return ['id' => (string) Str::uuid(), 'type' => $value['type'], 'attributes' => $attributes, 'children' => array_map($map, $children)];
        };

        return self::validate(array_replace(self::empty(), ['nodes' => array_map($map, $objects)]));
    }

    public static function toLegacy(array $document): array
    {
        $map = function ($node) use (&$map) {
            if (is_string($node)) {
                return $node;
            }
            $attributes = $node['attributes'] ?? [];
            $reserved = array_intersect_key($attributes, array_flip(['type', 'content', 'attributes']));
            $value = array_merge(['type' => $node['type']], array_diff_key($attributes, $reserved));
            if ($reserved) {
                $value['attributes'] = $reserved;
            }
            $children = array_map($map, $node['children'] ?? []);
            if ($children) {
                $value['content'] = count($children) === 1 && is_string($children[0]) ? $children[0] : $children;
            }

            return $value;
        };
        $nodes = array_map($map, $document['nodes'] ?? []);

        return count($nodes) === 1 ? $nodes[0] : $nodes;
    }

    public static function validate(array $document): array
    {
        $fail = fn () => throw ValidationException::withMessages(['document' => 'Neplatná štruktúra dokumentu alebo prekročený limit obsahu.']);
        if (($document['schema_version'] ?? null) !== 1 || ! is_array($document['nodes'] ?? null) || ! array_is_list($document['nodes'])) {
            $fail();
        }
        if (! is_string($document['css'] ?? null) || ! is_string($document['js'] ?? null)) {
            $fail();
        }
        if (strlen(json_encode($document, JSON_THROW_ON_ERROR)) > 2_000_000) {
            $fail();
        }
        $count = 0;
        $ids = [];
        $walk = function ($node, int $depth) use (&$walk, &$count, &$ids, $fail) {
            if (++$count > 5000 || $depth > 48) {
                $fail();
            }
            if (is_string($node)) {
                return;
            }
            if (! is_array($node) || ! Str::isUuid($node['id'] ?? '') || isset($ids[$node['id']]) || ! is_string($node['type'] ?? null) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9:_-]{0,79}$/', $node['type'])) {
                $fail();
            }
            if (! is_array($node['attributes'] ?? null) || ! is_array($node['children'] ?? null) || ! array_is_list($node['children'])) {
                $fail();
            }
            $ids[$node['id']] = true;
            foreach ($node['children'] as $child) {
                $walk($child, $depth + 1);
            }
        };
        foreach ($document['nodes'] as $node) {
            $walk($node, 0);
        }

        return $document;
    }
}
