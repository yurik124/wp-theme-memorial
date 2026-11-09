# Сайт «Мемориал»: тема и плагин

Монорепозиторий: блоковая тема и плагин WordPress (переход на блоковую архитектуру WordPress 7.1 — по этапам, см. MIGRATION.md).

| Папка | Что это |
|---|---|
| `memorial-theme/` | Тема Memorial Modern (text domain `memorial-theme`, префикс `memorial_theme_`) |
| `memorial-plugin/` | Memorial Plugin — функционал сайта (text domain `memorial-plugin`, префикс `memorial_plugin_`) |
| `memorial-plugin/src/` | Исходники JS (собираются в `memorial-plugin/build/`, в Git не хранится) |
| `tools/` | Проверки без WordPress: префиксы (`check-prefixes.php`), JSON-схемы (`validate-json.mjs`) |
| `tests/smoke/` | Дымовые тесты PHP без WordPress |
| `tests/e2e/` | Проверки в браузере на WordPress 7.1.4 (wp-env + Playwright) |
| `memorial-tests/` | Автотесты сайта на Python (только локально, в Git не попадают) |

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
```

Полный WPCS включается для кода по мере переписывания (список файлов — `memorial-plugin/phpcs.xml.dist`); старые модули до этого проверяются на синтаксис, префиксы и совместимость PHP.

## CI и выгрузка

- `.github/workflows/ci.yml` — все проверки выше на каждый push в `main`, `block-migration`, `stage-*` и на pull request.
- `.github/workflows/deploy.yml` — сборка и выгрузка на хостинг при push в `main` или вручную (можно выбрать ветку). Папки подменяются целиком: сначала выгрузка в `.memorial-*-new`, затем переименование.
