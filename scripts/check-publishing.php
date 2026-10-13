<?php
declare(strict_types=1);
// Local integration audit with disposable records. Never runs against production.
// php scripts/check-publishing.php --local [--keep-fixtures]
// php scripts/check-publishing.php --local --cleanup
if (PHP_SAPI !== 'cli' || !in_array('--local',$argv,true)) exit("Usage: php scripts/check-publishing.php --local [--keep-fixtures|--cleanup]\n");
$root=dirname(__DIR__);
$config=json_decode((string)file_get_contents($root.'/storage/tmp/dev-config.json'),true,512,JSON_THROW_ON_ERROR);
if(($config['DB_HOST']??'')!=='127.0.0.1'||($config['DB_DATABASE']??'')!=='wisdomnews_dev') throw new RuntimeException('Expected the isolated development database.');
foreach($config as $key=>$value)$_ENV[$key]=(string)$value;
$_ENV['APP_URL']='http://127.0.0.1:8000';$_ENV['APP_ENV']='development';$_ENV['SESSION_SECURE']='false';
require $root.'/app/bootstrap.php';
session_write_close();
$db=App\Core\Database::connection();
$manifestPath=$root.'/storage/tmp/publishing-fixtures.json';
function cleanFixtures(PDO $db,array $fixture): void {
    $prefix=$fixture['prefix']??'';
    if(!preg_match('/^publishing-audit-[a-f0-9]{12}$/',$prefix))throw new RuntimeException('Invalid fixture manifest.');
    $db->prepare('DELETE FROM advertisements WHERE name LIKE ?')->execute([$prefix.'%']);
    $db->prepare('DELETE FROM articles WHERE slug LIKE ?')->execute(['%'.$prefix]);
    $media=$db->prepare('SELECT * FROM media WHERE original_name=?');$media->execute([$prefix.'.png']);
    foreach($media->fetchAll() as $row){(new App\Services\MediaStorage())->delete($row);$db->prepare('DELETE FROM media WHERE id=?')->execute([$row['id']]);}
    foreach($fixture['positions']??[] as $id=>$state)$db->prepare('UPDATE advertisement_positions SET is_active=? WHERE id=?')->execute([$state,$id]);
    $users=$db->prepare('SELECT id FROM admin_users WHERE email LIKE ?');$users->execute([$prefix.'-%@example.test']);
    foreach($users->fetchAll(PDO::FETCH_COLUMN) as $id){$db->prepare('DELETE FROM activity_logs WHERE user_id=?')->execute([$id]);$db->prepare('DELETE FROM admin_users WHERE id=?')->execute([$id]);}
    foreach(glob(dirname(__DIR__).'/storage/tmp/'.$prefix.'*')?:[] as $file)if(is_file($file))unlink($file);
}
if(in_array('--cleanup',$argv,true)){
    if(is_file($manifestPath)){cleanFixtures($db,json_decode((string)file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR));unlink($manifestPath);}
    echo "Disposable publishing fixtures removed.\n";exit;
}
if(is_file($manifestPath))throw new RuntimeException('Clean up the previous fixture run first.');
$prefix='publishing-audit-'.bin2hex(random_bytes(6));
$fixture=['prefix'=>$prefix,'users'=>[],'positions'=>$db->query('SELECT id,is_active FROM advertisement_positions')->fetchAll(PDO::FETCH_KEY_PAIR)];
$checks=0;$viewIncrements=0;
function check(bool $condition,string $message): void {global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
function request(string $path,string $role='',array $post=[],string $method='GET',string $agent='WisdomNewsAuditBot'): array {
    global $root,$prefix;
    $parts=explode('?', $path,2);$encoded=implode('/',array_map('rawurlencode',explode('/',$parts[0])));
    $curl=curl_init('http://127.0.0.1:8000'.$encoded.(isset($parts[1])?'?'.$parts[1]:''));
    $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_USERAGENT=>$agent,CURLOPT_HEADER=>true];
    if($role){$options[CURLOPT_COOKIEFILE]=$options[CURLOPT_COOKIEJAR]=$root.'/storage/tmp/'.$prefix.'-'.$role.'.cookie';}
    if($method==='POST'){$options[CURLOPT_POST]=true;$multipart=(bool)array_filter($post,fn($value)=>$value instanceof CURLFile);$options[CURLOPT_POSTFIELDS]=$multipart?$post:http_build_query($post);}
    if($method==='HEAD')$options[CURLOPT_NOBODY]=true;
    curl_setopt_array($curl,$options);$result=curl_exec($curl);
    if($result===false)throw new RuntimeException(curl_error($curl));
    $size=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    return ['status'=>$status,'body'=>substr($result,$size),'headers'=>substr($result,0,$size)];
}
function token(string $path,string $role): string {$page=request($path,$role);check($page['status']===200,'Cannot get form '.$path);preg_match('/name="_csrf" value="([a-f0-9]+)"/',$page['body'],$matches);check(!empty($matches[1]),'Missing CSRF token');return $matches[1];}
function post(string $path,string $role,array $data,string $form='/admin'): array {$data['_csrf']=token($form,$role);return request($path,$role,$data,'POST');}
function login(string $role): void {global $fixture;$user=$fixture['users'][$role];$result=post('/admin/login',$role,['email'=>$user['email'],'password'=>$user['password']],'/admin/login');check($result['status']===302,'Login failed '.$role);}
try{
    foreach(['super-admin','editor','author'] as $role){
        $roleId=$db->query('SELECT id FROM roles WHERE slug='.$db->quote($role))->fetchColumn();
        $password=bin2hex(random_bytes(18));$email=$prefix.'-'.$role.'@example.test';
        $db->prepare('INSERT INTO admin_users(role_id,name,email,password_hash) VALUES(?,?,?,?)')->execute([$roleId,'Publishing audit '.ucfirst($role),$email,password_hash($password,PASSWORD_DEFAULT)]);
        $fixture['users'][$role]=['id'=>(int)$db->lastInsertId(),'email'=>$email,'password'=>$password];
    }
    file_put_contents($manifestPath,json_encode($fixture,JSON_PRETTY_PRINT));
    check(request('/admin/login','',[],'POST')['status']===419,'Missing CSRF token was accepted');
    foreach(array_keys($fixture['users']) as $role)login($role);
    foreach(['/admin','/admin/articles','/admin/articles?page=10','/admin/articles/create','/admin/media?page=18','/admin/tags?page=28','/admin/categories','/admin/subcategories','/admin/authors','/admin/breaking-news','/admin/pages','/admin/navigation','/admin/comments','/admin/users','/admin/settings','/admin/activity','/admin/ad-positions','/admin/advertisements','/admin/advertisements/create'] as $path){
        $response=request($path,'super-admin');check($response['status']===200,'Admin route failed: '.$path);check(!preg_match('/Warning:|Fatal error:|Notice:/',$response['body']),'PHP warning in '.$path);
    }
    check(str_contains(request('/admin/articles?page=10','super-admin')['body'],'Page 10 of 10'),'Article pagination failed');
    check(str_contains(request('/admin/media?page=18','super-admin')['body'],'Page 18 of 18'),'Media pagination failed');
    check(str_contains(request('/admin/tags?page=28','super-admin')['body'],'Page 28 of 28'),'Tag pagination failed');
    check(!preg_match('/\$2[ayb]\$\d{2}\$[.\/A-Za-z0-9]{53}/',request('/admin/users','super-admin')['body']),'Password hash leaked');
    foreach(['/admin/advertisements','/admin/ad-positions','/admin/users','/admin/settings','/admin/navigation','/admin/activity'] as $path)check(request($path,'editor')['status']===403,'Editor can access '.$path);
    foreach(['/admin/categories','/admin/tags','/admin/pages','/admin/comments','/admin/advertisements'] as $path)check(request($path,'author')['status']===403,'Author can access '.$path);
    $foreign=$db->query('SELECT id FROM articles ORDER BY id LIMIT 1')->fetchColumn();
    check(request('/admin/articles/edit/'.$foreign,'author')['status']===403,'Author can edit another story');
    check(post('/admin/articles/save','author',['id'=>$foreign])['status']===403,'Author can update another story');
    check(request('/admin/articles/save','super-admin',['_csrf'=>'invalid'],'POST')['status']===419,'Invalid CSRF accepted');
    $categories=$db->query('SELECT id,slug FROM categories WHERE is_active=1 ORDER BY id LIMIT 2')->fetchAll();
    $author=$db->query('SELECT id,slug FROM authors WHERE status="active" ORDER BY id DESC LIMIT 1')->fetch();
    $tags=$db->query('SELECT id,slug FROM tags ORDER BY id DESC LIMIT 2')->fetchAll();
    $content='<h2>Publishing test</h2><p><b>Bold emphasis</b> and <i>italic emphasis</i>.</p>';for($i=0;$i<4;$i++)$content.='<p>'.str_repeat('Reporting provides clear context for the community. ',45).'</p>';
    $slug='தமிழ்-செய்தி-'.$prefix;
    $data=['title'=>'Publishing audit story','slug'=>$slug,'summary'=>'A disposable story to verify the publishing workflow.','content'=>$content,'category_id'=>$categories[0]['id'],'additional_categories'=>[$categories[1]['id']],'author_id'=>$author['id'],'tags'=>array_column($tags,'id'),'status'=>'draft'];
    $saved=post('/admin/articles/save','author',$data);check($saved['status']===302,'Article creation failed');
    $s=$db->prepare('SELECT * FROM articles WHERE slug=?');$s->execute([$slug]);$article=$s->fetch();check((bool)$article,'Tamil slug was not preserved');$fixture['article_id']=$article['id'];$fixture['article_slug']=$slug;
    check(str_contains($article['content'],'<strong>Bold emphasis</strong>')&&str_contains($article['content'],'<em>italic emphasis</em>'),'Editor formatting lost during save');
    check(request('/article/'.$slug)['status']===404,'Draft is publicly visible');
    $data['id']=$article['id'];$data['title']='Publishing audit updated';check(post('/admin/articles/save','author',$data)['status']===302,'Article editing failed');
    check((int)$db->query('SELECT COUNT(*) FROM article_categories WHERE article_id='.(int)$article['id'])->fetchColumn()===2,'Multiple categories lost');
    check((int)$db->query('SELECT COUNT(*) FROM article_tags WHERE article_id='.(int)$article['id'])->fetchColumn()===2,'Tags lost');
    check(str_contains(request('/author/'.$author['slug'].'?page=2')['body'],'rel="prev"'),'Author archive pagination missing');
    $data['status']='scheduled';$data['scheduled_at']=date('Y-m-d\TH:i',strtotime('-5 minutes'));
    check(post('/admin/articles/save','author',$data)['status']===302,'Scheduling failed');
    check(request('/article/'.$slug)['status']===404,'Scheduled story visible early');
    $environment=array_merge(getenv(),array_map('strval',$config),['APP_ENV'=>'development','SESSION_SECURE'=>'false']);
    $process=proc_open([PHP_BINARY,$root.'/scripts/publish-scheduled.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$environment);
    if(!is_resource($process))throw new RuntimeException('Cannot run scheduler');$published=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($process)===0,'Scheduler failed: '.$errors);
    check(str_contains($published,'1 scheduled article(s) published.'),'Scheduler did not publish the due story');
    check(request('/article/'.$slug)['status']===200,'Published story missing');
    $priority=(int)$db->query('SELECT COALESCE(MAX(priority),0) FROM advertisements')->fetchColumn()+100;
    foreach($db->query('SELECT id,slug FROM advertisement_positions')->fetchAll() as $position){
        $html='<div style="padding:28px;background:#e8f0f9;color:#294664;min-height:70px;text-align:center;max-width:100%;box-sizing:border-box"><b>Advertising preview</b><br><small>'.e($position['slug']).' · local audit</small></div>';
        $db->prepare('INSERT INTO advertisements(position_id,name,type,html_content,status,priority) VALUES(?,?,"html",?,"active",?)')->execute([$position['id'],$prefix.'-'.$position['slug'],$html,$priority]);
        $db->prepare('UPDATE advertisement_positions SET is_active=1 WHERE id=?')->execute([$position['id']]);
    }
    $paths=['/'=>['header','footer','homepage-top','homepage-middle','homepage-bottom','sidebar'],'/category/'.$categories[0]['slug']=>['header','footer','category-top','category-page','category-bottom'],'/article/'.$slug=>['header','footer','article-top','article-middle','article-sidebar','article-bottom'],'/author/'.$author['slug']=>['header','footer','archive-top','archive-sidebar','archive-bottom'],'/tag/'.$tags[0]['slug']=>['header','footer','archive-top','archive-sidebar','archive-bottom'],'/page/about-us'=>['header','footer','page-top','page-bottom'],'/page/contact-us'=>['header','footer','page-top','page-bottom'],'/search?q=Publishing'=>['header','footer','search-top','search-bottom']];
    foreach($paths as $path=>$expected){$response=request($path);check($response['status']===200,'Public route failed '.$path);preg_match_all('/data-ad-placement="([^"]+)"/',$response['body'],$matches);$actual=$matches[1];sort($actual);sort($expected);check($actual===$expected,'Ad placements mismatch '.$path.': '.implode(',',$actual));}
    $html=request('/article/'.$slug)['body'];check(strpos($html,'data-ad-placement="article-middle"')<strrpos($html,'Reporting provides clear context'),'In-body ad is not between paragraphs');
    $header=$db->query('SELECT id FROM advertisement_positions WHERE slug="header"')->fetchColumn();
    $db->prepare('UPDATE advertisement_positions SET is_active=0 WHERE id=?')->execute([$header]);check(!str_contains(request('/')['body'],'data-ad-placement="header"'),'Disabled placement visible');$db->prepare('UPDATE advertisement_positions SET is_active=1 WHERE id=?')->execute([$header]);
    $adData=['name'=>$prefix.'-scheduled','position_id'=>$header,'type'=>'html','html_content'=>'future-audit-creative','status'=>'active','priority'=>$priority+1,'starts_at'=>date('Y-m-d\TH:i',strtotime('+1 day'))];
    check(post('/admin/advertisements/save','super-admin',$adData)['status']===302,'Ad campaign creation failed');check(!str_contains(request('/')['body'],'future-audit-creative'),'Future advertisement is visible');
    $adData['name']=$prefix.'-expired';$adData['starts_at']=date('Y-m-d\TH:i',strtotime('-2 days'));$adData['ends_at']=date('Y-m-d\TH:i',strtotime('-1 day'));$adData['html_content']='expired-audit-creative';
    check(post('/admin/advertisements/save','super-admin',$adData)['status']===302,'Expired campaign save failed');check(!str_contains(request('/')['body'],'expired-audit-creative'),'Expired advertisement visible');
    $adData['ends_at']=$adData['starts_at'];check(post('/admin/advertisements/save','super-admin',$adData)['status']===422,'Invalid ad dates accepted');$adData['ends_at']='';$adData['html_content']='';check(post('/admin/advertisements/save','super-admin',$adData)['status']===422,'Empty active creative accepted');
    $png=$root.'/storage/tmp/'.$prefix.'.png';file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD1cAAAAASUVORK5CYII='));
    $uploaded=post('/admin/media/upload','super-admin',['alt_text'=>'Disposable audit image','image'=>new CURLFile($png,'image/png',$prefix.'.png')]);check($uploaded['status']===302,'Media upload failed');
    $s=$db->prepare('SELECT * FROM media WHERE original_name=?');$s->execute([$prefix.'.png']);$media=$s->fetch();check((bool)$media&&is_file($root.'/public/'.$media['object_key']),'Local image not saved');check(request($media['url'])['status']===200,'Uploaded image URL missing');
    $db->prepare('UPDATE articles SET content=CONCAT(content,?) WHERE id=?')->execute(['<img src="'.$media['url'].'" alt="audit">',$article['id']]);
    post('/admin/media/delete/'.$media['id'],'super-admin',[]);check((int)$db->query('SELECT COUNT(*) FROM media WHERE id='.(int)$media['id'])->fetchColumn()===1,'In-use image deleted');
    $db->prepare('UPDATE articles SET content=? WHERE id=?')->execute([$content,$article['id']]);
    $db->prepare('INSERT INTO advertisements(position_id,name,type,image_url,status) VALUES(?,?,"image",?,"inactive")')->execute([$header,$prefix.'-media-use',url($media['url'])]);$imageAd=(int)$db->lastInsertId();
    post('/admin/media/delete/'.$media['id'],'super-admin',[]);check((int)$db->query('SELECT COUNT(*) FROM media WHERE id='.(int)$media['id'])->fetchColumn()===1,'Campaign image deleted');$db->prepare('DELETE FROM advertisements WHERE id=?')->execute([$imageAd]);
    post('/admin/media/delete/'.$media['id'],'super-admin',[]);clearstatcache();check((int)$db->query('SELECT COUNT(*) FROM media WHERE id='.(int)$media['id'])->fetchColumn()===0&&!is_file($root.'/public/'.$media['object_key']),'Unused media removal failed');
    $before=(int)$db->query('SELECT COALESCE(SUM(page_views),0) FROM site_daily_views')->fetchColumn();
    foreach(['/','/article/'.$slug] as $path){$response=request($path,'',[],'GET','Mozilla/5.0 WisdomNewsReader');check($response['status']===200,'Reader route failed');$viewIncrements++;check(str_contains($response['body'],'data-site-views'),'Footer counter missing');}
    request('/','',[],'HEAD','Mozilla/5.0 WisdomNewsReader');request('/missing-audit','',[],'GET','Mozilla/5.0 WisdomNewsReader');request('/');request('/admin/login','',[],'GET','Mozilla/5.0 WisdomNewsReader');request('/assets/magazine.css','',[],'GET','Mozilla/5.0 WisdomNewsReader');request('/','super-admin',[],'GET','Mozilla/5.0 WisdomNewsReader');
    $after=(int)$db->query('SELECT COALESCE(SUM(page_views),0) FROM site_daily_views')->fetchColumn();check($after-$before===2,'Counter includes excluded requests or misses readers');
    check((int)$db->query('SELECT view_count FROM articles WHERE id='.(int)$article['id'])->fetchColumn()===1,'Article views did not exclude bots');
    $db->prepare('UPDATE admin_users SET status="inactive" WHERE id=?')->execute([$fixture['users']['author']['id']]);check(request('/admin','author')['status']===302,'Inactive session still has access');
    $fixture['category_slug']=$categories[0]['slug'];file_put_contents($manifestPath,json_encode($fixture,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo "PASS: {$checks} integration checks; 20 placements; admin workflows, permissions, media, scheduling and counters verified.\n";
    if(in_array('--keep-fixtures',$argv,true))echo "Temporary fixtures retained for browser review; run --local --cleanup afterwards.\n";
}catch(Throwable $exception){fwrite(STDERR,"FAIL: ".$exception->getMessage()."\n");$failed=true;}
finally{
    if($viewIncrements)$db->prepare('UPDATE site_daily_views SET page_views=GREATEST(0,CAST(page_views AS SIGNED)-?) WHERE view_date=?')->execute([$viewIncrements,date('Y-m-d')]);
    if(!in_array('--keep-fixtures',$argv,true)||isset($failed)){cleanFixtures($db,$fixture);if(is_file($manifestPath))unlink($manifestPath);}
}
exit(isset($failed)?1:0);
