<?php

namespace App\Services\Cms;

use App\Models\User;
use DOMDocument;
use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AsusSiteAnalyzer
{
    public function authorize(User $user): void
    {
        abort_unless(in_array((int) $user->id, config('site-import.user_ids', []), true), 403, 'Import z ASUS-u zatiaľ nie je pre tento účet povolený.');
    }

    public function sources(User $user): array
    {
        $this->authorize($user);

        return collect(config('site-import.sources', []))->map(fn ($source, $id) => [
            'id' => $id, 'name' => $source['name'], 'domain' => $source['domain'], 'aliases' => $source['aliases'] ?? [],
        ])->values()->all();
    }

    public function analyze(User $user, string $id): array
    {
        $this->authorize($user);
        $source = config('site-import.sources', [])[$id] ?? abort(404, 'Tento web nie je v zozname zdrojov ASUS-u.');
        $root = realpath($source['root']);
        abort_unless($root && is_dir($root) && ! is_link($source['root']) && is_readable($root), 422, 'Zdrojový web nie je dostupný na čítanie.');
        [$files, $skipped] = $this->inventory($root);
        $pages = [];
        $sections = [];
        $includes = [];
        $references = [];
        $forms = [];
        $php = 0;
        $databaseEvidence = false;
        $usedSections = [];
        $dynamicIncludes = false;
        $fingerprint = hash_init('sha256');
        foreach ($files as $relative => $meta) {
            $path = $this->path($root, $relative);
            hash_update($fingerprint, $relative.'\0'.hash_file('sha256', $path));
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            if ($extension === 'php') {
                $php++;
            }
            if (! in_array($extension, ['php', 'html', 'htm']) || $meta['bytes'] > config('site-import.max_source_bytes')) {
                continue;
            }
            $text = file_get_contents($path);
            if ($extension === 'php') {
                $text = implode('', array_map(fn ($token) => is_array($token) ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT]) ? '' : $token[1]) : $token, token_get_all($text)));
            }
            // Analyze text only. Never include/eval a site's PHP or invoke its endpoints.
            $databaseEvidence = $databaseEvidence || (bool) preg_match('/\b(?:PDO|mysqli_connect|mysqli|pg_connect)\b/', $text);
            if (preg_match('#^sections/[^/]+\.php$#', $relative)) {
                $sections[] = $relative;
            }
            if (preg_match('#^includes/[^/]+\.php$#', $relative)) {
                $includes[] = $relative;
            }
            if (! str_contains($relative, '/') && in_array($extension, ['php', 'html', 'htm'])) {
                $pages[] = ['file' => $relative, 'route' => $relative === 'index.php' || $relative === 'index.html' ? '/' : '/'.$relative, 'title' => $this->title($text), 'kind' => $extension === 'php' ? 'PHP' : 'HTML'];
            }
            if (preg_match_all('#(?:include|require)(?:_once)?\s*\(?\s*[\x27\x22](sections/[^\x27\x22]+\.php)[\x27\x22]#', $text, $matches)) {
                foreach ($matches[1] as $section) {
                    if (str_contains($section, '$')) {
                        $dynamicIncludes = true;
                    } else {
                        $usedSections[] = $section;
                    }
                }
            }
            // A common PHP composition pattern: $Sections["index"] = array("hero", ...).
            if (preg_match_all('/\$Sections\s*\[\s*[\x27\x22][^\x27\x22]+[\x27\x22]\s*\]\s*=\s*(?:array\s*\((.*?)\)|\[(.*?)\])\s*;/s', $text, $groups, PREG_SET_ORDER)) {
                foreach ($groups as $group) {
                    preg_match_all('/[\x27\x22]([a-zA-Z0-9_-]+)[\x27\x22]/', ($group[1] ?? '').($group[2] ?? ''), $names);
                    foreach ($names[1] as $name) {
                        $usedSections[] = 'sections/'.$name.'.php';
                    }
                }
            }
            // Handlers may contain credentials. Only page/include/section markup contributes references.
            if (str_starts_with($relative, 'controllers/') || str_starts_with($relative, 'forms/')) {
                continue;
            }
            $markup = $extension === 'php' ? implode('', array_map(fn ($token) => is_array($token) && $token[0] === T_INLINE_HTML ? $token[1] : '', token_get_all($text))) : $text;
            $dom = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$markup, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            foreach ($dom->getElementsByTagName('*') as $element) {
                foreach (['href', 'src', 'action', 'poster'] as $attribute) {
                    if ($element->hasAttribute($attribute)) {
                        $references[] = ['file' => $relative, 'url' => $element->getAttribute($attribute)];
                    }
                }
                if (strtolower($element->tagName) === 'form') {
                    $forms[] = ['file' => $relative, 'action' => $this->action($element->getAttribute('action')), 'method' => strtoupper($element->getAttribute('method') ?: 'GET')];
                }
            }
        }
        $missing = [];
        $linkedDomains = [];
        $externalAssets = [];
        foreach ($references as $reference) {
            $url = trim($reference['url']);
            if ($url === '' || str_contains($url, '<?') || str_starts_with($url, '#') || preg_match('#^(data|mailto|tel|callto|javascript|skype|whatsapp):#i', $url)) {
                continue;
            }
            $parts = parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url);
            if ($parts === false) {
                continue;
            }
            $host = strtolower($parts['host'] ?? '');
            if ($host !== '') {
                if ($host === $source['domain'] || in_array($host, $source['aliases'] ?? [], true)) {
                    // Same host: inspect a local document path below.
                } elseif (str_ends_with($host, '.'.$source['domain'])) {
                    $linkedDomains[] = $host;

                    continue;
                } else {
                    if (preg_match('/\.(css|js|woff2?)(?:$|\?)/', $url) || str_contains($host, 'fonts.googleapis.com')) {
                        $externalAssets[] = $host;
                    }

                    continue;
                }
            }
            $urlPath = rawurldecode($parts['path'] ?? '');
            if ($urlPath === '' || $urlPath === '/') {
                continue;
            }
            // Includes/sections render at the page URL, not at their source directory.
            $base = str_starts_with($urlPath, '/') ? '' : (! str_contains($reference['file'], '/') ? dirname($reference['file']).'/' : '');
            $segments = [];
            foreach (explode('/', $base.$urlPath) as $segment) {
                if ($segment === '' || $segment === '.') {
                    continue;
                }
                if ($segment === '..') {
                    array_pop($segments);
                } else {
                    $segments[] = $segment;
                }
            }
            $target = implode('/', $segments);
            if ($target !== '' && ! isset($files[$target]) && ! is_dir($root.'/'.$target)) {
                $missing[$target] = true;
            }
        }
        $defaultTitle = isset($files['includes/head.php']) ? $this->title(file_get_contents($this->path($root, 'includes/head.php'))) : null;
        foreach ($pages as &$page) {
            $page['title'] ??= $defaultTitle;
        } unset($page);
        $assets = ['images' => 0, 'styles' => 0, 'scripts' => 0, 'fonts' => 0, 'documents' => 0];
        foreach (array_keys($files) as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $key = match (true) {
                in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico']) => 'images',
                $ext === 'css' => 'styles', $ext === 'js' => 'scripts',
                in_array($ext, ['woff', 'woff2', 'ttf', 'eot', 'otf']) => 'fonts', $ext === 'pdf' => 'documents', default => null,
            };
            if ($key) {
                $assets[$key]++;
            }
        }
        $family = collect(config('site-import.sources'))->filter(fn ($item) => $item['domain'] !== $source['domain'] && str_ends_with($item['domain'], '.'.$source['domain']))->map(fn ($item, $key) => ['id' => $key, 'domain' => $item['domain']])->values()->all();
        $available = array_column($family, 'domain');
        $unavailable = array_values(array_diff(array_unique($linkedDomains), $available, $source['aliases'] ?? []));
        sort($pages);
        sort($sections);
        sort($includes);
        $usedSections = array_values(array_intersect(array_unique($usedSections), $sections));
        sort($usedSections);

        return [
            'source_id' => $id, 'name' => $source['name'], 'domain' => $source['domain'], 'aliases' => $source['aliases'] ?? [],
            'analyzed_at' => now()->toIso8601String(), 'fingerprint' => hash_final($fingerprint), 'mode' => 'analysis_only',
            'site_type' => $php ? 'php' : 'static', 'php_files' => $php,
            'files_count' => count($files), 'bytes' => array_sum(array_column($files, 'bytes')), 'skipped_count' => $skipped,
            'pages' => $pages, 'sections' => $sections, 'used_sections' => $usedSections, 'includes' => $includes,
            'assets' => $assets, 'forms' => array_values(array_unique($forms, SORT_REGULAR)),
            'database' => $databaseEvidence ? 'possible' : 'not_detected',
            'subdomains' => $family, 'linked_subdomains_unavailable' => $unavailable,
            'missing_files' => array_slice(array_keys($missing), 0, 30), 'missing_files_count' => count($missing),
            'external_asset_hosts' => array_values(array_unique($externalAssets)), 'dynamic_includes' => $dynamicIncludes,
            'notes' => [
                'Analýza iba číta súbory. Pôvodný web, databázy ani záznamy projektov nemení.',
                'PHP sa nespúšťa a formuláre sa neodosielajú. Dynamické správanie treba overiť pri pracovnej kópii.',
                'Zoznam zahŕňa zdroje povolené pre import na ASUS-e. Alias www nie je druhý samostatný web.',
            ],
        ];
    }

    private function inventory(string $root): array
    {
        $files = [];
        $bytes = 0;
        $skipped = 0;
        $visited = 0;
        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator($directory, function ($file) use ($root) {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $parts = explode('/', $relative);

            return ! $file->isLink() && ! array_filter($parts, fn ($part) => str_starts_with($part, '.')) && ! in_array(strtolower($parts[0]), ['vendor', 'node_modules', 'storage', 'logs', 'backup', 'backups', 'cache']);
        });
        $iterator = new RecursiveIteratorIterator($filtered);
        foreach ($iterator as $file) {
            abort_if(++$visited > config('site-import.max_files') * 4, 422, 'Web obsahuje priveľa položiek pre túto etapu importu.');
            if ($file->isLink() || ! $file->isFile()) {
                $skipped++;

                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $parts = explode('/', $relative);
            if (array_filter($parts, fn ($part) => str_starts_with($part, '.'))) {
                $skipped++;

                continue;
            }
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['html', 'htm', 'php', 'css', 'js', 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'pdf', 'txt', 'map', 'scss', 'webmanifest'])) {
                $skipped++;

                continue;
            }
            abort_if(count($files) >= config('site-import.max_files') || ($bytes += $file->getSize()) > config('site-import.max_bytes'), 422, 'Web prekračuje limit veľkosti analýzy.');
            $files[$relative] = ['bytes' => $file->getSize()];
        }
        ksort($files);

        return [$files, $skipped];
    }

    private function path(string $root, string $relative): string
    {
        $resolved = realpath($root.'/'.$relative);
        abort_unless($resolved && str_starts_with($resolved, $root.'/') && is_file($resolved) && is_readable($resolved) && ! is_link($root.'/'.$relative), 422, 'Súbor nie je dostupný v povolenom zdroji.');

        return $resolved;
    }

    private function action(string $url): string
    {
        if (str_contains($url, '<?')) {
            return '(dynamická adresa)';
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return '(neplatná adresa)';
        }

        return (isset($parts['host']) ? ($parts['scheme'] ?? 'https').'://'.$parts['host'] : '').($parts['path'] ?? '');
    }

    private function title(string $text): ?string
    {
        return preg_match('#<title[^>]*>(.*?)</title>#is', $text, $match) ? html_entity_decode(trim(strip_tags($match[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }
}
