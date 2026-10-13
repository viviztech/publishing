<?php
$isCategory = isset($category);
$currentPage = (int)($pagination['page'] ?? 1);
$totalStories = (int)($pagination['total'] ?? count($articles));
$lead = $isCategory && $currentPage === 1 ? ($articles[0] ?? null) : null;
$gridStories = $lead ? array_slice($articles,1) : $articles;
$listingAds = $isCategory ? $ads : ($globalAds['archive']??[]);
?>
<div class="shell archive-page">
  <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?=e(url('/'))?>">Home</a><?=magazine_icon('chevron')?><span><?=$isCategory?'The sections':(isset($author)?'Our authors':'The topics')?></span></nav>
  <header class="archive-heading"><div><span class="eyebrow"><?=$isCategory?'A closer look':(isset($author)?'From the writer':'Explore the topic')?></span><h1 lang="<?=magazine_language($heading)?>"><?=e($heading)?></h1><?php if(!empty($description)): ?><p lang="<?=magazine_language($description)?>"><?=e(excerpt($description,250))?></p><?php endif; ?></div><span class="archive-count"><strong><?=number_format($totalStories)?></strong> <?=$totalStories===1?'story':'stories'?></span></header>
  <?php if($isCategory): ?><nav class="topic-chips section-chips" aria-label="Browse sections"><?php foreach($nav as $item): if($item['type']!=='category') continue; ?><a href="<?=e(url(magazine_nav_url($item)))?>" lang="<?=magazine_language($item['label'])?>" <?=$item['category_id']===$category['id']?'aria-current="page"':''?>><?=e($item['label'])?></a><?php endforeach; ?></nav><?php endif; ?>
  <?php foreach($listingAds['top']??[] as $ad) magazine_ad($ad); ?>
  <?php if($lead): ?><article class="archive-lead"><a class="archive-lead-image" href="<?=e(url('/article/'.$lead['slug']))?>" tabindex="-1" aria-hidden="true"><img src="<?=e($lead['image_url'])?>" alt="" fetchpriority="high" decoding="async"></a><div><span class="eyebrow">Leading this section</span><h2 lang="<?=magazine_language($lead['title'])?>"><a href="<?=e(url('/article/'.$lead['slug']))?>"><?=e($lead['title'])?></a></h2><p lang="<?=magazine_language($lead['summary'])?>"><?=e(excerpt($lead['summary'],230))?></p><div class="story-meta"><time datetime="<?=e(date('c',strtotime($lead['published_at'])))?>"><?=e(format_date($lead['published_at'],'M j, Y'))?></time><span>·</span><span><?=magazine_minutes($lead['content'])?> min read</span></div><a class="text-link" href="<?=e(url('/article/'.$lead['slug']))?>">Read the story <?=magazine_icon('arrow')?></a></div></article><?php endif; ?>
  <div class="archive-publishing-layout <?=!empty($listingAds['sidebar'])?'has-ad-sidebar':''?>">
  <section id="stories" class="archive-stories" aria-labelledby="archive-stories-title">
    <div class="archive-toolbar"><div><h2 id="archive-stories-title"><?=$lead?'More from this section':'All stories'?></h2><span class="results-note"><?php if($totalStories): ?>Showing <?=($currentPage-1)*$pagination['per_page']+1?>–<?=min($totalStories,$currentPage*$pagination['per_page'])?> of <?=number_format($totalStories)?><?php else: ?>No stories yet<?php endif; ?></span></div><div class="browse-controls">
      <?php if($isCategory): ?><form method="get" data-sort-form><label class="sr-only" for="story-sort">Sort stories</label><select id="story-sort" name="sort" data-story-sort><?php foreach(['newest'=>'Newest first','popular'=>'Most read','oldest'=>'Oldest first'] as $value=>$label): ?><option value="<?=$value?>" <?=($sort??'newest')===$value?'selected':''?>><?=$label?></option><?php endforeach; ?></select><noscript><button class="button">Apply</button></noscript></form><?php endif; ?>
      <div class="view-switch" role="group" aria-label="Article layout"><button class="icon-button" data-view="grid" aria-label="Grid view" aria-pressed="true"><?=magazine_icon('grid')?></button><button class="icon-button" data-view="list" aria-label="List view" aria-pressed="false"><?=magazine_icon('list')?></button></div>
    </div></div>
    <?php if($gridStories): ?><div class="archive-grid" data-story-grid data-layout="grid"><?php foreach($gridStories as $story) magazine_story($story,'standard'); ?></div><?php elseif(!$articles): ?><div class="empty-state"><span class="empty-symbol" aria-hidden="true"><?=magazine_icon('bookmark')?></span><h2>There are no stories in this section yet.</h2><p>Explore another section to find your next read.</p><a class="button" href="<?=e(url('/'))?>">Back to the latest <?=magazine_icon('arrow')?></a></div><?php endif; ?>
    <?php magazine_pagination($pagination,$isCategory?['sort'=>$sort??'newest']:[]); ?>
  </section>
  <?php if(!empty($listingAds['sidebar'])): ?><aside class="archive-ad-rail"><?php foreach($listingAds['sidebar'] as $ad) magazine_ad($ad); ?></aside><?php endif; ?>
  </div>
  <?php foreach($listingAds['bottom']??[] as $ad) magazine_ad($ad); ?>
</div>
