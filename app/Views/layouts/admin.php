<?php
use App\Core\Auth;
$user = Auth::user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$menuGroups = [
    '' => [['Overview', '/admin', 'admin.overview']],
    'Content' => [['Articles', '/admin/articles', 'admin.articles'], ['Categories', '/admin/categories', 'admin.categories'], ['Subcategories', '/admin/subcategories', 'admin.subcategories'], ['Authors', '/admin/authors', 'admin.authors'], ['Tags', '/admin/tags', 'admin.tags'], ['Breaking news', '/admin/breaking-news', 'admin.breakingNews']],
    'Publishing' => [['Media library', '/admin/media', 'admin.mediaLibrary'], ['Advertisements', '/admin/advertisements', 'admin.advertisements'], ['Ad positions', '/admin/ad-positions', 'admin.adPositions'], ['Pages', '/admin/pages', 'admin.pages'], ['Navigation', '/admin/navigation', 'admin.navigationItem']],
    'System' => [['Comments', '/admin/comments', 'admin.comments']],
];
if ($user && $user['role'] === 'super-admin') {
    array_push($menuGroups['System'], ['Users', '/admin/users', 'admin.users'], ['Settings', '/admin/settings', 'admin.settings'], ['Activity logs', '/admin/activity', 'admin.activityLogs']);
}
if (($user['role'] ?? '') === 'author') {
    $menuGroups = ['' => [['Overview','/admin','admin.overview']], 'Content' => [['Articles','/admin/articles','admin.articles'],['Media library','/admin/media','admin.mediaLibrary']]];
} elseif (($user['role'] ?? '') === 'editor') {
    $menuGroups['Publishing'] = [['Media library','/admin/media','admin.mediaLibrary'],['Pages','/admin/pages','admin.pages']];
}
$groupKeys = ['Content'=>'admin.content', 'Publishing'=>'admin.publishing', 'System'=>'admin.system'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?=e($pageTitle??'Admin')?> · Newsroom</title>
  <link rel="icon" type="image/svg+xml" href="<?=e(asset('favicon.svg'))?>">
  <link rel="stylesheet" href="<?=e(asset('app.css'))?>"><link rel="stylesheet" href="<?=e(asset('editorial.css'))?>"><link rel="stylesheet" href="<?=e(asset('edition.css'))?>?v=20261009-4">
  <link rel="stylesheet" href="<?=e(asset('fonts/fonts.css'))?>"><link rel="stylesheet" href="<?=e(asset('admin-publishing.css'))?>">
  <script type="module" src="<?=e(asset('app.js'))?>" defer></script>
</head>
<body class="admin-body">
<?php if(!empty($loginPage)):?>
  <?=$content?>
<?php else:?>
  <a class="admin-skip" href="#admin-content">Skip to content</a>
  <div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" data-admin-sidebar>
      <a class="admin-brand wisdom-admin-brand wisdom-lockup wisdom-lockup-admin" href="<?=e(url('/admin'))?>" aria-label="Wisdom News newsroom"><span class="wisdom-mark" aria-hidden="true">W</span><span class="wisdom-copy"><strong>Wisdom News</strong><small>Newsroom</small></span></a>
      <nav aria-label="Newsroom navigation" data-i18n-aria-label="admin.navigation">
        <?php foreach($menuGroups as $group=>$links):?>
          <?php if($group !== ''):?><p data-i18n="<?=e($groupKeys[$group])?>"><?=e($group)?></p><?php endif;?>
          <?php foreach($links as [$label,$path,$translationKey]):
            $active = $currentPath === $path || ($path !== '/admin' && str_starts_with($currentPath, $path.'/'));
          ?>
            <a href="<?=e(url($path))?>" data-i18n="<?=e($translationKey)?>" <?=$active?'aria-current="page"':''?>><?=e($label)?></a>
          <?php endforeach;?>
        <?php endforeach;?>
      </nav>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar">
        <button type="button" data-admin-menu aria-label="Toggle navigation" data-i18n-aria-label="admin.toggleNavigation" aria-controls="admin-sidebar" aria-expanded="false">☰</button>
        <div><strong><?=e($user['name'])?></strong><span><?=e(ucwords(str_replace('-',' ',$user['role'])))?></span></div>
        <a class="publication-link" href="<?=e(url('/'))?>" target="_blank" rel="noopener">View publication ↗</a>
        <form action="<?=e(url('/admin/logout'))?>" method="post"><?=csrf_field()?><button class="link-button" data-i18n="admin.signOut">Sign out</button></form>
      </header>
      <main class="admin-content" id="admin-content">
        <div class="admin-title"><div><p data-i18n="admin.newsroom">Newsroom</p><h1 <?=($currentPath==='/admin'?'data-i18n="admin.dashboard"':'')?>><?=e($pageTitle??'Dashboard')?></h1></div></div>
        <?php if($message=flash('success')):?><div class="alert success" role="status"><?=e($message)?></div><?php endif;?>
        <?php if($message=flash('error')):?><div class="alert error" role="alert"><?=e($message)?></div><?php endif;?>
        <?=$content?>
      </main>
    </div>
  </div>
<?php endif;?>
</body>
</html>
