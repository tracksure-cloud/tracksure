# Deployment Guide

Complete guide for deploying TrackSure to GitHub and WordPress.org.

## Table of Contents

1. [GitHub Setup](#github-setup)
2. [WordPress.org SVN Setup](#wordpressorg-svn-setup)
3. [Automated Deployment](#automated-deployment)
4. [Manual Deployment](#manual-deployment)
5. [Version Management](#version-management)

---

## GitHub Setup

### 1. Initialize Git Repository

```bash
cd /path/to/tracksure
git init
git add .
git commit -m "Initial commit: TrackSure v1.0.0"
```

### 2. Create GitHub Repository

1. Go to [GitHub](https://github.com/new)
2. Create new repository: `tracksure`
3. **Do not** initialize with README (we already have one)

### 3. Push to GitHub

```bash
git remote add origin https://github.com/YOUR-USERNAME/tracksure.git
git branch -M main
git push -u origin main
```

### 4. Configure GitHub Secrets

For automated WordPress.org deployment, add these secrets:

1. Go to **Settings > Secrets and variables > Actions**
2. Add **New repository secret**:
   - `SVN_USERNAME`: Your WordPress.org username
   - `SVN_PASSWORD`: Your WordPress.org password

---

## WordPress.org SVN Setup

### 1. Get SVN Access

1. Submit your plugin to WordPress.org
2. Wait for approval (usually 3-14 days)
3. You'll receive SVN repository URL: `https://plugins.svn.wordpress.org/tracksure`

### 2. Checkout SVN Repository

```bash
svn co https://plugins.svn.wordpress.org/tracksure svn-tracksure
cd svn-tracksure
```

### 3. SVN Directory Structure

```
svn-tracksure/
├── trunk/          # Development version
├── tags/           # Released versions
│   ├── 1.0.0/
│   ├── 1.0.1/
│   └── 1.1.0/
└── assets/         # WordPress.org assets (screenshots, banners)
```

---

## Automated Deployment

### GitHub Actions Workflow

Our plugin includes automated deployment via GitHub Actions.

#### Workflow File

`.github/workflows/deploy-to-wordpress-org.yml`

#### How It Works

1. Push a new tag to GitHub
2. GitHub Actions automatically:
   - Builds production version
   - Deploys to WordPress.org SVN
   - Creates new version in SVN tags/

#### Deploy New Version

```bash
# Update version numbers in:
# - tracksure.php
# - readme.txt
# - package.json

# Commit changes
git add .
git commit -m "Release v1.0.1"
git push

# Create and push tag
git tag 1.0.1
git push origin 1.0.1
```

GitHub Actions will automatically deploy to WordPress.org!

---

## Manual Deployment

### Option 1: Using NPM Script

```bash
# Update version first
npm run version:bump -- 1.0.1

# Build and create ZIP
npm run build:production
npm run zip:plugin

# The ZIP file is in: tracksure-1.0.1.zip
```

### Option 2: Manual SVN Deployment

```bash
# 1. Build production version
npm run build:production

# 2. Checkout SVN
svn co https://plugins.svn.wordpress.org/tracksure svn-tracksure
cd svn-tracksure

# 3. Copy files to trunk
rsync -av --exclude-from='.svnignore' /path/to/plugin/ trunk/

# 4. Add new files
svn add trunk/* --force

# 5. Commit to trunk
svn ci -m "Release 1.0.1"

# 6. Create tag
svn cp trunk tags/1.0.1
svn ci -m "Tagging version 1.0.1"
```

### Option 3: Using Deployment Script

We provide an automated deployment script:

```bash
npm run deploy:wordpress -- 1.0.1
```

This script will:

- Build production version
- Update SVN trunk
- Create SVN tag
- Commit changes

---

## Version Management

### Updating Version Numbers

Version must be consistent across all files:

1. **tracksure.php** - Plugin header
2. **readme.txt** - Stable tag
3. **package.json** - Version
4. **admin/package.json** - Version

#### Automated Version Update

```bash
npm run version:bump -- 1.0.1
```

This updates all version numbers automatically!

#### Manual Version Update

Edit these files:

**tracksure.php:**

```php
* Version: 1.0.1
```

**readme.txt:**

```
Stable tag: 1.0.1
```

**package.json:**

```json
"version": "1.0.1"
```

**admin/package.json:**

```json
"version": "1.0.1"
```

#### Verify Versions

```bash
npm run version:check
```

---

## Release Checklist

### Before Release

- [ ] Update version numbers in all files
- [ ] Update changelog in readme.txt
- [ ] Run all validations: `npm run validate`
- [ ] Build production: `npm run build:production`
- [ ] Test on fresh WordPress install
- [ ] Check for PHP/JS errors
- [ ] Verify all features work
- [ ] Test with popular plugins (WooCommerce, etc.)

### GitHub Release

- [ ] Commit all changes
- [ ] Push to GitHub
- [ ] Create Git tag: `git tag 1.0.1`
- [ ] Push tag: `git push origin 1.0.1`
- [ ] Create GitHub Release with changelog
- [ ] Attach ZIP file to release

### WordPress.org Release

- [ ] Wait for GitHub Actions deployment
- [ ] OR manually deploy to SVN
- [ ] Verify on WordPress.org
- [ ] Test installation from WordPress.org
- [ ] Check plugin page displays correctly
- [ ] Update assets (screenshots, banners) if needed

---

## WordPress.org Assets

Upload these to `assets/` directory in SVN:

### Required Assets

```
assets/
├── icon-128x128.png    # Plugin icon (small)
├── icon-256x256.png    # Plugin icon (large)
├── banner-772x250.png  # Banner (small screens)
├── banner-1544x500.png # Banner (large screens)
└── screenshot-*.png    # Screenshots
```

### Asset Guidelines

- **Icon:** 256x256px PNG, square
- **Banner:** 1544x500px PNG, 3:1 ratio
- **Screenshots:** 1200px wide PNG

### Upload Assets

```bash
cd svn-tracksure
# Copy assets to assets/ folder
svn add assets/* --force
svn ci -m "Add plugin assets"
```

---

## Rollback Version

If you need to rollback:

### GitHub

```bash
git revert HEAD
git push
```

### WordPress.org

```bash
# Update readme.txt stable tag to previous version
# Commit to SVN
svn ci -m "Rollback to 1.0.0"
```

WordPress.org will serve the version specified in `Stable tag`.

---

## Continuous Integration

Our CI workflow (`.github/workflows/ci.yml`) runs on every push:

### Checks Performed

- ✅ ESLint validation
- ✅ TypeScript type checking
- ✅ PHPCS WordPress standards
- ✅ Production build test
- ✅ Version consistency check
- ✅ readme.txt validation

### View CI Results

Go to **Actions** tab in GitHub repository.

---

## Troubleshooting

### SVN: "File already exists"

```bash
svn delete trunk/path/to/file
svn ci -m "Remove file"
# Then try again
```

### GitHub Actions Failed

1. Check **Actions** tab for error logs
2. Verify GitHub Secrets are set correctly
3. Check build logs for errors

### WordPress.org Not Updating

1. Check `Stable tag` in readme.txt
2. Verify tag exists in SVN `tags/` directory
3. Wait 5-15 minutes for WordPress.org to update
4. Clear WordPress.org cache

---

## Support

- **GitHub Issues:** [Report bugs](https://github.com/YOUR-USERNAME/tracksure/issues)
- **WordPress.org:** [Support forum](https://wordpress.org/support/plugin/tracksure/)
- **Documentation:** [Full docs](https://tracksure.cloud/docs/)

---

## Quick Commands

```bash
# Development
npm install
npm run build:production
npm run validate

# Version management
npm run version:bump -- 1.0.1
npm run version:check

# Create release
npm run zip:plugin

# GitHub
git tag 1.0.1
git push origin 1.0.1

# WordPress.org (automated)
# Just push tag to GitHub!

# WordPress.org (manual)
npm run deploy:wordpress -- 1.0.1
```

---

**Last Updated:** January 16, 2026  
**Plugin Version:** 1.0.0  
**Status:** Ready for deployment
