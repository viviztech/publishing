<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--local',$argv,true))exit("Usage: php scripts/check-legacy-links.php --local [--base=https://wisdomnews.in]\n");
$root=dirname(__DIR__);$config=json_decode((string)file_get_contents($root.'/storage/tmp/dev-config.json'),true,512,JSON_THROW_ON_ERROR);
if(($config['DB_HOST']??'')!=='127.0.0.1'||($config['DB_DATABASE']??'')!=='wisdomnews_dev')throw new RuntimeException('Expected the isolated local database.');
foreach($config as $key=>$value)$_ENV[$key]=(string)$value;
$_ENV['SESSION_SECURE']='false';require $root.'/app/bootstrap.php';session_write_close();
$base='http://127.0.0.1:8000';foreach($argv as $arg)if(str_starts_with($arg,'--base='))$base=rtrim(substr($arg,7),'/');
if(!filter_var($base,FILTER_VALIDATE_URL)||!in_array(parse_url($base,PHP_URL_HOST),['127.0.0.1','wisdomnews.in'],true))throw new RuntimeException('Unexpected verification host.');
$rows=App\Core\Database::connection()->query('SELECT w.source_id,w.source_url,a.slug FROM wordpress_imports w JOIN articles a ON a.id=w.local_id WHERE w.source_host="wisdomnews.in" AND w.entity="posts" AND a.status="published"')->fetchAll();
$count=0;
foreach($rows as $row){
    $sourcePath=rawurldecode(parse_url($row['source_url'],PHP_URL_PATH)?:'/');$path=implode('/',array_map('rawurlencode',explode('/',$sourcePath)));$query=parse_url($row['source_url'],PHP_URL_QUERY);if($query)$path.='?'.$query;
    foreach([$path,'/?p='.$row['source_id']] as $legacy){
        $curl=curl_init($base.$legacy);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_USERAGENT=>'WisdomNewsAuditBot',CURLOPT_TIMEOUT=>20]);$response=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if($code!==301||!preg_match('/^Location:\s*(.+)$/mi',(string)$response,$match)||rawurldecode(parse_url(trim($match[1]),PHP_URL_PATH)?:'')!=='/article/'.$row['slug'])throw new RuntimeException('Legacy redirect failed: '.$legacy.' HTTP '.$code);
        $count++;
    }
}
echo "PASS: {$count} WordPress permalink and post-ID redirects verified.\n";
