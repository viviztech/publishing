<section class="admin-card" id="stories">
  <div class="card-header">
    <form class="filter-row"><input name="q" value="<?=e($q)?>" placeholder="Search headlines" aria-label="Search headlines"><select name="status" aria-label="Filter by status"><option value="">All statuses</option><?php foreach(['draft','published','scheduled','archived'] as $s):?><option <?=$status===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select><button class="secondary-button">Filter</button></form>
    <a class="primary-button" href="<?=e(url('/admin/articles/create'))?>">New article</a>
  </div>
  <p class="record-count"><?=number_format($pagination['total'])?> articles · Page <?=$pagination['page']?> of <?=$pagination['pages']?></p>
  <div class="table-wrap"><table><thead><tr><th>Headline</th><th>Section</th><th>Author</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
  <?php foreach($items as $item):?><tr>
    <td data-label="Headline"><a href="<?=e(url('/admin/articles/edit/'.$item['id']))?>"><strong><?=e($item['title'])?></strong></a></td>
    <td data-label="Section"><?=e($item['category_name'])?></td>
    <td data-label="Author"><?=e($item['author_name'])?></td>
    <td data-label="Status"><span class="status <?=e($item['status'])?>"><?=e($item['status'])?></span></td>
    <td data-label="Updated"><?=e(format_date($item['updated_at']))?></td>
    <td data-label="Actions"><?php if(\App\Core\Auth::user()['role']!=='author'):?><form method="post" action="<?=e(url('/admin/articles/delete/'.$item['id']))?>" data-confirm="Delete this article permanently?"><?=csrf_field()?><button class="danger-link">Delete</button></form><?php endif;?></td>
  </tr><?php endforeach;?>
  </tbody></table></div>
  <?php $query=array_filter(['q'=>$q,'status'=>$status]); require __DIR__.'/../partials/pagination.php'; ?>
</section>
