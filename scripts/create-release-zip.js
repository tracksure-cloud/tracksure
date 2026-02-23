#!/usr/bin/env node

/**
 * Create release ZIP file for WordPress.org
 * 
 * Excludes development files and creates a clean distribution package
 * Uses archiver for cross-platform compatibility
 */

/* eslint-disable no-console, no-useless-escape */
const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const PLUGIN_SLUG = 'tracksure';
const ROOT_DIR = path.join(__dirname, '..');
const BUILD_DIR = path.join(ROOT_DIR, 'build');
const ZIP_NAME = `${PLUGIN_SLUG}.zip`;
const ZIP_PATH = path.join(BUILD_DIR, ZIP_NAME);

console.log('📦 Creating WordPress.org release package...\n');

// Files/folders to exclude from release
const excludePatterns = [
    /node_modules/,
    /admin[\/\\]src/,
    /[\/\\]src$/,
    /[\/\\]src[\/\\]/,
    /admin[\/\\]node_modules/,
    /tests/,
    /scripts/,
    /^build$/,             // Exclude build directory (must match root-level 'build')
    /^vendor$/,            // Dev dependencies (PHPCS, WPCS) - NOT needed in production (root vendor folder only)
    /^vendor\//,           // Match root-level vendor/ directory
    /docs/,                // Documentation - not needed in production
    /\.git/,
    /\.github/,
    /\.vscode/,
    /\.idea/,
    /\.svn-temp/,
    /\.wordpress-org/,     // WP.org SVN assets - not needed in plugin ZIP
    /\.editorconfig$/,
    /\.eslintrc\.js$/,
    /\.stylelintrc\.json$/,
    /\.svnignore$/,
    /\.gitattributes$/,
    /phpcs\.xml$/,
    /\.phpcs\.xml\.dist$/,
    /phpunit\.xml/,
    /composer\.json$/,
    /composer\.lock$/,
    /package\.json$/,
    /package-lock\.json$/,
    /webpack\.config\.js$/,
    /tsconfig\.json$/,
    /\.DS_Store$/,
    /Thumbs\.db$/,
    /\.map$/,
    /\.log$/,
    /\.md$/,               // All markdown files (except readme.txt which is .txt)
    /\.bak$/,
    /\.tmp$/,
    /\.swp$/,
    /CODE_REVIEW_ISSUES\.md$/,
    /VERIFICATION_CHECKLIST\.md$/,
    /DEPLOYMENT_SUMMARY\.md$/,
    /GITHUB_SETUP\.md$/,
    /PRODUCTION_RELEASE\.md$/,
    /FINAL_RELEASE_SUMMARY\.md$/,
    /GETTING_STARTED\.md$/,
    /CONTRIBUTING\.md$/,
    /\.gitignore$/,
];

/**
 * Check if file/folder should be excluded
 */
function shouldExclude(relativePath) {
    const normalizedPath = relativePath.replace(/\\/g, '/');
    return excludePatterns.some(pattern => pattern.test(normalizedPath));
}

// Create build directory
if (!fs.existsSync(BUILD_DIR)) {
    fs.mkdirSync(BUILD_DIR, { recursive: true });
}

// Remove old ZIP if exists
if (fs.existsSync(ZIP_PATH)) {
    fs.unlinkSync(ZIP_PATH);
    console.log('🗑️  Removed old ZIP file\n');
}

// Create output stream
const output = fs.createWriteStream(ZIP_PATH);
const archive = archiver('zip', {
    zlib: { level: 9 } // Maximum compression
});

// Listen for all archive data to be written
output.on('close', function() {
    const stats = fs.statSync(ZIP_PATH);
    const sizeMB = (stats.size / (1024 * 1024)).toFixed(2);

    console.log('');
    console.log('✅ Release package created successfully!');
    console.log('');
    console.log(`   📁 Location: ${ZIP_PATH}`);
    console.log(`   📊 Size: ${sizeMB} MB`);
    console.log(`   📦 Files: ${archive.pointer()} bytes compressed`);
    console.log('');
    console.log('📋 Next steps:');
    console.log('   1. Test the ZIP in a fresh WordPress install');
    console.log('   2. Run: npm run validate');
    console.log('   3. Check readme.txt: npm run readme:validate');
    console.log('   4. Submit to WordPress.org plugin repository');
    console.log('');
});

// Handle warnings
archive.on('warning', function(err) {
    if (err.code === 'ENOENT') {
        console.warn('Warning:', err);
    } else {
        throw err;
    }
});

// Handle errors
archive.on('error', function(err) {
    throw err;
});

// Pipe archive data to the file
archive.pipe(output);

console.log('📂 Adding plugin files (excluding dev files)...\n');

// Add files with exclusions
const entries = fs.readdirSync(ROOT_DIR, { withFileTypes: true });
let fileCount = 0;

for (const entry of entries) {
    const relativePath = entry.name;
    
    // Skip excluded files/folders
    if (shouldExclude(relativePath)) {
        console.log(`   ⏭️  Skipping: ${relativePath}`);
        continue;
    }

    const fullPath = path.join(ROOT_DIR, entry.name);

    if (entry.isDirectory()) {
        console.log(`   ✅ Adding directory: ${relativePath}/`);
        archive.directory(fullPath, relativePath, (entryData) => {
            // Filter function for files within directories
            // entryData.name contains the relative path from the directory root
            let relPath = entryData.name.replace(/\\/g, '/');
            
            // Prepend directory name to get full relative path
            if (!relPath.startsWith(relativePath + '/')) {
                relPath = relativePath + '/' + relPath;
            }
            
            // Must return false to exclude, or entry object to include
            if (shouldExclude(relPath)) {
                return false;
            }
            
            return entryData;
        });
    } else {
        console.log(`   ✅ Adding file: ${relativePath}`);
        archive.file(fullPath, { name: relativePath });
    }
    
    fileCount++;
}

console.log(`\n📦 Creating ZIP archive (${fileCount} top-level entries)...\n`);

// Finalize the archive
archive.finalize();
