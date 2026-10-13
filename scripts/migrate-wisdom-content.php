<?php
declare(strict_types=1);

use App\Core\Database;
use App\Services\R2Storage;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

function readEnvFile(string $path): array
{
    $values=[];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line=trim($line);
        if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
        [$key,$value]=array_map('trim',explode('=',$line,2));
        $values[$key]=trim($value,"\"'");
    }
    return $values;
}

function sourceImageUrl(?string $value): ?string
{
    $value=trim((string)$value);
    if ($value==='') return null;
    if (preg_match('~^https?://~i',$value)) return $value;
    return 'https://darkorchid-bat-862890.hostingersite.com/'.ltrim($value,'/');
}

function downloadImage(string $url): string
{
    $tmp=tempnam(sys_get_temp_dir(),'wisdom-image-');
    if ($tmp===false) throw new RuntimeException('Could not create a temporary image file.');
    $handle=fopen($tmp,'wb');
    if ($handle===false) throw new RuntimeException('Could not open a temporary image file.');
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_FILE=>$handle,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>60,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_USERAGENT=>'Wisdom News content migration/1.0']);
    $ok=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    $error=curl_error($curl);
    curl_close($curl);
    fclose($handle);
    if (!$ok || $status<200 || $status>=300 || filesize($tmp)===0) {
        @unlink($tmp);
        throw new RuntimeException("Image download failed ({$status}): {$error}");
    }
    return $tmp;
}

$targetRoot=dirname(__DIR__);
$sourceRoot=dirname(dirname($targetRoot)).'/darkorchid-bat-862890.hostingersite.com/public_html';
$sourceEnv=readEnvFile($sourceRoot.'/.env');
$source=new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$sourceEnv['DB_HOST']??'localhost',$sourceEnv['DB_PORT']??'3306',$sourceEnv['DB_NAME']??'wisdomnews'),
    $sourceEnv['DB_USER']??'',
    $sourceEnv['DB_PASSWORD']??'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
);
$target=Database::connection();

$backupTables=['website_settings','categories','subcategories','authors','media','articles','navigation_items','breaking_news'];
$backup=[];
foreach($backupTables as $table) $backup[$table]=$target->query("SELECT * FROM {$table}")->fetchAll();
$backupDir=$targetRoot.'/storage/backups';
if(!is_dir($backupDir) && !mkdir($backupDir,0750,true) && !is_dir($backupDir)) throw new RuntimeException('Could not create the backup directory.');
$backupPath=$backupDir.'/before-wisdom-migration-'.date('Ymd-His').'.json';
file_put_contents($backupPath,json_encode($backup,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));

$sourceCategories=$source->query("SELECT * FROM categories WHERE status='active' ORDER BY COALESCE(parent_id,0),sort_order,id")->fetchAll();
$sourcePosts=$source->query("SELECT * FROM posts WHERE status='published' ORDER BY published_at DESC,id DESC")->fetchAll();
$storage=new R2Storage();
$uploaded=0;
$imageFailures=[];

$target->beginTransaction();
try {
    $updateSetting=$target->prepare('UPDATE website_settings SET setting_value=? WHERE setting_key=?');
    $updateSetting->execute(['Wisdom News','site_name']);
    $updateSetting->execute(['உண்மையை அறிந்து, அறிவுடன் முன்னேறுவோம்.','site_description']);
    $updateSetting->execute(['அனைத்து உரிமைகளும் பாதுகாக்கப்பட்டவை.','copyright_text']);

    $authorId=$target->query("SELECT id FROM authors WHERE slug='wisdom-news' LIMIT 1")->fetchColumn();
    if(!$authorId){
        $stmt=$target->prepare('INSERT INTO authors(name,slug,bio,status) VALUES(?,?,?,?)');
        $stmt->execute(['Wisdom News','wisdom-news','Wisdom News editorial team.','active']);
        $authorId=(int)$target->lastInsertId();
    }

    $target->exec('UPDATE categories SET show_on_homepage=0');
    $categoryMap=[];
    $upsertCategory=$target->prepare('INSERT INTO categories(name,name_ta,slug,description,description_ta,sort_order,is_active,show_on_homepage,seo_title,seo_description) VALUES(?,?,?,?,?,?,1,1,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),name_ta=VALUES(name_ta),description=VALUES(description),description_ta=VALUES(description_ta),sort_order=VALUES(sort_order),is_active=1,show_on_homepage=1,seo_title=VALUES(seo_title),seo_description=VALUES(seo_description)');
    $findCategory=$target->prepare('SELECT id FROM categories WHERE slug=?');
    foreach($sourceCategories as $index=>$category){
        $description=trim((string)($category['description']??''));
        $upsertCategory->execute([$category['name'],$category['name'],$category['slug'],$description,$description,-100+$index,$category['name'],mb_substr(strip_tags($description),0,320)]);
        $findCategory->execute([$category['slug']]);
        $categoryMap[(int)$category['id']]=(int)$findCategory->fetchColumn();
    }

    $target->exec('UPDATE navigation_items SET is_active=0');
    $upsertNavigation=$target->prepare("INSERT INTO navigation_items(label,label_ta,type,category_id,sort_order,is_active,open_new_tab) VALUES(?,?,'category',?,?,1,0) ON DUPLICATE KEY UPDATE label=VALUES(label),label_ta=VALUES(label_ta),sort_order=VALUES(sort_order),is_active=1");
    $findNavigation=$target->prepare("SELECT id FROM navigation_items WHERE type='category' AND category_id=? LIMIT 1");
    $updateNavigation=$target->prepare('UPDATE navigation_items SET label=?,label_ta=?,sort_order=?,is_active=1 WHERE id=?');
    foreach($sourceCategories as $index=>$category){
        $categoryId=$categoryMap[(int)$category['id']];
        $findNavigation->execute([$categoryId]);
        $navigationId=$findNavigation->fetchColumn();
        if($navigationId) $updateNavigation->execute([$category['name'],$category['name'],$index,(int)$navigationId]);
        else $upsertNavigation->execute([$category['name'],$category['name'],$categoryId,$index]);
    }

    $demoSlugs=['city-councils-open-public-budget-access','manufacturers-energy-efficient-upgrades','researchers-map-neighbourhood-heat','community-leagues-youth-sport-calendar','independent-bookshops-slow-discovery'];
    $placeholders=implode(',',array_fill(0,count($demoSlugs),'?'));
    $target->prepare("UPDATE articles SET status='archived',is_featured=0,is_trending=0,is_breaking=0 WHERE slug IN ({$placeholders})")->execute($demoSlugs);

    $findArticle=$target->prepare('SELECT id,featured_image_id FROM articles WHERE slug=?');
    $findMedia=$target->prepare("SELECT id FROM media WHERE alt_text=? AND object_key LIKE 'imports/wisdom-news/%' ORDER BY id DESC LIMIT 1");
    $insertMedia=$target->prepare('INSERT INTO media(original_name,object_key,url,mime_type,size_bytes,width,height,alt_text,uploaded_by) VALUES(?,?,?,?,?,?,?,?,NULL)');
    $insertArticle=$target->prepare('INSERT INTO articles(title,title_ta,slug,summary,summary_ta,content,content_ta,category_id,author_id,featured_image_id,status,published_at,is_featured,is_trending,is_breaking,view_count,seo_title,seo_description) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $updateArticle=$target->prepare('UPDATE articles SET title=?,title_ta=?,summary=?,summary_ta=?,content=?,content_ta=?,category_id=?,author_id=?,featured_image_id=?,status=?,published_at=?,is_featured=?,is_trending=?,is_breaking=?,view_count=?,seo_title=?,seo_description=?,updated_at=NOW() WHERE id=?');

    foreach($sourcePosts as $index=>$post){
        $summary=trim((string)($post['short_description']??''));
        if($summary==='') $summary=trim(preg_replace('/\s+/u',' ',strip_tags((string)$post['content']))??'');
        $summary=mb_substr($summary,0,500);
        $categoryId=$categoryMap[(int)$post['category_id']]??reset($categoryMap);
        $findArticle->execute([$post['slug']]);
        $existing=$findArticle->fetch();
        $mediaId=$existing['featured_image_id']??null;
        $imageUrl=sourceImageUrl($post['featured_image']??null);
        if(!$mediaId && $imageUrl){
            $findMedia->execute([$post['title']]);
            $mediaId=$findMedia->fetchColumn()?:null;
            if(!$mediaId){
                try{
                    $tmp=downloadImage($imageUrl);
                    try{$media=$storage->importLocalImage($tmp,'imports/wisdom-news');}finally{@unlink($tmp);}
                    $originalName=mb_substr(basename((string)(parse_url($imageUrl,PHP_URL_PATH)?:'wisdom-news-image')),0,255);
                    $insertMedia->execute([$originalName,$media['object_key'],$media['url'],$media['mime_type'],$media['size_bytes'],$media['width'],$media['height'],$post['title']]);
                    $mediaId=(int)$target->lastInsertId();
                    $uploaded++;
                }catch(Throwable $e){$imageFailures[]=$post['slug'].': '.$e->getMessage();}
            }
        }
        $featured=$index<5?1:0;
        $trending=$index<6?1:0;
        $breaking=$index<6?1:0;
        $seoDescription=mb_substr(strip_tags($summary),0,320);
        if($existing){
            $updateArticle->execute([$post['title'],$post['title'],$summary,$summary,$post['content'],$post['content'],$categoryId,$authorId,$mediaId,'published',$post['published_at'],$featured,$trending,$breaking,(int)$post['views'],$post['title'],$seoDescription,(int)$existing['id']]);
        }else{
            $insertArticle->execute([$post['title'],$post['title'],$post['slug'],$summary,$summary,$post['content'],$post['content'],$categoryId,$authorId,$mediaId,'published',$post['published_at'],$featured,$trending,$breaking,(int)$post['views'],$post['title'],$seoDescription]);
        }
    }

    $target->exec('UPDATE breaking_news SET is_active=0');
    $findBreaking=$target->prepare('SELECT id FROM breaking_news WHERE url=? LIMIT 1');
    $insertBreaking=$target->prepare('INSERT INTO breaking_news(title,title_ta,url,priority,is_active) VALUES(?,?,?,?,1)');
    $updateBreaking=$target->prepare('UPDATE breaking_news SET title=?,title_ta=?,priority=?,is_active=1,starts_at=NULL,ends_at=NULL WHERE id=?');
    foreach(array_slice($sourcePosts,0,6) as $index=>$post){
        $url='/article/'.$post['slug'];
        $findBreaking->execute([$url]);
        $breakingId=$findBreaking->fetchColumn();
        if($breakingId) $updateBreaking->execute([$post['title'],$post['title'],100-$index,(int)$breakingId]);
        else $insertBreaking->execute([$post['title'],$post['title'],$url,100-$index]);
    }

    $target->commit();
}catch(Throwable $e){
    if($target->inTransaction()) $target->rollBack();
    fwrite(STDERR,'Migration failed: '.$e->getMessage().PHP_EOL);
    exit(1);
}

echo json_encode([
    'backup'=>$backupPath,
    'categories'=>count($sourceCategories),
    'articles'=>count($sourcePosts),
    'images_uploaded'=>$uploaded,
    'image_failures'=>$imageFailures,
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
