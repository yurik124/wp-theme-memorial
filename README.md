# Сайт «Мемориал»: тема и плагин

Монорепозиторий: блоковая тема **Memorial Modern 5.1.0** и плагин **Memorial Plugin 2.1.0** (финальная версия) для WordPress 7.1.

- `MIGRATION.md` — что было и что стало, как обновить рабочий сайт до 5.1.0 / 2.1.0 и откатить (из резервной копии); история перехода с 4.1.0 / 1.15.0.
- `STANDARDS_REPORT.md` — линтеры, доступность, контраст, тесты, допущения и отклонения.
- `CHANGELOG.md` — изменения выпуска.

| Папка | Что это |
|---|---|
| `memorial-theme/` | Тема Memorial Modern (text domain `memorial-theme`, префикс `memorial_theme_`) |
| `memorial-plugin/` | Memorial Plugin — функционал сайта (text domain `memorial-plugin`, префикс `memorial_plugin_`) |
| `memorial-plugin/src/` | Исходники JS экранов админки (собираются в `memorial-plugin/build/`, в Git не хранится) |
| `memorial-plugin/assets/js/view/` | Модули Interactivity API для блоков на сайте (без сборки) |
| `memorial-*/languages/` | Переводы: `.pot`, русский `.po` / `.mo`, JSON для скриптов админки |
| `tools/` | Проверки и инструменты без WordPress: префиксы (`check-prefixes.php`), JSON-схемы (`validate-json.mjs`), контраст (`check-contrast.mjs`), шаблоны (`check-templates.php`), переводы (`i18n.php`), сжатые копии (`minify.php`) |
| `memorial-plugin/tests/phpunit/` | PHPUnit: способности, REST, логика, переводы, удаление плагина |
| `tests/smoke/` | Дымовые тесты PHP без WordPress |
| `tests/e2e/` | Проверки в браузере на WordPress 7.1.4 (wp-env + Playwright) |
| `memorial-tests/` | Автотесты сайта на Python (только локально, в Git не попадают) |

## Установка на сайт

1. Выгрузка — Actions → «Деплой на сайт» (или вручную: папки `memorial-theme` в `wp-content/themes/`, `memorial-plugin` с собранной `build/` в `wp-content/plugins/`).
2. Плагины → Memorial Plugin → Активировать: плагин выключит Memorial Core и включит тему. Подробно — `MIGRATION.md`.
3. Язык сайта `ru_RU` — интерфейс на русском из `languages/` темы и плагина.

## Требования

WordPress 7.1+, PHP 8.1+ (на хостинге 8.4). Для разработки: Node.js 22 (`.nvmrc`), Composer 2, Docker — для wp-env.

## Сборка и проверки

```
npm install            # первый раз; затем закоммитьте package-lock.json
composer install       # PHPCS: WPCS, PHPCompatibilityWP, VariableAnalysis
npm run build          # JS → memorial-plugin/build
npm run lint:js        # ESLint (@wordpress/eslint-plugin)
npm run lint:css       # Stylelint (@wordpress/stylelint-config)
npm run lint:json      # theme.json / block.json по схемам WordPress
php tools/check-prefixes.php
npm run test:smoke
composer lint:plugin   # WPCS для нового кода плагина
composer lint:compat   # совместимость PHP 8.1+ для всего кода
npm run env:start && npm run test:e2e   # WordPress 7.1.4 в Docker
npx wp-env run tests-cli --env-cwd=wp-content/plugins/memorial-plugin vendor/bin/phpunit   # PHPUnit (нужен "testsEnvironment": true и composer install в memorial-plugin)
php tools/i18n.php check                 # переводы полные и собраны
```

## Переводы

Строки интерфейса — на английском в функциях перевода (`memorial-plugin`, `memorial-theme`), русский перевод лежит в `languages/`. После изменения строк:

```
php tools/i18n.php pot     # обновить .pot (PHP, JS, заголовки, theme.json, паттерны)
# дополнить languages/*-ru_RU.po (Poedit или вручную)
php tools/i18n.php build   # собрать .mo и JSON для скриптов админки
```

## Удаление плагина

`uninstall.php` по умолчанию удаляет только временные данные, расписания и кэш Rewio — содержимое сайта и настройки остаются. Полное удаление — константа `MEMORIAL_PLUGIN_DELETE_DATA` в `wp-config.php` (после резервной копии).

Полный WPCS включается для кода по мере переписывания (список файлов — `memorial-plugin/phpcs.xml.dist`); старые модули до этого проверяются на синтаксис, префиксы и совместимость PHP.

## CI и выгрузка

- `.github/workflows/ci.yml` — все проверки выше на каждый push в `main`, `block-migration`, `stage-*` и на pull request.
- `.github/workflows/deploy.yml` — сборка и выгрузка на хостинг при push в `main` или вручную (можно выбрать ветку). Папки подменяются целиком: сначала выгрузка в `.memorial-*-new`, затем переименование.
