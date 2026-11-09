/**
 * Playwright для проверок на WordPress из wp-env (http://localhost:8888, admin / password).
 * Запуск: npm run env:start && npm run test:e2e
 * В CI проверки делятся на части: npm run test:e2e -- --shard=1/2 (и 2/2).
 *
 * Запись: в успешном прогоне — ничего. Скриншот — только упавшего теста; видео выключено
 * (режим retain-on-failure всё равно пишет его в каждом тесте); трассировка — только при повторе упавшего.
 */
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: '.',
	timeout: 60_000,
	retries: process.env.CI ? 1 : 0,
	fullyParallel: true,
	workers: process.env.CI ? 1 : undefined,
	reporter: process.env.CI
		? [
				[ 'list' ],
				[
					'html',
					{
						open: 'never',
						outputFolder: '../../artifacts/playwright-report',
					},
				],
			]
		: 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		screenshot: 'only-on-failure',
		video: 'off',
		trace: 'on-first-retry',
	},
	outputDir: '../../artifacts/test-results',
} );
