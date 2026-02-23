#!/usr/bin/env node

/**
 * Validate README.txt for WordPress.org compatibility
 * 
 * Checks:
 * - Required headers (Plugin Name, Contributors, Tags, etc.)
 * - Version consistency
 * - Tested up to version
 * - Changelog format
 * - License information
 */

/* eslint-disable no-console, prefer-const, security/detect-non-literal-regexp */
const fs = require('fs');
const path = require('path');

const README_PATH = path.join(__dirname, '..', 'readme.txt');
const PLUGIN_FILE = path.join(__dirname, '..', 'tracksure.php');

console.log('🔍 Validating README.txt for WordPress.org...\n');

let errors = [];
let warnings = [];

// Read files
if (!fs.existsSync(README_PATH)) {
    console.error('❌ readme.txt not found!');
    process.exit(1);
}

const readme = fs.readFileSync(README_PATH, 'utf8');
const pluginFile = fs.existsSync(PLUGIN_FILE) ? fs.readFileSync(PLUGIN_FILE, 'utf8') : '';

// Extract version from plugin file
const pluginVersionMatch = pluginFile.match(/\* Version:\s+([0-9.]+)/);
const pluginVersion = pluginVersionMatch ? pluginVersionMatch[1] : null;

// Check required headers
const requiredHeaders = [
    'Contributors',
    'Tags',
    'Requires at least',
    'Tested up to',
    'Stable tag',
    'License',
    'License URI',
];

requiredHeaders.forEach(header => {
    const regex = new RegExp(`^${header}:`, 'im');
    if (!regex.test(readme)) {
        errors.push(`Missing required header: ${header}`);
    }
});

// Check version consistency
const stableTagMatch = readme.match(/Stable tag:\s+([0-9.]+)/i);
const stableTag = stableTagMatch ? stableTagMatch[1] : null;

if (pluginVersion && stableTag && pluginVersion !== stableTag) {
    errors.push(`Version mismatch: plugin file (${pluginVersion}) vs readme (${stableTag})`);
}

// Check WordPress version
const testedUpToMatch = readme.match(/Tested up to:\s+([0-9.]+)/i);
const testedUpTo = testedUpToMatch ? testedUpToMatch[1] : null;

if (testedUpTo) {
    const version = parseFloat(testedUpTo);
    if (version < 6.0) {
        warnings.push(`Tested up to ${testedUpTo} is quite old. Consider testing with latest WP version.`);
    }
}

// Check for changelog
if (!readme.includes('== Changelog ==')) {
    errors.push('Missing changelog section');
}

// Check for description
if (!readme.includes('== Description ==')) {
    errors.push('Missing description section');
}

// Check tags count (max 12 for WP.org)
const tagsMatch = readme.match(/Tags:\s+(.+)/i);
if (tagsMatch) {
    const tags = tagsMatch[1].split(',').map(t => t.trim());
    if (tags.length > 12) {
        warnings.push(`Too many tags (${tags.length}). WordPress.org allows maximum 12 tags.`);
    }
}

// Check short description length (max 150 chars)
const shortDescMatch = readme.match(/^(.+?)\n\n/m);
if (shortDescMatch && shortDescMatch[1].length > 150) {
    warnings.push(`Short description too long (${shortDescMatch[1].length} chars). Keep under 150 characters.`);
}

// Report results
console.log('📋 Validation Results:\n');

if (errors.length === 0 && warnings.length === 0) {
    console.log('✅ README.txt is valid!\n');
    console.log(`   Plugin Version: ${pluginVersion || 'N/A'}`);
    console.log(`   Stable Tag: ${stableTag || 'N/A'}`);
    console.log(`   Tested up to: ${testedUpTo || 'N/A'}`);
    process.exit(0);
}

if (errors.length > 0) {
    console.log('❌ ERRORS:\n');
    errors.forEach(err => console.log(`   • ${err}`));
    console.log('');
}

if (warnings.length > 0) {
    console.log('⚠️  WARNINGS:\n');
    warnings.forEach(warn => console.log(`   • ${warn}`));
    console.log('');
}

if (errors.length > 0) {
    console.log('Fix errors before submitting to WordPress.org\n');
    process.exit(1);
} else {
    console.log('✅ No critical errors, but check warnings\n');
    process.exit(0);
}
