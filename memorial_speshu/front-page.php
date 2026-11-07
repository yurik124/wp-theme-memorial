<?php
/* Без плагина Memorial Core блоков главной нет (они собираются из «Мемориал → Главная страница») — показываем текст страницы */
if (!defined('MEM_CORE_VER')) { require get_template_directory() . (is_page() ? '/page.php' : '/index.php'); return; }
get_header();
$tel    = esc_attr(mem_tel());
$hero   = mem_home('hero');
$img    = $hero['img'] ?? '';
$e      = fn($s) => esc_html(mem_t($s));   // текст из настроек с подстановками {years} и т.п.
$head   = function ($sec) use ($e) {       // заголовок блока
    if (!empty($sec['eyebrow']) || !empty($sec['title'])) {
        echo '<header class="sec-head">';
        if (!empty($sec['eyebrow'])) echo '<p class="eyebrow">' . $e($sec['eyebrow']) . '</p>';
        if (!empty($sec['title'])) echo '<h2>' . $e($sec['title']) . '</h2>';
        echo '</header>';
    }
};
?>
<main id="main">

<?php
// Тонировка первого экрана: цвет, сила, вид (настройки «Главная страница → Первый экран»)
$tint = $hero['tint'] ?? 'grad';
$op = max(0, min(100, (int) (($hero['tint_op'] ?? '') === '' ? 75 : $hero['tint_op'])));
if ($tint === 'off') $op = 0;
$a1 = $tint === 'flat' ? $op : (int) round($op * .73);
$a2 = $tint === 'flat' ? $op : min(100, (int) round($op * 1.23));
$hs = ['--hero-a1:' . $a1 . '%', '--hero-a2:' . $a2 . '%'];
if (!empty($hero['tint_color'])) $hs[] = '--hero-tint:' . $hero['tint_color'];
if ($img) $hs[] = '--hero:url(' . esc_url($img) . ')';
?>
<section class="hero<?php echo $a2 < 45 ? ' hero--light' : ''; echo $img && ($hero['mobile'] ?? 'bg') === 'top' ? ' hero--mtop' : ''; ?>" style="<?php echo esc_attr(implode(';', $hs)); ?>">
  <div class="wrap hero__in">
    <?php if (!empty($hero['eyebrow'])) : ?><p class="eyebrow"><?php echo $e($hero['eyebrow']); ?></p><?php endif; ?>
    <h1><?php echo $e($hero['title']);
      if (!empty($hero['title_em'])) echo ($hero['title'] ? '<br>' : '') . '<em>' . $e($hero['title_em']) . '</em>'; ?></h1>
    <?php if (!empty($hero['lead'])) : ?><p class="hero__lead"><?php echo nl2br($e($hero['lead'])); ?></p><?php endif; ?>
    <?php if (!empty($hero['trust'])) : ?>
      <ul class="hero__trust">
        <?php foreach ($hero['trust'] as $t) : ?><li><?php echo mem_icon('check') . $e($t['text']); ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <div class="hero__cta">
      <?php if ($tel) : ?><a class="btn btn--gold btn--lg" href="tel:<?php echo $tel; ?>"><?php echo mem_icon('phone'); ?><span class="nowrap"><?php echo esc_html(mem_opt('phone')); ?></span></a><?php endif; ?>
      <?php if ($tel && mem_night()) : ?><p class="hero__night"><span class="dot" aria-hidden="true"></span><?php echo esc_html(mem_night()); ?></p><?php endif; ?>
    </div>
  </div>
</section>

<?php $blocks = []; /* блоки собираются, затем выводятся в порядке из «Мемориал → Главная страница» */ ?>
<?php ob_start(); ?>
<?php $s = mem_home('services'); $svc = mem_services_items();
if (!empty($s['show']) && $svc) : ?>
<section class="section" id="uslugi">
  <div class="wrap">
    <?php $head($s); echo mem_services_grid($svc); ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['services'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = mem_home('feats'); if (!empty($s['show']) && !empty($s['items'])) : ?>
<section class="section section--alt" id="services">
  <div class="wrap">
    <?php $head($s); ?>
    <div class="feats">
      <?php foreach ($s['items'] as $i => $f) : ?>
        <?php $ic = mem_feat_icon($f); ?>
        <div class="feat reveal<?php echo !empty($f['img']) ? ' feat--img' : ''; ?>">
          <?php if (!empty($f['img'])) : ?>
            <span class="feat__img"><img src="<?php echo esc_url($f['img']); ?>" alt="" loading="lazy" decoding="async"></span>
          <?php elseif ($ic) : ?>
            <span class="feat__ico"><?php echo mem_icon($ic); ?></span>
          <?php else : ?>
            <span class="feat__n"><?php echo sprintf('%02d', $i + 1); ?></span>
          <?php endif; ?>
          <?php if ($f['title']) : ?><h3><?php echo $e($f['title']); ?></h3><?php endif; ?>
          <?php if ($f['text']) : ?><p><?php echo nl2br($e($f['text'])); ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php $blocks['feats'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = mem_home('catalog');
$cats = !empty($s['show']) ? get_terms(['taxonomy' => 'mem_cat', 'parent' => 0, 'hide_empty' => false]) : [];
if (!is_wp_error($cats) && $cats) : ?>
<section class="section" id="catalog">
  <div class="wrap">
    <?php $head($s); ?>
    <?php // плитки как в меню под «каталожной» страницей, иначе — все категории
    $root = mem_catalog_page();
    ($root && ($kids = mem_menu_children($root))) ? mem_page_tiles($kids) : mem_cat_tiles($cats); ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['catalog'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = mem_home('prices'); $pkgs = mem_packages(); if (!empty($s['show']) && $pkgs) : ?>
<section class="section section--alt" id="prices">
  <div class="wrap">
    <?php $head($s); ?>
    <?php mem_packages_html(); ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['prices'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = mem_home('stats'); if (!empty($s['show']) && !empty($s['items'])) : ?>
<section class="stats">
  <div class="wrap stats__in" style="--n:<?php echo min(4, count($s['items'])); ?>">
    <?php foreach ($s['items'] as $st) : ?>
      <div class="stat">
        <b<?php if ($st['num'] !== '') echo ' data-count="' . (int) $st['num'] . '"'; ?>><?php echo $st['num'] !== '' ? esc_html(number_format((int) $st['num'], 0, '', "\u{00A0}")) : ''; ?></b>
        <span><?php echo $e($st['label']); ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['stats'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = mem_home('about'); if (!empty($s['show'])) : ?>
<section class="section" id="about">
  <div class="wrap about">
    <div>
      <?php if (!empty($s['eyebrow'])) : ?><p class="eyebrow"><?php echo $e($s['eyebrow']); ?></p><?php endif; ?>
      <?php if (!empty($s['title'])) : ?><h2><?php echo $e($s['title']); ?></h2><?php endif; ?>
    </div>
    <div class="about__text">
      <?php echo wp_kses_post(wpautop(mem_t($s['text'] ?? ''))); ?>
      <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php $blocks['about'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php get_template_part('template-parts/reviews'); ?>
<?php $blocks['reviews'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = mem_home('contacts'); if (!empty($s['show'])) : ?>
<section class="section section--alt" id="contacts">
  <div class="wrap contacts">
    <div>
      <?php if (!empty($s['eyebrow'])) : ?><p class="eyebrow"><?php echo $e($s['eyebrow']); ?></p><?php endif; ?>
      <?php if (!empty($s['title'])) : ?><h2><?php echo $e($s['title']); ?></h2><?php endif; ?>
      <?php get_template_part('template-parts/contacts-list'); ?>
    </div>
    <?php get_template_part('template-parts/maps'); ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['contacts'] = ob_get_clean(); ?>

<?php
$order = mem_settings('mem_home')['layout'] ?? array_keys(mem_home_blocks());
$alt = false;
foreach (array_unique(array_merge($order, array_keys($blocks))) as $k) {
    $html = trim($blocks[$k] ?? '');
    if ($html === '') continue;
    // светлые блоки чередуются с бежевыми при любом порядке
    if (preg_match('/^<section class="section( section--alt)?"/', $html)) {
        $html = preg_replace('/^<section class="section( section--alt)?"/', '<section class="section' . ($alt ? ' section--alt' : '') . '"', $html);
        $alt = !$alt;
    } else {
        $alt = false; // после тёмной полосы с цифрами — снова светлый
    }
    echo $html, "\n";
}
?>

</main>
<?php get_footer(); ?>
