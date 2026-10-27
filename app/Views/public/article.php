<?php
$articleUrl=url('/article/'.$article['slug']);
$articleLanguage=magazine_language($article['title']);
$categoryName=trim((string)($article['category_name_ta']??''))?:$article['category_name'];
$hasHero=trim((string)$article['image_url'])!=='' && basename((string)parse_url($article['image_url'],PHP_URL_PATH))!=='news-placeholder.svg';
$hasSidebarAds=!empty($ads['sidebar']);
$articleDate=static function(?string $value,bool $withTime=true): string {
    $timestamp=$value?strtotime($value):false;
    if($timestamp===false)return '';
    if(class_exists(\IntlDateFormatter::class)){
        $formatter=new \IntlDateFormatter('ta_IN',\IntlDateFormatter::NONE,\IntlDateFormatter::NONE,date_default_timezone_get(),\IntlDateFormatter::GREGORIAN,$withTime?'d MMMM y · a h:mm':'d MMM y');
        $formatted=$formatter->format($timestamp);
        if($formatted!==false)return $formatted;
    }
    $months=[1=>'ஜனவரி','பிப்ரவரி','மார்ச்','ஏப்ரல்','மே','ஜூன்','ஜூலை','ஆகஸ்ட்','செப்டம்பர்','அக்டோபர்','நவம்பர்','டிசம்பர்'];
    return date('j',$timestamp).' '.$months[(int)date('n',$timestamp)].' '.date('Y',$timestamp).($withTime?' · '.(date('A',$timestamp)==='AM'?'மு.ப.':'பி.ப.').' '.date('g:i',$timestamp):'');
};
$showUpdated=(strtotime((string)$article['updated_at'])?:0)>(strtotime((string)$article['published_at'])?:0)+60;
?>
<div class="reading-progress" aria-hidden="true"><span data-reading-progress></span></div>
<article class="shell article-detail" data-article-id="<?=$article['id']?>" data-article-url="<?=e($articleUrl)?>" data-article-title="<?=e($article['title'])?>" data-article-image="<?=e($article['image_url'])?>" data-article-category="<?=e($categoryName)?>">
  <nav class="article-detail-breadcrumbs" aria-label="பக்க வழிகாட்டி">
    <a href="<?=e(url('/'))?>">முகப்பு</a><?=magazine_icon('chevron')?><a href="<?=e(url('/category/'.$article['category_slug']))?>" lang="<?=magazine_language($categoryName)?>"><?=e($categoryName)?></a><?=magazine_icon('chevron')?><span aria-current="page">கட்டுரை</span>
  </nav>
  <header class="article-detail-header">
    <div class="article-detail-kicker">
      <a class="article-detail-category" href="<?=e(url('/category/'.$article['category_slug']))?>" lang="<?=magazine_language($categoryName)?>"><?=e($categoryName)?></a>
      <span class="article-detail-readtime"><?=magazine_icon('clock')?> <?=magazine_minutes($article['content'])?> நிமிட வாசிப்பு</span>
    </div>
    <h1 lang="<?=$articleLanguage?>"><?=e($article['title'])?></h1>
    <?php if(trim((string)$article['summary'])!==''):?><p class="article-detail-summary" lang="<?=$articleLanguage?>"><?=e(excerpt($article['summary'],280))?></p><?php endif;?>
    <div class="article-detail-meta">
      <div class="article-detail-author">
        <span class="article-detail-avatar" aria-hidden="true">W</span>
        <div><a href="<?=e(url('/author/'.$article['author_slug']))?>"><?=e(magazine_author($article['author_name']))?></a><time datetime="<?=e(date('c',strtotime($article['published_at'])))?>"><?=e($articleDate($article['published_at']))?> <span>இந்திய நேரம்</span></time></div>
      </div>
      <div class="article-detail-actions">
        <button type="button" class="button button-quiet" data-save-article aria-pressed="false"><?=magazine_icon('bookmark')?><span data-save-label>சேமிக்கவும்</span></button>
        <button type="button" class="button button-quiet" data-share-article aria-expanded="false" aria-controls="article-share-panel"><?=magazine_icon('share')?><span>பகிரவும்</span></button>
        <button type="button" class="icon-button" data-copy-link aria-label="கட்டுரை இணைப்பை நகலெடுக்கவும்" title="இணைப்பை நகலெடுக்கவும்"><?=magazine_icon('link')?></button>
      </div>
    </div>
    <div class="article-detail-share" id="article-share-panel" data-share-panel hidden>
      <span>இந்தக் கட்டுரையைப் பகிரவும்</span>
      <a href="https://wa.me/?text=<?=rawurlencode($article['title'].' '.$articleUrl)?>" target="_blank" rel="noopener">WhatsApp</a>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?=rawurlencode($articleUrl)?>" target="_blank" rel="noopener">Facebook</a>
      <a href="https://twitter.com/intent/tweet?url=<?=rawurlencode($articleUrl)?>&text=<?=rawurlencode($article['title'])?>" target="_blank" rel="noopener">X</a>
      <button type="button" class="text-button" data-copy-link><?=magazine_icon('link')?> இணைப்பை நகலெடுக்கவும்</button>
    </div>
  </header>
  <?php foreach($ads['top']??[] as $ad)magazine_ad($ad);?>
  <div class="article-detail-layout <?=$hasSidebarAds?'has-ad-sidebar':''?>">
    <div class="article-detail-main">
      <?php if($hasHero):?>
      <figure class="article-detail-hero">
        <a class="article-detail-image" href="<?=e(url($article['image_url']))?>" target="_blank" rel="noopener" aria-label="கட்டுரையின் படத்தை முழு அளவில் பார்க்கவும்">
          <img src="<?=e(url($article['image_url']))?>" alt="<?=e($article['image_alt']?:$article['title'])?>" fetchpriority="high" decoding="async">
          <span class="article-detail-image-label">முழுப் படம் <span aria-hidden="true">↗</span></span>
        </a>
        <?php if(trim((string)$article['image_caption'])!==''):?><figcaption><?=e($article['image_caption'])?></figcaption><?php endif;?>
      </figure>
      <?php endif;?>
      <div class="prose article-detail-prose" id="article-body" lang="<?=$articleLanguage?>" data-reading-body><?=magazine_article_content($article['content'],$ads['middle']??[])?></div>
      <?php if($showUpdated):?><p class="article-detail-updated">புதுப்பிக்கப்பட்டது <time datetime="<?=e(date('c',strtotime($article['updated_at'])))?>"><?=e($articleDate($article['updated_at']))?></time></p><?php endif;?>
      <?php if($tags):?>
      <section class="article-detail-topics" aria-label="தொடர்புடைய தலைப்புகள்">
        <span>தொடர்புடைய தலைப்புகள்</span>
        <div class="topic-chips"><?php foreach($tags as $tag):?><a href="<?=e(url('/tag/'.$tag['slug']))?>" lang="<?=magazine_language($tag['name'])?>"><?=e($tag['name'])?></a><?php endforeach;?></div>
      </section>
      <?php endif;?>
      <div class="article-detail-end">
        <span class="brand-mark" aria-hidden="true">W</span>
        <div><strong>பின்னர் வாசிக்கச் சேமிக்கவும்.</strong><p>விரும்பிய கட்டுரைகளை உங்கள் வாசிப்புப் பட்டியலில் வைத்திருங்கள்.</p></div>
        <button type="button" class="button button-quiet" data-save-article aria-pressed="false"><?=magazine_icon('bookmark')?><span data-save-label>சேமிக்கவும்</span></button>
      </div>
    </div>
    <?php if($hasSidebarAds):?><aside class="article-detail-sidebar" aria-label="விளம்பரங்கள்"><span class="article-detail-ad-heading">விளம்பரம்</span><?php foreach($ads['sidebar'] as $ad)magazine_ad($ad);?></aside><?php endif;?>
  </div>
  <?php foreach($ads['bottom']??[] as $ad)magazine_ad($ad);?>
  <?php if($previous||$next):?>
  <nav class="article-detail-adjacent" aria-label="முந்தைய மற்றும் அடுத்த கட்டுரைகள்">
    <?php if($previous):?><a class="article-detail-previous" href="<?=e(url('/article/'.$previous['slug']))?>" rel="prev"><span>முந்தைய கட்டுரை</span><strong lang="<?=magazine_language($previous['title'])?>"><?=e($previous['title'])?></strong><span class="article-detail-nav-arrow" aria-hidden="true">←</span></a><?php endif;?>
    <?php if($next):?><a class="article-detail-next" href="<?=e(url('/article/'.$next['slug']))?>" rel="next"><span>அடுத்த கட்டுரை</span><strong lang="<?=magazine_language($next['title'])?>"><?=e($next['title'])?></strong><span class="article-detail-nav-arrow" aria-hidden="true">→</span></a><?php endif;?>
  </nav>
  <?php endif;?>
  <?php if($related):?>
  <section class="article-detail-related" aria-labelledby="related-title">
    <div class="article-detail-section-heading"><h2 id="related-title">தொடர்ந்து வாசிக்க</h2><a class="text-link" href="<?=e(url('/category/'.$article['category_slug']))?>">இந்தப் பிரிவில் மேலும் <?=magazine_icon('arrow')?></a></div>
    <div class="article-detail-related-grid">
      <?php foreach($related as $story):$hasStoryImage=trim((string)$story['image_url'])!==''&&basename((string)parse_url($story['image_url'],PHP_URL_PATH))!=='news-placeholder.svg';$storyCategoryName=trim((string)($story['category_name_ta']??''))?:$story['category_name'];?>
      <article class="article-detail-related-card">
        <a class="article-detail-related-image" href="<?=e(url('/article/'.$story['slug']))?>" tabindex="-1" aria-hidden="true"><?php if($hasStoryImage):?><img src="<?=e(url($story['image_url']))?>" alt="" loading="lazy" decoding="async"><?php else:?><span class="article-detail-related-placeholder" aria-hidden="true">W</span><?php endif;?></a>
        <a class="article-detail-related-category" href="<?=e(url('/category/'.$story['category_slug']))?>" lang="<?=magazine_language($storyCategoryName)?>"><?=e($storyCategoryName)?></a>
        <h3 lang="<?=magazine_language($story['title'])?>"><a href="<?=e(url('/article/'.$story['slug']))?>"><?=e($story['title'])?></a></h3>
        <p lang="<?=magazine_language($story['summary'])?>"><?=e(excerpt($story['summary'],105))?></p>
        <div class="article-detail-related-meta"><time datetime="<?=e(date('c',strtotime($story['published_at'])))?>"><?=e($articleDate($story['published_at'],false))?></time><span><?=magazine_minutes($story['content'])?> நிமிட வாசிப்பு</span></div>
      </article>
      <?php endforeach;?>
    </div>
  </section>
  <?php endif;?>
</article>
