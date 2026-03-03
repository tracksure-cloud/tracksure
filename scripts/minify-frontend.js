#!/usr/bin/env node

/**
 * Minify frontend tracking scripts.
 *
 * Produces .min.js versions of all browser-facing JS files.
 * WordPress enqueue picks the .min.js when SCRIPT_DEBUG is false (default
 * on production sites).
 *
 * Usage:  node scripts/minify-frontend.js
 * Or:     npm run minify
 *
 * @since 1.0.1
 */

/* eslint-disable no-console, security/detect-non-literal-fs-filename */
const { minify } = require('terser');
const fs = require('fs');
const path = require('path');

// Files to minify (relative to plugin root).
const FILES = [
	'assets/js/ts-web.js',
	'assets/js/ts-minicart.js',
	'assets/js/ts-currency.js',
	'assets/js/consent-listeners.js',
	'admin/tracking-goals.js',
	'admin/tracksure-goal-constants.js',
];

const ROOT = path.resolve(__dirname, '..');

const TERSER_OPTIONS = {
	compress: {
		drop_console: true, // Remove console.log/warn in production
		passes: 2,
	},
	mangle: true,
	output: {
		comments: /^!|@license|@preserve/i, // Keep license comments only
	},
};

async function run() {
	console.log('🔧 Minifying frontend scripts...\n');

	let totalSaved = 0;

	for (const file of FILES) {
		const src = path.join(ROOT, file);

		if (!fs.existsSync(src)) {
			console.log(`   ⚠  ${file} — not found, skipping`);
			continue;
		}

		const code = fs.readFileSync(src, 'utf8');
		const originalSize = Buffer.byteLength(code, 'utf8');

		try {
			const result = await minify(code, TERSER_OPTIONS);

			if (!result.code) {
				console.log(`   ⚠  ${file} — minification returned empty, skipping`);
				continue;
			}

			const minSize = Buffer.byteLength(result.code, 'utf8');
			const saved = originalSize - minSize;
			totalSaved += saved;

			// Write .min.js next to original.
			const outFile = file.replace(/\.js$/, '.min.js');
			const outPath = path.join(ROOT, outFile);
			fs.writeFileSync(outPath, result.code, 'utf8');

			const pctSaved = ((saved / originalSize) * 100).toFixed(1);
			console.log(
				`   ✅ ${file}  ${(originalSize / 1024).toFixed(1)} KB → ${(minSize / 1024).toFixed(1)} KB  (−${pctSaved}%)`
			);
		} catch (err) {
			console.error(`   ❌ ${file} — ${err.message}`);
		}
	}

	console.log(`\n📦 Total saved: ${(totalSaved / 1024).toFixed(1)} KB\n`);
}

run().catch((err) => {
	console.error('Fatal:', err);
	process.exit(1);
});
