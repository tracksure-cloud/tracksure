/**
 * Deploy to WordPress.org SVN
 * 
 * Usage: node scripts/deploy-to-wordpress-org.js [version]
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const readline = require('readline');

const PLUGIN_SLUG = 'tracksure';
const SVN_URL = `https://plugins.svn.wordpress.org/${PLUGIN_SLUG}`;
const PLUGIN_DIR = path.resolve(__dirname, '..');
const SVN_DIR = path.join(PLUGIN_DIR, '.svn-temp');

// Colors
const colors = {
    reset: '\x1b[0m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m'
};

function log(message, color = 'reset') {
    console.log(`${colors[color]}${message}${colors.reset}`);
}

function exec(command, options = {}) {
    try {
        return execSync(command, { 
            cwd: options.cwd || PLUGIN_DIR,
            stdio: options.silent ? 'pipe' : 'inherit',
            encoding: 'utf8'
        });
    } catch (error) {
        log(`Error executing: ${command}`, 'red');
        throw error;
    }
}

async function prompt(question) {
    const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout
    });

    return new Promise(resolve => {
        rl.question(question, answer => {
            rl.close();
            resolve(answer);
        });
    });
}

async function deploy() {
    // Get version
    const version = process.argv[2] || require('../package.json').version;
    
    log('\nTrackSure WordPress.org Deployment', 'green');
    log('====================================\n', 'green');
    log(`Plugin: ${PLUGIN_SLUG}`);
    log(`Version: ${version}`);
    log(`SVN URL: ${SVN_URL}\n`);

    // Check SVN credentials
    const svnUsername = process.env.SVN_USERNAME || await prompt('WordPress.org username: ');
    const svnPassword = process.env.SVN_PASSWORD || await prompt('WordPress.org password: ');

    if (!svnUsername || !svnPassword) {
        log('SVN credentials required!', 'red');
        process.exit(1);
    }

    const svnAuth = `--username ${svnUsername} --password ${svnPassword} --non-interactive`;

    try {
        // Step 1: Build production
        log('\n[1/6] Building production version...', 'yellow');
        exec('npm run build:production');

        // Step 2: Checkout SVN
        log('\n[2/6] Checking out SVN repository...', 'yellow');
        if (fs.existsSync(SVN_DIR)) {
            log('SVN directory exists, updating...');
            exec(`svn update ${svnAuth}`, { cwd: SVN_DIR });
        } else {
            log('Checking out fresh copy...');
            exec(`svn co ${SVN_URL} .svn-temp ${svnAuth}`);
        }

        // Step 3: Update trunk
        log('\n[3/6] Updating trunk...', 'yellow');
        const trunkDir = path.join(SVN_DIR, 'trunk');
        
        // Clear trunk
        if (fs.existsSync(trunkDir)) {
            exec(`rm -rf trunk/*`, { cwd: SVN_DIR });
        }

        // Copy files (respecting .svnignore)
        const ignoreFile = path.join(PLUGIN_DIR, '.svnignore');
        const excludes = fs.existsSync(ignoreFile) 
            ? fs.readFileSync(ignoreFile, 'utf8')
                .split('\n')
                .filter(line => line.trim() && !line.startsWith('#'))
                .map(pattern => `--exclude=${pattern}`)
                .join(' ')
            : '';

        exec(`rsync -av ${excludes} --exclude=.svn-temp --exclude=.git ${PLUGIN_DIR}/ ${trunkDir}/`);

        // Step 4: Sync SVN changes
        log('\n[4/6] Syncing SVN changes...', 'yellow');
        
        // Add new files
        try {
            exec(`svn add --force * --auto-props --parents --depth infinity -q`, { 
                cwd: trunkDir,
                silent: true 
            });
        } catch (e) {
            // Ignore errors (files might already be added)
        }

        // Remove deleted files
        const svnStatus = exec('svn status', { cwd: trunkDir, silent: true });
        const deletedFiles = svnStatus
            .split('\n')
            .filter(line => line.startsWith('!'))
            .map(line => line.substring(1).trim());
        
        deletedFiles.forEach(file => {
            try {
                exec(`svn rm "${file}"`, { cwd: trunkDir, silent: true });
            } catch (e) {
                // Ignore
            }
        });

        // Step 5: Create tag
        log('\n[5/6] Creating version tag...', 'yellow');
        const tagDir = path.join(SVN_DIR, 'tags', version);
        
        if (!fs.existsSync(tagDir)) {
            exec(`svn copy trunk tags/${version}`, { cwd: SVN_DIR });
            log(`Tag ${version} created`);
        } else {
            log(`Tag ${version} already exists, skipping...`);
        }

        // Step 6: Commit
        log('\n[6/6] Committing to WordPress.org...', 'yellow');
        const status = exec('svn status', { cwd: SVN_DIR, silent: true });
        console.log(status);

        const confirm = await prompt('\nCommit these changes? (y/n): ');
        
        if (confirm.toLowerCase() === 'y') {
            exec(`svn ci -m "Release version ${version}" ${svnAuth}`, { cwd: SVN_DIR });
            
            log(`\n✓ Successfully deployed version ${version} to WordPress.org!`, 'green');
            log(`✓ Plugin will be available at: https://wordpress.org/plugins/${PLUGIN_SLUG}/`, 'green');
        } else {
            log('\nDeployment cancelled.', 'red');
            process.exit(1);
        }

        // Cleanup
        log('\nCleaning up temporary files...', 'yellow');
        // Uncomment to remove SVN directory
        // exec(`rm -rf .svn-temp`);

        log('\nDeployment complete!', 'green');
        log(`Check your plugin at: https://wordpress.org/plugins/${PLUGIN_SLUG}/\n`, 'green');

    } catch (error) {
        log('\nDeployment failed!', 'red');
        console.error(error);
        process.exit(1);
    }
}

// Run deployment
deploy();
