<?php
/* Без плагина Memorial Core блоков главной нет (они собираются из «Мемориал → Главная страница») — показываем текст страницы */
if (!defined('MEM_CORE_VER')) { require get_template_directory() . (is_page() ? '/page.php' : '/index.php'); return; }
get_header();
$tel    = esc_attr(mem_tel());
$hero   = mem_home('hero');
$img    = $hero['img'] ?? '';
$e      = fn($s) => memorial_nbsp(esc_html(mem_t($s)));   // текст из настроек с подстановками {years} и т.п.; предлоги не отрываются
$head   = fn($sec) => memorial_sec_head($sec, $e);   // надзаголовок — лавровый разделитель — H2 (functions.php)
?>
<main id="main">

<?php
// Первый экран «Светлая память»: текст слева, фото траурного зала справа в рамке (без тёмного затемнения), ветвь лавра в углу.
// На телефоне фото — под текстом; настройка «сначала фото» ставит его над текстом, но невысокой полосой.
$hero_mtop = $img && ($hero['mobile'] ?? 'bg') === 'top';
?>
<section class="hero<?php echo $img ? ' hero--img' : ''; echo $hero_mtop ? ' hero--mtop' : ''; ?>">
  <span class="hero__branch" aria-hidden="true"><?php echo mem_laurel('branch'); ?></span>
  <div class="wrap hero__in">
    <div class="hero__text">
      <?php if (!empty($hero['eyebrow'])) : ?><p class="eyebrow"><?php echo $e($hero['eyebrow']); ?></p><?php endif; ?>
      <h1><?php echo $e($hero['title']);
        if (!empty($hero['title_em'])) echo ($hero['title'] ? '<br>' : '') . '<em>' . $e($hero['title_em']) . '</em>'; ?></h1>
      <?php if (!empty($hero['lead'])) : ?><p class="hero__lead"><?php echo nl2br($e($hero['lead'])); ?></p><?php endif; ?>
      <div class="hero__cta">
        <?php if ($tel) : ?><a class="btn btn--gold btn--lg btn--tel" href="tel:<?php echo $tel; ?>"><?php echo mem_icon('phone'); ?><span class="nowrap"><?php echo esc_html(mem_opt('phone')); ?></span></a>
        <button class="btn btn--line btn--lg" type="button" data-callback>Перезвоните мне</button><?php endif; ?>
      </div>
      <?php if (!empty($hero['trust'])) : ?>
        <ul class="hero__trust lv-list">
          <?php foreach ($hero['trust'] as $t) : ?><li><?php echo mem_laurel('leaf') . '<span>' . $e($t['text']) . '</span>'; ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php // «Что делать, если умер человек»: следующий шаг виден сразу, без меню
      if (function_exists('mem_home_help_link') && ($help = mem_home_help_link())) : ?>
        <a class="link-more hero__help" href="<?php echo esc_url($help[0]); ?>"><?php echo esc_html($help[1]); ?> <span aria-hidden="true">→</span></a>
      <?php endif; ?>
      <?php if ($tel && mem_night()) : ?><p class="hero__night"><?php echo mem_icon('clock') . esc_html(mem_night()); ?></p><?php endif; ?>
    </div>
    <?php if ($img) : [$img_d, $img_m] = memorial_hero_srcs($img); ?>
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
    <?php foreach ($s['items'] as $st) : $num = $st['num'] !== '' ? number_format((int) $st['num'], 0, '', "\u{00A0}") : ''; ?>
      <div class="stat<?php echo mb_strlen($num) > 3 ? ' stat--long' : ''; ?>">
        <span class="stat__ring"><?php echo mem_laurel('wreath_berry'); ?>
          <b<?php if ($st['num'] !== '') echo ' data-count="' . (int) $st['num'] . '"'; ?>><?php echo esc_html($num); ?></b></span>
        <span class="stat__label"><?php echo $e($st['label']); ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php $blocks['stats'] = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php $s = mem_home('about'); if (!empty($s['show'])) : ?>
<section class="section" id="about">
  <span class="sec-branch" aria-hidden="true"><?php echo mem_laurel('branch_plain'); ?></span>
  <div class="wrap about">
    <div>
      <?php if (!empty($s['eyebrow'])) : ?><p class="eyebrow"><?php echo $e($s['eyebrow']); ?></p><?php endif; ?>
      <?php if (!empty($s['title'])) : ?><h2><?php echo $e($s['title']); ?></h2><?php endif; ?>
      <span class="about__div"><?php echo mem_laurel('divider'); ?></span>
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

</main>
<?php get_footer(); ?>
