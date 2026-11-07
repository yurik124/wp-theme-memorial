<?php /* Список контактов: все телефоны, почта, адреса с временем работы */ ?>
<ul class="clist">
  <?php foreach (mem_phones() as $p) : ?>
    <li><?php echo mem_icon('phone'); ?><span>
      <a class="nowrap" href="tel:<?php echo esc_attr(mem_tel_of($p['num'])); ?>"><?php echo esc_html($p['num']); ?></a>
      <?php if (!empty($p['note'])) : ?><small class="clist__note"><?php echo esc_html($p['note']); ?></small><?php endif; ?>
    </span></li>
  <?php endforeach; ?>
  <?php foreach (mem_emails() as $m) : ?>
    <li><?php echo mem_icon('mail'); ?><a href="mailto:<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></a></li>
  <?php endforeach; ?>
  <?php $msg = mem_messengers(); if ($msg) : ?>
    <li><?php echo mem_icon('chat'); ?><span class="clist__msg">
      <?php foreach ($msg as $k => $url) : ?>
        <a class="cb__m" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html(['max' => 'MAX', 'telegram' => 'Telegram', 'whatsapp' => 'WhatsApp'][$k]); ?></a>
      <?php endforeach; ?>
    </span></li>
  <?php endif; ?>
  <?php foreach (mem_addrs() as $a) : ?>
    <li><?php echo mem_icon('pin'); ?><span>
      <?php echo esc_html(memorial_addr($a['addr'])); ?>
      <?php if (!empty($a['hours'])) : ?><small class="clist__note"><?php echo mem_icon('clock') . esc_html($a['hours']); ?></small><?php endif; ?>
    </span></li>
  <?php endforeach; ?>
</ul>
