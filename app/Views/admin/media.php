<section class="admin-card" id="stories">
  <div class="card-header"><div><h2>Upload images</h2><p>JPEG, PNG, WebP, or GIF. Maximum 8 MB. <?=$storageLocal?'Local development storage.':'Cloudflare R2 storage.'?></p></div></div>
  <?php if($r2Configured):?>
    <form method="post" action="<?=e(url('/admin/media/upload'))?>" enctype="multipart/form-data" class="upload-row">
      <?=csrf_field()?>
      <label>Image<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required data-image-input></label>
      <label>Alt text<input name="alt_text" required maxlength="255" placeholder="Describe the image"></label>
      <button class="primary-button">Upload image</button>
    </form>
  <?php else:?>
    <div class="configuration-notice media-configuration"><b>Cloudflare R2 must be configured before images can be uploaded.</b><span>Set the five R2 environment values in the project’s .env file, then reload this page.</span></div>
  <?php endif;?>
  <div class="card-header"><form class="filter-row"><input type="search" name="q" value="<?=e($q)?>" aria-label="Search media" placeholder="Filename or alt text"><button class="secondary-button">Search</button></form><p><?=number_format($pagination['total'])?> images · Page <?=$pagination['page']?> of <?=$pagination['pages']?></p></div>
  <div class="media-grid">
    <?php foreach($items as $item):?><article>
      <img src="<?=e($item['url'])?>" alt="<?=e($item['alt_text'])?>" loading="lazy">
      <div><strong><?=e($item['original_name'])?></strong><small><?=number_format($item['size_bytes']/1024)?> KB · <?=$item['width']?>×<?=$item['height']?></small><?php if(\App\Core\Auth::user()['role']!=='author'):?><form method="post" action="<?=e(url('/admin/media/delete/'.$item['id']))?>" data-confirm="Delete this unused image permanently?"><?=csrf_field()?><button class="danger-link">Delete</button></form><?php endif;?></div>
    </article><?php endforeach;?>
  </div>
  <?php $query=array_filter(['q'=>$q]); require __DIR__.'/../partials/pagination.php'; ?>
</section>
