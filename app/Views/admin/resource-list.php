<?php $visibleColumns=$items?array_slice(array_keys($items[0]),0,6):[]; ?>
<section class="admin-card" id="stories">
  <div class="card-header"><form class="filter-row"><input type="search" name="q" value="<?=e($q)?>" aria-label="Search records" placeholder="Search by name"><button class="secondary-button">Search</button></form><p><?=number_format($pagination['total'])?> records · Page <?=$pagination['page']?> of <?=$pagination['pages']?></p><?php if(empty($config['readonly'])):?><a class="primary-button" href="<?=e(url('/admin/'.$resource.'/create'))?>">Add new</a><?php endif;?></div>
  <div class="table-wrap"><table><thead><tr><?php foreach($visibleColumns as $column):?><th><?=e(ucwords(str_replace('_',' ',$column)))?></th><?php endforeach;?><th></th></tr></thead><tbody>
  <?php foreach($items as $item):?><tr>
    <?php foreach($visibleColumns as $column):?><td data-label="<?=e(ucwords(str_replace('_',' ',$column)))?>"><?=e(mb_strimwidth((string)($item[$column]??''),0,70,'…'))?></td><?php endforeach;?>
    <td data-label="Actions"><?php if(empty($config['readonly'])):?><div class="row-actions"><a href="<?=e(url('/admin/'.$resource.'/edit/'.$item['id']))?>">Edit</a><form method="post" action="<?=e(url('/admin/'.$resource.'/delete/'.$item['id']))?>" data-confirm="Delete this record permanently?"><?=csrf_field()?><button class="danger-link">Delete</button></form></div><?php endif;?></td>
  </tr><?php endforeach;?>
  </tbody></table><?php if(!$items):?><div class="empty-state"><h2>Nothing here yet</h2><p>Create the first record to get started.</p></div><?php endif;?></div>
  <?php $query=array_filter(['q'=>$q]); require __DIR__.'/../partials/pagination.php'; ?>
</section>
