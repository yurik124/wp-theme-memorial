/**
 * Playwright для проверок на WordPress из wp-env (http://localhost:8888, admin / password).
 * Запуск: npm run env:start && npm run test:e2e
 */
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: '.',
	timeout: 60_000,
	retries: process.env.CI ? 1 : 0,
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
		trace: 'retain-on-failure',
	},
	outputDir: '../../artifacts/test-results',
} );
