<?php $storyHref = url('/article/'.$story['slug']); ?>
<article class="mag-story mag-story--<?=e($variant)?>">
  <a class="story-image" href="<?=e($storyHref)?>" tabindex="-1" aria-hidden="true"><img src="<?=e($story['image_url'])?>" alt="" loading="lazy" decoding="async"></a>
  <div class="story-copy">
    <a class="story-category" lang="<?=magazine_language($story['category_name'])?>" href="<?=e(url('/category/'.$story['category_slug']))?>"><?=e($story['category_name'])?></a>
    <h3 class="story-title" lang="<?=magazine_language($story['title'])?>"><a href="<?=e($storyHref)?>"><?=e($story['title'])?></a></h3>
    <?php if ($variant !== 'compact'): ?><p class="story-summary" lang="<?=magazine_language($story['summary'])?>"><?=e(excerpt($story['summary'],180))?></p><?php endif; ?>
    <div class="story-meta"><time datetime="<?=e(date('c',strtotime($story['published_at'])))?>"><?=e(format_date($story['published_at'],'M j, Y'))?></time><span aria-hidden="true">·</span><span><?=magazine_minutes($story['content'])?> min read</span></div>
  </div>
</article>
