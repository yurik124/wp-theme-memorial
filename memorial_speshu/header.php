<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="format-detection" content="telephone=no">
<meta name="theme-color" content="<?php echo esc_attr(mem_theme_color()); ?>">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#main">Перейти к содержимому</a>

<?php $a0 = mem_addrs()[0] ?? null; if ($a0) : ?>
<div class="topbar">
  <div class="wrap topbar__in">
    <span><?php echo mem_icon('pin') . esc_html(memorial_addr($a0['addr'])); ?></span>
    <?php if (!empty($a0['hours'])) : ?><span><?php echo mem_icon('clock') . esc_html($a0['hours']); ?></span><?php endif; ?>
    <?php if (mem_night()) : ?><span class="topbar__night"><?php echo mem_icon('phone') . esc_html(mem_night()); ?></span><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<header class="header" id="header">
  <div class="wrap header__in">
    <a class="logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php bloginfo('name'); ?> — на главную">
      <?php if (has_custom_logo()) :
          echo wp_get_attachment_image(get_theme_mod('custom_logo'), 'full', false, ['class' => 'logo__img', 'alt' => '']);
      else : ?>
        <span class="logo__mark" aria-hidden="true"><?php echo mem_laurel('mark', mem_logo_letter()); ?></span>
      <?php endif; ?>
      <span class="logo__text">
        <b><?php bloginfo('name'); ?></b>
        <small>ритуальная компания</small>
      </span>
    </a>

    <nav class="nav" id="nav" aria-label="Главное меню">
      <?php
      wp_nav_menu([
          'theme_location' => 'primary',
          'container'      => false,
          'depth'          => 3,
          'fallback_cb'    => function () {
              $h = esc_url(home_url('/'));
              echo '<ul>'
                 . '<li><a href="' . esc_url(mem_catalog_url()) . '">Каталог</a></li>'
                 . '<li><a href="' . $h . '#prices">Цены</a></li>'
                 . '<li><a href="' . $h . '#about">О нас</a></li>'
                 . '<li><a href="' . $h . '#contacts">Контакты</a></li></ul>';
          },
      ]);
      ?>
      <?php if (mem_tel()) : ?><button class="btn btn--line nav__cb" type="button" data-callback>Перезвоните мне</button><?php endif; ?>
    </nav>

    <?php if (mem_tel()) : ?>
    <a class="header__phone" href="tel:<?php echo esc_attr(mem_tel()); ?>">
      <b class="nowrap"><?php echo esc_html(mem_opt('phone')); ?></b>
      <?php if (mem_night()) : ?><small>круглосуточно</small><?php endif; ?>
    </a>
    <button class="btn btn--line header__cb" type="button" data-callback>Перезвоните мне</button>
    <a class="header__call" href="tel:<?php echo esc_attr(mem_tel()); ?>" aria-label="Позвонить <?php echo esc_attr(mem_opt('phone')); ?>"><?php echo mem_icon('phone'); ?></a>
    <?php endif; ?>

    <button class="burger" id="burger" type="button" aria-label="Меню" aria-controls="nav" aria-expanded="false"><span></span></button>
  </div>
</header>
<script>
/* Меню не помещается в строку — переключаем шапку на бургер. Проверяем до отрисовки, без мигания. */
(function () {
  var h = document.getElementById('header'), nav = document.getElementById('nav');
  if (!h || !nav) return;
  function over() { // последний пункт вылез за край меню или пункты переносятся на новую строку
    var li = nav.querySelectorAll(':scope > ul > li');   // кнопка .nav__cb видна только в мобильном меню
    if (!li.length) return false;
    var a = li[0].getBoundingClientRect(), b = li[li.length - 1].getBoundingClientRect();
    return b.right > nav.getBoundingClientRect().right + 1 || b.top > a.top + 4;
  }
  function fit() {
    var was = h.classList.contains('is-compact');
    h.classList.add('is-measuring'); // без анимации, иначе меню мелькает
    h.classList.remove('is-compact', 'is-tworow');
    var compact = innerWidth <= 900, two = false;
    if (!compact && over()) {
      // на компьютере меню переносим во вторую строку шапки, а не прячем в «бургер»
      h.classList.add('is-tworow');
      two = !over();
      if (!two) { h.classList.remove('is-tworow'); compact = true; }
    }
    h.classList.toggle('is-compact', compact);
    document.documentElement.classList.toggle('hdr-two', two);
    if (two) document.documentElement.style.setProperty('--hh2', h.offsetHeight + 'px');
    void nav.offsetWidth;
    h.classList.remove('is-measuring');
    if (was && !compact) {
      nav.classList.remove('is-open');
      document.body.classList.remove('menu-open');
      document.getElementById('burger').setAttribute('aria-expanded', 'false');
    }
  }
  fit();
  if (window.ResizeObserver) new ResizeObserver(fit).observe(h.querySelector('.header__in'));
  else addEventListener('resize', fit);
  if (document.fonts) document.fonts.ready.then(fit);
  addEventListener('load', fit);
})();
</script>
