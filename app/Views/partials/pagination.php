<?php
if ($pagination['pages'] <= 1) return;
$pageLink = static fn(int $number): string => url((parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/').'?'.http_build_query(array_merge($query,['page'=>$number])).'#stories');
$numbers = array_unique([1, ...range(max(1,$pagination['page']-1),min($pagination['pages'],$pagination['page']+1)), $pagination['pages']]);
sort($numbers); $previousNumber = 0;
?>
<nav class="mag-pagination" aria-label="Article pages">
  <?php if ($pagination['page'] > 1): ?><a class="pagination-step" href="<?=e($pageLink($pagination['page']-1))?>" rel="prev">Previous</a><?php else: ?><span class="pagination-step is-disabled">Previous</span><?php endif; ?>
  <div class="page-numbers"><?php foreach ($numbers as $number): ?><?php if ($previousNumber && $number > $previousNumber+1): ?><span class="page-ellipsis">…</span><?php endif; ?><a href="<?=e($pageLink($number))?>" <?=$number===$pagination['page']?'aria-current="page"':''?>><?=$number?></a><?php $previousNumber=$number; endforeach; ?></div>
  <?php if ($pagination['page'] < $pagination['pages']): ?><a class="pagination-step" href="<?=e($pageLink($pagination['page']+1))?>" rel="next">Next <?=magazine_icon('arrow')?></a><?php else: ?><span class="pagination-step is-disabled">Next</span><?php endif; ?>
</nav>
