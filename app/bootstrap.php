<?php
declare(strict_types=1);

use App\Core\Env;

spl_autoload_register(function(string $class): void {
    $prefix='App\\';
    if (str_starts_with($class,$prefix)) {
        $file=__DIR__.'/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
        if (is_file($file)) require $file;
    }
});
require __DIR__.'/helpers.php';
Env::load(dirname(__DIR__).'/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE','Asia/Kolkata') ?: 'Asia/Kolkata');
ini_set('display_errors', Env::bool('APP_DEBUG') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__).'/storage/logs/php.log');
session_name('traditional_news_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>Env::bool('SESSION_SECURE'),'httponly'=>true,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
