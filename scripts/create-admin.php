<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if(in_array('--help',$argv,true))exit("Usage: php scripts/create-admin.php [--local]\nUse --local for the isolated wisdomnews_dev database.\n");
if(in_array('--local',$argv,true)){
    $config=json_decode((string)file_get_contents(dirname(__DIR__).'/storage/tmp/dev-config.json'),true,512,JSON_THROW_ON_ERROR);
    if(($config['DB_HOST']??'')!=='127.0.0.1'||($config['DB_DATABASE']??'')!=='wisdomnews_dev')throw new RuntimeException('Expected the isolated local database.');
    foreach($config as $key=>$value)$_ENV[$key]=(string)$value;
    $_ENV['APP_ENV']='development';$_ENV['SESSION_SECURE']='false';
}
require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Database;

$name=trim((string)readline('Administrator name: '));
$email=mb_strtolower(trim((string)readline('Administrator email: ')));
fwrite(STDOUT,"Password (input is visible in this terminal): ");
$password=trim((string)fgets(STDIN));
if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){fwrite(STDERR,"Name, a valid email, and a password of at least 12 characters are required.\n");exit(1);}
$role=(int)Database::connection()->query("SELECT id FROM roles WHERE slug='super-admin'")->fetchColumn();
if(!$role){fwrite(STDERR,"Seed roles before creating the administrator.\n");exit(1);}
$stmt=Database::connection()->prepare('INSERT INTO admin_users(role_id,name,email,password_hash,status) VALUES(?,?,?,?,"active")');
try{$stmt->execute([$role,$name,$email,password_hash($password,PASSWORD_DEFAULT)]);fwrite(STDOUT,"Administrator created.\n");}catch(Throwable $e){fwrite(STDERR,"Could not create administrator. The email may already exist.\n");exit(1);}
