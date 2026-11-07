<?php
/**
 * Яндекс.Метрика: счётчик + цели «Звонок» (phone_click) и «Письмо» (email_click).
 * Номер счётчика вводится в «Мемориал → Аналитика». К каждой цели передаётся, где нажали
 * (шапка, первый экран, карточка товара…) — это видно в отчёте «Параметры визитов».
 */
defined('ABSPATH') || exit;

function mem_metrika_schema() {
    return ['counter' => ['type' => 'group', 'label' => 'Счётчик', 'fields' => [
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

function mem_metrika_defaults() {
    $old = get_option('mem_metrika'); // до 1.1: галочка «Спрашивать согласие»
    $mode = is_array($old) && isset($old['consent']['on']) && !$old['consent']['on'] ? 'off' : 'ask';
    return [
        'counter' => ['id' => '', 'webvisor' => 1, 'skip_admins' => 1, 'clean_utm' => 1],
        'consent' => ['mode' => $mode, 'code' => '',
                      'text' => 'Мы используем cookie и Яндекс Метрику, чтобы понимать, как посетители пользуются сайтом. Нажимая «Принять», вы соглашаетесь на обработку этих данных в соответствии с {cookies:Политикой использования cookie}.',
                      'text_notice' => 'Продолжая использовать сайт, вы соглашаетесь с обработкой персональных данных, собираемых посредством метрической программы «Яндекс Метрика», в соответствии с {cookies:Политикой использования cookie} и {policy:Политикой обработки персональных данных}.'],
    ];
}

add_filter('mem_admin_pages', function ($pages) {
    $pages['mem_metrika'] = [
        'slug'   => 'mem-metrika',
        'title'  => 'Аналитика',
        'schema' => 'mem_metrika_schema',
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
function mem_metrika_id() {
    $m = mem_settings('mem_metrika')['counter'];
    $id = (int) ($m['id'] ?? 0);
    if (!$id || is_admin() || is_customize_preview()) return 0;
    if (!empty($m['skip_admins']) && current_user_can('edit_posts')) return 0;
    return $id;
}

function mem_consent_cfg() {
    return mem_settings('mem_metrika')['consent'] ?? [];
}

/** Режим плашки: ask — спрашивать согласие, notice — только уведомлять, off — без плашки */
function mem_consent_mode() {
    $m = mem_consent_cfg()['mode'] ?? 'ask';
    return in_array($m, ['ask', 'notice', 'off'], true) ? $m : 'ask';
}

/** Текст плашки со ссылками на документы */
function mem_consent_text() {
    $c = mem_consent_cfg();
    $t = (string) (mem_consent_mode() === 'notice' ? ($c['text_notice'] ?? '') : ($c['text'] ?? ''));
    $html = function_exists('mem_legal_text') ? mem_legal_text($t) : esc_html(preg_replace('~\{\w+:([^}]+)\}~u', '$1', $t));
    if (!preg_match('~\{\w+:~', $t) && function_exists('mem_legal_url') && ($u = mem_legal_url('cookies'))) {
        $html .= ' <a href="' . esc_url($u) . '">Политика использования cookie</a>';
    }
    return $html;
}

/** Нужно ли что-то запускать на этой странице (счётчик или доп. коды) */
function mem_tracking_on() {
    if (is_admin() || is_customize_preview()) return false;
    $m = mem_settings('mem_metrika')['counter'];
    if (!empty($m['skip_admins']) && current_user_can('edit_posts')) return false;
    return (bool) ((int) ($m['id'] ?? 0) || trim(mem_consent_cfg()['code'] ?? ''));
}

/** Показывать плашку согласия: всем посетителям, включая администратора (он должен её видеть),
 *  даже без счётчика — cookie ставят и сторонние скрипты (коллтрекинг, пиксели, виджеты). */
function mem_banner_on() {
    return mem_consent_mode() !== 'off' && !is_admin() && !is_customize_preview();
}

/* Чистый адрес: метки рекламы убираются из адресной строки сразу при открытии страницы.
   Исходный адрес (с метками) запоминается в memOrigUrl — Метрика отправит просмотр именно с ним (см. load() ниже). */
add_action('wp_head', function () {
    if (is_admin() || empty(mem_settings('mem_metrika')['counter']['clean_utm'])) return;
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
    if (!mem_tracking_on() && !mem_banner_on()) return;
    $id = mem_tracking_on() ? mem_metrika_id() : 0; // администратору счётчик не запускаем
    $cfg = [
        'id'   => $id,
        'need' => mem_consent_mode() === 'ask',   // ждать «Принять»
        'mode' => mem_consent_mode(),
        'opts' => [
            'clickmap'            => true,
            'trackLinks'          => true,
            'accurateTrackBounce' => true,
            'webvisor'            => !empty(mem_settings('mem_metrika')['counter']['webvisor']),
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
    if (!mem_tracking_on() && !mem_banner_on()) return;
    $c = mem_consent_cfg();
    if (mem_tracking_on() && trim($c['code'] ?? '')) {
        echo '<template id="mem-after-consent">' . $c['code'] . '</template>' . "\n"; // код вставляет только администратор
    }
    if (mem_banner_on()) : $notice = mem_consent_mode() === 'notice'; ?>
<div class="consent<?php echo $notice ? ' consent--notice' : ''; ?>" id="consent" role="region" aria-label="Cookie" hidden>
  <p><?php echo mem_consent_text(); ?></p>
  <div class="consent__btns">
    <?php if ($notice) : ?>
    <button class="btn btn--gold btn--sm" type="button" data-consent="ok">Понятно</button>
    <?php else : ?>
    <button class="btn btn--gold btn--sm" type="button" data-consent="yes">Принять</button>
    <button class="btn btn--line btn--sm" type="button" data-consent="no">Отклонить</button>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
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
    var v = b.getAttribute('data-consent'); set(v); if (v !== 'ok') log(v); show(false);
    if (v === 'yes') { load(); document.dispatchEvent(new Event('mem:consent')); }
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

/* Журнал согласий: сколько раз в день нажали «Принять» / «Отклонить» (без персональных данных) */
add_action('rest_api_init', function () {
    register_rest_route('mem/v1', '/consent', ['methods' => 'POST', 'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $r) {
            $v = $r->get_param('choice') === 'yes' ? 'yes' : 'no';
            $log = get_option('mem_consent_log', []);
            $day = wp_date('Y-m-d');
            $log[$day][$v] = ($log[$day][$v] ?? 0) + 1;
            if (count($log) > 400) $log = array_slice($log, -400, null, true);
            update_option('mem_consent_log', $log, false);
            return ['ok' => true];
        }]);
});
