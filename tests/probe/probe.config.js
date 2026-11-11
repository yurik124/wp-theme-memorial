/**
 * Диагностика на копии сайта (workflow «Проверка на копии сайта»): снимки сценариев, а не сравнение.
 */
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: '.',
	testMatch: 'probe.spec.js',
	timeout: 120_000,
	workers: 1,
	retries: 0,
	reporter: [ [ 'list' ] ],
	outputDir: ( process.env.PROBE_DIR || '../../probe' ) + '/results',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		screenshot: 'off',
		video: 'off',
		trace: 'off',
	},
} );
