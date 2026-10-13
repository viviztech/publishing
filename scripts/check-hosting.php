<?php
declare(strict_types=1);

// CLI-only readiness check. Does not display credentials or contact R2.
// php scripts/check-hosting.php [--database]
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(in_array('--help',$argv,true))exit("Usage: php scripts/check-hosting.php [--database]\nChecks production runtime and configuration; --database also checks database access and schema.\n");
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{
    if(str_starts_with($class,'App\\')){
        $file=$root.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require $file;
    }
});
App\Core\Env::load($root.'/.env');
date_default_timezone_set(App\Core\Env::get('APP_TIMEZONE','Asia/Kolkata')?:'Asia/Kolkata');
$failed=0;
$report=static function(bool $passed,string $label)use(&$failed):void{echo($passed?'PASS: ':'FAIL: ').$label."\n";if(!$passed)$failed++;};
$report(version_compare(PHP_VERSION,'8.3.0','>='),'PHP 8.3 or later (current '.PHP_VERSION.')');
foreach(['PDO','pdo_mysql','curl','dom','mbstring','fileinfo','session'] as $extension)$report(extension_loaded($extension),'PHP extension '.$extension);
$report(is_file($root.'/.env'),'Server .env file exists');
$report(App\Core\Env::get('APP_ENV')==='production','APP_ENV is production');
$report(!App\Core\Env::bool('APP_DEBUG'),'APP_DEBUG is disabled');
$appUrl=(string)App\Core\Env::get('APP_URL','');
$report((bool)filter_var($appUrl,FILTER_VALIDATE_URL)&&parse_url($appUrl,PHP_URL_SCHEME)==='https','APP_URL uses HTTPS');
$report(App\Core\Env::bool('SESSION_SECURE'),'Secure session cookies are enabled');
foreach(['DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $key){$value=(string)App\Core\Env::get($key,'');$report($value!==''&&!str_starts_with($value,'replace_with_'),$key.' is configured');}
$bundle=(string)App\Core\Env::get('CA_BUNDLE_PATH','');$report($bundle===''||is_readable($bundle),'Configured CA bundle is readable or the system default is used');
foreach(['storage/logs','storage/cache','storage/backups','storage/tmp'] as $directory)$report(is_dir($root.'/'.$directory)&&is_writable($root.'/'.$directory),$directory.' is writable');
$report(is_dir($root.'/public/uploads'),'Editorial media directory exists');
$report(App\Services\R2Storage::configured(),'Production R2 upload configuration is present (connectivity not tested)');
if(in_array('--database',$argv,true)){
    try{
        $db=App\Core\Database::connection();$report(true,'Application database connection');
        foreach(['roles','admin_users','categories','authors','articles','article_tags','article_categories','media','advertisement_positions','advertisements','website_settings','wordpress_imports','site_daily_views'] as $table){$statement=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$statement->execute([$table]);$report((int)$statement->fetchColumn()===1,'Database table '.$table);}
        $report((int)$db->query('SELECT COUNT(*) FROM admin_users u JOIN roles r ON r.id=u.role_id WHERE u.status="active" AND r.slug="super-admin"')->fetchColumn()>0,'An active owner account exists');
        $slugs=$db->query('SELECT slug FROM advertisement_positions')->fetchAll(PDO::FETCH_COLUMN);$report(!array_diff(array_keys(App\Services\AdPlacements::definitions()),$slugs),'All 20 ad positions exist');
    }catch(Throwable){$report(false,'Database connection or schema check failed; review server configuration and private logs');}
}
echo $failed?"Readiness check has {$failed} issue(s).\n":"Production configuration checks passed. Verify routes, HTTPS, uploads, and cron separately.\n";
exit($failed?1:0);
