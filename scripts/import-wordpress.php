<?php
declare(strict_types=1);

/**
 * Import wisdomnews.in into the isolated local development database.
 *
 * php scripts/import-wordpress.php --local
 * php scripts/import-wordpress.php --local --from-cache
 *
 * Public source snapshots and image manifests live in storage/cache. Original
 * WordPress IDs/payloads are recorded in wordpress_imports for repeatable updates.
 * This command deliberately requires --local and never uses the production .env DB.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--local', $argv, true)) {
    fwrite(STDERR, "Usage: php scripts/import-wordpress.php --local [--from-cache]\n");
    exit(1);
}

$root = dirname(__DIR__);
$config = json_decode((string)file_get_contents($root . '/storage/tmp/dev-config.json'), true, 512, JSON_THROW_ON_ERROR);
if (($config['DB_HOST'] ?? '') !== '127.0.0.1' || ($config['DB_DATABASE'] ?? '') !== 'wisdomnews_dev') {
    throw new RuntimeException('Expected the isolated wisdomnews_dev database on localhost.');
}
foreach ($config as $key => $value) $_ENV[$key] = (string)$value;
$_ENV['APP_URL'] = 'http://127.0.0.1:8000';
$_ENV['SESSION_SECURE'] = 'false';
require $root . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\HtmlSanitizer;

const WP_SOURCE = 'https://wisdomnews.in';
const WP_HOST = 'wisdomnews.in';
const WP_JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR;

function wpSaveJson(string $path, array $data): void
{
    $temporary = $path . '.tmp';
    if (file_put_contents($temporary, json_encode($data, WP_JSON_FLAGS), LOCK_EX) === false || !rename($temporary, $path)) {
        throw new RuntimeException('Could not save ' . $path);
    }
}

function wpBackup(PDO $db, string $path, array $tables): void
{
    // Stream rows so repeated imports do not hold a second full export in memory.
    $temporary = $path . '.tmp';
    $file = fopen($temporary, 'wb');
    if ($file === false) throw new RuntimeException('Cannot create database backup.');
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    try {
        fwrite($file, "{\n");
        foreach ($tables as $index => $table) {
            if ($index > 0) fwrite($file, ",\n");
            fwrite($file, json_encode($table, JSON_THROW_ON_ERROR) . ":[\n");
            $query = $db->query("SELECT * FROM {$table}"); $first = true;
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                if (!$first) fwrite($file, ",\n");
                fwrite($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $first = false;
            }
            $query->closeCursor(); fwrite($file, "\n]");
        }
        fwrite($file, "\n}\n");
    } finally {
        $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        fclose($file);
    }
    if (!rename($temporary, $path)) throw new RuntimeException('Cannot save database backup.');
}

function wpFetchList(string $kind): array
{
    $items = []; $totalPages = 1; $total = null;
    for ($page = 1; $page <= $totalPages; $page++) {
        $query = ['rest_route' => '/wp/v2/' . $kind, 'per_page' => 100, 'page' => $page];
        if ($kind === 'posts') $query += ['_embed' => 1, 'status' => 'publish', 'orderby' => 'date', 'order' => 'desc'];
        $url = WP_SOURCE . '/?' . http_build_query($query);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $headers = [];
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 60,
                CURLOPT_USERAGENT => 'Wisdom News local content importer/1.0',
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                    if (str_contains($line, ':')) {
                        [$key, $value] = explode(':', $line, 2);
                        $headers[strtolower(trim($key))] = trim($value);
                    }
                    return strlen($line);
                },
            ]);
            $body = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            if ($body !== false && $status === 200) break;
            if ($attempt === 3) throw new RuntimeException("Fetching {$kind} failed: HTTP {$status} {$error}");
            usleep(500000);
        }
        $batch = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
        if (!array_is_list($batch)) throw new RuntimeException('Expected a WordPress list for ' . $kind);
        $totalPages = (int)($headers['x-wp-totalpages'] ?? 1);
        $total = isset($headers['x-wp-total']) ? (int)$headers['x-wp-total'] : $total;
        array_push($items, ...$batch);
        echo "Fetched {$kind} page {$page}/{$totalPages}.\n";
    }
    if ($total !== null && count($items) !== $total) throw new RuntimeException('Incomplete export for ' . $kind);
    return $items;
}

function wpText(string $html, ?int $limit = null): string
{
    $text = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return $limit === null ? $text : mb_substr($text, 0, $limit);
}

function wpUrl(string $url): string
{
    $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (str_starts_with($url, '//')) $url = 'https:' . $url;
    elseif (str_starts_with($url, '/')) $url = WP_SOURCE . $url;
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return '';
    if (in_array(strtolower($parts['host'] ?? ''), [WP_HOST, 'www.' . WP_HOST], true)) {
        return WP_SOURCE . ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
    return $url;
}

function wpDocument(string $html): DOMDocument
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    return $document;
}

function wpFeaturedImageUrl(array $media): string
{
    // WordPress exposes generated image previews for PDF featured attachments.
    $url = $media['source_url'] ?? '';
    if (!str_starts_with((string)($media['mime_type'] ?? 'image/'), 'image/')) {
        $url = $media['media_details']['sizes']['full']['source_url'] ?? $url;
    }
    return wpUrl((string)$url);
}

function wpCollectImages(array $posts): array
{
    $images = [];
    foreach ($posts as $post) {
        foreach ($post['_embedded']['wp:featuredmedia'] ?? [] as $media) {
            $url = wpFeaturedImageUrl($media);
            if ($url !== '') $images[$url] = [
                'alt' => wpText((string)(($media['alt_text'] ?? '') ?: $post['title']['rendered']), 255),
                'caption' => wpText((string)($media['caption']['rendered'] ?? ''), 500),
                'wordpress_id' => $media['id'] ?? null,
            ];
        }
        $document = wpDocument($post['content']['rendered']);
        foreach ($document->getElementsByTagName('img') as $image) {
            $url = wpUrl($image->getAttribute('src'));
            if ($url !== '' && !isset($images[$url])) $images[$url] = ['alt' => wpText($image->getAttribute('alt'), 255), 'caption' => ''];
        }
    }
    return $images;
}

function wpDownloadImages(array $images, string $root): array
{
    $manifestPath = $root . '/storage/cache/wordpress-images.json';
    $manifest = is_file($manifestPath) ? json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR) : [];
    $directory = $root . '/public/uploads/wordpress';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Cannot create the image directory.');
    $pending = []; $failures = [];
    foreach ($images as $url => $metadata) {
        if (isset($manifest[$url]['url']) && is_file($root . '/public' . $manifest[$url]['url'])) continue;
        if (!in_array(strtolower((string)parse_url($url, PHP_URL_HOST)), [WP_HOST, 'www.' . WP_HOST], true)) {
            $failures[$url] = 'External image kept at its original URL.';
            continue;
        }
        $pending[] = $url;
    }
    echo count($images) . ' unique images; ' . count($pending) . " need downloading.\n";
    $completed = 0;
    foreach (array_chunk($pending, 4) as $batch) {
        $multi = curl_multi_init(); $requests = [];
        foreach ($batch as $url) {
            $temporary = tempnam($root . '/storage/tmp', 'wp-image-');
            if ($temporary === false) throw new RuntimeException('Cannot create temporary image.');
            $file = fopen($temporary, 'wb');
            if ($file === false) throw new RuntimeException('Cannot open temporary image.');
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 40,
                CURLOPT_USERAGENT => 'Wisdom News local content importer/1.0',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($file): int {
                    if (ftell($file) + strlen($chunk) > 16 * 1024 * 1024) return 0;
                    return (int)fwrite($file, $chunk);
                },
            ]);
            curl_multi_add_handle($multi, $curl);
            $requests[] = compact('curl', 'url', 'file', 'temporary');
        }
        do {
            $status = curl_multi_exec($multi, $active);
            if ($active && curl_multi_select($multi, 1.0) === -1) usleep(10000);
        } while ($active && $status === CURLM_OK);
        foreach ($requests as $request) {
            ['curl' => $curl, 'url' => $url, 'file' => $file, 'temporary' => $temporary] = $request;
            fclose($file);
            $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
            $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'image/gif'=>'gif', 'image/avif'=>'avif'];
            $dimensions = @getimagesize($temporary);
            // PHP 8.3's image header reader does not recognize every valid AVIF.
            if (!$dimensions && $mime === 'image/avif' && function_exists('imagecreatefromavif')) {
                $decoded = @imagecreatefromavif($temporary);
                if ($decoded !== false) {
                    $dimensions = [imagesx($decoded), imagesy($decoded)];
                    imagedestroy($decoded);
                }
            }
            if ($http === 200 && $error === '' && isset($extensions[$mime]) && $dimensions) {
                $key = 'uploads/wordpress/' . hash('sha256', $url) . '.' . $extensions[$mime];
                if (!rename($temporary, $root . '/public/' . $key)) throw new RuntimeException('Cannot save image.');
                $manifest[$url] = $images[$url] + [
                    'object_key' => $key, 'url' => '/' . $key, 'mime_type' => $mime,
                    'size_bytes' => filesize($root . '/public/' . $key), 'width' => $dimensions[0], 'height' => $dimensions[1],
                    'original_name' => mb_substr(rawurldecode(basename((string)parse_url($url, PHP_URL_PATH))), 0, 255),
                ];
            } else {
                $failures[$url] = "HTTP {$http}; " . ($error ?: 'not a supported image');
                unlink($temporary);
            }
            curl_multi_remove_handle($multi, $curl); curl_close($curl);
            $completed++;
        }
        curl_multi_close($multi);
        wpSaveJson($manifestPath, $manifest);
        if ($completed % 20 === 0 || $completed === count($pending)) echo "Downloaded {$completed}/" . count($pending) . " images.\n";
    }
    foreach ($manifest as $url => &$metadata) if (isset($images[$url])) $metadata = $images[$url] + $metadata;
    unset($metadata);
    wpSaveJson($manifestPath, $manifest);
    return [$manifest, $failures];
}

function wpSqlDate(array $post, string $field): string
{
    $gmt = $post[$field . '_gmt'] ?? '';
    if ($gmt !== '' && !str_starts_with($gmt, '0000-')) {
        return (new DateTimeImmutable($gmt, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
    return (new DateTimeImmutable($post[$field]))->format('Y-m-d H:i:s');
}

function wpFindMapping(PDO $db, string $entity, int|string $sourceId): ?int
{
    $statement = $db->prepare('SELECT local_id FROM wordpress_imports WHERE source_host=? AND entity=? AND source_id=?');
    $statement->execute([WP_HOST, $entity, (string)$sourceId]);
    $id = $statement->fetchColumn();
    return $id === false ? null : (int)$id;
}

function wpRecord(PDO $db, string $entity, int|string $sourceId, int $localId, array $payload, string $url = ''): void
{
    $statement = $db->prepare('INSERT INTO wordpress_imports(source_host,entity,source_id,local_id,source_url,payload) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE local_id=VALUES(local_id),source_url=VALUES(source_url),payload=VALUES(payload)');
    $statement->execute([WP_HOST, $entity, (string)$sourceId, $localId, $url, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
}

function wpUpsert(PDO $db, string $table, string $entity, int|string $sourceId, array $fields, array $payload, string $sourceUrl = ''): int
{
    $id = wpFindMapping($db, $entity, $sourceId);
    if ($id !== null) {
        $exists = $db->prepare("SELECT id FROM {$table} WHERE id=?"); $exists->execute([$id]);
        if (!$exists->fetchColumn()) $id = null;
    }
    if (isset($fields['slug'])) {
        $limit = match ($table) { 'articles' => 280, 'tags' => 120, default => 140 };
        $slug = rawurldecode((string)$fields['slug']);
        if ($slug === '' || !preg_match('/^[\p{L}\p{M}\p{N}_-]+$/u', $slug)) $slug = 'wordpress-' . $entity . '-' . $sourceId;
        $slug = mb_substr($slug, 0, $limit);
        $candidate = $slug; $suffix = 0;
        do {
            $statement = $db->prepare("SELECT id FROM {$table} WHERE slug=?"); $statement->execute([$candidate]);
            $collision = $statement->fetchColumn();
            if ($collision === false || (int)$collision === $id) break;
            $tail = '-wp-' . $sourceId . ($suffix > 0 ? '-' . $suffix : '');
            $candidate = mb_substr($slug, 0, $limit - mb_strlen($tail)) . $tail; $suffix++;
        } while (true);
        $fields['slug'] = $candidate;
    }
    if ($id === null) {
        $columns = implode(',', array_keys($fields));
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})")->execute(array_values($fields));
        $id = (int)$db->lastInsertId();
    } else {
        $assignments = implode(',', array_map(static fn(string $column): string => $column . '=?', array_keys($fields)));
        $db->prepare("UPDATE {$table} SET {$assignments} WHERE id=?")->execute([...array_values($fields), $id]);
    }
    wpRecord($db, $entity, $sourceId, $id, $payload, $sourceUrl);
    return $id;
}

function wpRewriteContent(string $html, array $manifest, array $links): string
{
    $document = wpDocument($html);
    foreach ($document->getElementsByTagName('img') as $image) {
        $source = wpUrl($image->getAttribute('src'));
        $image->setAttribute('src', $manifest[$source]['url'] ?? $source);
        $image->setAttribute('loading', 'lazy');
    }
    foreach ($document->getElementsByTagName('a') as $anchor) {
        $source = wpUrl($anchor->getAttribute('href'));
        if (isset($links[$source])) $anchor->setAttribute('href', $links[$source]);
        elseif (isset($manifest[$source])) $anchor->setAttribute('href', $manifest[$source]['url']);
    }
    $html = '';
    foreach ($document->documentElement->childNodes as $node) $html .= $document->saveHTML($node);
    return HtmlSanitizer::clean($html);
}

try {
    $snapshotPath = $root . '/storage/cache/wordpress-source.json';
    if (in_array('--from-cache', $argv, true)) {
        $source = json_decode((string)file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
    } else {
        $source = ['source' => WP_SOURCE, 'fetched_at' => gmdate('c')];
        foreach (['categories', 'tags', 'posts'] as $kind) $source[$kind] = wpFetchList($kind);
        wpSaveJson($snapshotPath, $source);
    }
    if (($source['source'] ?? '') !== WP_SOURCE || empty($source['posts']) || empty($source['categories'])) throw new RuntimeException('The source export is empty or from an unexpected site.');
    $posts = $source['posts'];
    [$manifest, $imageFailures] = wpDownloadImages(wpCollectImages($posts), $root);
    $db = Database::connection();

    $tables = ['categories', 'subcategories', 'tags', 'authors', 'media', 'articles', 'article_tags', 'navigation_items', 'website_settings'];
    foreach (['wordpress_imports', 'article_categories'] as $optional) {
        if ($db->query('SHOW TABLES LIKE ' . $db->quote($optional))->fetchColumn()) $tables[] = $optional;
    }
    $backupPath = $root . '/storage/backups/before-wordpress-import-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
    wpBackup($db, $backupPath, $tables);
    $db->exec((string)file_get_contents($root . '/database/patches/003_wordpress_imports.sql'));
    $db->beginTransaction();

    $categoryMap = []; $categorySource = []; $tagMap = []; $authorMap = []; $mediaMap = []; $postMap = []; $links = [];
    $categories = $source['categories'];
    usort($categories, static fn(array $a, array $b): int => (int)$b['count'] <=> (int)$a['count']);
    foreach ($categories as $index => $category) {
        $name = wpText($category['name'], 120); $description = wpText($category['description']);
        $id = wpUpsert($db, 'categories', 'categories', $category['id'], [
            'name' => $name, 'name_ta' => $name, 'slug' => $category['slug'],
            'description' => $description, 'description_ta' => $description,
            'sort_order' => -100 + $index, 'is_active' => 1, 'show_on_homepage' => $category['count'] > 0 ? 1 : 0,
            'seo_title' => $name, 'seo_description' => mb_substr($description, 0, 320),
        ], $category, $category['link']);
        $categoryMap[$category['id']] = $id; $categorySource[$category['id']] = $category;
        $slug = $db->query('SELECT slug FROM categories WHERE id=' . $id)->fetchColumn();
        $links[wpUrl($category['link'])] = '/category/' . $slug;
    }
    foreach ($source['tags'] as $tag) {
        $id = wpUpsert($db, 'tags', 'tags', $tag['id'], [
            'name' => wpText($tag['name'], 100), 'slug' => $tag['slug'], 'description' => wpText($tag['description']),
        ], $tag, $tag['link']);
        $tagMap[$tag['id']] = $id;
        $slug = $db->query('SELECT slug FROM tags WHERE id=' . $id)->fetchColumn();
        $links[wpUrl($tag['link'])] = '/tag/' . $slug;
    }
    foreach ($posts as $post) {
        $sourceId = (int)$post['author'];
        if (isset($authorMap[$sourceId])) continue;
        $author = $post['_embedded']['author'][0] ?? ['id' => $sourceId, 'name' => 'Wisdom News', 'slug' => 'wordpress-author-' . $sourceId];
        $authorMap[$sourceId] = wpUpsert($db, 'authors', 'authors', $sourceId, [
            'name' => wpText((string)$author['name'], 120), 'slug' => $author['slug'],
            'bio' => wpText((string)($author['description'] ?? '')), 'status' => 'active',
        ], $author, $author['link'] ?? '');
    }
    foreach (wpCollectImages($posts) as $url => $metadata) {
        $image = $manifest[$url] ?? null;
        $mediaMap[$url] = wpUpsert($db, 'media', 'images', hash('sha256', $url), [
            'original_name' => $image['original_name'] ?? mb_substr(rawurldecode(basename((string)parse_url($url, PHP_URL_PATH))), 0, 255),
            'object_key' => $image['object_key'] ?? 'wordpress-remote/' . hash('sha256', $url),
            'url' => $image['url'] ?? $url, 'mime_type' => $image['mime_type'] ?? 'image/jpeg',
            'size_bytes' => $image['size_bytes'] ?? 0, 'width' => $image['width'] ?? null, 'height' => $image['height'] ?? null,
            'alt_text' => $metadata['alt'] ?: 'Wisdom News article image',
        ], $metadata, $url);
    }

    $inserted = 0; $updated = 0;
    foreach ($posts as $index => $post) {
        $existing = wpFindMapping($db, 'posts', $post['id']);
        $sourceCategories = array_values(array_filter($post['categories'], static fn(int $id): bool => isset($categoryMap[$id])));
        if ($sourceCategories === []) throw new RuntimeException('Missing category for post ' . $post['id']);
        // Prefer a specific child category for the byline; retain every category below.
        usort($sourceCategories, static fn(int $a, int $b): int => (int)($categorySource[$b]['parent'] > 0) <=> (int)($categorySource[$a]['parent'] > 0));
        $featured = $post['_embedded']['wp:featuredmedia'][0] ?? [];
        $imageUrl = wpFeaturedImageUrl($featured);
        $title = wpText($post['title']['rendered'], 255);
        $summary = wpText($post['excerpt']['rendered'] ?: $post['content']['rendered'], 500);
        $fields = [
            'title' => $title, 'title_ta' => $title, 'slug' => $post['slug'], 'summary' => $summary, 'summary_ta' => $summary,
            'content' => HtmlSanitizer::clean($post['content']['rendered']), 'content_ta' => HtmlSanitizer::clean($post['content']['rendered']),
            'category_id' => $categoryMap[$sourceCategories[0]], 'author_id' => $authorMap[$post['author']],
            'featured_image_id' => $mediaMap[$imageUrl] ?? null, 'image_caption' => wpText((string)($featured['caption']['rendered'] ?? ''), 500),
            'status' => 'published', 'published_at' => wpSqlDate($post, 'date'),
            'seo_title' => wpText((string)($post['yoast_head_json']['title'] ?? $title), 255),
            'seo_description' => wpText((string)($post['yoast_head_json']['description'] ?? $summary), 320),
            'created_at' => wpSqlDate($post, 'date'), 'updated_at' => wpSqlDate($post, 'modified'),
        ];
        if ($existing === null) $fields += ['is_featured' => $index < 5 ? 1 : 0, 'is_trending' => $index < 6 ? 1 : 0];
        $id = wpUpsert($db, 'articles', 'posts', $post['id'], $fields, $post, $post['link']);
        $postMap[$post['id']] = $id;
        $slug = $db->query('SELECT slug FROM articles WHERE id=' . $id)->fetchColumn();
        $links[wpUrl($post['link'])] = '/article/' . $slug;
        $links[WP_SOURCE . '/?p=' . $post['id']] = '/article/' . $slug;
        $db->prepare('DELETE FROM article_tags WHERE article_id=?')->execute([$id]);
        foreach ($post['tags'] as $tagId) {
            if (!isset($tagMap[$tagId])) throw new RuntimeException('Missing tag ' . $tagId);
            $db->prepare('INSERT INTO article_tags(article_id,tag_id) VALUES(?,?)')->execute([$id, $tagMap[$tagId]]);
        }
        $db->prepare('DELETE FROM article_categories WHERE article_id=?')->execute([$id]);
        foreach ($sourceCategories as $categoryId) $db->prepare('INSERT INTO article_categories(article_id,category_id) VALUES(?,?)')->execute([$id, $categoryMap[$categoryId]]);
        $existing === null ? $inserted++ : $updated++;
    }
    foreach ($posts as $post) {
        $content = wpRewriteContent($post['content']['rendered'], $manifest, $links);
        $db->prepare('UPDATE articles SET content=?,content_ta=?,updated_at=? WHERE id=?')->execute([$content, $content, wpSqlDate($post, 'modified'), $postMap[$post['id']]]);
    }

    // Retire the bundled starter navigation and stories after the import succeeds.
    $seedSlugs = ['politics', 'world', 'india', 'tamil-nadu', 'business', 'technology', 'sports', 'culture'];
    $marks = implode(',', array_fill(0, count($seedSlugs), '?'));
    $db->prepare("UPDATE categories SET show_on_homepage=0,is_active=0 WHERE slug IN ({$marks})")->execute($seedSlugs);
    $db->prepare("UPDATE navigation_items n JOIN categories c ON c.id=n.category_id SET n.is_active=0 WHERE c.slug IN ({$marks})")->execute($seedSlugs);
    $sampleSlugs = ['city-councils-open-public-budget-access', 'manufacturers-energy-efficient-upgrades', 'researchers-map-neighbourhood-heat', 'community-leagues-youth-sport-calendar', 'independent-bookshops-slow-discovery'];
    $db->prepare('UPDATE articles SET status="archived",is_featured=0,is_trending=0 WHERE slug IN (' . implode(',', array_fill(0, count($sampleSlugs), '?')) . ')')->execute($sampleSlugs);
    foreach ($categories as $index => $category) {
        wpUpsert($db, 'navigation_items', 'navigation', $category['id'], [
            'label' => wpText($category['name'], 100), 'label_ta' => wpText($category['name'], 100), 'type' => 'category',
            'category_id' => $categoryMap[$category['id']], 'sort_order' => $index, 'is_active' => 1, 'open_new_tab' => 0,
        ], ['wordpress_category_id' => $category['id']]);
    }
    $db->prepare('UPDATE website_settings SET setting_value=? WHERE setting_key="site_name"')->execute(['Wisdom News']);
    $db->prepare('UPDATE website_settings SET setting_value=? WHERE setting_key="site_description"')->execute(['அறிவின் வழி • உண்மையின் மொழி']);
    $db->commit();

    $report = [
        'source' => WP_SOURCE, 'target' => 'local wisdomnews_dev', 'completed_at' => gmdate('c'),
        'articles' => count($posts), 'inserted' => $inserted, 'updated' => $updated,
        'categories' => count($categoryMap), 'tags' => count($tagMap), 'authors' => count($authorMap),
        'local_images' => count(array_intersect_key($manifest, wpCollectImages($posts))),
        'image_failures' => $imageFailures, 'backup' => $backupPath, 'snapshot' => $snapshotPath,
    ];
    wpSaveJson($root . '/storage/cache/wordpress-import-report.json', $report);
    echo json_encode($report, WP_JSON_FLAGS) . PHP_EOL;
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'Import failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
