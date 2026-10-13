<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\PublicController;
use App\Core\Router;

$router=new Router();
$public=new PublicController();
$admin=new AdminController();

$router->get('/',[$public,'home']);
$router->get('/category/{slug}',[$public,'category']);
$router->get('/article/{slug}',[$public,'article']);
$router->get('/author/{slug}',[$public,'author']);
$router->get('/tag/{slug}',[$public,'tag']);
$router->get('/page/{slug}',[$public,'page']);
$router->get('/search',[$public,'search']);
$router->get('/admin/login',[$admin,'loginForm']);
$router->post('/admin/login',[$admin,'login']);
$router->post('/admin/logout',[$admin,'logout']);
$router->get('/admin',[$admin,'dashboard']);
$router->get('/admin/articles',[$admin,'articles']);
$router->get('/admin/articles/edit/{id}',[$admin,'articleForm']);
$router->get('/admin/articles/create',[$admin,'articleForm']);
$router->post('/admin/articles/save',[$admin,'articleSave']);
$router->post('/admin/articles/delete/{id}',[$admin,'articleDelete']);
$router->get('/admin/media',[$admin,'media']);
$router->post('/admin/media/upload',[$admin,'mediaUpload']);
$router->post('/admin/media/delete/{id}',[$admin,'mediaDelete']);
$router->get('/admin/settings',[$admin,'settings']);
$router->post('/admin/settings',[$admin,'settingsSave']);
$router->get('/admin/{resource}',[$admin,'resource']);
$router->get('/admin/{resource}/edit/{id}',[$admin,'resourceForm']);
$router->get('/admin/{resource}/create',[$admin,'resourceForm']);
$router->post('/admin/{resource}/save',[$admin,'resourceSave']);
$router->post('/admin/{resource}/delete/{id}',[$admin,'resourceDelete']);

try { $router->dispatch($_SERVER['REQUEST_METHOD']??'GET',$_SERVER['REQUEST_URI']??'/'); }
catch(Throwable $e){ error_log((string)$e); if(\App\Core\Env::bool('APP_DEBUG')) throw $e; abort(500,'We could not complete that request. Please try again shortly.'); }
