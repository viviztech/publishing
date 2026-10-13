<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use PDO;

final class PublicController
{
    private PDO $db;
    private ?bool $hasCategoryMemberships = null;
    public function __construct() { $this->db=Database::connection(); }
    private function base(): array
    {
        $nav=$this->db->query('SELECT n.*, c.slug category_slug, p.slug page_slug FROM navigation_items n LEFT JOIN categories c ON n.category_id=c.id LEFT JOIN pages p ON n.page_id=p.id WHERE n.is_active=1 ORDER BY n.sort_order,n.id')->fetchAll();
        $breaking=$this->db->query('SELECT * FROM breaking_news WHERE is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY priority DESC,id DESC LIMIT 8')->fetchAll();
        $globalAds=['header'=>$this->ads('header'),'footer'=>$this->ads('footer')];
        $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
        if(str_starts_with($path,'/page/')) {$globalAds['page_top']=$this->ads('page-top');$globalAds['page_bottom']=$this->ads('page-bottom');}
        if(str_starts_with($path,'/author/')||str_starts_with($path,'/tag/')) $globalAds['archive']=['top'=>$this->ads('archive-top'),'sidebar'=>$this->ads('archive-sidebar'),'bottom'=>$this->ads('archive-bottom')];
        return compact('nav','breaking','globalAds');
    }
    private function articleSql(): string
    {
        return 'SELECT a.*, c.name category_name,c.name_ta category_name_ta,c.slug category_slug,au.name author_name,au.slug author_slug,COALESCE(m.url,"/assets/news-placeholder.svg") image_url,COALESCE(m.alt_text,"Editorial story image") image_alt FROM articles a JOIN categories c ON c.id=a.category_id JOIN authors au ON au.id=a.author_id LEFT JOIN media m ON m.id=a.featured_image_id WHERE a.status="published" AND a.published_at<=NOW()';
    }
    private function categoryFilter(int $categoryId): string
    {
        // Older installations keep working until the optional import patch is applied.
        $this->hasCategoryMemberships ??= (bool)$this->db->query("SHOW TABLES LIKE 'article_categories'")->fetchColumn();
        if (!$this->hasCategoryMemberships) return ' AND a.category_id=' . $categoryId;
        return ' AND (a.category_id=' . $categoryId . ' OR EXISTS(SELECT 1 FROM article_categories ac WHERE ac.article_id=a.id AND ac.category_id=' . $categoryId . '))';
    }
    private function ads(string $position): array
    {
        $s=$this->db->prepare('SELECT ad.*,ap.slug placement_slug,ap.recommended_width,ap.recommended_height FROM advertisements ad JOIN advertisement_positions ap ON ap.id=ad.position_id WHERE ap.slug=? AND ap.is_active=1 AND ad.status="active" AND (ad.starts_at IS NULL OR ad.starts_at<=NOW()) AND (ad.ends_at IS NULL OR ad.ends_at>=NOW()) ORDER BY ad.priority DESC,ad.id DESC LIMIT 1'); $s->execute([$position]); return $s->fetchAll();
    }
    public function home(): void
    {
        $base=$this->base(); $sql=$this->articleSql();
        $featured=$this->db->query($sql.' AND a.is_featured=1 ORDER BY a.published_at DESC LIMIT 5')->fetchAll();
        $latest=$this->db->query($sql.' ORDER BY a.published_at DESC LIMIT 10')->fetchAll();
        $trending=$this->db->query($sql.' ORDER BY a.is_trending DESC,a.view_count DESC,a.published_at DESC LIMIT 6')->fetchAll();
        $sections=$this->db->query('SELECT * FROM categories WHERE is_active=1 AND show_on_homepage=1 ORDER BY sort_order LIMIT 6')->fetchAll();
        foreach($sections as &$section){$section['articles']=$this->db->query($sql.$this->categoryFilter((int)$section['id']).' ORDER BY a.published_at DESC LIMIT 4')->fetchAll();}
        $ads=['home_top'=>$this->ads('homepage-top'),'home_middle'=>$this->ads('homepage-middle'),'home_bottom'=>$this->ads('homepage-bottom'),'sidebar'=>$this->ads('sidebar')];
        render('home',array_merge($base,compact('featured','latest','trending','sections','ads'),['metaTitle'=>setting('site_name','The Daily Chronicle'),'metaDescription'=>setting('site_description','Independent journalism and trusted reporting.') ]));
    }
    public function category(string $slug): void
    {
        $s=$this->db->prepare('SELECT * FROM categories WHERE slug=? AND is_active=1');$s->execute([$slug]);$category=$s->fetch();if(!$category)abort(404);
        $page=max(1,(int)($_GET['page']??1));$per=12;$filter=$this->categoryFilter((int)$category['id']);$count=$this->db->query('SELECT COUNT(*) FROM articles a WHERE a.status="published" AND a.published_at<=NOW()'.$filter);$pagination=paginate((int)$count->fetchColumn(),$page,$per);
        $sort=is_string($_GET['sort']??null)?$_GET['sort']:'newest';$orders=['newest'=>'a.published_at DESC,a.id DESC','popular'=>'a.view_count DESC,a.published_at DESC,a.id DESC','oldest'=>'a.published_at ASC,a.id ASC'];if(!isset($orders[$sort]))$sort='newest';
        $s=$this->db->prepare($this->articleSql().$filter.' ORDER BY '.$orders[$sort].' LIMIT ? OFFSET ?');$s->bindValue(1,$per,PDO::PARAM_INT);$s->bindValue(2,($page-1)*$per,PDO::PARAM_INT);$s->execute();$articles=$s->fetchAll();
        render('listing',array_merge($this->base(),compact('category','articles','pagination','sort'),['heading'=>$category['name'],'headingTa'=>$category['name_ta']??'','description'=>$category['description'],'descriptionTa'=>$category['description_ta']??'','ads'=>['top'=>$this->ads('category-top'),'sidebar'=>$this->ads('category-page'),'bottom'=>$this->ads('category-bottom')],'metaTitle'=>$category['seo_title']?:$category['name'],'metaDescription'=>$category['seo_description']?:$category['description']]));
    }
    public function article(string $slug): void
    {
        $s=$this->db->prepare($this->articleSql().' AND a.slug=? LIMIT 1');$s->execute([$slug]);$article=$s->fetch();if(!$article)abort(404);
        $t=$this->db->prepare('SELECT t.* FROM tags t JOIN article_tags at ON at.tag_id=t.id WHERE at.article_id=?');$t->execute([$article['id']]);$tags=$t->fetchAll();
        $r=$this->db->prepare($this->articleSql().$this->categoryFilter((int)$article['category_id']).' AND a.id<>? ORDER BY a.published_at DESC LIMIT 4');$r->execute([$article['id']]);$related=$r->fetchAll();
        $prev=$this->db->prepare('SELECT slug,title FROM articles WHERE status="published" AND published_at<? ORDER BY published_at DESC LIMIT 1');$prev->execute([$article['published_at']]);
        $next=$this->db->prepare('SELECT slug,title FROM articles WHERE status="published" AND published_at>? ORDER BY published_at LIMIT 1');$next->execute([$article['published_at']]);
        render('article',array_merge($this->base(),compact('article','tags','related'),['previous'=>$prev->fetch(),'next'=>$next->fetch(),'ads'=>['top'=>$this->ads('article-top'),'middle'=>$this->ads('article-middle'),'sidebar'=>$this->ads('article-sidebar'),'bottom'=>$this->ads('article-bottom')],'metaTitle'=>$article['seo_title']?:$article['title'],'metaDescription'=>$article['seo_description']?:$article['summary'],'canonical'=>$article['canonical_url']?:url('/article/'.$article['slug']),'ogImage'=>$article['image_url']]));
    }
    public function search(): void
    {
        $q=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$per=12;$articles=[];$total=0;
        if(mb_strlen($q)>=2){$needle='%'.$q.'%';$where=' AND (a.title LIKE ? OR a.summary LIKE ? OR a.content LIKE ? OR c.name LIKE ? OR au.name LIKE ? OR EXISTS(SELECT 1 FROM article_tags at JOIN tags t ON t.id=at.tag_id WHERE at.article_id=a.id AND t.name LIKE ?))';$count=$this->db->prepare('SELECT COUNT(DISTINCT a.id) FROM articles a JOIN categories c ON c.id=a.category_id JOIN authors au ON au.id=a.author_id WHERE a.status="published" AND a.published_at<=NOW()'.$where);$count->execute(array_fill(0,6,$needle));$total=(int)$count->fetchColumn();$s=$this->db->prepare($this->articleSql().$where.' ORDER BY a.published_at DESC LIMIT ? OFFSET ?');for($i=1;$i<=6;$i++)$s->bindValue($i,$needle);$s->bindValue(7,$per,PDO::PARAM_INT);$s->bindValue(8,($page-1)*$per,PDO::PARAM_INT);$s->execute();$articles=$s->fetchAll();}
        render('search',array_merge($this->base(),compact('q','articles'),['ads'=>['top'=>$this->ads('search-top'),'bottom'=>$this->ads('search-bottom')],'pagination'=>paginate($total,$page,$per),'metaTitle'=>$q?'Search: '.$q:'Search']));
    }
    public function page(string $slug): void
    {
        $s=$this->db->prepare('SELECT * FROM pages WHERE slug=? AND status="published"');
        $s->execute([$slug]);
        $page=$s->fetch();
        if (!$page && $slug === 'contact-us') {
            render('contact', array_merge($this->base(), ['metaTitle'=>'Contact']));
            return;
        }
        if (!$page) abort(404);
        render('page',array_merge($this->base(),compact('page'),['metaTitle'=>$page['seo_title']?:$page['title'],'metaDescription'=>$page['seo_description']]));
    }
    public function author(string $slug): void
    {
        $s=$this->db->prepare('SELECT * FROM authors WHERE slug=? AND status="active"');$s->execute([$slug]);$author=$s->fetch();if(!$author)abort(404);
        $this->archive($author,true);
    }
    public function tag(string $slug): void
    {
        $s=$this->db->prepare('SELECT * FROM tags WHERE slug=?');$s->execute([$slug]);$tag=$s->fetch();if(!$tag)abort(404);
        $this->archive($tag,false);
    }
    private function archive(array $entity,bool $isAuthor): void
    {
        $filter=$isAuthor?' AND a.author_id=?':' AND EXISTS(SELECT 1 FROM article_tags at WHERE at.article_id=a.id AND at.tag_id=?)';
        $count=$this->db->prepare('SELECT COUNT(*) FROM articles a WHERE a.status="published" AND a.published_at<=NOW()'.$filter);$count->execute([$entity['id']]);
        $total=(int)$count->fetchColumn();$per=12;$page=min(max(1,(int)($_GET['page']??1)),max(1,(int)ceil($total/$per)));$pagination=paginate($total,$page,$per);
        $s=$this->db->prepare($this->articleSql().$filter.' ORDER BY a.published_at DESC,a.id DESC LIMIT ? OFFSET ?');$s->bindValue(1,(int)$entity['id'],PDO::PARAM_INT);$s->bindValue(2,$per,PDO::PARAM_INT);$s->bindValue(3,($page-1)*$per,PDO::PARAM_INT);$s->execute();$articles=$s->fetchAll();
        render('listing',array_merge($this->base(),compact('articles','pagination'),[$isAuthor?'author':'tag'=>$entity,'heading'=>($isAuthor?'':'#').$entity['name'],'description'=>$isAuthor?$entity['bio']:$entity['description'],'ads'=>[],'metaTitle'=>$entity['name']]));
    }
}
