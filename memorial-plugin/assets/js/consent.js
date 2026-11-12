/**
 * Consent banner and the Yandex Metrika loader (seo/metrika.php).
 *
 * The counter (tag.js) and the extra codes of “Memorial → Metrika and cookie” are loaded only after
 * the visitor agrees (mode “ask”) or right away (modes “notice” and “off”). Config: window.memTrack.
 */
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
	function log(v) { try { navigator.sendBeacon(T.log, new Blob([JSON.stringify({ choice: v })], { type: 'application/json' })); } catch (e) {} }
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
