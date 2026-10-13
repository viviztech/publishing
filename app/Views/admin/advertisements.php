<section class="admin-card" id="stories">
  <div class="card-header"><form class="filter-row"><input type="search" name="q" value="<?=e($q)?>" aria-label="Search campaigns" placeholder="Search campaigns"><button class="secondary-button">Search</button></form><a class="primary-button" href="<?=e(url('/admin/advertisements/create'))?>">Create campaign</a></div>
  <p class="record-count"><?=number_format($pagination['total'])?> campaigns · <a href="<?=e(url('/admin/ad-positions'))?>">View all ad placements →</a></p>
  <div class="table-wrap"><table><thead><tr><th>Campaign</th><th>Position</th><th>Delivery</th><th>Schedule</th><th>Priority</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($items as $item):
    $position=$placementMap[$item['position_id']]??null;
    $delivery=$item['status']!=='active'?'Inactive':(!$position?'Missing position':(!$position['is_active']?'Position disabled':(!empty($item['starts_at'])&&strtotime($item['starts_at'])>time()?'Scheduled':(!empty($item['ends_at'])&&strtotime($item['ends_at'])<time()?'Expired':(trim($item[$item['type']==='html'?'html_content':'image_url']??'')===''?'Missing creative':'Eligible')))));
  ?>
    <tr><td data-label="Campaign"><a href="<?=e(url('/admin/advertisements/edit/'.$item['id']))?>"><strong><?=e($item['name'])?></strong></a><small class="cell-note"><?=e(ucfirst($item['type']))?> creative</small></td><td data-label="Position"><?=e($position['name']??'Unassigned')?></td><td data-label="Delivery"><span class="status <?=$delivery==='Eligible'?'published':'draft'?>"><?=e($delivery)?></span></td><td data-label="Schedule"><small><?=e($item['starts_at']?format_date($item['starts_at'],'M j, Y H:i'):'Start immediately')?><br><?=e($item['ends_at']?'Until '.format_date($item['ends_at'],'M j, Y H:i'):'No end date')?></small></td><td data-label="Priority"><?=(int)$item['priority']?></td><td data-label="Actions"><div class="row-actions"><a href="<?=e(url('/admin/advertisements/edit/'.$item['id']))?>">Edit</a><form method="post" action="<?=e(url('/admin/advertisements/delete/'.$item['id']))?>" data-confirm="Delete this campaign permanently?"><?=csrf_field()?><button class="danger-link">Delete</button></form></div></td></tr>
  <?php endforeach; ?>
  </tbody></table><?php if(!$items):?><div class="empty-state"><h2>No campaigns found</h2><p>Create an image or HTML campaign and choose one of the connected positions.</p></div><?php endif;?></div>
  <?php $query=array_filter(['q'=>$q]); require __DIR__.'/../partials/pagination.php'; ?>
</section>
