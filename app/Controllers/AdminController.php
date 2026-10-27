<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Services\HtmlSanitizer;
use App\Services\MediaStorage;
use App\Services\SiteAnalytics;
use App\Services\AdPlacements;
use App\Services\ArticleTitleTranslator;
use PDO;

final class AdminController
{
    private PDO $db;
    private array $resources;
    public function __construct()
    {
        $this->db=Database::connection();
        $this->resources=[
            'categories'=>['table'=>'categories','title'=>'Categories','roles'=>['super-admin','editor'],'fields'=>['name'=>'text','name_ta'=>'text','slug'=>'slug','description'=>'textarea','description_ta'=>'textarea','image_url'=>'url','sort_order'=>'number','is_active'=>'checkbox','show_on_homepage'=>'checkbox','seo_title'=>'text','seo_description'=>'textarea']],
            'subcategories'=>['table'=>'subcategories','title'=>'Subcategories','roles'=>['super-admin','editor'],'required'=>['category_id','name'],'fields'=>['category_id'=>['relation','categories','name'],'name'=>'text','slug'=>'slug','description'=>'textarea','sort_order'=>'number','is_active'=>'checkbox']],
            'authors'=>['table'=>'authors','title'=>'Authors','roles'=>['super-admin','editor'],'fields'=>['name'=>'text','slug'=>'slug','email'=>'email','bio'=>'textarea','profile_image_url'=>'url','social_links'=>'textarea','status'=>['select',['active','inactive']]]],
            'tags'=>['table'=>'tags','title'=>'Tags','roles'=>['super-admin','editor'],'fields'=>['name'=>'text','slug'=>'slug','description'=>'textarea']],
            'breaking-news'=>['table'=>'breaking_news','title'=>'Breaking News','roles'=>['super-admin','editor'],'fields'=>['title'=>'text','title_ta'=>'text','url'=>'url','priority'=>'number','starts_at'=>'datetime-local','ends_at'=>'datetime-local','is_active'=>'checkbox']],
            'advertisements'=>['table'=>'advertisements','title'=>'Advertisements','roles'=>['super-admin'],'required'=>['name','position_id'],'fields'=>['name'=>'text','type'=>['select',['image','html']],'image_url'=>'url','html_content'=>'textarea','destination_url'=>'url','position_id'=>['relation','advertisement_positions','name'],'starts_at'=>'datetime-local','ends_at'=>'datetime-local','status'=>['select',['active','inactive']],'priority'=>'number','alt_text'=>'text']],
            'ad-positions'=>['table'=>'advertisement_positions','title'=>'Advertisement Positions','roles'=>['super-admin'],'fields'=>['name'=>'text','slug'=>'slug','description'=>'textarea','recommended_width'=>'number','recommended_height'=>'number','is_active'=>'checkbox']],
            'pages'=>['table'=>'pages','title'=>'Pages','roles'=>['super-admin','editor'],'fields'=>['title'=>'text','slug'=>'slug','content'=>'richtext','status'=>['select',['draft','published']],'seo_title'=>'text','seo_description'=>'textarea']],
            'navigation'=>['table'=>'navigation_items','title'=>'Navigation','roles'=>['super-admin'],'fields'=>['label'=>'text','label_ta'=>'text','type'=>['select',['custom','category','page']],'url'=>'text','category_id'=>['relation','categories','name'],'page_id'=>['relation','pages','title'],'sort_order'=>'number','is_active'=>'checkbox','open_new_tab'=>'checkbox']],
            'comments'=>['table'=>'comments','title'=>'Comments','roles'=>['super-admin','editor'],'required'=>['article_id','author_name','author_email','content'],'fields'=>['article_id'=>['relation','articles','title'],'author_name'=>'text','author_email'=>'email','content'=>'textarea','status'=>['select',['pending','approved','rejected','spam']]]],
            'users'=>['table'=>'admin_users','title'=>'Admin Users','roles'=>['super-admin'],'required'=>['name','email','role_id'],'fields'=>['name'=>'text','email'=>'email','role_id'=>['relation','roles','name'],'status'=>['select',['active','inactive']],'password'=>'password']],
            'activity'=>['table'=>'activity_logs','title'=>'Activity Logs','roles'=>['super-admin'],'readonly'=>true,'fields'=>[]],
        ];
    }
    public function loginForm(): void { if(Auth::check())redirect('/admin'); render_admin('login',['pageTitle'=>'Sign in','loginPage'=>true]); }
    public function login(): void { Csrf::verify(); if(!Auth::attempt((string)($_POST['email']??''),(string)($_POST['password']??''))){flash('error','Invalid email or password.');redirect('/admin/login');}redirect('/admin'); }
    public function logout(): void { Auth::require();Csrf::verify();activity('logout','Signed out');Auth::logout();redirect('/admin/login'); }
    public function dashboard(): void
    {
        Auth::require();$own=Auth::user()['role']==='author';$where=$own?' WHERE created_by='.(int)Auth::id():'';
        $stats=['articles'=>(int)$this->db->query('SELECT COUNT(*) FROM articles'.$where)->fetchColumn()];
        foreach(['categories','authors','advertisements'] as $table)$stats[$table]=(int)$this->db->query('SELECT COUNT(*) FROM '.$table.($table==='authors'?' WHERE status="active"':''))->fetchColumn();
        foreach(['published','draft','scheduled'] as $status)$stats[$status==='draft'?'drafts':$status]=(int)$this->db->query('SELECT COUNT(*) FROM articles'.($where?$where.' AND':' WHERE').' status='.$this->db->quote($status))->fetchColumn();
        $recent=$this->db->query('SELECT a.id,a.title,a.title_ta,a.status,a.updated_at,au.name author_name FROM articles a JOIN authors au ON au.id=a.author_id'.($own?' WHERE a.created_by='.(int)Auth::id():'').' ORDER BY a.updated_at DESC,a.id DESC LIMIT 8')->fetchAll();
        $traffic=SiteAnalytics::summary();
        $activeAds=(int)$this->db->query('SELECT COUNT(*) FROM advertisements ad JOIN advertisement_positions ap ON ap.id=ad.position_id WHERE ad.status="active" AND ap.is_active=1 AND (ad.starts_at IS NULL OR ad.starts_at<=NOW()) AND (ad.ends_at IS NULL OR ad.ends_at>=NOW())')->fetchColumn();
        render_admin('dashboard',compact('stats','recent','traffic','activeAds','own')+['pageTitle'=>'Dashboard']);
    }
    private function pagination(int $total,int $perPage=25): array
    {
        $page=min(max(1,(int)($_GET['page']??1)),max(1,(int)ceil($total/$perPage)));
        return paginate($total,$page,$perPage);
    }
    public function articles(): void
    {
        Auth::require();$q=trim((string)($_GET['q']??''));$status=(string)($_GET['status']??'');$where=[];$params=[];
        if($q!==''){$where[]='(a.title LIKE ? OR a.title_ta LIKE ?)';$params[]='%'.$q.'%';$params[]='%'.$q.'%';}
        if(in_array($status,['draft','published','scheduled','archived'],true)){$where[]='a.status=?';$params[]=$status;}
        if(Auth::user()['role']==='author'){$where[]='a.created_by=?';$params[]=Auth::id();}
        $filter=$where?' WHERE '.implode(' AND ',$where):'';
        $count=$this->db->prepare('SELECT COUNT(*) FROM articles a'.$filter);$count->execute($params);$pagination=$this->pagination((int)$count->fetchColumn());
        $s=$this->db->prepare('SELECT a.*,c.name category_name,au.name author_name FROM articles a JOIN categories c ON c.id=a.category_id JOIN authors au ON au.id=a.author_id'.$filter.' ORDER BY a.updated_at DESC,a.id DESC LIMIT ? OFFSET ?');
        foreach($params as $index=>$value)$s->bindValue($index+1,$value);
        $s->bindValue(count($params)+1,$pagination['per_page'],PDO::PARAM_INT);$s->bindValue(count($params)+2,($pagination['page']-1)*$pagination['per_page'],PDO::PARAM_INT);$s->execute();
        render_admin('articles',['items'=>$s->fetchAll(),'pagination'=>$pagination,'pageTitle'=>'Articles','q'=>$q,'status'=>$status]);
    }
    public function articleForm(string $id='0'): void
    {
        Auth::require();$article=null;
        if((int)$id){$s=$this->db->prepare('SELECT * FROM articles WHERE id=?');$s->execute([(int)$id]);$article=$s->fetch();if(!$article)abort(404);if(Auth::user()['role']==='author'&&(int)$article['created_by']!==Auth::id())abort(403);}
        $lookups=[];foreach(['categories','subcategories','authors','media','tags'] as $table)$lookups[$table]=$this->db->query("SELECT * FROM {$table} ORDER BY ".($table==='media'?'created_at DESC':'name'))->fetchAll();
        $selectedTags=[];$selectedCategories=[];
        if($article){$s=$this->db->prepare('SELECT tag_id FROM article_tags WHERE article_id=?');$s->execute([$article['id']]);$selectedTags=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
            if($this->db->query("SHOW TABLES LIKE 'article_categories'")->fetchColumn()){$s=$this->db->prepare('SELECT category_id FROM article_categories WHERE article_id=?');$s->execute([$article['id']]);$selectedCategories=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
        }
        $r2Configured=MediaStorage::configured();$storageLocal=MediaStorage::local();
        $translationEnabled=Env::bool('ARTICLE_TITLE_TRANSLATION_ENABLED',false);
        $englishTitle=$article?ArticleTitleTranslator::cached((string)($article['title_ta']?:$article['title'])):null;
        render_admin('article-form',compact('article','lookups','selectedTags','selectedCategories','r2Configured','storageLocal','englishTitle','translationEnabled')+['pageTitle'=>$article?'கட்டுரையைத் திருத்து':'புதிய கட்டுரை']);
    }
    public function articleTranslateTitle(): void
    {
        if(!Auth::check())$this->articleJson(['error'=>'உங்கள் அமர்வு முடிந்துவிட்டது. மீண்டும் உள்நுழையவும்.'],401);
        $token=(string)($_POST['_csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??'');
        $expected=(string)($_SESSION['_csrf']??'');
        if($expected===''||$token===''||!hash_equals($expected,$token))$this->articleJson(['error'=>'பக்கத்தைப் புதுப்பித்து மீண்டும் முயற்சிக்கவும்.'],419);
        Csrf::verify();
        $id=(int)($_POST['id']??0);
        if($id){$statement=$this->db->prepare('SELECT created_by FROM articles WHERE id=?');$statement->execute([$id]);$owner=$statement->fetchColumn();if($owner===false)$this->articleJson(['error'=>'கட்டுரை கிடைக்கவில்லை.'],404);if(Auth::user()['role']==='author'&&(int)$owner!==Auth::id())$this->articleJson(['error'=>'இந்தக் கட்டுரையைத் திருத்த அனுமதி இல்லை.'],403);}
        $title=ArticleTitleTranslator::normalize((string)($_POST['title']??''));
        if($title===''||mb_strlen($title)>255)$this->articleJson(['error'=>'255 எழுத்துகளுக்குள் ஒரு தலைப்பை உள்ளிடவும்.'],422);
        if(!Env::bool('ARTICLE_TITLE_TRANSLATION_ENABLED',false)&&preg_match('/[\x{0B80}-\x{0BFF}]/u',$title)&&ArticleTitleTranslator::cached($title)===null)$this->articleJson(['error'=>'தானியங்கி மொழிபெயர்ப்பு இன்னும் செயல்படுத்தப்படவில்லை. இணைப்புக்கான ஆங்கிலத் தலைப்பை உள்ளிடவும்.'],503);
        // Translation can take a few seconds; do not lock the editor's other requests.
        session_write_close();
        try{
            $english=(new ArticleTitleTranslator())->translate($title);
            $this->articleJson(['english_title'=>$english,'slug'=>$this->uniqueArticleSlug(ArticleTitleTranslator::asciiSlug($english),$id)]);
        }catch(\InvalidArgumentException){$this->articleJson(['error'=>'தலைப்பைச் சரிபார்த்து மீண்டும் முயற்சிக்கவும்.'],422);}
        catch(\Throwable){$this->articleJson(['error'=>'மொழிபெயர்ப்பு தற்போது கிடைக்கவில்லை. மீண்டும் முயற்சிக்கவும் அல்லது ஆங்கிலத் தலைப்பை உள்ளிடவும்.'],503);}
    }
    public function articleImageUpload(): void
    {
        if(!Auth::check())$this->articleJson(['error'=>'உங்கள் அமர்வு முடிந்துவிட்டது. மீண்டும் உள்நுழையவும்.'],401);
        $token=(string)($_POST['_csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??'');
        $expected=(string)($_SESSION['_csrf']??'');
        if($expected===''||$token===''||!hash_equals($expected,$token))$this->articleJson(['error'=>'பக்கத்தைப் புதுப்பித்து மீண்டும் முயற்சிக்கவும்.'],419);
        Csrf::verify();
        $alt=trim((string)($_POST['alt_text']??''));
        if($alt===''||mb_strlen($alt)>255)$this->articleJson(['error'=>'படத்திற்கான விளக்கத்தை 255 எழுத்துகளுக்குள் உள்ளிடவும்.'],422);
        if(!MediaStorage::configured())$this->articleJson(['error'=>'படப் பதிவேற்றம் தற்போது கிடைக்கவில்லை. சிறிது நேரம் கழித்து முயற்சிக்கவும்.'],503);
        $storage=new MediaStorage();
        try{
            $media=$storage->upload($_FILES['image']??[],'articles');
        }catch(\InvalidArgumentException){$this->articleJson(['error'=>'8 MB-க்குள் உள்ள சரியான JPEG, PNG, WebP அல்லது GIF படத்தைத் தேர்ந்தெடுக்கவும்.'],422);}
        catch(\Throwable){$this->articleJson(['error'=>'படத்தைப் பதிவேற்ற முடியவில்லை. மீண்டும் முயற்சிக்கவும்.'],503);}
        $media['original_name']=mb_substr(basename((string)($_FILES['image']['name']??'article-image')),0,255);
        $media['alt_text']=$alt;$media['uploaded_by']=Auth::id();
        try{
            $statement=$this->db->prepare('INSERT INTO media(original_name,object_key,url,mime_type,size_bytes,width,height,alt_text,uploaded_by) VALUES(:original_name,:object_key,:url,:mime_type,:size_bytes,:width,:height,:alt_text,:uploaded_by)');
            $statement->execute($media);$id=(int)$this->db->lastInsertId();
        }catch(\Throwable){
            // This upload has not been returned to the editor or linked to a story.
            try{$storage->delete($media);}catch(\Throwable){error_log('Could not remove an article image after its media record failed to save.');}
            $this->articleJson(['error'=>'பட விவரங்களைச் சேமிக்க முடியவில்லை. மீண்டும் பதிவேற்றவும்.'],500);
        }
        $this->articleJson(['id'=>$id,'url'=>$media['url'],'alt'=>$alt,'width'=>(int)$media['width'],'height'=>(int)$media['height'],'original_name'=>$media['original_name']],201);
    }
    private function articleJson(array $data,int $status=200): never
    {
        http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
    }
    private function uniqueArticleSlug(string $slug,int $id): string
    {
        $base=rtrim(mb_substr($slug,0,280),'-');
        if($base==='')throw new \InvalidArgumentException('Enter a valid article link.');
        $statement=$this->db->prepare('SELECT COUNT(*) FROM articles WHERE slug=? AND id<>?');
        $candidate=$base;$suffix=1;
        while(true){$statement->execute([$candidate,$id]);if(!(int)$statement->fetchColumn())return $candidate;$ending='-'.(++$suffix);$candidate=rtrim(mb_substr($base,0,280-strlen($ending)),'-').$ending;}
    }
    public function articleSave(): void
    {
        Auth::require();
        Csrf::verify();
        $id=(int)($_POST['id']??0);
        $existing=null;
        if($id){$ownerStatement=$this->db->prepare('SELECT created_by,title,title_ta,slug,status FROM articles WHERE id=?');$ownerStatement->execute([$id]);$existing=$ownerStatement->fetch();if(!$existing)abort(404);if(Auth::user()['role']==='author'&&(int)$existing['created_by']!==Auth::id())abort(403);}
        $tamilOnly=($_POST['tamil_only']??'')==='1';
        $title=trim((string)($_POST['title']??''));
        $titleTa=trim((string)($_POST['title_ta']??''))?:null;
        $summary=trim((string)($_POST['summary']??''));
        $summaryTa=trim((string)($_POST['summary_ta']??''))?:null;
        $content=HtmlSanitizer::clean((string)($_POST['content']??''));
        $contentTa=trim((string)($_POST['content_ta']??''))!==''?HtmlSanitizer::clean((string)$_POST['content_ta']):null;
        if($tamilOnly){
            if($summary==='')$summary=excerpt($content,220)?:$title;
            $titleTa=$title;$summaryTa=$summary;$contentTa=$content;
        }
        $categoryId=(int)($_POST['category_id']??0);
        $authorId=(int)($_POST['author_id']??0);
        if($title===''||mb_strlen($title)>255||$summary===''||mb_strlen($summary)>500||$content===''||!$categoryId||!$authorId)abort(422,'Title, summary, body, category, and author are required.');
        if(($titleTa!==null&&mb_strlen($titleTa)>255)||($summaryTa!==null&&mb_strlen($summaryTa)>500))abort(422,'Tamil headline or summary is too long.');
        $canonical=trim((string)($_POST['canonical_url']??''));
        if($canonical!==''&&!filter_var($canonical,FILTER_VALIDATE_URL))abort(422,'The canonical URL is invalid.');
        $status=in_array($_POST['status']??'draft',['draft','published','scheduled','archived'],true)?$_POST['status']:'draft';
        $parseArticleDate=static function(string $value,string $message): ?string {
            if($value==='')return null;
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value);
            $errors=\DateTimeImmutable::getLastErrors();
            if(!$date||($errors!==false&&($errors['warning_count']>0||$errors['error_count']>0))||$date->format('Y-m-d\TH:i')!==$value)abort(422,$message);
            return $date->format('Y-m-d H:i:s');
        };
        $scheduled=$parseArticleDate((string)($_POST['scheduled_at']??''),'The scheduled date is invalid.');
        if($status==='scheduled'&&!$scheduled)abort(422,'A scheduled article requires a scheduled date.');
        $published=$parseArticleDate((string)($_POST['published_at']??''),'The publish date is invalid.')??($status==='published'?date('Y-m-d H:i:s'):null);
        $translationWarning=false;
        $submittedSlug=trim((string)($_POST['slug']??''));
        if($tamilOnly){
            $manual=($_POST['slug_mode']??'auto')==='manual';
            $titleUnchanged=$existing&&ArticleTitleTranslator::normalize($title)===ArticleTitleTranslator::normalize((string)($existing['title_ta']?:$existing['title']));
            if($manual&&$submittedSlug!==''){
                $slug=slugify($submittedSlug);
            }elseif($titleUnchanged&&($submittedSlug===''||$submittedSlug===$existing['slug'])&&($existing['status']!=='draft'||$status==='draft'||preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',(string)$existing['slug']))){
                // Editing the body or settings must not change a published article's URL.
                $slug=(string)$existing['slug'];
            }else{
                $english=trim((string)($_POST['english_title']??''));
                $source=ArticleTitleTranslator::normalize((string)($_POST['translated_source']??''));
                try{
                    if($source!==ArticleTitleTranslator::normalize($title)||$english===''||preg_match('/[\x{0B80}-\x{0BFF}]/u',$english))$english=(new ArticleTitleTranslator())->translate($title);
                    $slug=$this->uniqueArticleSlug(ArticleTitleTranslator::asciiSlug($english),$id);
                }catch(\Throwable){
                    if($status!=='draft')abort(422,'ஆங்கில இணைப்பை உருவாக்க முடியவில்லை. மீண்டும் முயற்சிக்கவும் அல்லது இணைப்பை நீங்களே உள்ளிடவும். வரைவாகவும் சேமிக்கலாம்.');
                    $slug=$this->uniqueArticleSlug(slugify($title),$id);$translationWarning=true;
                }
            }
        }else{$slug=slugify((string)($submittedSlug?:$title));}
        if(mb_strlen($slug)>280)abort(422,'Use a URL slug of up to 280 characters.');
        $duplicate=$this->db->prepare('SELECT COUNT(*) FROM articles WHERE slug=? AND id<>?');$duplicate->execute([$slug,$id]);if($duplicate->fetchColumn())abort(422,'This URL slug is already used by another article.');
        $authorCheck=$this->db->prepare('SELECT COUNT(*) FROM authors WHERE id=?');$authorCheck->execute([$authorId]);if(!$authorCheck->fetchColumn())abort(422,'Choose an existing author.');
        $tagIds=array_values(array_filter(array_unique(array_map('intval',(array)($_POST['tags']??[])))));
        $categoryIds=array_values(array_unique([$categoryId,...array_map('intval',(array)($_POST['additional_categories']??[]))]));
        foreach(['categories'=>$categoryIds,'tags'=>$tagIds] as $table=>$ids){if(!$ids)continue;$check=$this->db->prepare('SELECT COUNT(*) FROM '.$table.' WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$check->execute($ids);if((int)$check->fetchColumn()!==count($ids))abort(422,'Choose valid '.$table.'.');}
        $subcategory=($_POST['subcategory_id']??'')?:null;
        if($subcategory){$s=$this->db->prepare('SELECT COUNT(*) FROM subcategories WHERE id=? AND category_id=? AND is_active=1');$s->execute([(int)$subcategory,$categoryId]);if(!(int)$s->fetchColumn())abort(422,'The subcategory does not belong to the selected category.');}
        $featuredImageId=($_POST['featured_image_id']??'')?:null;
        $upload=$_FILES['featured_image_upload']??null;
        if($upload&&($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            try{
                $media=(new MediaStorage())->upload($upload,'articles');
                $media['original_name']=mb_substr(basename((string)($upload['name']??'article-image')),0,255);
                $media['alt_text']=trim((string)($_POST['featured_image_alt']??''))?:$title;
                $media['uploaded_by']=Auth::id();
                $insert=$this->db->prepare('INSERT INTO media(original_name,object_key,url,mime_type,size_bytes,width,height,alt_text,uploaded_by) VALUES(:original_name,:object_key,:url,:mime_type,:size_bytes,:width,:height,:alt_text,:uploaded_by)');
                $insert->execute($media);
                $featuredImageId=(int)$this->db->lastInsertId();
            }catch(\Throwable $e){flash('error',$e->getMessage());redirect($id?'/admin/articles/edit/'.$id:'/admin/articles/create');}
        }
        $this->db->beginTransaction();
        $data=[$title,$titleTa,$slug,$summary,$summaryTa,$content,$contentTa,$categoryId,$subcategory,$authorId,$featuredImageId,trim((string)($_POST['image_caption']??'')),$status,$published,$scheduled,(int)isset($_POST['is_featured']),(int)isset($_POST['is_trending']),(int)isset($_POST['is_breaking']),(int)isset($_POST['is_editors_choice']),trim((string)($_POST['seo_title']??'')),trim((string)($_POST['seo_description']??'')),trim((string)($_POST['seo_keywords']??'')),$canonical];
        if($id){$s=$this->db->prepare('SELECT created_by FROM articles WHERE id=?');$s->execute([$id]);$owner=$s->fetchColumn();if($owner===false)abort(404);if(Auth::user()['role']==='author'&&(int)$owner!==Auth::id())abort(403);$sql='UPDATE articles SET title=?,title_ta=?,slug=?,summary=?,summary_ta=?,content=?,content_ta=?,category_id=?,subcategory_id=?,author_id=?,featured_image_id=?,image_caption=?,status=?,published_at=?,scheduled_at=?,is_featured=?,is_trending=?,is_breaking=?,is_editors_choice=?,seo_title=?,seo_description=?,seo_keywords=?,canonical_url=?,updated_by=?,updated_at=NOW() WHERE id=?';$data[] = Auth::id();$data[]=$id;$this->db->prepare($sql)->execute($data);$action='updated';}else{$sql='INSERT INTO articles(title,title_ta,slug,summary,summary_ta,content,content_ta,category_id,subcategory_id,author_id,featured_image_id,image_caption,status,published_at,scheduled_at,is_featured,is_trending,is_breaking,is_editors_choice,seo_title,seo_description,seo_keywords,canonical_url,created_by,updated_by) VALUES('.implode(',',array_fill(0,25,'?')).')';$data[]=Auth::id();$data[]=Auth::id();$this->db->prepare($sql)->execute($data);$id=(int)$this->db->lastInsertId();$action='created';}
        $this->db->prepare('DELETE FROM article_tags WHERE article_id=?')->execute([$id]);$tagStmt=$this->db->prepare('INSERT INTO article_tags(article_id,tag_id) VALUES(?,?)');foreach(array_unique(array_map('intval',$_POST['tags']??[])) as $tagId)if($tagId)$tagStmt->execute([$id,$tagId]);if($this->db->query("SHOW TABLES LIKE 'article_categories'")->fetchColumn()){$this->db->prepare('DELETE FROM article_categories WHERE article_id=?')->execute([$id]);$membership=$this->db->prepare('INSERT INTO article_categories(article_id,category_id) VALUES(?,?)');foreach($categoryIds as $category)$membership->execute([$id,$category]);}
        $this->db->commit();activity('article_'.$action,"Article {$action}: {$title}",'article',$id);flash('success',$tamilOnly?'கட்டுரை சேமிக்கப்பட்டது.':'Article saved successfully.');if($translationWarning)flash('error','வரைவு சேமிக்கப்பட்டது. மொழிபெயர்ப்பு கிடைக்காததால் தற்காலிக தமிழிணைப்பு பயன்படுத்தப்பட்டுள்ளது. வெளியிடுவதற்கு முன் ஆங்கில இணைப்பை உருவாக்கவும்.');redirect('/admin/articles/edit/'.$id);
    }
    public function articleDelete(string $id): void { Auth::require(['super-admin','editor']);Csrf::verify();$this->db->prepare('DELETE FROM articles WHERE id=?')->execute([(int)$id]);activity('article_deleted','Article deleted','article',(int)$id);flash('success','Article deleted.');redirect('/admin/articles'); }
    public function media(): void
    {
        Auth::require();$q=trim((string)($_GET['q']??''));$params=['%'.$q.'%','%'.$q.'%'];
        $count=$this->db->prepare('SELECT COUNT(*) FROM media WHERE original_name LIKE ? OR alt_text LIKE ?');$count->execute($params);$pagination=$this->pagination((int)$count->fetchColumn(),24);
        $s=$this->db->prepare('SELECT * FROM media WHERE original_name LIKE ? OR alt_text LIKE ? ORDER BY created_at DESC,id DESC LIMIT ? OFFSET ?');$s->bindValue(1,$params[0]);$s->bindValue(2,$params[1]);$s->bindValue(3,$pagination['per_page'],PDO::PARAM_INT);$s->bindValue(4,($pagination['page']-1)*$pagination['per_page'],PDO::PARAM_INT);$s->execute();
        render_admin('media',['items'=>$s->fetchAll(),'pagination'=>$pagination,'q'=>$q,'pageTitle'=>'Media library','r2Configured'=>MediaStorage::configured(),'storageLocal'=>MediaStorage::local()]);
    }
    public function mediaUpload(): void
    {
        Auth::require();Csrf::verify();
        try{$alt=trim((string)($_POST['alt_text']??''));if($alt===''||mb_strlen($alt)>255)throw new \InvalidArgumentException('Describe the image in alt text, using up to 255 characters.');
            $data=(new MediaStorage())->upload($_FILES['image']??[]);$data['original_name']=mb_substr(basename((string)($_FILES['image']['name']??'')),0,255);$data['alt_text']=$alt;$data['uploaded_by']=Auth::id();
            $s=$this->db->prepare('INSERT INTO media(original_name,object_key,url,mime_type,size_bytes,width,height,alt_text,uploaded_by) VALUES(:original_name,:object_key,:url,:mime_type,:size_bytes,:width,:height,:alt_text,:uploaded_by)');$s->execute($data);flash('success','Image uploaded.');
        }catch(\Throwable $e){flash('error',$e->getMessage());}redirect('/admin/media');
    }
    public function mediaDelete(string $id): void
    {
        Auth::require(['super-admin','editor']);Csrf::verify();$s=$this->db->prepare('SELECT * FROM media WHERE id=?');$s->execute([(int)$id]);$media=$s->fetch();
        if($media){$used=$this->db->prepare('SELECT (SELECT COUNT(*) FROM articles WHERE featured_image_id=? OR content LIKE ? OR content_ta LIKE ?)+(SELECT COUNT(*) FROM pages WHERE content LIKE ?)+(SELECT COUNT(*) FROM advertisements WHERE image_url IN (?,?))+(SELECT COUNT(*) FROM authors WHERE profile_image_url IN (?,?))+(SELECT COUNT(*) FROM categories WHERE image_url IN (?,?))');$used->execute([(int)$id,'%'.$media['url'].'%','%'.$media['url'].'%','%'.$media['url'].'%',$media['url'],url($media['url']),$media['url'],url($media['url']),$media['url'],url($media['url'])]);
            if((int)$used->fetchColumn()>0){flash('error','This image is used by a story, page, profile, or advertisement. Replace it before deleting.');redirect('/admin/media');}
            try{(new MediaStorage())->delete($media);$this->db->prepare('DELETE FROM media WHERE id=?')->execute([(int)$id]);activity('media_deleted','Media deleted','media',(int)$id);flash('success','Image deleted.');}catch(\Throwable $e){flash('error',$e->getMessage());}
        }redirect('/admin/media');
    }
    public function settings(): void { Auth::require(['super-admin']);$items=$this->db->query('SELECT * FROM website_settings ORDER BY setting_group,setting_key')->fetchAll();render_admin('settings',compact('items')+['pageTitle'=>'Website settings']); }
    public function settingsSave(): void { Auth::require(['super-admin']);Csrf::verify();$s=$this->db->prepare('UPDATE website_settings SET setting_value=?,updated_by=? WHERE setting_key=?');foreach($_POST['settings']??[] as $key=>$value)$s->execute([trim((string)$value),Auth::id(),$key]);activity('settings_updated','Website settings updated');flash('success','Settings saved.');redirect('/admin/settings'); }
    private function config(string $resource): array { if(!isset($this->resources[$resource]))abort(404);$c=$this->resources[$resource];Auth::require($c['roles']);return $c; }
    public function resource(string $resource): void
    {
        $c=$this->config($resource);$q=trim((string)($_GET['q']??''));$field='';
        foreach(['name','title','label'] as $candidate)if(isset($c['fields'][$candidate])){$field=$candidate;break;}
        $filter=$q!==''&&$field!==''?' WHERE '.$field.' LIKE ?':'';$params=$filter?['%'.$q.'%']:[];
        $count=$this->db->prepare('SELECT COUNT(*) FROM '.$c['table'].$filter);$count->execute($params);$pagination=$this->pagination((int)$count->fetchColumn());
        $s=$this->db->prepare('SELECT * FROM '.$c['table'].$filter.' ORDER BY id DESC LIMIT ? OFFSET ?');
        if($params)$s->bindValue(1,$params[0]);$s->bindValue(count($params)+1,$pagination['per_page'],PDO::PARAM_INT);$s->bindValue(count($params)+2,($pagination['page']-1)*$pagination['per_page'],PDO::PARAM_INT);$s->execute();$items=$s->fetchAll();
        if($resource==='users')foreach($items as &$item)unset($item['password_hash']);unset($item);
        $placements=$this->db->query('SELECT * FROM advertisement_positions ORDER BY id')->fetchAll();$placementMap=array_column($placements,null,'id');$definitions=AdPlacements::definitions();
        $articleSlug=$this->db->query('SELECT slug FROM articles WHERE status="published" AND published_at<=NOW() ORDER BY published_at DESC LIMIT 1')->fetchColumn();
        $categorySlug=$this->db->query('SELECT slug FROM categories WHERE is_active=1 ORDER BY sort_order LIMIT 1')->fetchColumn();
        $authorSlug=$this->db->query('SELECT slug FROM authors WHERE status="active" ORDER BY id DESC LIMIT 1')->fetchColumn();
        $previews=['all'=>'/','home'=>'/','category'=>$categorySlug?'/category/'.$categorySlug:'/','article'=>$articleSlug?'/article/'.$articleSlug:'/','archive'=>$authorSlug?'/author/'.$authorSlug:'/','page'=>'/page/about-us','search'=>'/search'];
        $view=match($resource){'ad-positions'=>'ad-positions','advertisements'=>'advertisements',default=>'resource-list'};
        render_admin($view,compact('resource','items','pagination','q','placements','placementMap','definitions','previews')+['config'=>$c,'pageTitle'=>$c['title']]);
    }
    public function resourceForm(string $resource,string $id='0'): void { $c=$this->config($resource);if($c['readonly']??false)abort(403);$item=null;if((int)$id){$s=$this->db->prepare('SELECT * FROM '.$c['table'].' WHERE id=?');$s->execute([(int)$id]);$item=$s->fetch();if(!$item)abort(404);}$relations=[];foreach($c['fields'] as $field=>$type)if(is_array($type)&&$type[0]==='relation')$relations[$field]=$this->db->query('SELECT id,'.$type[2].' label FROM '.$type[1].' ORDER BY '.$type[2])->fetchAll();$mediaOptions=$resource==='advertisements'?$this->db->query('SELECT id,original_name,url FROM media ORDER BY created_at DESC')->fetchAll():[];render_admin('resource-form',compact('resource','item','relations','mediaOptions')+['config'=>$c,'pageTitle'=>($item?'Edit ':'New ').rtrim($c['title'],'s')]); }
    public function resourceSave(string $resource): void
    {
        $c=$this->config($resource);if($c['readonly']??false)abort(403);Csrf::verify();$id=(int)($_POST['id']??0);foreach($c['required']??[] as $required)if(trim((string)($_POST[$required]??''))==='')abort(422,ucwords(str_replace('_',' ',$required)).' is required.');$values=[];$columns=[];foreach($c['fields'] as $field=>$type){if($field==='password'){if(trim((string)($_POST[$field]??''))!==''){if(strlen((string)$_POST[$field])<12)abort(422,'Passwords must contain at least 12 characters.');$columns[]='password_hash';$values[]=password_hash((string)$_POST[$field],PASSWORD_DEFAULT);}continue;}$columns[]=$field;$kind=is_array($type)?$type[0]:$type;if($kind==='checkbox')$value=isset($_POST[$field])?1:0;elseif($kind==='richtext')$value=HtmlSanitizer::clean((string)($_POST[$field]??''));elseif($kind==='slug')$value=slugify((string)(($_POST[$field]??'')?:($_POST['name']??$_POST['title']??'')));else{$value=trim((string)($_POST[$field]??''));if($kind==='number'&&$value==='')$value='0';if(($value===''&&in_array($kind,['relation','datetime-local','url','email'],true))||($field==='social_links'&&$value===''))$value=null;if($kind==='datetime-local'&&$value!==null){$value=str_replace('T',' ',$value).':00';if(!\DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$value))abort(422,'Please enter a valid date and time.');}if($field==='social_links'&&$value!==null&&json_decode($value,true)===null)abort(422,'Social links must be valid JSON.');if($value!==null&&$kind==='email'&&!filter_var($value,FILTER_VALIDATE_EMAIL))abort(422,'Please enter a valid email address.');if($value!==null&&$kind==='url'&&!preg_match('#^/(?!/)[^\\s]*$#u',(string)$value)&&!(filter_var($value,FILTER_VALIDATE_URL)&&in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['http','https'],true)))abort(422,'Please enter a valid URL.');if($value!==null&&$kind==='number'&&filter_var($value,FILTER_VALIDATE_INT)===false)abort(422,'Please enter a valid whole number.');if($kind==='select'&&!in_array($value,$type[1],true))abort(422,'Please select a valid option.');if($value!==null&&mb_strlen((string)$value)>10000)abort(422,'One of the submitted values is too long.');}$values[]=$value;}
        if($resource==='advertisements'){
            $data=array_combine($columns,$values);$slot=$this->db->prepare('SELECT COUNT(*) FROM advertisement_positions WHERE id=?');$slot->execute([(int)$data['position_id']]);if(!$slot->fetchColumn())abort(422,'Choose an existing advertisement position.');
            if(!empty($data['starts_at'])&&!empty($data['ends_at'])&&strtotime($data['ends_at'])<=strtotime($data['starts_at']))abort(422,'The advertisement end must be later than its start.');
            if($data['status']==='active'&&($data['type']==='image'?empty($data['image_url']):trim((string)$data['html_content'])===''))abort(422,'Add a creative before activating this advertisement.');
        }
        if($resource==='ad-positions'&&$id){$original=$this->db->prepare('SELECT slug FROM advertisement_positions WHERE id=?');$original->execute([$id]);$oldSlug=$original->fetchColumn();$data=array_combine($columns,$values);if(isset(AdPlacements::definitions()[$oldSlug])&&$data['slug']!==$oldSlug)abort(422,'Keep the connected position slug unchanged.');}
        if($resource==='users'&&!$id&&!in_array('password_hash',$columns,true))abort(422,'A password is required for new users.');
        if($id){$set=implode(',',array_map(fn($x)=>"{$x}=?",$columns));$values[]=$id;$this->db->prepare('UPDATE '.$c['table'].' SET '.$set.' WHERE id=?')->execute($values);}else{$this->db->prepare('INSERT INTO '.$c['table'].' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')')->execute($values);$id=(int)$this->db->lastInsertId();}activity('resource_saved',$c['title'].' saved',$c['table'],$id);flash('success','Saved successfully.');redirect('/admin/'.$resource.'/edit/'.$id);
    }
    public function resourceDelete(string $resource,string $id): void { $c=$this->config($resource);if($c['readonly']??false)abort(403);Csrf::verify();try{$this->db->prepare('DELETE FROM '.$c['table'].' WHERE id=?')->execute([(int)$id]);flash('success','Deleted successfully.');}catch(\PDOException){flash('error','This record is in use and cannot be deleted. Deactivate it instead.');}redirect('/admin/'.$resource); }
}
