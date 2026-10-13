<form method="post" action="<?=e(url('/admin/'.$resource.'/save'))?>" class="admin-card form-card resource-form">
  <?=csrf_field()?><input type="hidden" name="id" value="<?=e($item['id']??'')?>">
  <?php if($resource==='advertisements'):?>
    <div class="ad-help"><strong>Campaign setup</strong><p>Choose an image URL with alt text, or paste trusted HTML from your ad provider. Use one of the connected positions, set priority and optional dates, then activate the campaign. The highest priority eligible creative is shown in each slot.</p><p>HTML creatives run on your publication; use only code you trust. Responsive provider code must fit mobile screens.</p><label>Choose an image from the media library<select data-ad-image><option value="">Choose image…</option><?php foreach($mediaOptions as $media):?><option value="<?=e(url($media['url']))?>"><?=e($media['original_name'])?></option><?php endforeach;?></select></label></div>
  <?php elseif($resource==='ad-positions'):?>
    <div class="ad-help"><strong>Connected ad placement</strong><p>Keep the position slug to retain its template connection. Recommended dimensions guide image creatives; HTML creatives must handle their own responsive size.</p></div>
  <?php endif;?>
  <?php foreach($config['fields'] as $field=>$spec): $type=is_array($spec)?$spec[0]:$spec;$value=$item[$field]??'';$label=ucwords(str_replace('_',' ',$field)); ?>
    <label class="<?=$type==='richtext'?'wide':''?>"><?=e($label)?>
    <?php if($type==='textarea'):?>
      <textarea name="<?=$field?>" rows="<?=$field==='html_content'?8:4?>"><?=e($value)?></textarea>
    <?php elseif($type==='richtext'):?>
      <div class="editor-toolbar" role="toolbar" aria-label="Text formatting"><button type="button" data-format="h2">Heading</button><button type="button" data-format="strong">Bold</button><button type="button" data-format="a">Link</button></div>
      <div class="rich-editor" contenteditable="true" role="textbox" aria-label="<?=e($label)?>" aria-multiline="true" data-editor><?=$value?></div><textarea name="<?=$field?>" hidden data-editor-input></textarea>
    <?php elseif($type==='select'):?>
      <select name="<?=$field?>"><?php foreach($spec[1] as $option):?><option value="<?=e($option)?>" <?=$value===$option?'selected':''?>><?=e(ucfirst($option))?></option><?php endforeach;?></select>
    <?php elseif($type==='relation'):?>
      <select name="<?=$field?>" <?=in_array($field,$config['required']??[],true)?'required':''?>><option value="">Choose…</option><?php foreach($relations[$field] as $option):?><option value="<?=$option['id']?>" <?=(string)$value===(string)$option['id']?'selected':''?>><?=e($option['label'])?></option><?php endforeach;?></select>
    <?php elseif($type==='checkbox'):?>
      <span class="check"><input type="checkbox" name="<?=$field?>" value="1" <?=$value?'checked':''?>> Enabled</span>
    <?php else:?>
      <input type="<?=in_array($type,['slug','url'],true)?'text':$type?>" <?=$type==='url'?'inputmode="url"':''?> name="<?=$field?>" value="<?=e($type==='password'?'':($type==='datetime-local'&&$value?date('Y-m-d\TH:i',strtotime($value)):$value))?>" <?=$field==='name'||$field==='title'||($field==='password'&&!$item)?'required':''?> <?=($resource==='ad-positions'&&$field==='slug'&&$item)?'readonly':''?>>
    <?php endif;?>
    <?php if($field==='priority'):?><small>Higher numbers take precedence in the same slot.</small><?php elseif($field==='password'):?><small>Use at least 12 characters. Leave blank to keep an existing password.</small><?php endif;?>
    </label>
  <?php endforeach;?>
  <div class="form-actions"><a class="secondary-button" href="<?=e(url('/admin/'.$resource))?>">Cancel</a><button class="primary-button">Save changes</button></div>
</form>
