<?php
/** Страница «Контакты»: телефоны, мессенджеры, адреса с маршрутом, карты, текст страницы, реквизиты */
defined('ABSPATH') || exit;
$id = memorial_plugin_legacy_post_id($block);
    $phones = memorial_plugin_phones();
    $msg    = memorial_plugin_messengers();
    $emails = memorial_plugin_emails();
    $addrs  = memorial_plugin_addrs();
    $legal  = memorial_plugin_legal_parts();
    $hours  = $addrs[0]['hours'] ?? '';
    $names  = ['max' => 'MAX', 'telegram' => 'Telegram', 'whatsapp' => 'WhatsApp'];
    $route  = fn($a) => 'https://yandex.ru/maps/?rtext=~' . rawurlencode(preg_replace('/^\d{6},\s*/', '', $a)) . '&rtt=auto';
?>
    <header class="kontakty__head">
      <h1><?php echo esc_html(get_the_title($id)); ?></h1>
      <p class="kontakty__lead">
        <?php /* Часы есть у каждого адреса; наверху — только если не указано про ночные звонки */
        if ($hours && !memorial_plugin_night()) : ?><span class="kontakty__badge"><?php echo memorial_plugin_icon('clock') . esc_html($hours); ?></span><?php endif; ?>
        <?php if (memorial_plugin_night()) : ?><span class="kontakty__badge kontakty__badge--night"><?php echo memorial_plugin_icon('phone') . esc_html(memorial_plugin_night()); ?></span><?php endif; ?>
        Позвоните — подскажем, что делать, и возьмём хлопоты на себя.
      </p>
    </header>

    <div class="kgrid">
      <?php if ($phones) : ?>
      <section class="kcard kcard--dark" aria-labelledby="k-call">
        <h2 class="kcard__title" id="k-call"><?php echo memorial_plugin_icon('phone'); ?>Позвонить</h2>
        <ul class="kphones">
          <?php foreach ($phones as $i => $p) : ?>
            <li class="<?php echo $i === 0 ? 'is-main' : ''; ?>">
              <a href="tel:<?php echo esc_attr(memorial_plugin_tel_of($p['num'])); ?>"><?php echo esc_html($p['num']); ?></a>
              <?php if (!empty($p['note'])) : ?><small><?php echo esc_html($p['note']); ?></small><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <button class="btn btn--gold kcard__btn" type="button" data-callback>Перезвоните мне</button>
      </section>
      <?php endif; ?>

      <?php if ($msg || $emails) : ?>
      <section class="kcard" aria-labelledby="k-write">
        <h2 class="kcard__title" id="k-write"><?php echo memorial_plugin_icon('chat'); ?>Написать</h2>
        <?php if ($msg) : ?>
          <div class="kmsg">
            <?php foreach ($msg as $k => $url) : ?>
              <a class="kmsg__btn kmsg__btn--<?php echo esc_attr($k); ?>" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Написать в <?php echo esc_html($names[$k]); ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php foreach ($emails as $m) : ?>
          <p class="kmail"><?php echo memorial_plugin_icon('mail'); ?><a href="mailto:<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></a></p>
        <?php endforeach; ?>
        <p class="kcard__hint">Можно прислать фото, текст для ленты на венке или вопросы о документах — ответим в рабочее время.</p>
      </section>
      <?php endif; ?>

      <?php foreach ($addrs as $a) : ?>
      <section class="kcard kaddr">
        <h2 class="kcard__title"><?php echo memorial_plugin_icon('pin'); ?><?php echo esc_html(!empty($a['title']) ? $a['title'] : memorial_plugin_short_addr($a['addr'])); ?></h2>
        <p class="kaddr__full"><?php echo esc_html($a['addr']); ?></p>
        <?php if (!empty($a['hours'])) : ?><p class="kaddr__hours"><?php echo memorial_plugin_icon('clock') . esc_html($a['hours']); ?></p><?php endif; ?>
        <a class="kaddr__route" href="<?php echo esc_url($route($a['addr'])); ?>" target="_blank" rel="noopener">Построить маршрут →</a>
      </section>
      <?php endforeach; ?>
    </div>

    <?php if ($addrs) : ?>
      <section class="kontakty__maps" aria-label="Карта">
        <?php memorial_plugin_legacy_part('maps'); ?>
      </section>
    <?php endif; ?>

    <?php $text = trim((string) get_post_field('post_content', $id)); if ($text !== '') : ?>
      <div class="content kontakty__text"><?php echo apply_filters('the_content', $text); ?></div>
    <?php endif; ?>

    <?php if ($legal) : ?>
      <section class="kontakty__legal" aria-labelledby="k-legal">
        <h2 id="k-legal">Реквизиты</h2>
        <p><?php echo esc_html(implode(' · ', $legal)); ?></p>
      </section>
    <?php endif; ?>

