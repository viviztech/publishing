<div class="shell archive-page search-page">
  <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?=e(url('/'))?>">Home</a><?=magazine_icon('chevron')?><span>Search</span></nav>
  <header class="archive-heading"><div><span class="eyebrow">The magazine archive</span><h1>Find your next read.</h1><p>Search articles, topics and writers in Tamil or English.</p></div></header>
  <form class="mag-search-form archive-search" action="<?=e(url('/search'))?>"><label class="sr-only" for="archive-search">Search the magazine</label><div class="search-field"><?=magazine_icon('search')?><input id="archive-search" type="search" name="q" value="<?=e($q)?>" minlength="2" placeholder="What would you like to read?" required><button class="button">Search <?=magazine_icon('arrow')?></button></div></form>
  <?php foreach($ads['top']??[] as $ad) magazine_ad($ad); ?>
  <section id="stories"><div class="archive-toolbar"><div><h2><?=$q?'Search results':'Browse the sections'?></h2><?php if($q): ?><span class="results-note"><?=number_format($pagination['total'])?> result<?=$pagination['total']===1?'':'s'?> for “<?=e($q)?>”</span><?php endif; ?></div></div>
  <?php if($articles): ?><div class="archive-grid"><?php foreach($articles as $story) magazine_story($story); ?></div><?php elseif($q): ?><div class="empty-state"><h2>No stories matched your search.</h2><p>Try a different keyword, writer’s name, or a shorter phrase.</p></div><?php else: ?><nav class="sections-grid" aria-label="Browse sections"><?php foreach($nav as $item): ?><a lang="<?=magazine_language($item['label'])?>" href="<?=e(url(magazine_nav_url($item)))?>"><?=e($item['label'])?> <?=magazine_icon('arrow')?></a><?php endforeach; ?></nav><?php endif; ?>
  <?php magazine_pagination($pagination,['q'=>$q]); ?></section>
  <?php foreach($ads['bottom']??[] as $ad) magazine_ad($ad); ?>
</div>
