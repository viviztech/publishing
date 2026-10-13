<?php
$site = setting('site_name','Wisdom News');
$metaTitle = $metaTitle ?? $site;
$metaDescription = $metaDescription ?? setting('site_description');
$currentPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$navigation = $nav ?? [];
$socials = array_filter(['Instagram'=>setting('instagram_url'),'YouTube'=>setting('youtube_url'),'Facebook'=>setting('facebook_url'),'X'=>setting('x_url')]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#17243a">
  <title><?=e($metaTitle)?></title><meta name="description" content="<?=e($metaDescription)?>">
  <link rel="canonical" href="<?=e($canonical ?? url(parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/'))?>">
  <meta property="og:type" content="<?=isset($article)?'article':'website'?>"><meta property="og:title" content="<?=e($metaTitle)?>"><meta property="og:description" content="<?=e($metaDescription)?>">
  <?php if(!empty($ogImage)): ?><meta property="og:image" content="<?=e(url($ogImage))?>"><?php endif; ?>
  <link rel="icon" type="image/svg+xml" href="<?=e(asset('favicon.svg'))?>">
  <link rel="stylesheet" href="<?=e(asset('fonts/fonts.css'))?>">
  <link rel="stylesheet" href="<?=e(asset('magazine.css'))?>?v=20261013-1">
  <script type="module" src="<?=e(asset('magazine.js'))?>?v=20261013-1"></script>
</head>
<body class="magazine <?=isset($article)?'is-article':''?>">
<a class="skip-link" href="#content">Skip to content</a>
<div class="edition-bar"><div class="shell edition-bar-inner"><span>A Tamil news magazine</span><time datetime="<?=date('Y-m-d')?>"><?=e(date('l, d F Y'))?></time><a href="<?=e(url('/page/about-us'))?>">Our story <?=magazine_icon('arrow')?></a></div></div>
<header class="mag-header">
  <div class="shell masthead">
    <a class="mag-brand" href="<?=e(url('/'))?>" aria-label="<?=e($site)?> home"><span class="brand-mark" aria-hidden="true">W</span><span class="brand-copy"><strong><?=e($site)?></strong><small lang="ta">அறிவின் வழி · உண்மையின் மொழி</small></span></a>
    <div class="masthead-actions">
      <button class="header-action saved-trigger" data-saved-open aria-label="Open saved articles"><?=magazine_icon('bookmark')?><span>Saved</span><span class="saved-count" data-saved-count hidden>0</span></button>
      <button class="icon-button" data-search-open aria-label="Search articles"><?=magazine_icon('search')?></button>
      <button class="header-action sections-trigger" data-sections-open aria-label="All sections" aria-haspopup="dialog" aria-controls="sections-dialog"><?=magazine_icon('menu')?><span>All sections</span></button>
    </div>
  </div>
  <nav class="primary-nav" aria-label="Main navigation"><div class="shell nav-links"><a href="<?=e(url('/'))?>" <?=$currentPath==='/'?'aria-current="page"':''?>>The latest</a><?php foreach(array_slice($navigation,0,6) as $item): $href=magazine_nav_url($item); ?><a href="<?=e(url($href))?>" lang="<?=magazine_language($item['label'])?>" <?=$item['open_new_tab']?'target="_blank" rel="noopener"':''?> <?=$currentPath===rawurldecode($href)?'aria-current="page"':''?>><?=e($item['label'])?></a><?php endforeach; ?></div></nav>
</header>
<dialog class="mag-dialog sections-dialog" id="sections-dialog" data-sections-dialog aria-labelledby="sections-title">
  <div class="dialog-heading"><div><span class="eyebrow">Explore Wisdom News</span><h2 id="sections-title">Find your perspective.</h2></div><button class="icon-button" data-dialog-close aria-label="Close sections"><?=magazine_icon('close')?></button></div>
  <nav class="sections-grid" aria-label="All sections"><a href="<?=e(url('/'))?>">The latest <?=magazine_icon('arrow')?></a><?php foreach($navigation as $item): ?><a lang="<?=magazine_language($item['label'])?>" href="<?=e(url(magazine_nav_url($item)))?>" <?=$item['open_new_tab']?'target="_blank" rel="noopener"':''?>><?=e($item['label'])?> <?=magazine_icon('arrow')?></a><?php endforeach; ?></nav>
  <div class="dialog-bottom"><a href="<?=e(url('/page/about-us'))?>">About Wisdom News</a><a href="<?=e(url('/page/contact-us'))?>">Contact the newsroom</a></div>
</dialog>
<dialog class="mag-dialog search-dialog" data-search-dialog aria-labelledby="search-title">
  <div class="dialog-heading"><div><span class="eyebrow">The archive</span><h2 id="search-title">What are you curious about?</h2></div><button class="icon-button" data-dialog-close aria-label="Close search"><?=magazine_icon('close')?></button></div>
  <form action="<?=e(url('/search'))?>" class="mag-search-form"><label class="sr-only" for="global-search">Search articles, topics and authors</label><div class="search-field"><?=magazine_icon('search')?><input id="global-search" type="search" name="q" minlength="2" placeholder="Search articles, topics, authors…" required><button class="button" type="submit">Search <?=magazine_icon('arrow')?></button></div><p class="field-hint">Search in Tamil or English.</p></form>
  <div class="search-topics"><span class="eyebrow">Browse a section</span><div class="topic-chips"><?php foreach(array_slice($navigation,0,4) as $item): ?><a href="<?=e(url(magazine_nav_url($item)))?>" lang="<?=magazine_language($item['label'])?>"><?=e($item['label'])?></a><?php endforeach; ?></div></div>
</dialog>
<dialog class="mag-dialog saved-dialog" data-saved-dialog aria-labelledby="saved-title">
  <div class="dialog-heading"><div><span class="eyebrow">Your reading list</span><h2 id="saved-title">A little reading, for later.</h2></div><button class="icon-button" data-dialog-close aria-label="Close saved articles"><?=magazine_icon('close')?></button></div>
  <div data-saved-list></div><p class="field-hint">Saved in this browser, ready when you are.</p>
</dialog>
<?php foreach($globalAds['header'] ?? [] as $ad): ?><div class="shell"><?php magazine_ad($ad); ?></div><?php endforeach; ?>
<?php if(!empty($breaking)): ?><aside class="breaking-strip shell" aria-label="Breaking news"><span class="eyebrow"><i class="live-dot" aria-hidden="true"></i> Breaking</span><div><?php foreach($breaking as $item): ?><a href="<?=e($item['url'] ?: '#')?>" lang="<?=magazine_language($item['title'])?>"><?=e($item['title'])?></a><?php endforeach; ?></div></aside><?php endif; ?>
<main id="content" tabindex="-1"><?php foreach($globalAds['page_top']??[] as $ad): ?><div class="shell"><?php magazine_ad($ad); ?></div><?php endforeach; ?><?=$content?><?php foreach($globalAds['page_bottom']??[] as $ad): ?><div class="shell"><?php magazine_ad($ad); ?></div><?php endforeach; ?></main>
<?php foreach($globalAds['footer'] ?? [] as $ad): ?><div class="shell"><?php magazine_ad($ad); ?></div><?php endforeach; ?>
<footer class="mag-footer">
  <div class="shell footer-top"><a class="mag-brand" href="<?=e(url('/'))?>"><span class="brand-mark" aria-hidden="true">W</span><span class="brand-copy"><strong><?=e($site)?></strong><small lang="ta">அறிவின் வழி · உண்மையின் மொழி</small></span></a><a class="footer-top-link" href="#content">Back to top <span aria-hidden="true">↑</span></a></div>
  <div class="shell footer-grid">
    <section class="footer-intro"><h2>A wider world.<br>A closer look.</h2><p lang="ta"><?=e(setting('site_description','அறிவின் வழி · உண்மையின் மொழி'))?></p><?php if($socials): ?><div class="footer-socials"><?php foreach($socials as $label=>$href): ?><a href="<?=e($href)?>" target="_blank" rel="noopener"><?=e($label)?></a><?php endforeach; ?></div><?php endif; ?></section>
    <section><h2 class="footer-label">Explore</h2><?php foreach(array_slice($navigation,0,6) as $item): ?><a href="<?=e(url(magazine_nav_url($item)))?>" lang="<?=magazine_language($item['label'])?>"><?=e($item['label'])?></a><?php endforeach; ?></section>
    <section><h2 class="footer-label">The magazine</h2><a href="<?=e(url('/page/about-us'))?>">About us</a><a href="<?=e(url('/page/editorial-policy'))?>">Editorial policy</a><a href="<?=e(url('/page/contact-us'))?>">Contact</a><button class="text-button" data-saved-open>Saved articles</button></section>
    <section><h2 class="footer-label">Get in touch</h2><?php $email=setting('contact_email'); if(filter_var($email,FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?=e($email)?>"><?=e($email)?></a><?php else: ?><a href="<?=e(url('/page/contact-us'))?>">Contact the newsroom <?=magazine_icon('arrow')?></a><?php endif; ?><?php if(setting('address')): ?><p><?=e(setting('address'))?></p><?php endif; ?></section>
  </div>
  <div class="shell footer-bottom"><span>© <?=date('Y')?> <?=e($site)?>. All rights reserved.</span><?php $siteViews=\App\Services\SiteAnalytics::summary();if($siteViews['available']): ?><span class="footer-site-views" title="Public page views tracked<?=!empty($siteViews['started_at'])?' since '.e($siteViews['started_at']):' from now'?>. Repeat visits are included.">Site views <strong data-site-views><?=number_format((int)$siteViews['total'])?></strong></span><?php endif; ?><div><a href="<?=e(url('/page/privacy-policy'))?>">Privacy</a><a href="<?=e(url('/page/terms'))?>">Terms</a></div></div>
</footer>
<div class="toast" data-toast role="status" aria-live="polite" hidden></div>
</body>
</html>
