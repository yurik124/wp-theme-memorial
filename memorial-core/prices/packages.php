<?php
/**
 * Карточки пакетов услуг (главная и шорткод [mem_packages] для страниц).
 * Ключевые позиции (отмечаются в настройках) — списком с лавровыми листками, весь состав с ценами — свёрнут («Весь состав и цены»).
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
          <?php if ($p['items']) :
              $keys = array_filter($p['items'], fn($it) => !empty($it[2]));
              // ничего не отмечено в настройках — берём главное по названию, иначе первые четыре
              if (!$keys) $keys = array_filter($p['items'], fn($it) => preg_match('/^(Гроб|Крест|Катафалк|Венок|Услуги по захоронению)/u', $it[0]));
              if (!$keys) $keys = array_slice($p['items'], 0, 4);
              $mark = function_exists('mem_laurel') ? mem_laurel('leaf') : mem_icon('check'); ?>
          <?php if (function_exists('mem_laurel')) : ?><span class="pkg__div" aria-hidden="true"><?php echo mem_laurel('divider'); ?></span><?php endif; ?>
          <ul class="pkg__key">
            <?php foreach ($keys as $it) : ?><li><?php echo $mark; ?><span><?php echo esc_html($it[0]); ?></span></li><?php endforeach; ?>
          </ul>
          <details class="pkg__list">
            <summary>Весь состав и цены (<?php echo count($p['items']); ?>)</summary>
            <ul>
              <?php foreach ($p['items'] as $it) : ?>
                <li><span><?php echo esc_html($it[0]); ?></span><?php if ($it[1]) : ?><b><?php echo esc_html(mem_rub($it[1])); ?></b><?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
          </details>
          <?php endif; ?>
          <?php if ($tel) : ?><a class="btn <?php echo $feat ? 'btn--gold' : 'btn--line'; ?>" href="tel:<?php echo $tel; ?>"><?php echo $e($s['btn'] ?: 'Заказать'); ?></a><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
}

/** Карточка «Кремация» в том же виде, что пакеты похорон (данные — «Мемориал → Цены → Пакет кремации») */
function mem_cremation_card_html() {
    $c = mem_prices('cremation');
    $rows = array_values(array_filter((array) ($c['rows'] ?? []), fn($r) => trim($r['name'] ?? '') !== ''));
    if (!$rows) return '';
    $total = mem_cremation_total();
    $keys = array_filter($rows, fn($r) => !empty($r['key']));
    if (!$keys) $keys = count($rows) <= 5 ? $rows : array_slice($rows, 0, 4);
    $mark = function_exists('mem_laurel') ? mem_laurel('leaf') : mem_icon('check');
    $tel = mem_tel();
    $page = function_exists('mem_guess_page') ? (int) mem_guess_page(['ritualnye-uslugi/kremacziya', 'kremacziya', 'kremaciya', 'kremaciya-v-belgorode']) : 0;
    $page = $page && get_post_status($page) === 'publish' && $page !== get_the_ID() ? get_permalink($page) : '';
    $btn = mem_prices('packages')['btn'] ?? '';
    $h = '<article class="pkg pkg--crem reveal"><h3>' . esc_html(trim($c['card_title'] ?? '') ?: 'Кремация') . '</h3>';
    if ($total) $h .= '<p class="pkg__total">' . esc_html(mem_rub($total)) . '</p>';
    if (function_exists('mem_laurel')) $h .= '<span class="pkg__div" aria-hidden="true">' . mem_laurel('divider') . '</span>';
    $h .= '<ul class="pkg__key">';
    foreach ($keys as $r) $h .= '<li>' . $mark . '<span>' . esc_html($r['name']) . '</span></li>';
    $h .= '</ul><details class="pkg__list"><summary>' . 'Весь состав и цены' . ' (' . count($rows) . ')</summary><ul>';
    foreach ($rows as $r) $h .= '<li><span>' . esc_html($r['name']) . '</span>' . (!empty($r['price']) ? '<b>' . esc_html(mem_rub((int) $r['price'])) . '</b>' : '') . '</li>';
    $h .= '</ul></details>';
    if (!empty($c['total'])) $h .= '<p class="pkg__note">' . esc_html($c['total']) . '</p>';
    if ($page) $h .= '<a class="link-more pkg__more" href="' . esc_url($page) . '">Подробнее о кремации <span aria-hidden="true">→</span></a>';
    if ($tel) $h .= '<a class="btn btn--line" href="tel:' . esc_attr($tel) . '">' . esc_html(mem_t($btn ?: 'Заказать')) . '</a>';
    return $h . '</article>';
}

add_shortcode('mem_packages', function ($atts = []) {
    $a = shortcode_atts(['cremation' => ''], $atts, 'mem_packages');
    ob_start();
    mem_packages_html();
    $html = ob_get_clean();
    // карточка кремации: на странице «Цены» (если включено в настройках) или по cremation="1"; cremation="0" — никогда
    $slug = (string) get_post_field('post_name', get_the_ID());
    $crem = $a['cremation'] === '1' || ($a['cremation'] === '' && !empty(mem_prices('cremation')['card']) && in_array($slug, ['ceny', 'tseny', 'prices'], true));
    if ($crem && $html && ($card = mem_cremation_card_html())) $html = preg_replace('~</div>\s*$~', $card . '</div>', $html, 1);
    return '<div class="mem-pkgs">' . $html . '</div>';
});
