<?php $v=fn(string $key,string $default=''): string=>e($article[$key]??$default); ?>
<form method="post" action="<?=e(url('/admin/articles/save'))?>" enctype="multipart/form-data" class="editor-layout">
  <?=csrf_field()?>
  <input type="hidden" name="id" value="<?=$v('id')?>">
  <section class="editor-main">
    <div class="admin-card form-card">
      <label>Headline<input class="headline-input" name="title" value="<?=$v('title')?>" required maxlength="255" data-slug-source></label>
      <div class="two-col">
        <label>URL slug<input name="slug" value="<?=$v('slug')?>" maxlength="280" data-slug-target></label>
        <label>Short summary<textarea name="summary" rows="3" maxlength="500" required><?=$v('summary')?></textarea></label>
      </div>
      <label>Article body
        <div class="editor-toolbar" role="toolbar"><button type="button" data-format="h2">Heading</button><button type="button" data-format="strong"><b>Bold</b></button><button type="button" data-format="em"><i>Italic</i></button><button type="button" data-format="blockquote">Quote</button><button type="button" data-format="ul">List</button><button type="button" data-format="a">Link</button><select data-editor-image aria-label="Choose an image"><option value="">Choose image…</option><?php foreach($lookups['media'] as $x):?><option value="<?=e($x['url'])?>" data-alt="<?=e($x['alt_text'])?>"><?=e($x['original_name'])?></option><?php endforeach;?></select><button type="button" data-insert-image>Insert image</button></div>
        <div class="rich-editor" contenteditable="true" role="textbox" aria-label="Article body" aria-multiline="true" data-placeholder="Begin writing…" data-editor><?=$article['content']??''?></div>
        <textarea name="content" hidden data-editor-input></textarea>
      </label>
    </div>
    <div class="admin-card form-card">
      <h2>Tamil translation <small>Optional — English is shown when a Tamil field is empty.</small></h2>
      <label>Tamil headline<input class="headline-input" name="title_ta" value="<?=$v('title_ta')?>" maxlength="255" lang="ta"></label>
      <label>Tamil short summary<textarea name="summary_ta" rows="3" maxlength="500" lang="ta"><?=$v('summary_ta')?></textarea></label>
      <label>Tamil article body
        <div class="editor-toolbar" role="toolbar"><button type="button" data-format="h2">Heading</button><button type="button" data-format="strong"><b>Bold</b></button><button type="button" data-format="em"><i>Italic</i></button><button type="button" data-format="blockquote">Quote</button><button type="button" data-format="ul">List</button><button type="button" data-format="a">Link</button></div>
        <div class="rich-editor" contenteditable="true" role="textbox" aria-label="Tamil article body" aria-multiline="true" data-editor data-placeholder="தமிழ் மொழிபெயர்ப்பை இங்கே எழுதுங்கள்…" lang="ta"><?=$article['content_ta']??''?></div>
        <textarea name="content_ta" hidden data-editor-input></textarea>
      </label>
    </div>
    <div class="admin-card form-card"><h2>Search & social</h2><label>SEO title<input name="seo_title" value="<?=$v('seo_title')?>" maxlength="255"></label><label>Meta description<textarea name="seo_description" maxlength="320"><?=$v('seo_description')?></textarea></label><div class="two-col"><label>Keywords<input name="seo_keywords" value="<?=$v('seo_keywords')?>"></label><label>Canonical URL<input name="canonical_url" type="url" value="<?=$v('canonical_url')?>"></label></div></div>
  </section>
  <aside class="editor-side"><div class="admin-card form-card sticky">
    <div class="card-header"><h2>Publish</h2><button class="primary-button">Save article</button></div>
    <label>Status<select name="status"><?php foreach(['draft','published','scheduled','archived'] as $s):?><option value="<?=$s?>" <?=$v('status','draft')===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach;?></select></label>
    <label>Publish date<input type="datetime-local" name="published_at" value="<?=e(!empty($article['published_at'])?date('Y-m-d\TH:i',strtotime($article['published_at'])):'')?>"></label>
    <label>Scheduled date<input type="datetime-local" name="scheduled_at" value="<?=e(!empty($article['scheduled_at'])?date('Y-m-d\TH:i',strtotime($article['scheduled_at'])):'')?>"></label><hr>
    <label>Category<select name="category_id" required data-category-select><option value="">Choose category</option><?php foreach($lookups['categories'] as $x):?><option value="<?=$x['id']?>" <?=$v('category_id')===(string)$x['id']?'selected':''?>><?=e($x['name'])?></option><?php endforeach;?></select></label>
    <fieldset><legend>Additional categories</legend><small>The primary category is always included.</small><div class="check-grid"><?php foreach($lookups['categories'] as $x):?><label class="check"><input type="checkbox" name="additional_categories[]" value="<?=$x['id']?>" <?=in_array((int)$x['id'],$selectedCategories,true)?'checked':''?>><?=e($x['name'])?></label><?php endforeach;?></div></fieldset>
    <label>Subcategory<select name="subcategory_id" data-subcategory-select><option value="">None</option><?php foreach($lookups['subcategories'] as $x):?><option value="<?=$x['id']?>" data-category-id="<?=$x['category_id']?>" <?=$v('subcategory_id')===(string)$x['id']?'selected':''?>><?=e($x['name'])?></option><?php endforeach;?></select><small data-subcategory-help>Select a category to see its subcategories.</small></label>
    <label>Author<select name="author_id" required><?php foreach($lookups['authors'] as $x):?><option value="<?=$x['id']?>" <?=$v('author_id')===(string)$x['id']?'selected':''?>><?=e($x['name'])?></option><?php endforeach;?></select></label>
    <label>Featured image<select name="featured_image_id" data-featured-image-select><option value="">No image</option><?php foreach($lookups['media'] as $x):?><option value="<?=$x['id']?>" data-image-url="<?=e($x['url'])?>" <?=$v('featured_image_id')===(string)$x['id']?'selected':''?>><?=e($x['original_name'])?></option><?php endforeach;?></select></label>
    <div class="featured-image-preview" data-featured-image-preview <?php if(empty($article['featured_image_id'])):?>hidden<?php endif;?>><img alt="Featured image preview"></div>
    <div class="inline-upload"><strong>Or upload a new image</strong><?php if($r2Configured):?><label>Image file<input type="file" name="featured_image_upload" accept="image/jpeg,image/png,image/webp,image/gif" data-featured-upload></label><label>Image alt text<input name="featured_image_alt" maxlength="255" placeholder="Describe the image for readers"></label><small>JPEG, PNG, WebP, or GIF up to 8 MB. <?=$storageLocal?'Local development uploads are stored on this computer.':'Uploads are stored in Cloudflare R2.'?></small><?php else:?><div class="configuration-notice"><b>Image uploads are not connected.</b><span>Add R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET_NAME, and R2_PUBLIC_URL to the project’s .env file.</span></div><?php endif;?></div>
    <a class="secondary-button media-library-link" href="<?=e(url('/admin/media'))?>">Open media library</a>
    <label>Image caption<input name="image_caption" value="<?=$v('image_caption')?>"></label>
    <fieldset><legend>Story placement</legend><?php foreach(['is_featured'=>'Featured','is_trending'=>'Trending','is_breaking'=>'Breaking','is_editors_choice'=>"Editor's choice"] as $key=>$label):?><label class="check"><input type="checkbox" name="<?=$key?>" <?=!empty($article[$key])?'checked':''?>><?=$label?></label><?php endforeach;?></fieldset>
    <fieldset><legend>Tags</legend><label>Find tags<input type="search" data-tag-filter aria-controls="article-tags" placeholder="Search by name"></label><small data-tag-count role="status"></small><div class="check-grid" id="article-tags"><?php foreach($lookups['tags'] as $x):?><label class="check"><input type="checkbox" name="tags[]" value="<?=$x['id']?>" <?=in_array((int)$x['id'],$selectedTags,true)?'checked':''?>><?=e($x['name'])?></label><?php endforeach;?></div></fieldset>
  </div></aside>
</form>
