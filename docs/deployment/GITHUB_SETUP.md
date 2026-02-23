# GitHub Setup & Deployment Guide

## 🚀 Quick Start - First Time Setup

### 1. Initialize Git Repository

```powershell
# Navigate to plugin directory
cd "d:\1LocalDev\Local Sites\New\app\public\wp-content\plugins\tracksure"

# Initialize Git (if not already done)
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial commit: TrackSure v1.0.0"
```

### 2. Create GitHub Repository

**Option A: Using GitHub CLI (Recommended)**

```powershell
# Install GitHub CLI: https://cli.github.com/
# Login to GitHub
gh auth login

# Create repository and push
gh repo create tracksure --public --source=. --remote=origin --push

# Or for private repository:
gh repo create tracksure --private --source=. --remote=origin --push
```

**Option B: Manual Setup**

1. Go to https://github.com/new
2. Create repository named `tracksure`
3. Choose public or private
4. **Do NOT** initialize with README (you already have files)
5. Copy the repository URL

Then run:

```powershell
git remote add origin https://github.com/YOUR-USERNAME/tracksure.git
git branch -M main
git push -u origin main
```

---

## 🔑 GitHub Secrets Setup (Required for WordPress.org Deployment)

After creating your GitHub repository, you need to add secrets for automatic WordPress.org deployment.

### 1. Get WordPress.org SVN Credentials

First, you need a WordPress.org account and plugin approval:

1. Create account at https://wordpress.org/support/register.php
2. Submit plugin at https://wordpress.org/plugins/developers/add/
3. Wait for approval (1-2 weeks)
4. You'll receive SVN repository access

### 2. Add Secrets to GitHub

1. Go to your GitHub repository
2. Click **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**

Add these two secrets:

**Secret 1: SVN_USERNAME**

- Name: `SVN_USERNAME`
- Value: Your WordPress.org username

**Secret 2: SVN_PASSWORD**

- Name: `SVN_PASSWORD`
- Value: Your WordPress.org password

⚠️ **Security Note**: Never commit these credentials to your repository!

---

## 📦 Release Workflow

### Creating a New Release

#### Step 1: Update Version Numbers

Update version in these 3 files:

1. **tracksure.php** (Plugin header)

   ```php
   * Version: 1.0.1
   ```

2. **package.json** (Root)

   ```json
   "version": "1.0.1"
   ```

3. **readme.txt** (WordPress.org)
   ```
   Stable tag: 1.0.1
   ```

#### Step 2: Update Changelog

Edit **readme.txt**:

```
== Changelog ==

= 1.0.1 - 2026-01-16 =
* Added: New feature X
* Fixed: Bug in Y component
* Improved: Performance optimization for Z
```

#### Step 3: Build & Test Locally

```powershell
# Full build with validation
npm run prepare:release

# This will:
# ✅ Run PHPCS validation
# ✅ Run ESLint validation
# ✅ Run Stylelint validation
# ✅ Build React production app
# ✅ Create ZIP file in build/

# Test the ZIP file manually before releasing
```

#### Step 4: Commit & Tag Release

```powershell
# Commit version changes
git add .
git commit -m "Release v1.0.1"

# Create Git tag (triggers GitHub Action)
git tag v1.0.1

# Push to GitHub (including tag)
git push origin main --tags
```

#### Step 5: Automated Deployment

When you push a tag (e.g., `v1.0.1`), GitHub Actions will automatically:

1. ✅ Build production assets
2. ✅ Create plugin ZIP
3. ✅ Deploy to WordPress.org SVN
4. ✅ Create GitHub Release with ZIP file

Monitor progress at: `https://github.com/YOUR-USERNAME/tracksure/actions`

---

## 🔄 Daily Development Workflow

### Making Changes

```powershell
# 1. Create feature branch (optional)
git checkout -b feature/my-new-feature

# 2. Make your changes...

# 3. Test locally
cd admin
npm run dev  # Watch mode for React development

# 4. Validate code
npm run validate:fix

# 5. Commit changes
git add .
git commit -m "Add: Description of changes"

# 6. Push to GitHub
git push origin main
```

### Code Quality Checks (CI)

Every push to `main` or `develop` branches triggers automatic CI checks:

- ✅ PHPCS validation
- ✅ ESLint validation
- ✅ Stylelint validation
- ✅ Build production assets
- ✅ Version consistency check
- ✅ README.txt validation

View results at: `https://github.com/YOUR-USERNAME/tracksure/actions`

---

## 📂 GitHub Actions Workflows

### 1. CI Workflow (`.github/workflows/ci.yml`)

**Triggers**: Push to `main` or `develop`, Pull Requests

**What it does**:

- Runs code quality checks (PHPCS, ESLint, Stylelint)
- Builds production assets
- Validates version numbers
- Creates build artifact

### 2. Deploy Workflow (`.github/workflows/deploy-to-wporg.yml`)

**Triggers**: Push tag matching `v*.*.*` (e.g., `v1.0.1`)

**What it does**:

- Builds production ZIP
- Deploys to WordPress.org SVN
- Creates GitHub Release with ZIP file

---

## 🛠️ Troubleshooting

### GitHub Actions Failing?

**Check the logs**:

1. Go to your repository on GitHub
2. Click **Actions** tab
3. Click on the failed workflow
4. Expand failed steps to see error details

**Common issues**:

**1. SVN Credentials Invalid**

- Check that `SVN_USERNAME` and `SVN_PASSWORD` secrets are correct
- Verify you have SVN access to WordPress.org plugin

**2. Build Fails**

- Run `npm run build:production` locally first
- Fix any errors before pushing tag

**3. Version Mismatch**

- Ensure version is consistent in:
  - tracksure.php
  - package.json
  - readme.txt

### Build Locally Fails?

```powershell
# Clear caches and reinstall
rm -r -force node_modules
rm -r -force admin/node_modules
npm install
cd admin
npm install
cd ..

# Try build again
npm run build:production
```

### Git Push Rejected (Large Files)?

If you accidentally committed `node_modules/`:

```powershell
# Remove from Git history
git rm -r --cached node_modules
git rm -r --cached admin/node_modules
git commit -m "Remove node_modules"
git push
```

---

## 📊 WordPress.org SVN Structure

After deployment, your WordPress.org SVN will look like:

```
https://plugins.svn.wordpress.org/tracksure/
├── trunk/               ← Development version
│   ├── admin/
│   ├── assets/
│   ├── includes/
│   ├── readme.txt
│   └── tracksure.php
├── tags/                ← Released versions
│   ├── 1.0.0/
│   ├── 1.0.1/
│   └── 1.0.2/
└── assets/              ← Plugin listing assets
    ├── banner-772x250.png
    ├── banner-1544x500.png
    ├── icon-128x128.png
    ├── icon-256x256.png
    └── screenshot-1.png
```

---

## 🎯 Release Checklist

Before creating a new release:

- [ ] Update version in tracksure.php
- [ ] Update version in package.json
- [ ] Update version in readme.txt
- [ ] Update changelog in readme.txt
- [ ] Test plugin locally
- [ ] Run `npm run validate`
- [ ] Run `npm run prepare:release`
- [ ] Test build/tracksure.zip manually
- [ ] Commit changes
- [ ] Create and push Git tag
- [ ] Monitor GitHub Actions
- [ ] Verify WordPress.org update (may take 30-60 min)
- [ ] Test update on live WordPress site

---

## 📞 Additional Resources

- **GitHub Actions Docs**: https://docs.github.com/en/actions
- **WordPress.org Plugin Handbook**: https://developer.wordpress.org/plugins/
- **WordPress.org SVN Guide**: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- **10up Deploy Action**: https://github.com/10up/action-wordpress-plugin-deploy

---

**Last Updated**: January 16, 2026
