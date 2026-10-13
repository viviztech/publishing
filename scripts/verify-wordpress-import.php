<?php
declare(strict_types=1);

// Verify imported source records, every category/tag relationship, dates, files,
// and optionally every public article route. Run after importing or rerunning.
// php scripts/verify-wordpress-import.php --local [--web]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--local', $argv, true)) { fwrite(STDERR, "Usage: php scripts/verify-wordpress-import.php --local [--web]\n"); exit(1); }
$root = dirname(__DIR__);
$config = json_decode((string)file_get_contents($root . '/storage/tmp/dev-config.json'), true, 512, JSON_THROW_ON_ERROR);
if (($config['DB_HOST'] ?? '') !== '127.0.0.1' || ($config['DB_DATABASE'] ?? '') !== 'wisdomnews_dev') throw new RuntimeException('Expected the isolated local database.');
foreach ($config as $key => $value) $_ENV[$key] = (string)$value;
$_ENV['APP_URL'] = 'http://127.0.0.1:8000'; $_ENV['SESSION_SECURE'] = 'false';
require $root . '/app/bootstrap.php';

function verifyCondition(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function verifyText(string $html): string
{
    return trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function verifyCanonical(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    if (!array_is_list($value)) ksort($value);
    foreach ($value as &$item) $item = verifyCanonical($item);
    unset($item);
    return $value;
}

function verifyHttp(string $path): string
{
    $segments = array_map('rawurlencode', explode('/', ltrim($path, '/')));
    $curl = curl_init('http://127.0.0.1:8000/' . implode('/', $segments));
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $body = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
    verifyCondition($body !== false && $status === 200, 'HTTP ' . $status . ' for ' . $path);
    return (string)$body;
}

try {
    $source = json_decode((string)file_get_contents($root . '/storage/cache/wordpress-source.json'), true, 512, JSON_THROW_ON_ERROR);
    $db = App\Core\Database::connection();
    $mapping = [];
    foreach ($db->query("SELECT * FROM wordpress_imports WHERE source_host='wisdomnews.in'")->fetchAll() as $row) $mapping[$row['entity']][$row['source_id']] = $row;
    foreach (['posts', 'categories', 'tags'] as $entity) verifyCondition(count($mapping[$entity] ?? []) === count($source[$entity]), 'Count mismatch for ' . $entity);
    verifyCondition((int)$db->query('SELECT COUNT(*) FROM articles WHERE status="published"')->fetchColumn() === count($source['posts']), 'Published count includes unexpected duplicates or sample stories.');
    $articles = [];
    foreach ($db->query('SELECT * FROM articles')->fetchAll() as $article) $articles[$article['id']] = $article;
    $categories = []; $tags = [];
    foreach ($db->query('SELECT * FROM article_categories')->fetchAll() as $relation) $categories[$relation['article_id']][] = (int)$relation['category_id'];
    foreach ($db->query('SELECT * FROM article_tags')->fetchAll() as $relation) $tags[$relation['article_id']][] = (int)$relation['tag_id'];
    $categoryRelations = 0; $tagRelations = 0; $webPages = 0; $tamilSlugs = 0;
    foreach ($source['posts'] as $post) {
        $article = $articles[$mapping['posts'][$post['id']]['local_id']] ?? null;
        verifyCondition($article !== null, 'Missing article ' . $post['id']);
        $title = mb_substr(verifyText($post['title']['rendered']), 0, 255);
        verifyCondition($article['title'] === $title && $article['title_ta'] === $title, 'Title encoding mismatch for ' . $post['id']);
        verifyCondition(verifyText($article['content']) === verifyText($post['content']['rendered']), 'Article text mismatch for ' . $post['id']);
        verifyCondition(verifyCanonical(json_decode($mapping['posts'][$post['id']]['payload'], true, 512, JSON_THROW_ON_ERROR)) === verifyCanonical($post), 'Original source payload changed for ' . $post['id']);
        $date = (new DateTimeImmutable($post['date_gmt'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
        verifyCondition($article['published_at'] === $date, 'Publication date mismatch for ' . $post['id']);
        foreach (['categories' => $categories, 'tags' => $tags] as $entity => $relationships) {
            $expected = array_map(static fn(int $sourceId): int => (int)$mapping[$entity][$sourceId]['local_id'], $post[$entity]);
            $actual = $relationships[$article['id']] ?? []; sort($expected); sort($actual);
            verifyCondition($expected === $actual, 'Relationship mismatch: ' . $entity . ' on ' . $post['id']);
        }
        $categoryRelations += count($post['categories']); $tagRelations += count($post['tags']);
        if (preg_match('/[\p{L}\p{M}]/u', preg_replace('/[a-zA-Z]/', '', $article['slug']))) $tamilSlugs++;
        if (in_array('--web', $argv, true)) {
            $body = verifyHttp('/article/' . $article['slug']);
            $document = new DOMDocument('1.0','UTF-8');
            $previousErrors = libxml_use_internal_errors(true);
            $document->loadHTML('<?xml encoding="utf-8"?>'.$body);
            libxml_clear_errors(); libxml_use_internal_errors($previousErrors);
            verifyCondition($document->getElementsByTagName('h1')->item(0)?->textContent === $title, 'Rendered title mismatch for ' . $post['id']); $webPages++;
        }
    }
    $localImages = 0;
    $media = $db->query('SELECT * FROM media WHERE url LIKE "/uploads/wordpress/%"')->fetchAll();
    foreach ($media as $image) {
        $path = $root . '/public' . $image['url'];
        verifyCondition(is_file($path) && filesize($path) === (int)$image['size_bytes'], 'Missing or changed image ' . $image['id']);
        verifyCondition((new finfo(FILEINFO_MIME_TYPE))->file($path) === $image['mime_type'], 'Image type mismatch ' . $image['id']); $localImages++;
    }
    foreach ($source['posts'] as $post) {
        if (empty($post['_embedded']['wp:featuredmedia'][0]['source_url'])) continue;
        $article = $articles[$mapping['posts'][$post['id']]['local_id']];
        $statement = $db->prepare('SELECT url FROM media WHERE id=?'); $statement->execute([$article['featured_image_id']]);
        verifyCondition(str_starts_with((string)$statement->fetchColumn(), '/uploads/wordpress/'), 'Featured image is not local for ' . $post['id']);
    }
    if (in_array('--web', $argv, true)) {
        verifyHttp('/');
        foreach ($source['categories'] as $category) {
            $id = (int)$mapping['categories'][$category['id']]['local_id'];
            $row = $db->query('SELECT slug FROM categories WHERE id=' . $id)->fetch();
            verifyHttp('/category/' . $row['slug']); $webPages++;
        }
    }
    echo json_encode(['verified_articles' => count($source['posts']), 'category_relationships' => $categoryRelations, 'tag_relationships' => $tagRelations, 'local_images' => $localImages, 'tamil_article_slugs' => $tamilSlugs, 'http_pages' => $webPages], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR, 'Verification failed: ' . $exception->getMessage() . PHP_EOL); exit(1); }
