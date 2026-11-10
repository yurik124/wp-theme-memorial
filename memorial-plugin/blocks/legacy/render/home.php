<?php
/** Главная: первый экран и разделы в порядке из «Мемориал → Главная страница» (как прежний front-page.php темы) */
defined('ABSPATH') || exit;
$tel    = esc_attr(memorial_plugin_tel());
$hero   = memorial_plugin_home('hero');
$img    = $hero['img'] ?? '';
$e      = 'memorial_plugin_e';   // текст из настроек с подстановками {years} и т.п.; предлоги не отрываются
$head   = fn($sec) => print(memorial_plugin_sec_head($sec));   // надзаголовок — лавровый разделитель — H2
?>

<?php
// Первый экран «Светлая память»: порядок элементов прежний; фото траурного зала — справа в рамке (без тёмного затемнения), ветвь лавра в углу.
// На телефоне фото — под текстом; настройка «сначала фото» ставит его над текстом, но невысокой полосой.
$hero_mtop = $img && ($hero['mobile'] ?? 'bg') === 'top';
$hero_vars = '';
if ($img) { [$img_d, $img_m] = memorial_plugin_hero_srcs($img); $hero_vars = '--hero-d:url(' . esc_url($img_d) . ');--hero-m:url(' . esc_url($img_m) . ')'; }   // адреса фото — как раньше (их читают проверки и скрипты)
?>
<section class="hero<?php echo $img ? ' hero--img' : ''; echo $hero_mtop ? ' hero--mtop' : ''; ?>"<?php echo $hero_vars ? ' style="' . esc_attr($hero_vars) . '"' : ''; ?>>
  <span class="hero__branch" aria-hidden="true"><?php echo memorial_plugin_laurel('branch_plain'); ?></span>
  <div class="wrap hero__in">
    <div class="hero__text">
      <?php if (!empty($hero['eyebrow'])) : ?><p class="eyebrow"><?php echo $e($hero['eyebrow']); ?></p><?php endif; ?>
      <h1><?php echo $e($hero['title']);
        if (!empty($hero['title_em'])) echo ($hero['title'] ? '<br>' : '') . '<em>' . $e($hero['title_em']) . '</em>'; ?></h1>
      <?php if (!empty($hero['lead'])) : ?><p class="hero__lead"><?php echo nl2br($e($hero['lead'])); ?></p><?php endif; ?>
      <?php if (!empty($hero['trust'])) : ?>
        <ul class="hero__trust lv-list">
          <?php foreach ($hero['trust'] as $t) : ?><li><?php echo memorial_plugin_laurel('leaf') . '<span>' . $e($t['text']) . '</span>'; ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <div class="hero__cta">
        <?php if ($tel) : ?><a class="btn btn--gold btn--lg btn--tel" href="tel:<?php echo $tel; ?>"><?php echo memorial_plugin_icon('phone'); ?><span class="nowrap"><?php echo esc_html(memorial_plugin_opt('phone')); ?></span></a><?php endif; ?>
        <?php // вторая кнопка — «Что делать, если умер человек»: следующий шаг виден сразу, без меню
        if (function_exists('memorial_plugin_home_help_link') && ($help = memorial_plugin_home_help_link())) : ?>
          <a class="btn btn--light btn--lg" href="<?php echo esc_url($help[0]); ?>"><?php echo esc_html($help[1]); ?> <span aria-hidden="true">→</span></a>
        <?php endif; ?>
      </div>
      <?php if ($tel && memorial_plugin_night()) : ?><p class="hero__night"><?php echo memorial_plugin_icon('clock') . esc_html(memorial_plugin_night()); ?></p><?php endif; ?>
    </div>
    <?php if ($img) : ?>
      <figure class="hero__photo">
        <picture>
          <?php if ($img_m !== $img_d) : ?><source media="(max-width:700px)" srcset="<?php echo esc_url($img_m); ?>"><?php endif; ?>
          <img src="<?php echo esc_url($img_d); ?>" alt="" fetchpriority="high" decoding="async">
        </picture>
      </figure>
    <?php endif; ?>
  </div>
</section>

<?php $blocks = []; /* блоки собираются, затем выводятся в порядке из «Мемориал → Главная страница» */ ?>
<?php ob_start(); ?>
<?php $s = memorial_plugin_home('services'); $svc = memorial_plugin_services_items();
if (!empty($s['show']) && $svc) : ?>
<section class="section" id="uslugi">
  <div class="wrap">
    <?php $head($s); echo memorial_plugin_services_grid($svc); ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['services'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = memorial_plugin_home('feats'); if (!empty($s['show']) && !empty($s['items'])) : ?>
<section class="section section--alt" id="services">
  <div class="wrap">
    <?php $head($s); ?>
    <div class="feats">
      <?php foreach ($s['items'] as $i => $f) : ?>
        <?php $ic = memorial_plugin_feat_icon($f); ?>
        <div class="feat reveal<?php echo !empty($f['img']) ? ' feat--img' : ''; ?>">
          <?php if (!empty($f['img'])) : ?>
            <span class="feat__img"><img src="<?php echo esc_url($f['img']); ?>" alt="" loading="lazy" decoding="async"></span>
          <?php elseif ($ic) : ?>
            <span class="feat__ico"><?php echo memorial_plugin_icon($ic); ?></span>
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
<?php $s = memorial_plugin_home('catalog');
$cats = !empty($s['show']) ? get_terms(['taxonomy' => 'mem_cat', 'parent' => 0, 'hide_empty' => false]) : [];
if (!is_wp_error($cats) && $cats) : ?>
<section class="section" id="catalog">
  <div class="wrap">
    <?php $head($s); ?>
    <?php // плитки как в меню под «каталожной» страницей, иначе — все категории
    $root = memorial_plugin_catalog_page();
    ($root && ($kids = memorial_plugin_menu_children($root))) ? memorial_plugin_page_tiles($kids) : memorial_plugin_cat_tiles($cats); ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['catalog'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = memorial_plugin_home('prices'); $pkgs = memorial_plugin_packages(); if (!empty($s['show']) && $pkgs) : ?>
<section class="section section--alt" id="prices">
  <div class="wrap">
    <?php $head($s); ?>
    <?php memorial_plugin_packages_html(); ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['prices'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = memorial_plugin_home('stats'); if (!empty($s['show']) && !empty($s['items'])) : ?>
<section class="stats">
  <div class="wrap stats__in" style="--n:<?php echo min(4, count($s['items'])); ?>">
    <?php foreach ($s['items'] as $st) : $num = $st['num'] !== '' ? number_format((int) $st['num'], 0, '', "\u{00A0}") : ''; ?>
      <div class="stat<?php echo mb_strlen($num) > 3 ? ' stat--long' : ''; ?>">
        <span class="stat__ring"><?php echo memorial_plugin_laurel('wreath_berry'); ?>
          <b<?php if ($st['num'] !== '') echo ' data-count="' . (int) $st['num'] . '"'; ?>><?php echo esc_html($num); ?></b></span>
        <span class="stat__label"><?php echo $e($st['label']); ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['stats'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = memorial_plugin_home('about'); if (!empty($s['show'])) : ?>
<section class="section" id="about">
  <span class="sec-branch" aria-hidden="true"><?php echo memorial_plugin_laurel('branch_plain'); ?></span>
  <div class="wrap about">
    <div>
      <?php if (!empty($s['eyebrow'])) : ?><p class="eyebrow"><?php echo $e($s['eyebrow']); ?></p><?php endif; ?>
      <?php if (!empty($s['title'])) : ?><h2><?php echo $e($s['title']); ?></h2><?php endif; ?>
      <span class="about__div"><?php echo memorial_plugin_laurel('divider'); ?></span>
    </div>
    <div class="about__text">
      <?php echo wp_kses_post(wpautop(memorial_plugin_t($s['text'] ?? ''))); ?>
      <?php // текст самой главной страницы (если главная — страница)
      if (is_page() && ($front = get_post(get_queried_object_id()))) echo apply_filters('the_content', $front->post_content); ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php $blocks['about'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php memorial_plugin_legacy_part('reviews'); ?>
<?php $blocks['reviews'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = memorial_plugin_home('contacts'); if (!empty($s['show'])) : ?>
<section class="section section--alt" id="contacts">
  <div class="wrap contacts">
    <div>
      <?php if (!empty($s['eyebrow'])) : ?><p class="eyebrow"><?php echo $e($s['eyebrow']); ?></p><?php endif; ?>
      <?php if (!empty($s['title'])) : ?><h2><?php echo $e($s['title']); ?></h2><?php endif; ?>
      <?php memorial_plugin_legacy_part('contacts-list'); ?>
    </div>
    <?php memorial_plugin_legacy_part('maps'); ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['contacts'] = ob_get_clean(); ?>

<?php
$order = memorial_plugin_settings('mem_home')['layout'] ?? array_keys(memorial_plugin_home_blocks());
$alt = true;   // первый блок после первого экрана — на «слоновой кости», дальше чередуются
foreach (array_unique(array_merge($order, array_keys($blocks))) as $k) {
    $html = trim($blocks[$k] ?? '');
    if ($html === '') continue;
    // светлые блоки чередуются с бежевыми при любом порядке
    if (preg_match('/^<section class="section( section--alt)?"/', $html)) {
        $html = preg_replace('/^<section class="section( section--alt)?"/', '<section class="section' . ($alt ? ' section--alt' : '') . '"', $html);
        $alt = !$alt;
    } else {
        $alt = false; // после шалфейной полосы с цифрами — снова светлый
    }
    echo $html, "\n";
}
?>

