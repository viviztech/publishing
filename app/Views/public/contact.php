<?php
$email=trim(setting('contact_email'));
$phone=trim(setting('contact_phone'));
$address=trim(setting('address'));
?>
<article class="shell static-page">
  <header class="page-header"><p class="eyebrow"><?=e(setting('site_name','Newsroom'))?></p><h1>Contact</h1></header>
  <div class="prose">
    <?php if ($email !== ''): ?><p><strong>Email</strong><br><?php if (filter_var($email, FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?=e($email)?>"><?=e($email)?></a><?php else: ?><?=e($email)?><?php endif; ?></p><?php endif; ?>
    <?php if ($phone !== ''): ?><p><strong>Phone</strong><br><?=e($phone)?></p><?php endif; ?>
    <?php if ($address !== ''): ?><p><strong>Address</strong><br><?=nl2br(e($address))?></p><?php endif; ?>
    <?php if ($email === '' && $phone === '' && $address === ''): ?><p>Contact details have not been configured.</p><?php endif; ?>
  </div>
</article>
