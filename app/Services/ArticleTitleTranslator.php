<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/** Translates only an article's headline; the article itself stays in Tamil. */
final class ArticleTitleTranslator
{
    private const ENDPOINT = 'https://api.mymemory.translated.net/get';

    public static function normalize(string $title): string
    {
        $title = trim((string)preg_replace('/\s+/u', ' ', $title));
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($title) ?: $title) : $title;
    }

    public static function cached(string $title): ?string
    {
        $title = self::normalize($title);
        $path = self::cachePath($title);
        if (!is_file($path)) return null;
        $entry = json_decode((string)@file_get_contents($path), true);
        $english = is_array($entry) ? (string)($entry['english_title'] ?? '') : '';
        return ($entry['source'] ?? null) === $title && self::validEnglish($english) ? $english : null;
    }

    public function translate(string $title): string
    {
        $title = self::normalize($title);
        if ($title === '' || mb_strlen($title) > 255) throw new \InvalidArgumentException('Use a headline of up to 255 characters.');
        if (!preg_match('/[\x{0B80}-\x{0BFF}]/u', $title)) {
            if (!self::validEnglish($title)) throw new \RuntimeException('Enter an English title to create the article link.');
            return $title;
        }
        if (($cached = self::cached($title)) !== null) return $cached;
        // Headlines can be unpublished. Enable the external provider only after
        // the newsroom has approved sending those headlines for translation.
        if (!Env::bool('ARTICLE_TITLE_TRANSLATION_ENABLED')) throw new \RuntimeException('Automatic English title translation has not been enabled.');

        $translated = [];
        foreach (self::chunks($title) as $chunk) $translated[] = $this->request($chunk);
        $english = self::normalize(implode(' ', $translated));
        if (!self::validEnglish($english)) throw new \RuntimeException('An English headline could not be generated. Please retry or enter one.');

        $path = self::cachePath($title);
        if (is_dir(dirname($path)) || @mkdir(dirname($path), 0775, true)) {
            $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($temporary, json_encode(['source'=>$title, 'english_title'=>$english], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) !== false) {
                if (!@rename($temporary, $path)) @unlink($temporary);
            }
        }
        return $english;
    }

    public static function asciiSlug(string $english): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $english);
        $slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii === false ? $english : $ascii), '-'));
        return rtrim(substr($slug, 0, 280), '-');
    }

    private static function validEnglish(string $english): bool
    {
        return $english !== '' && !preg_match('/[\x{0B80}-\x{0BFF}]/u', $english)
            && preg_match('/[a-z]/i', $english) === 1
            && !preg_match('/MYMEMORY WARNING|QUERY LENGTH LIMIT|INVALID LANGUAGE|NO QUERY|USED ALL AVAILABLE|PLEASE SELECT.*LANGUAGE|LANGUAGE PAIR.*NOT SUPPORTED/i', $english)
            && self::asciiSlug($english) !== '';
    }

    /** MyMemory accepts at most 500 UTF-8 bytes per query. */
    private static function chunks(string $title): array
    {
        $chunks = [];
        while (strlen($title) > 500) {
            $chunk = mb_strcut($title, 0, 500, 'UTF-8');
            $space = strrpos($chunk, ' ');
            if ($space !== false && $space > 0) $chunk = substr($chunk, 0, $space);
            $chunks[] = $chunk;
            $title = ltrim(substr($title, strlen($chunk)));
        }
        if ($title !== '') $chunks[] = $title;
        return $chunks;
    }

    private function request(string $chunk): string
    {
        $curl = curl_init(self::ENDPOINT . '?' . http_build_query(['q'=>$chunk, 'langpair'=>'ta|en', 'mt'=>1], '', '&', PHP_QUERY_RFC3986));
        $options = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>7, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_HTTPHEADER=>['Accept: application/json'], CURLOPT_USERAGENT=>'WisdomNews/1.0'];
        if (($bundle = Env::get('CA_BUNDLE_PATH'))) $options[CURLOPT_CAINFO] = $bundle;
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $data = is_string($body) ? json_decode($body, true) : null;
        $english = is_array($data) ? html_entity_decode(strip_tags((string)($data['responseData']['translatedText'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
        if ($status !== 200 || (int)($data['responseStatus'] ?? 0) !== 200 || !empty($data['quotaFinished']) || !self::validEnglish($english)) {
            throw new \RuntimeException('English title translation is unavailable. Please retry or enter an English title.');
        }
        return self::normalize($english);
    }

    private static function cachePath(string $title): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/title-translation-' . hash('sha256', $title) . '.json';
    }
}
