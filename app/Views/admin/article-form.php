<?php
$v=fn(string $key,string $default=''): string=>e($article[$key]??$default);
$tamil=fn(string $key): string=>e(trim((string)($article[$key.'_ta']??''))!==''?$article[$key.'_ta']:($article[$key]??''));
$body=trim((string)($article['content_ta']??''))!==''?$article['content_ta']:($article['content']??'');
$statusLabels=['draft'=>'வரைவு','published'=>'வெளியிடப்பட்டது','scheduled'=>'பின்னர் வெளியிடு','archived'=>'காப்பகம்'];
?>
<form method="post" action="<?=e(url('/admin/articles/save'))?>" enctype="multipart/form-data" class="editor-layout tamil-editor" data-article-form data-translate-url="<?=e(url('/admin/articles/translate-title'))?>" data-translation-enabled="<?=!empty($translationEnabled)?'true':'false'?>" data-image-upload-url="<?=e(url('/admin/articles/upload-image'))?>" data-image-upload-enabled="<?=$r2Configured?'true':'false'?>" lang="ta">
  <?=csrf_field()?>
  <input type="hidden" name="id" value="<?=$v('id')?>">
  <input type="hidden" name="tamil_only" value="1">
  <input type="hidden" name="slug_mode" value="auto" data-slug-mode>
  <input type="hidden" name="translated_source" value="<?=!empty($englishTitle)?$tamil('title'):''?>" data-translated-source>
  <section class="editor-main">
    <div class="editor-intro"><p>தமிழில் எழுதுங்கள். <?=!empty($translationEnabled)?'ஆங்கிலத் தலைப்பும் கட்டுரை இணைப்பும் தானாக உருவாகும்.':'இணைப்புக்கான ஆங்கிலத் தலைப்பை மட்டும் தனியாக உள்ளிடலாம்.'?></p><a href="<?=e(url('/admin/articles'))?>">← கட்டுரைகள்</a></div>
    <div class="admin-card form-card writing-card">
      <label for="article-title">தலைப்பு <span class="required-note">தேவை</span></label>
      <textarea id="article-title" class="headline-input" name="title" rows="2" required maxlength="255" placeholder="கட்டுரையின் தலைப்பை எழுதுங்கள்" data-title-input><?=$tamil('title')?></textarea>
      <div class="editor-field" data-editor-field>
        <div class="writing-label"><label id="article-body-label" for="article-body-editor">கட்டுரை <span class="required-note">தேவை</span></label><span data-word-count>0 சொற்கள்</span></div>
        <div class="editor-toolbar" role="toolbar" aria-label="எழுத்து வடிவமைப்பு">
          <button type="button" data-format="h2" title="துணைத்தலைப்பு">துணைத்தலைப்பு</button><button type="button" data-format="strong" aria-label="தடித்த எழுத்து"><b>B</b></button><button type="button" data-format="em" aria-label="சாய்வு எழுத்து"><i>I</i></button><button type="button" data-format="blockquote">மேற்கோள்</button><button type="button" data-format="ul">பட்டியல்</button><button type="button" data-format="a">இணைப்பு</button><button type="button" class="body-image-button" data-open-body-image aria-haspopup="dialog" aria-controls="body-image-dialog">＋ படம் சேர்க்க</button>
        </div>
        <div id="article-body-editor" class="rich-editor" contenteditable="true" role="textbox" aria-labelledby="article-body-label" aria-multiline="true" aria-required="true" aria-describedby="article-body-error" data-placeholder="உங்கள் கட்டுரையை இங்கே எழுதுங்கள்…" data-editor><?=$body?></div>
        <textarea name="content" hidden data-editor-input></textarea>
        <p id="article-body-error" class="field-error" data-editor-error role="alert" hidden>கட்டுரையின் உள்ளடக்கத்தை எழுதுங்கள்.</p>
        <p class="body-image-help">படம் வேண்டிய இடத்தில் கிளிக் செய்து, “படம் சேர்க்க” என்பதைத் தேர்ந்தெடுக்கவும்.</p>
      </div>
    </div>
    <div class="admin-card form-card cover-card">
      <h2>முதன்மைப் படம் <small>விருப்பம்</small></h2>
      <?php if($r2Configured):?><label class="cover-upload">புதிய படத்தைப் பதிவேற்றவும்<input type="file" name="featured_image_upload" accept="image/jpeg,image/png,image/webp,image/gif" data-featured-upload><small>JPG, PNG, WebP, GIF · அதிகபட்சம் 8 MB</small></label><?php else:?><p class="form-help">பட நூலகத்திலிருந்து ஒரு படத்தைத் தேர்ந்தெடுக்கலாம்.</p><?php endif;?>
      <details class="editor-details media-details" <?=!empty($article['featured_image_id'])?'open':''?>><summary>பட நூலகத்திலிருந்து தேர்ந்தெடுக்க</summary><div class="details-fields"><label>படத்தைத் தேடு<input type="search" data-media-filter placeholder="கோப்பின் பெயர்"></label><label>படம்<select name="featured_image_id" data-featured-image-select><option value="">படம் இல்லை</option><?php foreach($lookups['media'] as $x):?><option value="<?=$x['id']?>" data-image-url="<?=e($x['url'])?>" <?=$v('featured_image_id')===(string)$x['id']?'selected':''?>><?=e($x['original_name'])?></option><?php endforeach;?></select></label></div></details>
      <div class="featured-image-preview" data-featured-image-preview hidden><img alt="தேர்ந்தெடுத்த படம்"></div>
      <details class="editor-details"><summary>பட விளக்கம்</summary><div class="details-fields"><label>படத்திற்கான மாற்று உரை<input name="featured_image_alt" maxlength="255" placeholder="படத்தில் இருப்பதைச் சுருக்கமாக விளக்குங்கள்"></label><label>படத்தின் கீழ் காட்டும் குறிப்பு<input name="image_caption" value="<?=$v('image_caption')?>"></label></div></details>
    </div>
    <details class="admin-card editor-details summary-settings"><summary>சுருக்கம் <span>விருப்பம் · எழுதாவிட்டால் கட்டுரையிலிருந்து உருவாகும்</span></summary><div class="details-fields"><label>சுருக்கமான அறிமுகம்<textarea name="summary" rows="3" maxlength="500" placeholder="கட்டுரையைப் பற்றிய சில வரிகள்"><?=$tamil('summary')?></textarea></label></div></details>
    <details class="admin-card editor-details url-settings" data-url-settings><summary>கட்டுரை இணைப்பு <span data-url-summary><?=e($article['slug']??'தானாக உருவாகும்')?></span></summary><div class="details-fields">
      <p class="form-help">தமிழ்த் தலைப்பை ஆங்கிலத்தில் மொழிபெயர்த்து இணைப்பு உருவாக்கப்படும். தேவைப்பட்டால் மாற்றலாம்.</p>
      <label>இணைப்புக்கான ஆங்கிலத் தலைப்பு<input name="english_title" value="<?=e($englishTitle??'')?>" maxlength="500" lang="en" data-english-title placeholder="English title"><small>இது கட்டுரையின் தமிழ்த் தலைப்பை மாற்றாது.</small></label>
      <label>URL slug<input name="slug" value="<?=$v('slug')?>" maxlength="280" lang="en" data-article-slug placeholder="article-link"></label>
      <div class="slug-feedback"><p data-translation-status role="status" aria-live="polite"></p><button type="button" class="text-button" data-regenerate-slug>மீண்டும் உருவாக்கு</button></div>
    </div></details>
    <details class="admin-card editor-details"><summary>கூடுதல் அமைப்புகள் <span>குறிச்சொற்கள், முகப்புப் பக்க இடம், தேடல் விவரங்கள்</span></summary><div class="details-fields">
      <fieldset><legend>கூடுதல் பிரிவுகள்</legend><div class="check-grid"><?php foreach($lookups['categories'] as $x):?><label class="check"><input type="checkbox" name="additional_categories[]" value="<?=$x['id']?>" <?=in_array((int)$x['id'],$selectedCategories,true)?'checked':''?>><?=e($x['name'])?></label><?php endforeach;?></div></fieldset>
      <fieldset><legend>குறிச்சொற்கள்</legend><label>குறிச்சொல்லைத் தேடு<input type="search" data-tag-filter aria-controls="article-tags" placeholder="பெயரை எழுதுங்கள்"></label><small data-tag-count role="status"></small><div class="check-grid" id="article-tags"><?php foreach($lookups['tags'] as $x):?><label class="check"><input type="checkbox" name="tags[]" value="<?=$x['id']?>" <?=in_array((int)$x['id'],$selectedTags,true)?'checked':''?>><?=e($x['name'])?></label><?php endforeach;?></div></fieldset>
      <fieldset><legend>முகப்புப் பக்கத்தில் காட்டும் இடம்</legend><?php foreach(['is_featured'=>'முக்கியச் செய்தி','is_trending'=>'பிரபலமானவை','is_breaking'=>'உடனடிச் செய்தி','is_editors_choice'=>'ஆசிரியர் தேர்வு'] as $key=>$label):?><label class="check"><input type="checkbox" name="<?=$key?>" <?=!empty($article[$key])?'checked':''?>><?=$label?></label><?php endforeach;?></fieldset>
      <h3>தேடல் மற்றும் சமூகப் பகிர்வு</h3><p class="form-help">காலியாக விட்டால் கட்டுரையின் தலைப்பும் சுருக்கமும் பயன்படுத்தப்படும்.</p><label>தேடல் தலைப்பு<input name="seo_title" value="<?=$v('seo_title')?>" maxlength="255"></label><label>தேடல் விளக்கம்<textarea name="seo_description" maxlength="320"><?=$v('seo_description')?></textarea></label><label>முக்கியச் சொற்கள்<input name="seo_keywords" value="<?=$v('seo_keywords')?>"></label><label>மூலக் கட்டுரை இணைப்பு<input name="canonical_url" type="url" value="<?=$v('canonical_url')?>"></label>
    </div></details>
  </section>
  <aside class="editor-side"><div class="admin-card form-card sticky publish-card">
    <h2>வெளியீடு</h2><p class="form-help">பிரிவைத் தேர்ந்தெடுத்து கட்டுரையைச் சேமியுங்கள்.</p>
    <label>பிரிவு <span class="required-note">தேவை</span><select name="category_id" required data-category-select><option value="">பிரிவைத் தேர்ந்தெடுக்கவும்</option><?php foreach($lookups['categories'] as $x):?><option value="<?=$x['id']?>" <?=$v('category_id')===(string)$x['id']?'selected':''?>><?=e($x['name'])?></option><?php endforeach;?></select></label>
    <label data-subcategory-field>உட்பிரிவு<select name="subcategory_id" data-subcategory-select><option value="">உட்பிரிவு இல்லை</option><?php foreach($lookups['subcategories'] as $x):?><option value="<?=$x['id']?>" data-category-id="<?=$x['category_id']?>" <?=$v('subcategory_id')===(string)$x['id']?'selected':''?>><?=e($x['name'])?></option><?php endforeach;?></select><small data-subcategory-help></small></label>
    <label>ஆசிரியர்<select name="author_id" required><?php foreach($lookups['authors'] as $x):?><option value="<?=$x['id']?>" <?=$v('author_id')===(string)$x['id']?'selected':''?>><?=e($x['name'])?></option><?php endforeach;?></select></label>
    <label>வெளியீட்டு நிலை<select name="status" data-publish-status><?php foreach($statusLabels as $value=>$label):?><option value="<?=$value?>" <?=$v('status','draft')===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label>
    <label data-schedule-field hidden>வெளியிட வேண்டிய நாள் மற்றும் நேரம்<input type="datetime-local" name="scheduled_at" value="<?=e(!empty($article['scheduled_at'])?date('Y-m-d\TH:i',strtotime($article['scheduled_at'])):'')?>"><small>இந்திய நேரம் (IST)</small></label>
    <details class="editor-details"><summary>வெளியீட்டுத் தேதியை மாற்ற</summary><div class="details-fields"><label>வெளியீட்டுத் தேதி<input type="datetime-local" name="published_at" value="<?=e(!empty($article['published_at'])?date('Y-m-d\TH:i',strtotime($article['published_at'])):'')?>"><small>காலியாக விட்டால் தற்போதைய நேரம் பயன்படுத்தப்படும்.</small></label></div></details>
    <button type="submit" class="primary-button publish-submit" data-save-article-button>கட்டுரையைச் சேமி</button>
    <p class="publish-note" data-publish-note>வரைவு வாசகர்களுக்குத் தெரியாது.</p>
    <?php if(!empty($article['id'])&&($article['status']??'')==='published'):?><a class="secondary-button" href="<?=e(url('/article/'.$article['slug']))?>" target="_blank" rel="noopener">கட்டுரையைப் பார் ↗</a><?php endif;?>
  </div></aside>
  <dialog id="body-image-dialog" class="body-image-dialog" data-body-image-dialog aria-labelledby="body-image-title" aria-describedby="body-image-description">
    <div class="body-image-heading"><div><h2 id="body-image-title">கட்டுரைக்குள் படம் சேர்க்க</h2><p id="body-image-description">நீங்கள் தேர்ந்தெடுத்த இடத்தில் படம் சேர்க்கப்படும்.</p></div><button type="button" class="body-image-close" data-close-body-image aria-label="மூடு">×</button></div>
    <div class="body-image-fields">
      <?php if($r2Configured):?><label for="body-image-file" class="body-image-upload">புதிய படத்தைப் பதிவேற்றவும்<input id="body-image-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-body-image-file><small>JPG, PNG, WebP, GIF · அதிகபட்சம் 8 MB</small></label><?php else:?><input type="file" data-body-image-file disabled hidden><p class="form-help">பட நூலகத்திலிருந்து ஒரு படத்தைத் தேர்ந்தெடுக்கவும்.</p><?php endif;?>
      <div class="body-image-divider"><span>அல்லது பட நூலகத்திலிருந்து தேர்ந்தெடுக்கவும்</span></div>
      <label for="body-image-search">படத்தைத் தேடு<input id="body-image-search" type="search" data-body-image-search placeholder="படத்தின் பெயரை எழுதுங்கள்"></label>
      <label for="body-image-library">பட நூலகம்<select id="body-image-library" data-body-image-library><option value="">படத்தைத் தேர்ந்தெடுக்கவும்</option><?php foreach($lookups['media'] as $x):?><option value="<?=e($x['url'])?>" data-id="<?=$x['id']?>" data-alt="<?=e($x['alt_text'])?>" data-width="<?=e($x['width']??'')?>" data-height="<?=e($x['height']??'')?>"><?=e($x['original_name'])?></option><?php endforeach;?></select></label>
      <div class="body-image-preview" data-body-image-preview hidden><img alt="தேர்ந்தெடுத்த படம்"></div>
      <label for="body-image-alt">பட விளக்கம் <span class="required-note">தேவை</span><input id="body-image-alt" type="text" data-body-image-alt maxlength="255" placeholder="படத்தில் இருப்பதை விவரிக்கவும்"><small>படத்தைப் பார்க்க முடியாத வாசகர்களுக்கு உதவும்.</small></label>
      <label for="body-image-caption">படத்தின் கீழ் காட்டும் குறிப்பு <span class="required-note">விருப்பம்</span><input id="body-image-caption" type="text" data-body-image-caption maxlength="500" placeholder="படக் குறிப்பு"></label>
      <p class="body-image-status" data-body-image-status role="status" aria-live="polite"></p>
    </div>
    <div class="body-image-actions"><button type="button" class="secondary-button" data-close-body-image>ரத்து செய்</button><button type="button" class="primary-button" data-body-image-insert>படத்தைச் சேர்</button></div>
  </dialog>
</form>
