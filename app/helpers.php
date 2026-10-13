<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { if(preg_match('#^(https?://|mailto:)#i',$path))return $path; return rtrim((string)Env::get('APP_URL', ''), '/') . '/' . ltrim($path, '/'); }
function asset(string $path): string { return url('assets/' . ltrim($path, '/')); }
function redirect(string $path): never { header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path))); exit; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">'; }
function flash(string $key, ?string $value = null): ?string { if ($value !== null) { $_SESSION['_flash'][$key] = $value; return null; } $v=$_SESSION['_flash'][$key]??null; unset($_SESSION['_flash'][$key]); return $v; }
function slugify(string $value): string { $value = mb_strtolower(rawurldecode(trim($value)), 'UTF-8'); $value = preg_replace('/[^\p{L}\p{M}\p{N}_-]+/u', '-', $value); return trim((string)$value, '-') ?: bin2hex(random_bytes(4)); }
function excerpt(string $html, int $length = 150): string { $text=trim((string)preg_replace('/\s+/', ' ', strip_tags($html))); return mb_strlen($text)>$length ? mb_substr($text,0,$length-1).'…' : $text; }
function format_date(?string $date, string $format='M j, Y'): string { return $date ? date($format, strtotime($date)) : ''; }
function setting(string $key, string $default=''): string { static $settings; if ($settings===null) { try { $settings=Database::connection()->query('SELECT setting_key,setting_value FROM website_settings')->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Throwable) {$settings=[];} } return (string)($settings[$key]??$default); }
function render(string $view, array $data=[]): void { if($view!=='error') \App\Services\SiteAnalytics::record(isset($data['article']['id'])?(int)$data['article']['id']:null); extract($data, EXTR_SKIP); ob_start(); require __DIR__.'/Views/public/'.$view.'.php'; $content=ob_get_clean(); require __DIR__.'/Views/layouts/public.php'; }
function render_admin(string $view, array $data=[]): void { extract($data, EXTR_SKIP); ob_start(); require __DIR__.'/Views/admin/'.$view.'.php'; $content=ob_get_clean(); require __DIR__.'/Views/layouts/admin.php'; }
function abort(int $code, string $message=''): never { http_response_code($code); $title=match($code){403=>'Access denied',404=>'Page not found',419=>'Session expired',default=>'Something went wrong'}; if (str_starts_with(parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)?:'', '/admin')) { echo '<!doctype html><html><body><main style="font:16px system-ui;max-width:700px;margin:15vh auto"><h1>'.e($title).'</h1><p>'.e($message).'</p><a href="'.e(url('/admin')).'">Return to dashboard</a></main></body></html>'; } else render('error', compact('title','message','code')); exit; }
function activity(string $action, string $description, ?string $entityType=null, ?int $entityId=null): void { try { $s=Database::connection()->prepare('INSERT INTO activity_logs(user_id,action,description,entity_type,entity_id,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)'); $s->execute([Auth::id(),$action,$description,$entityType,$entityId,$_SERVER['REMOTE_ADDR']??null,mb_substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]); } catch(Throwable $e) { error_log($e->getMessage()); } }
function paginate(int $total, int $page, int $perPage): array { return ['total'=>$total,'page'=>$page,'per_page'=>$perPage,'pages'=>max(1,(int)ceil($total/$perPage))]; }

function magazine_language(string $text): string { return preg_match('/[\x{0B80}-\x{0BFF}]/u', $text) ? 'ta' : 'en'; }
function magazine_minutes(string $html): int { return max(1, (int)ceil(count(preg_split('/\s+/u', trim(strip_tags($html)), -1, PREG_SPLIT_NO_EMPTY) ?: []) / 180)); }
function magazine_author(string $name): string { return ctype_digit($name) || filter_var($name, FILTER_VALIDATE_EMAIL) ? 'Wisdom News' : $name; }
function magazine_story(array $story, string $variant = 'standard'): void { require __DIR__.'/Views/partials/story-card.php'; }
function magazine_pagination(array $pagination, array $query = []): void { require __DIR__.'/Views/partials/pagination.php'; }
function magazine_ad(array $ad): void { if (!empty($ad['image_url']) || !empty($ad['html_content'])) require __DIR__.'/Views/partials/advertisement.php'; }
function magazine_article_content(string $html, array $ads): string
{
    if (!$ads) return $html;
    ob_start(); foreach ($ads as $ad) magazine_ad($ad); $advertising = ob_get_clean();
    $document = new DOMDocument('1.0','UTF-8'); $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><div id="content-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $root = $document->getElementById('content-root');
    if (!$root) return $html.$advertising;
    $blocks = iterator_to_array($root->childNodes);
    $words = count(preg_split('/\s+/u',trim($root->textContent),-1,PREG_SPLIT_NO_EMPTY)?:[]);
    $target = max(120,(int)ceil($words*.5)); $seen = 0; $result = ''; $inserted = false;
    foreach ($blocks as $index=>$block) {
        $result .= $document->saveHTML($block);
        $seen += count(preg_split('/\s+/u',trim($block->textContent),-1,PREG_SPLIT_NO_EMPTY)?:[]);
        if (!$inserted && $words>=260 && $seen>=$target && $index<count($blocks)-1 && $block instanceof DOMElement) { $result.=$advertising; $inserted=true; }
    }
    return $result.($inserted?'':$advertising);
}
function magazine_nav_url(array $item): string { return (string)($item['type'] === 'category' ? '/category/'.$item['category_slug'] : ($item['type'] === 'page' ? '/page/'.$item['page_slug'] : $item['url'])); }
function magazine_icon(string $name): string
{
    $paths = [
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'bookmark' => '<path d="M6 4h12v17l-6-4-6 4z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/>',
        'link' => '<path d="m10 13 4-4m-5 7-2 2a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0m2-1 2-2a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'list' => '<path d="M9 5h12M9 12h12M9 19h12M3 5h1M3 12h1M3 19h1"/>',
        'check' => '<path d="m5 12 4 4L20 5"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['arrow']).'</svg>';
}
