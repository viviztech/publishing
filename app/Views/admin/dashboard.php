<div class="stat-grid">
  <article><span><?=$own?'Your articles':'Total articles'?></span><strong><?=number_format($stats['articles'])?></strong><a href="<?=e(url('/admin/articles'))?>" data-i18n="admin.manageStories">Manage stories →</a></article>
  <article><span data-i18n="admin.published">Published</span><strong><?=number_format($stats['published'])?></strong><small><?=number_format($stats['drafts'])?> <span class="inline-copy" data-i18n="admin.draftsInProgress">drafts in progress</span></small></article>
  <article><span data-i18n="admin.categories">Categories</span><strong><?=number_format($stats['categories'])?></strong><?php if(!$own):?><a href="<?=e(url('/admin/categories'))?>" data-i18n="admin.organizeCoverage">Organize coverage →</a><?php endif;?></article>
  <article><span data-i18n="admin.activeNewsroom">Active newsroom</span><strong><?=number_format($stats['authors'])?></strong><small><?=number_format($stats['advertisements'])?> <span class="inline-copy" data-i18n="admin.adCampaigns">advertising campaigns</span></small></article>
</div>
<?php if(!$own):?>
<section class="admin-card publishing-overview">
  <div class="card-header"><div><h2>Publishing overview</h2><p>Public page views since tracking began; repeat views are included. Signed-in staff and known bots are excluded.</p></div></div>
  <div class="publishing-metrics"><article><span>Total site views</span><strong><?=number_format($traffic['total'])?></strong></article><article><span>Today</span><strong><?=number_format($traffic['today'])?></strong></article><article><span>Last 7 days</span><strong><?=number_format($traffic['last_seven_days'])?></strong></article><article><span>Scheduled articles</span><strong><?=number_format($stats['scheduled'])?></strong></article></div>
  <?php if(\App\Core\Auth::user()['role']==='super-admin'):?><div class="publishing-links"><a href="<?=e(url('/admin/ad-positions'))?>">20 connected ad placements →</a><a href="<?=e(url('/admin/advertisements'))?>"><?=$activeAds?> eligible campaigns →</a><a href="<?=e(url('/admin/settings'))?>">Publication settings →</a></div><?php endif;?>
</section>
<?php endif;?>
<section class="admin-card">
  <div class="card-header"><div><h2 data-i18n="admin.recentlyEdited">Recently edited</h2><p data-i18n="admin.latestActivity">The latest newsroom activity.</p></div><a class="primary-button" href="<?=e(url('/admin/articles/create'))?>" data-i18n="admin.newArticle">New article</a></div>
  <div class="table-wrap"><table><thead><tr><th data-i18n="admin.story">Story</th><th data-i18n="admin.author">Author</th><th data-i18n="admin.status">Status</th><th data-i18n="admin.updated">Updated</th></tr></thead><tbody>
  <?php foreach($recent as $item):?><tr>
    <td data-label="Story" data-i18n-data-label="admin.story"><a href="<?=e(url('/admin/articles/edit/'.$item['id']))?>"><strong data-localized-content data-en="<?=e($item['title'])?>" data-ta="<?=e($item['title_ta']??'')?>"><?=e($item['title'])?></strong></a></td>
    <td data-label="Author" data-i18n-data-label="admin.author"><?=e($item['author_name'])?></td>
    <td data-label="Status" data-i18n-data-label="admin.status"><span class="status <?=e($item['status'])?>" data-i18n="status.<?=e($item['status'])?>"><?=e($item['status'])?></span></td>
    <td data-label="Updated" data-i18n-data-label="admin.updated"><?=e(format_date($item['updated_at'],'M j, g:i A'))?></td>
  </tr><?php endforeach;?>
  </tbody></table></div>
</section>
