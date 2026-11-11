<?php
/**
 * Яндекс.Метрика: счётчик + цели «Звонок» (phone_click) и «Письмо» (email_click).
 * Номер счётчика вводится в «Мемориал → Аналитика». К каждой цели передаётся, где нажали
 * (шапка, первый экран, карточка товара…) — это видно в отчёте «Параметры визитов».
 */
defined('ABSPATH') || exit;

function memorial_plugin_metrika_schema() {
    return ['counter' => ['type' => 'group', 'label' => 'Счётчик', 'fields' => [
        'load'        => ['type' => 'select', 'label' => __('How Yandex Metrica loads', 'memorial-plugin'), 'options' => [
                              'consent' => __('After cookie consent (recommended): the counter starts only after the visitor clicks “Accept”', 'memorial-plugin'),
                              'always'  => __('Always: the counter loads on every page without waiting (deferred); the cookie notice is still shown', 'memorial-plugin'),
                              'off'     => __('Off: no Metrica code on the site — connect it through a tag manager if needed', 'memorial-plugin'),
                          ],
                          'help' => __('“After cookie consent” and “Always” also set how the cookie bar works. With “Off” the bar follows the setting below.', 'memorial-plugin')],
        'id'          => ['type' => 'number', 'label' => 'Номер счётчика Яндекс.Метрики',
                          'help' => 'Только цифры, например 98765432. Пусто — Метрика на сайте отключена.'],
        'webvisor'    => ['type' => 'checkbox', 'label' => 'Вебвизор — запись того, как посетители смотрят сайт'],
        'skip_admins' => ['type' => 'checkbox', 'label' => 'Не считать визиты тех, кто вошёл в админку (ваши собственные заходы не испортят статистику)'],
        'clean_utm'   => ['type' => 'checkbox', 'label' => 'Убирать рекламные метки из адресной строки (utm_…, yclid, gclid…): посетитель видит и копирует чистый адрес. Метрика всё равно получает адрес с метками — источники в отчётах не теряются'],
    ]],
    'consent' => ['type' => 'group', 'label' => 'Плашка о cookie',
        'help' => 'Ссылки в тексте: {cookies:текст} — на Политику cookie, {policy:текст} — на Политику обработки ПД, {consent:текст} — на Согласие (страницы выбираются в «Мемориал → Документы»). Без меток ссылка на Политику cookie добавится в конце сама.', 'fields' => [
        'mode' => ['type' => 'select', 'label' => 'Как работает', 'options' => [
            'ask'    => 'Спрашивать согласие: Метрика и коды ниже запускаются после «Принять» (строгая трактовка 152-ФЗ)',
            'notice' => 'Только уведомлять: «Продолжая использовать сайт…» — Метрика работает сразу, кнопка «Понятно»',
            'off'    => 'Без плашки: Метрика работает сразу',
        ]],
        'text'        => ['type' => 'textarea', 'label' => 'Текст плашки — «Спрашивать согласие»', 'rows' => 3],
        'text_notice' => ['type' => 'textarea', 'label' => 'Текст плашки — «Только уведомлять»', 'rows' => 3],
        'code' => ['type' => 'code', 'label' => 'Другие счётчики — запускаются только после согласия', 'rows' => 8,
                   'help' => 'Вставьте сюда целиком коды пикселя VK, подмены номеров (Gudok) и т.п. — вместе с тегами <script>. После переноса удалите их из плагина «Simple Custom CSS and JS».'],
    ]],
    ];
}

function memorial_plugin_metrika_defaults() {
    $old = get_option('mem_metrika'); // до 1.1: галочка «Спрашивать согласие»
    $mode = is_array($old) && isset($old['consent']['on']) && !$old['consent']['on'] ? 'off' : 'ask';
    return [
        'counter' => ['load' => 'consent', 'id' => '', 'webvisor' => 1, 'skip_admins' => 1, 'clean_utm' => 1],
        'consent' => ['mode' => $mode, 'code' => '',
                      'text' => 'Мы используем cookie и Яндекс Метрику, чтобы понимать, как посетители пользуются сайтом. Нажимая «Принять», вы соглашаетесь на обработку этих данных в соответствии с {cookies:Политикой использования cookie}.',
                      'text_notice' => 'Продолжая использовать сайт, вы соглашаетесь с обработкой персональных данных, собираемых посредством метрической программы «Яндекс Метрика», в соответствии с {cookies:Политикой использования cookie} и {policy:Политикой обработки персональных данных}.'],
    ];
}

add_filter('memorial_plugin_admin_pages', function ($pages) {
    $pages['mem_metrika'] = [
        'slug'   => 'mem-metrika',
        'title'  => 'Аналитика',
        'schema' => 'memorial_plugin_metrika_schema',
        'intro'  => '<b>Как настроить подсчёт звонков:</b></p><ol class="mem-steps">'
            . '<li>Создайте счётчик на <a href="https://metrika.yandex.ru/add" target="_blank" rel="noopener">metrika.yandex.ru</a> и вставьте его номер ниже. Сохраните.</li>'
            . '<li>В Метрике откройте счётчик → <b>Цели</b> → <b>Добавить цель</b> → тип <b>«JavaScript-событие»</b>. '
            . 'Название «Звонок», идентификатор <code>phone_click</code>. По желанию — ещё цели: <code>callback_sent</code> «Заявка на звонок», <code>max_click</code> «Переход в MAX», <code>email_click</code> «Письмо».</li>'
            . '<li>Через день-два смотрите: <b>Отчёты → Источники → Сводка</b>, вверху выберите цель «Звонок» — увидите, откуда пришли звонящие (Яндекс, Google, карты, реклама). '
            . 'В <b>Отчёты → Содержание → Параметры визитов</b> видно, на какой кнопке нажимают чаще: шапка, первый экран, карточка товара и т.д.</li>'
            . '</ol><p class="description">Цель «Звонок» отправляется при нажатии на номер с мобильного телефона; на компьютере вместо звонка открывается MAX или окно заявки, их считают цели <code>max_click</code> и <code>callback_sent</code>. Если человек переписал номер с экрана вручную, Метрика такой звонок не увидит — для точного учёта всех звонков нужен коллтрекинг.',
    ];
    return $pages;
});

/** Номер счётчика, если Метрику нужно выводить на этой странице */
/**
 * Как загружается Метрика: consent — после согласия, always — сразу, off — не подключается.
 *
 * @return string
 */
function memorial_plugin_metrika_load() {
    $l = memorial_plugin_settings('mem_metrika')['counter']['load'] ?? 'consent';
    return in_array($l, ['consent', 'always', 'off'], true) ? $l : 'consent';
}

function memorial_plugin_metrika_id() {
    $m = memorial_plugin_settings('mem_metrika')['counter'];
    $id = memorial_plugin_metrika_load() === 'off' ? 0 : (int) ($m['id'] ?? 0);
    if (!$id || is_admin() || is_customize_preview()) return 0;
    if (!empty($m['skip_admins']) && current_user_can('edit_posts')) return 0;
    return $id;
}

function memorial_plugin_consent_cfg() {
    return memorial_plugin_settings('mem_metrika')['consent'] ?? [];
}

/** Режим плашки: ask — спрашивать согласие, notice — только уведомлять, off — без плашки.
 *  Режим загрузки Метрики главнее: «после согласия» — всегда спрашиваем, «всегда» — всегда уведомляем
 *  (плашка нужна и виджету отзывов); при «выключено» — как выбрано в настройке плашки. */
function memorial_plugin_consent_mode() {
    $load = memorial_plugin_metrika_load();
    if ($load === 'consent') return 'ask';
    if ($load === 'always') return 'notice';
    $m = memorial_plugin_consent_cfg()['mode'] ?? 'ask';
    return in_array($m, ['ask', 'notice', 'off'], true) ? $m : 'ask';
}

/** Текст плашки со ссылками на документы */
function memorial_plugin_consent_text() {
    $c = memorial_plugin_consent_cfg();
    $t = (string) (memorial_plugin_consent_mode() === 'notice' ? ($c['text_notice'] ?? '') : ($c['text'] ?? ''));
    $html = function_exists('memorial_plugin_legal_text') ? memorial_plugin_legal_text($t) : esc_html(preg_replace('~\{\w+:([^}]+)\}~u', '$1', $t));
    if (!preg_match('~\{\w+:~', $t) && function_exists('memorial_plugin_legal_url') && ($u = memorial_plugin_legal_url('cookies'))) {
        $html .= ' <a href="' . esc_url($u) . '">Политика использования cookie</a>';
    }
    return $html;
}

/** Нужно ли что-то запускать на этой странице (счётчик или доп. коды) */
function memorial_plugin_tracking_on() {
    if (is_admin() || is_customize_preview()) return false;
    $m = memorial_plugin_settings('mem_metrika')['counter'];
    if (!empty($m['skip_admins']) && current_user_can('edit_posts')) return false;
    $id = memorial_plugin_metrika_load() === 'off' ? 0 : (int) ($m['id'] ?? 0);
    return (bool) ($id || trim(memorial_plugin_consent_cfg()['code'] ?? ''));
}

/** Показывать плашку согласия: всем посетителям, включая администратора (он должен её видеть),
 *  даже без счётчика — cookie ставят и сторонние скрипты (коллтрекинг, пиксели, виджеты). */
function memorial_plugin_banner_on() {
    return memorial_plugin_consent_mode() !== 'off' && !is_admin() && !is_customize_preview();
}

/* Чистый адрес: метки рекламы убираются из адресной строки сразу при открытии страницы.
   Исходный адрес (с метками) запоминается в memOrigUrl — Метрика отправит просмотр именно с ним (см. load() ниже). */
add_action('wp_head', function () {
    if (is_admin() || empty(memorial_plugin_settings('mem_metrika')['counter']['clean_utm'])) return;
    ?>
<script>
(function(){try{var u=new URL(location.href),p=u.searchParams,k=[];p.forEach(function(v,n){if(/^utm_|^(yclid|gclid|fbclid|ysclid|msclkid|_openstat|roistat|etext)$/i.test(n))k.push(n)});
if(!k.length)return;window.memOrigUrl=location.href;k.forEach(function(n){p.delete(n)});var q=p.toString();
history.replaceState(history.state,'',u.pathname+(q?'?'+q:'')+u.hash)}catch(e){}})();
</script>
<?php
}, 1);

/* Счётчик: заготовка ym() сразу (цели копятся), сам tag.js — только после согласия */
add_action('wp_head', function () {
    if (!memorial_plugin_tracking_on() && !memorial_plugin_banner_on()) return;
    $id = memorial_plugin_tracking_on() ? memorial_plugin_metrika_id() : 0; // администратору счётчик не запускаем
    $cfg = [
        'id'   => $id,
        'need' => memorial_plugin_consent_mode() === 'ask',   // ждать «Принять»
        'mode' => memorial_plugin_consent_mode(),
        'opts' => [
            'clickmap'            => true,
            'trackLinks'          => true,
            'accurateTrackBounce' => true,
            'webvisor'            => !empty(memorial_plugin_settings('mem_metrika')['counter']['webvisor']),
        ],
    ];
    ?>
<script>
window.memTrack=<?php echo wp_json_encode($cfg); ?>;
<?php if ($id) : ?>window.memYm=<?php echo (int) $id; ?>;
window.ym=window.ym||function(){(ym.a=ym.a||[]).push(arguments)};ym.l=1*new Date();
<?php endif; ?>
</script>
<?php
}, 2);

/* Загрузчик: Метрика + доп. коды; плашка согласия */
add_action('wp_footer', function () {
    if (!memorial_plugin_tracking_on() && !memorial_plugin_banner_on()) return;
    $c = memorial_plugin_consent_cfg();
    if (memorial_plugin_tracking_on() && trim($c['code'] ?? '')) {
        echo '<template id="mem-after-consent">' . $c['code'] . '</template>' . "\n"; // код вставляет только администратор
    }
    // плашка: в блоковой теме — блок memorial-plugin/cookie-banner в подвале (Block Hooks), в классической — здесь
    if (!wp_is_block_theme()) echo memorial_plugin_consent_banner_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана с экранированием.
    ?>
<script>
(function () {
  var T = window.memTrack, KEY = 'mem_consent_v1', box = document.getElementById('consent');
  function load() {
    if (load.done) return; load.done = 1;
    if (T.id) {
      var s = document.createElement('script'); s.async = 1; s.src = 'https://mc.yandex.ru/metrika/tag.js';
      document.head.appendChild(s);
      if (window.memOrigUrl) {   // адрес почистили от меток — просмотр отправляем вручную, с исходным адресом
        ym(T.id, 'init', Object.assign({}, T.opts, { defer: true }));
        ym(T.id, 'hit', window.memOrigUrl, { title: document.title, referer: document.referrer });
      } else ym(T.id, 'init', T.opts);
    }
    var tpl = document.getElementById('mem-after-consent');
    if (tpl) [].forEach.call(tpl.content.childNodes, function (n) {
      if (n.nodeName === 'SCRIPT') {               // скрипты из шаблона нужно пересоздать, иначе не запустятся
        var x = document.createElement('script');
        [].forEach.call(n.attributes, function (a) { x.setAttribute(a.name, a.value); });
        x.text = n.textContent; document.body.appendChild(x);
      } else document.body.appendChild(n.cloneNode(true));
    });
  }
  // Отзыв согласия: счётчик больше ничего не отправляет, cookie Метрики (_ym*) удаляются.
  // Уже отправленные данные отозвать нельзя — это написано в политике cookie.
  function revoke() {
    if (T.id) window['disableYaCounter' + T.id] = true;
    var host = location.hostname.split('.'), doms = [''];
    for (var i = 0; i < host.length - 1; i++) doms.push('; domain=.' + host.slice(i).join('.'));
    document.cookie.split(';').forEach(function (c) {
      var name = c.split('=')[0].trim();
      if (name.indexOf('_ym') !== 0) return;
      doms.forEach(function (d) { document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + d; });
    });
    document.dispatchEvent(new Event('mem:consent-revoked'));
  }
  function get() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function set(v) { try { localStorage.setItem(KEY, v); } catch (e) {} }
  function log(v) { try { navigator.sendBeacon('<?php echo esc_url_raw(rest_url('mem/v1/consent')); ?>', new Blob([JSON.stringify({ choice: v })], { type: 'application/json' })); } catch (e) {} }
  function show(on) { if (!box) return; box.hidden = !on; document.body.classList.toggle('has-consent', on);
    if (on) document.documentElement.style.setProperty('--consent-h', box.offsetHeight + 'px'); }
  if (!T.need || get() === 'yes') load();
  if (T.mode === 'notice') { if (get() !== 'ok' && get() !== 'yes') show(true); }   // уведомление: показать, пока не нажали «Понятно»
  else if (T.need && get() !== 'yes' && get() !== 'no') show(true);
  if (box) box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-consent]'); if (!b) return;
    var was = get(), v = b.getAttribute('data-consent'); set(v); if (v !== 'ok') log(v); show(false);
    if (v === 'yes') { load(); document.dispatchEvent(new Event('mem:consent')); }
    if (v === 'no' && was === 'yes') revoke();
  });
  // «Настройки cookie» в подвале — передумать
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-consent-reset]')) return;
    e.preventDefault(); try { localStorage.removeItem(KEY); } catch (x) {} show(true);
  });
})();
</script>
<?php
}, 20);

/** Плашка о cookie (один раз на странице; пусто, если плашка не нужна) */
function memorial_plugin_consent_banner_html() {
    static $done = false;
    if ($done || !memorial_plugin_banner_on()) return '';
    $done = true;
    $notice = memorial_plugin_consent_mode() === 'notice';
    ob_start(); ?>
<div class="consent<?php echo $notice ? ' consent--notice' : ''; ?>" id="consent" role="region" aria-label="Cookie" hidden>
  <p><?php echo memorial_plugin_consent_text(); ?></p>
  <div class="consent__btns">
    <?php if ($notice) : ?>
    <button class="btn btn--gold btn--sm" type="button" data-consent="ok">Понятно</button>
    <?php else : ?>
    <button class="btn btn--gold btn--sm" type="button" data-consent="yes">Принять</button>
    <button class="btn btn--line btn--sm" type="button" data-consent="no">Отклонить</button>
    <?php endif; ?>
  </div>
</div>
<?php
    return (string) ob_get_clean();
}

/* Журнал согласий: сколько раз в день нажали «Принять» / «Отклонить» (без персональных данных) */
add_action('rest_api_init', function () {
    register_rest_route('mem/v1', '/consent', ['methods' => 'POST', 'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $r) {
            // один голос в сутки с адреса (хранится только хэш адреса, на сутки) — журнал нельзя «накрутить»
            $key = 'mem_cl_' . substr(md5((string) ($_SERVER['REMOTE_ADDR'] ?? '') . wp_salt('nonce')), 0, 16);
            if (get_transient($key)) return ['ok' => true];
            set_transient($key, 1, DAY_IN_SECONDS);
            $v = $r->get_param('choice') === 'yes' ? 'yes' : 'no';
            $log = get_option('mem_consent_log', []);
            $day = wp_date('Y-m-d');
            $log[$day][$v] = ($log[$day][$v] ?? 0) + 1;
            if (count($log) > 400) $log = array_slice($log, -400, null, true);
            update_option('mem_consent_log', $log, false);
            return ['ok' => true];
        }]);
});

/**
 * Разовый перенос (2.0): режим загрузки Метрики выбирается по тому, как сайт работал до обновления.
 *   номера счётчика нет            → «Выключено»
 *   плашка «Спрашивать согласие»   → «После согласия»
 *   «Только уведомлять» / «Без плашки» → «Всегда»
 * Администратору показывается просьба подтвердить режим — пока он не сохранит «Аналитику».
 */
function memorial_plugin_metrika_migrate_load() {
    if (get_option('memorial_plugin_metrika_load_migrated')) return;
    $raw = get_option('mem_metrika', []);
    $raw = is_array($raw) ? $raw : [];
    if (empty($raw['counter']['load'])) {
        $mode = $raw['consent']['mode'] ?? memorial_plugin_metrika_defaults()['consent']['mode'];
        if (!(int) ($raw['counter']['id'] ?? 0)) $load = 'off';
        elseif ($mode === 'ask') $load = 'consent';
        else $load = 'always';
        $raw['counter']['load'] = $load;
        update_option('mem_metrika', $raw);
        update_option('memorial_plugin_metrika_confirm', 1, false);
    }
    update_option('memorial_plugin_metrika_load_migrated', 1, false);
}
add_action('admin_init', 'memorial_plugin_metrika_migrate_load');

/* Просьба подтвердить режим; снимается при сохранении страницы «Аналитика» (даже без изменений) */
add_action('admin_notices', function () {
    if (!get_option('memorial_plugin_metrika_confirm') || !current_user_can('manage_options')) return;
    $labels = [
        'consent' => __('after cookie consent', 'memorial-plugin'),
        'always'  => __('always', 'memorial-plugin'),
        'off'     => __('off', 'memorial-plugin'),
    ];
    printf(
        '<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
        esc_html(sprintf(
            /* translators: %s: current Yandex Metrica load mode. */
            __('Yandex Metrica now has a load mode setting. It was set to “%s” to match how the site worked before the update. Please confirm or change it.', 'memorial-plugin'),
            $labels[(get_option('mem_metrika', [])['counter']['load'] ?? '')] ?? $labels['consent']
        )),
        esc_url(admin_url('admin.php?page=mem-metrika')),
        esc_html__('Open Analytics settings', 'memorial-plugin')
    );
});
add_filter('pre_update_option_mem_metrika', function ($value) {
    delete_option('memorial_plugin_metrika_confirm');
    return $value;
});
