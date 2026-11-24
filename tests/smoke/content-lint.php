<?php
/**
 * Дымовой тест без WordPress: проверка текстов страниц (tools/content-lint.php) на образцах
 * tests/content-lint/pages. Хороший файл (катафалк) проходит, в плохих находятся все нарушения из ТЗ.
 * Запуск: php tests/smoke/content-lint.php — код выхода 1 при ошибке.
 */
$root = dirname( __DIR__, 2 );
$cmd  = 'php ' . escapeshellarg( $root . '/tools/content-lint.php' )
	. ' --pages=' . escapeshellarg( $root . '/tests/content-lint/pages' )
	. ' --site=' . escapeshellarg( $root . '/tools/content-site.json' ) . ' 2>&1';
putenv( 'GITHUB_ACTIONS' );
exec( $cmd, $lines, $code );
$out  = implode( "\n", $lines );
$fail = array();
if ( 1 !== $code ) {
	$fail[] = "код выхода $code, ожидался 1";
}
foreach ( array(
	'gruz-200.html: version',
	'gruz-200.html: SEO-заголовок длиннее нормы',
	'gruz-200.html: H1 в тексте',
	'gruz-200.html: Блок core/buttons не из списка',
	'gruz-200.html: Блок «Ритуальный транспорт» на странице груза 200',
	'gruz-200.html: «Ford Transit» на странице груза 200',
	'gruz-200.html: link-button: страница указана числом',
	'gruz-200.html: Цена цифрами: «15 000 ₽»',
	'gruz-200.html: Неизвестный код {price:neizvestno}',
	'gruz-200.html: Запрещено: «WhatsApp»',
	'gruz-200.html: Запрещено: «Лучший»',
	'gruz-200.html: Ссылка на несуществующий адрес /net-takoj/',
	'eksgumaciya.html: Запрещено: «Оформим выплату за вас»',
	'eksgumaciya.html: H2 «какие нужны документы» повторяется',
	'perezahoronenie.html: H2 «какие нужны документы» повторяется',
	'внимание tests/content-lint/pages/ritualnye-uslugi/katafalk.html: H2 «что входит»',
) as $want ) {
	if ( false === strpos( $out, $want ) ) {
		$fail[] = "нет сообщения: $want";
	}
}
if ( preg_match( '/ОШИБКА [^\n]*(katafalk|perezahoronenie)\.html: (?!H2)/u', $out, $m ) ) {
	$fail[] = 'лишняя ошибка: ' . $m[0];
}
if ( $fail ) {
	fwrite( STDERR, "content-lint:\n- " . implode( "\n- ", $fail ) . "\n\n$out\n" );
	exit( 1 );
}
echo "content-lint: всё найдено\n";
