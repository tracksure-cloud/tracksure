/**
 * Bump version across all plugin files
 * 
 * Usage: npm run version:bump -- 1.0.1
 */

const fs = require('fs');
const path = require('path');

const newVersion = process.argv[2];

if (!newVersion) {
    console.error('Error: Version number required');
    console.log('Usage: npm run version:bump -- 1.0.1');
    process.exit(1);
}

// Validate version format
if (!/^\d+\.\d+\.\d+$/.test(newVersion)) {
    console.error(`Error: Invalid version format "${newVersion}"`);
    console.log('Expected format: X.Y.Z (e.g., 1.0.1)');
    process.exit(1);
}

console.log(`\nBumping version to ${newVersion}...\n`);

const files = [
    {
        path: 'tracksure.php',
        replacements: [
            {
                regex: /(\* Version:\s+)[\d.]+/,
                replacement: `$1${newVersion}`
            },
            {
                regex: /(define\(\s*'TRACKSURE_VERSION',\s*')[\d.]+('\s*\);)/,
                replacement: `$1${newVersion}$2`
            }
        ]
    },
    {
        path: 'readme.txt',
        replacements: [
            {
                regex: /(Stable tag:\s+)[\d.]+/,
                replacement: `$1${newVersion}`
            }
        ]
    },
    {
        path: 'package.json',
        replacements: [
            {
                regex: /("version":\s*")[\d.]+(")/,
                replacement: `$1${newVersion}$2`
            }
        ]
    },
    {
        path: 'admin/package.json',
        replacements: [
            {
                regex: /("version":\s*")[\d.]+(")/,
                replacement: `$1${newVersion}$2`
            }
        ]
    }
];

let updated = 0;
let errors = 0;

files.forEach(file => {
    const filePath = path.resolve(__dirname, '..', file.path);
    
    try {
        if (!fs.existsSync(filePath)) {
            console.log(`⚠️  Skipping ${file.path} (not found)`);
            return;
        }

        let content = fs.readFileSync(filePath, 'utf8');
        let fileUpdated = false;

        file.replacements.forEach(({ regex, replacement }) => {
            if (regex.test(content)) {
                content = content.replace(regex, replacement);
                fileUpdated = true;
            }
        });

        if (fileUpdated) {
            fs.writeFileSync(filePath, content, 'utf8');
            console.log(`✓ Updated ${file.path}`);
            updated++;
        } else {
            console.log(`⚠️  No changes in ${file.path}`);
        }
    } catch (error) {
        console.error(`✗ Error updating ${file.path}:`, error.message);
        errors++;
    }
});

console.log(`\n${updated} file(s) updated to version ${newVersion}`);

if (errors > 0) {
    console.error(`${errors} error(s) occurred`);
    process.exit(1);
}

console.log('\nNext steps:');
console.log('1. Review changes: git diff');
console.log('2. Test the plugin');
console.log('3. Commit: git add . && git commit -m "Bump version to ' + newVersion + '"');
console.log('4. Tag: git tag ' + newVersion);
console.log('5. Push: git push && git push --tags\n');
