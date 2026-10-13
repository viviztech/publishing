<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDOException;

final class SiteAnalytics
{
    private static bool $recorded = false;
    public static function record(?int $articleId = null): void
    {
        if (self::$recorded || PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || http_response_code() >= 300 || Auth::check()) return;
        $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($agent === '' || preg_match('/bot|crawler|spider|headless|curl|wget|python|powershell|preview|facebookexternalhit/i', $agent)) return;
        if (str_contains(strtolower((string)($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? '')), 'prefetch')) return;
        self::$recorded = true;
        try {
            $db = Database::connection();
            $db->prepare('INSERT INTO site_daily_views(view_date,page_views) VALUES(?,1) ON DUPLICATE KEY UPDATE page_views=page_views+1')->execute([date('Y-m-d')]);
            if ($articleId) $db->prepare('UPDATE articles SET view_count=view_count+1 WHERE id=?')->execute([$articleId]);
        } catch (PDOException $exception) { error_log('Site views: '.$exception->getMessage()); }
    }
    public static function summary(): array
    {
        try {
            $statement = Database::connection()->prepare('SELECT COALESCE(SUM(page_views),0) total, COALESCE(SUM(CASE WHEN view_date=? THEN page_views ELSE 0 END),0) today, COALESCE(SUM(CASE WHEN view_date>=? THEN page_views ELSE 0 END),0) last_seven_days, MIN(view_date) started_at FROM site_daily_views');
            $statement->execute([date('Y-m-d'),date('Y-m-d',strtotime('-6 days'))]);
            return $statement->fetch() + ['available'=>true];
        } catch (PDOException) { return ['total'=>0,'today'=>0,'last_seven_days'=>0,'started_at'=>null,'available'=>false]; }
    }
}
