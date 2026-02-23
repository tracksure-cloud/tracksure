#!/usr/bin/env node

/**
 * Check version consistency across plugin files
 * 
 * Ensures version numbers match in:
 * - tracksure.php (plugin header)
 * - package.json
 * - readme.txt (stable tag)
 * - admin/package.json
 */

/* eslint-disable no-console, security/detect-non-literal-fs-filename */
const fs = require('fs');
const path = require('path');

console.log('🔍 Checking version consistency...\n');

const files = {
    plugin: path.join(__dirname, '..', 'tracksure.php'),
    packageJson: path.join(__dirname, '..', 'package.json'),
    readme: path.join(__dirname, '..', 'readme.txt'),
    adminPackage: path.join(__dirname, '..', 'admin', 'package.json'),
};

const versions = {};

// Extract version from tracksure.php
if (fs.existsSync(files.plugin)) {
    const content = fs.readFileSync(files.plugin, 'utf8');
    const match = content.match(/\* Version:\s+([0-9.]+)/);
    if (match) {
        versions.plugin = match[1];
    }
    
    // Also check TRACKSURE_VERSION constant
    const constMatch = content.match(/define\s*\(\s*'TRACKSURE_VERSION'\s*,\s*'([0-9.]+)'/);
    if (constMatch && constMatch[1] !== versions.plugin) {
        console.log(`⚠️  TRACKSURE_VERSION constant (${constMatch[1]}) differs from header (${versions.plugin})`);
    }
}

// Extract version from package.json
if (fs.existsSync(files.packageJson)) {
    const pkg = JSON.parse(fs.readFileSync(files.packageJson, 'utf8'));
    versions.packageJson = pkg.version;
}

// Extract stable tag from readme.txt
if (fs.existsSync(files.readme)) {
    const content = fs.readFileSync(files.readme, 'utf8');
    const match = content.match(/Stable tag:\s+([0-9.]+)/i);
    if (match) {
        versions.readme = match[1];
    }
}

// Extract version from admin/package.json
if (fs.existsSync(files.adminPackage)) {
    const pkg = JSON.parse(fs.readFileSync(files.adminPackage, 'utf8'));
    versions.adminPackage = pkg.version;
}

// Compare versions
console.log('📦 Version Numbers:\n');
console.log(`   tracksure.php:       ${versions.plugin || 'NOT FOUND'}`);
console.log(`   package.json:        ${versions.packageJson || 'NOT FOUND'}`);
console.log(`   readme.txt:          ${versions.readme || 'NOT FOUND'}`);
console.log(`   admin/package.json:  ${versions.adminPackage || 'NOT FOUND'}`);
console.log('');

const allVersions = Object.values(versions).filter(v => v);
const uniqueVersions = [...new Set(allVersions)];

if (uniqueVersions.length === 1) {
    console.log(`✅ All versions match: ${uniqueVersions[0]}\n`);
    process.exit(0);
} else {
    console.log('❌ Version mismatch detected!\n');
    console.log('   All files should have the same version number.');
    console.log('   Update all files to match before releasing.\n');
    process.exit(1);
}
