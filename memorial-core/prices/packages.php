<?php
/**
 * Карточки пакетов услуг (главная и шорткод [mem_packages] для страниц).
 * «Светлая память»: название и цена по центру, лавровый разделитель, весь состав с ценами и лавровыми маркерами.
 * Данные — «Мемориал → Цены → Пакеты похорон».
 */
defined('ABSPATH') || exit;

function mem_packages_html() {
    $s = mem_prices('packages'); $pkgs = mem_packages();
    if (!$pkgs) return;
    $tel = esc_attr(mem_tel());
    $e = fn($x) => esc_html(mem_t($x)); ?>
    <div class="pkgs">
      <?php foreach ($pkgs as $p) :
          $feat = !empty($p['featured']);
          $total = array_sum(array_column($p['items'], 1)); ?>
        <article class="pkg<?php echo $feat ? ' pkg--feat' : ''; ?> reveal">
          <?php if ($feat && !empty($s['badge'])) : ?><span class="pkg__badge"><?php echo $e($s['badge']); ?></span><?php endif; ?>
          <h3><?php echo esc_html($p['name']); ?></h3>
          <?php if ($total) : ?><p class="pkg__total"><?php echo esc_html(mem_rub($total)); ?></p><?php endif; ?>
          <?php if ($p['items']) : ?>
          <span class="pkg__div" aria-hidden="true"><?php echo function_exists('mem_laurel') ? mem_laurel('divider') : ''; ?></span>
          <ul class="pkg__all">
            <?php foreach ($p['items'] as $it) : ?>
              <li><?php echo function_exists('mem_laurel') ? mem_laurel('leaf') : mem_icon('check'); ?><span><?php echo esc_html($it[0]); ?></span><?php if ($it[1]) : ?><b><?php echo esc_html(mem_rub($it[1])); ?></b><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if ($tel) : ?><a class="btn <?php echo $feat ? 'btn--gold' : 'btn--line'; ?>" href="tel:<?php echo $tel; ?>"><?php echo $e($s['btn'] ?: 'Заказать'); ?></a><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}

add_shortcode('mem_packages', function () {
    ob_start();
    mem_packages_html();
    return '<div class="mem-pkgs">' . ob_get_clean() . '</div>';
});
