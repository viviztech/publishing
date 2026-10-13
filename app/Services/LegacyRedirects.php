<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use PDOException;

final class LegacyRedirects
{
    public static function resolve(string $uri): ?string
    {
        $path=rawurldecode(parse_url($uri,PHP_URL_PATH)?:'/');
        $postId=isset($_GET['p'])&&ctype_digit((string)$_GET['p'])?(string)$_GET['p']:null;
        if(!$postId&&($path==='/'||preg_match('#^/(?:admin|article|category|author|tag|page|search|assets|uploads)(?:/|$)#u',$path)))return null;
        try{
            $db=Database::connection();
            if(!$db->query("SHOW TABLES LIKE 'wordpress_imports'")->fetchColumn())return null;
            if($postId){
                $s=$db->prepare('SELECT a.slug FROM wordpress_imports w JOIN articles a ON a.id=w.local_id WHERE w.source_host="wisdomnews.in" AND w.entity="posts" AND w.source_id=? AND a.status="published" AND a.published_at<=NOW() LIMIT 1');$s->execute([$postId]);$slug=$s->fetchColumn();
                return $slug?'/article/'.rawurlencode((string)$slug):null;
            }
            $rows=$db->query('SELECT w.source_url,a.slug FROM wordpress_imports w JOIN articles a ON a.id=w.local_id WHERE w.source_host="wisdomnews.in" AND w.entity="posts" AND a.status="published" AND a.published_at<=NOW()')->fetchAll();
            foreach($rows as $row){$original=rawurldecode(parse_url((string)$row['source_url'],PHP_URL_PATH)?:'/');if(rtrim($original,'/')===rtrim($path,'/'))return '/article/'.rawurlencode($row['slug']);}
        }catch(PDOException $exception){error_log('Legacy redirect: '.$exception->getMessage());}
        return null;
    }
}
