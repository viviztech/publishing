<?php
$cover = $featured[0] ?? $latest[0] ?? null;
$spotlight = array_values(array_filter($featured, static fn(array $story): bool => $story['id'] !== ($cover['id'] ?? null)));
$latestStories = array_values(array_filter($latest, static fn(array $story): bool => $story['id'] !== ($cover['id'] ?? null)));
?>
<div class="shell home-page">
  <div class="edition-heading"><div><span class="eyebrow">News · Ideas · Perspectives</span><h1>Today’s edition<span class="title-period">.</span></h1></div><p lang="ta">அறிவின் வழி.<br>உண்மையின் மொழி.</p></div>
  <?php foreach($ads['home_top'] ?? [] as $ad) magazine_ad($ad); ?>
  <?php if($cover): ?>
  <article class="edition-cover">
    <a class="cover-image" href="<?=e(url('/article/'.$cover['slug']))?>" tabindex="-1" aria-hidden="true"><img src="<?=e($cover['image_url'])?>" alt="" fetchpriority="high" decoding="async"></a>
    <div class="cover-copy"><div class="cover-kicker"><span class="eyebrow">In focus</span><a class="story-category" lang="<?=magazine_language($cover['category_name'])?>" href="<?=e(url('/category/'.$cover['category_slug']))?>"><?=e($cover['category_name'])?></a></div>
      <h2 lang="<?=magazine_language($cover['title'])?>"><a href="<?=e(url('/article/'.$cover['slug']))?>"><?=e($cover['title'])?></a></h2>
      <p class="cover-summary" lang="<?=magazine_language($cover['summary'])?>"><?=e(excerpt($cover['summary'],205))?></p>
      <div class="story-meta"><time datetime="<?=e(date('c',strtotime($cover['published_at'])))?>"><?=e(format_date($cover['published_at'],'M j, Y'))?></time><span>·</span><span><?=magazine_minutes($cover['content'])?> min read</span></div>
      <a class="text-link cover-read" href="<?=e(url('/article/'.$cover['slug']))?>">Read the story <?=magazine_icon('arrow')?></a>
    </div>
  </article>
  <?php else: ?><div class="empty-state"><h2>The next edition starts here.</h2><p>New stories will appear as they are published.</p></div><?php endif; ?>
  <?php if($spotlight): ?><section class="spotlight-section" aria-labelledby="spotlight-title"><div class="section-heading"><h2 id="spotlight-title">The edit</h2><span class="section-note">Selected stories, a little more perspective.</span></div><div class="mag-grid mag-grid--four"><?php foreach($spotlight as $story) magazine_story($story,'feature'); ?></div></section><?php endif; ?>
  <div class="news-layout">
    <section id="latest-stories" aria-labelledby="latest-title"><div class="section-heading"><div><span class="eyebrow">From the newsroom</span><h2 id="latest-title">The latest stories</h2></div><a class="text-link" href="<?=e(url('/search'))?>">Browse the archive <?=magazine_icon('arrow')?></a></div><div class="latest-grid"><?php foreach(array_slice($latestStories,0,6) as $story) magazine_story($story,'standard'); ?></div></section>
    <aside class="discover-rail"><section class="popular-panel" aria-labelledby="popular-title"><span class="eyebrow">Worth your time</span><h2 id="popular-title">In conversation</h2><div><?php foreach(array_slice($trending,0,4) as $story) magazine_story($story,'compact'); ?></div></section><section class="explore-panel"><span class="eyebrow">Follow your curiosity</span><h2>Find a new perspective.</h2><p>Browse every section of the magazine.</p><button class="text-link" data-sections-open>Explore the sections <?=magazine_icon('arrow')?></button></section><?php foreach($ads['sidebar'] ?? [] as $ad) magazine_ad($ad); ?></aside>
  </div>
  <?php foreach($ads['home_middle'] ?? [] as $ad) magazine_ad($ad); ?>
  <div class="category-shelves"><?php foreach($sections as $section): if(!$section['articles']) continue; ?><section aria-labelledby="shelf-<?=$section['id']?>"><div class="section-heading"><div><span class="eyebrow">Explore the magazine</span><h2 id="shelf-<?=$section['id']?>" lang="<?=magazine_language($section['name'])?>"><?=e($section['name'])?></h2></div><a class="text-link" href="<?=e(url('/category/'.$section['slug']))?>">View this section <?=magazine_icon('arrow')?></a></div><div class="mag-grid mag-grid--four"><?php foreach($section['articles'] as $story) magazine_story($story,'feature'); ?></div></section><?php endforeach; ?></div>
  <?php foreach($ads['home_bottom']??[] as $ad) magazine_ad($ad); ?>
</div>
