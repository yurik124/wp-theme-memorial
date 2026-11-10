/**
 * Скриншоты «до/после» (workflow «Скриншоты»): первый прогон с --update-snapshots снимает эталон
 * на ветке «до», второй сравнивает с ним ветку «после». Эталоны и отличия — вне папок с кодом.
 */
const { defineConfig } = require( '@playwright/test' );

const OUT = process.env.SHOTS_DIR || '../../shots';

module.exports = defineConfig( {
	testDir: '.',
	testMatch: 'visual.spec.js',
	timeout: 90_000,
	workers: 2,
	retries: 0,
	reporter: [ [ 'list' ], [ 'json', { outputFile: OUT + '/report.json' } ] ],
	snapshotPathTemplate: OUT + '/baseline/{projectName}/{arg}{ext}',
	outputDir: OUT + '/results',
	expect: {
		toHaveScreenshot: {
			maxDiffPixelRatio: 0.002,
			animations: 'disabled',
			caret: 'hide',
			scale: 'css',
		},
	},
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		screenshot: 'off',
		video: 'off',
		trace: 'off',
	},
	projects: [
		{ name: 'pc', use: { viewport: { width: 1280, height: 900 } } },
		{
			name: 'phone',
			use: {
				viewport: { width: 390, height: 844 },
				isMobile: true,
				hasTouch: true,
				deviceScaleFactor: 1,
			},
		},
	],
} );
