# ✅ Build & Deploy - Verification Checklist

## Current Status: READY FOR GITHUB ✅

---

## 📦 What's Been Configured

### ✅ Build System

- [x] Root `package.json` with build scripts
- [x] Admin `package.json` with webpack build
- [x] Production build command: `npm run build:production`
- [x] CI build command: `npm run build:ci`
- [x] ZIP creation: `npm run zip:plugin`
- [x] Full release: `npm run prepare:release`

### ✅ GitHub Actions

- [x] CI workflow (`.github/workflows/ci.yml`)
  - Runs on push to `main`/`develop`
  - Validates PHP, JS, CSS
  - Builds production assets
  - Creates build artifact
- [x] Deploy workflow (`.github/workflows/deploy-to-wporg.yml`)
  - Runs on Git tags (`v*.*.*`)
  - Builds production ZIP
  - Deploys to WordPress.org
  - Creates GitHub Release

### ✅ Documentation

- [x] `GITHUB_SETUP.md` - Complete setup guide
- [x] `DEPLOYMENT_SUMMARY.md` - Quick overview
- [x] `BUILD_DEPLOY_GUIDE.md` - Detailed build instructions
- [x] `QUICK_REFERENCE.md` - Command reference
- [x] `.wordpress-org/README.md` - Assets guide

### ✅ File Structure

- [x] `.github/workflows/` created
- [x] `.wordpress-org/` created for plugin assets
- [x] `.gitignore` properly configured
- [x] Build scripts in `scripts/` directory

---

## 🚀 Next Steps (What YOU Need to Do)

### STEP 1: Initialize Git Repository ⏳

```powershell
# Navigate to plugin directory
cd "d:\1LocalDev\Local Sites\New\app\public\wp-content\plugins\tracksure"

# Initialize Git
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial commit: TrackSure v1.0.0"
```

**Status**: ⏳ **DO THIS FIRST**

---

### STEP 2: Create GitHub Repository ⏳

**Option A: Using GitHub CLI (Easier)**

```powershell
# Install GitHub CLI from: https://cli.github.com/
# Then run:
gh auth login
gh repo create tracksure --public --source=. --remote=origin --push
```

**Option B: Manual**

1. Go to https://github.com/new
2. Repository name: `tracksure`
3. Choose **Public** (required for WordPress.org)
4. **Do NOT** initialize with README
5. Click "Create repository"
6. Run these commands:

```powershell
git remote add origin https://github.com/YOUR-USERNAME/tracksure.git
git branch -M main
git push -u origin main
```

**Status**: ⏳ **DO THIS SECOND**

---

### STEP 3: Test Build Locally ⏳

```powershell
# Install dependencies (if not already done)
npm install
cd admin
npm install
cd ..

# Build production version
npm run build:production

# Should see:
# ✅ Admin build completed
# ✅ Files created in admin/dist/

# Create ZIP
npm run zip:plugin

# Should see:
# ✅ Created build/tracksure.zip
```

**Expected output location**: `build/tracksure.zip`

**Test the ZIP**:

1. Extract `build/tracksure.zip` to a test directory
2. Check it contains:
   - ✅ `tracksure.php`
   - ✅ `admin/dist/` (built files)
   - ✅ `includes/` (PHP classes)
   - ❌ No `node_modules/`
   - ❌ No `admin/src/` (source files)

**Status**: ⏳ **DO THIS THIRD**

---

### STEP 4: Submit to WordPress.org ⏳

**Before auto-deployment works, you need WordPress.org approval:**

1. **Create WordPress.org Account**

   - https://wordpress.org/support/register.php

2. **Submit Plugin**

   - Go to: https://wordpress.org/plugins/developers/add/
   - Upload: `build/tracksure.zip`
   - Fill out form (description, tags, etc.)
   - Submit for review

3. **Wait for Approval** (typically 1-2 weeks)

   - Check email for approval notification
   - You'll receive SVN repository URL

4. **Save Credentials**
   - WordPress.org username
   - WordPress.org password
   - You'll need these for GitHub secrets

**Status**: ⏳ **REQUIRED FOR AUTO-DEPLOY**

---

### STEP 5: Configure GitHub Secrets ⏳

**After WordPress.org approval, add secrets to GitHub:**

1. Go to: `https://github.com/YOUR-USERNAME/tracksure/settings/secrets/actions`
2. Click **"New repository secret"**

**Add Secret #1**:

- Name: `SVN_USERNAME`
- Value: Your WordPress.org username
- Click "Add secret"

**Add Secret #2**:

- Name: `SVN_PASSWORD`
- Value: Your WordPress.org password
- Click "Add secret"

⚠️ **Without these secrets, auto-deployment won't work!**

**Status**: ⏳ **REQUIRED FOR AUTO-DEPLOY**

---

### STEP 6: Create Your First Release ⏳

**Only do this AFTER Steps 1-5 are complete!**

```powershell
# 1. Update version in 3 files:
#    - tracksure.php (line 12): Version: 1.0.1
#    - package.json (line 3): "version": "1.0.1"
#    - readme.txt (line 6): Stable tag: 1.0.1

# 2. Update changelog in readme.txt

# 3. Build & validate
npm run prepare:release

# 4. Commit changes
git add .
git commit -m "Release v1.0.1"

# 5. Create tag (this triggers auto-deployment!)
git tag v1.0.1

# 6. Push to GitHub
git push origin main --tags
```

**What happens automatically**:

1. GitHub Actions detects tag
2. Builds production assets
3. Deploys to WordPress.org SVN
4. Creates GitHub Release with ZIP

**Monitor progress**: `https://github.com/YOUR-USERNAME/tracksure/actions`

**Status**: ⏳ **DO THIS LAST**

---

## 🎯 Optional: Add Plugin Assets

**Make your plugin listing look professional:**

1. **Create Assets** (Photoshop, Figma, Canva, etc.):

   - `icon-256x256.png` - Plugin icon (square)
   - `banner-1544x500.png` - Header banner (Retina)
   - `banner-772x250.png` - Header banner (standard)
   - `screenshot-1.png` - Dashboard screenshot
   - `screenshot-2.png` - Settings page screenshot

2. **Add to Repository**:

   ```powershell
   # Place assets in .wordpress-org/ directory
   copy your-icon.png .wordpress-org/icon-256x256.png
   copy your-banner.png .wordpress-org/banner-1544x500.png

   # Commit and push
   git add .wordpress-org/
   git commit -m "Add plugin assets"
   git push
   ```

3. **Deploy Assets**:
   - Assets will be uploaded to WordPress.org on next tag push
   - Or upload manually to SVN `assets/` directory

---

## 🔍 Verification Commands

### Check Git Status

```powershell
cd "d:\1LocalDev\Local Sites\New\app\public\wp-content\plugins\tracksure"
git status
```

### Check Remote Repository

```powershell
git remote -v
# Should show:
# origin  https://github.com/YOUR-USERNAME/tracksure.git
```

### Test Build

```powershell
npm run build:production
# Should complete without errors
```

### Test ZIP Creation

```powershell
npm run zip:plugin
# Should create build/tracksure.zip
```

### Validate Code

```powershell
npm run validate
# Should pass or show only non-critical warnings
```

---

## 📊 Current Files Status

### ✅ Created/Modified Files

- `.github/workflows/ci.yml` - CI/CD pipeline
- `.github/workflows/deploy-to-wporg.yml` - Auto-deployment
- `.wordpress-org/README.md` - Assets guide
- `GITHUB_SETUP.md` - Complete setup guide
- `DEPLOYMENT_SUMMARY.md` - Quick overview
- `package.json` - Updated with CI build scripts

### ✅ Existing Files (Already Good)

- `.gitignore` - Properly configured
- `BUILD_DEPLOY_GUIDE.md` - Build instructions
- `QUICK_REFERENCE.md` - Command reference
- `scripts/create-release-zip.js` - ZIP builder
- `scripts/validate-readme.js` - README validator
- `scripts/check-versions.js` - Version checker

---

## ⚠️ Important Notes

### DO NOT Push These Files

Already excluded by `.gitignore`:

- `node_modules/` - Too large, install locally
- `admin/dist/` - Build output, generated by CI
- `build/` - ZIP files, generated by CI
- `*.log` - Debug files

### DO Push These Files

Required for GitHub Actions:

- `.github/workflows/*.yml` - Workflow definitions
- `package.json` - Build scripts
- `admin/src/` - React source code
- `admin/package.json` - Admin dependencies
- All PHP files in `includes/`

### Version Consistency

Always update version in ALL 3 files:

1. `tracksure.php` (Plugin header)
2. `package.json` (Root)
3. `readme.txt` (Stable tag)

Use `npm run version:check` to verify!

---

## 🚨 Troubleshooting

### "GitHub Actions failed" - What to do?

1. **Check the error**:

   - Go to GitHub repo → Actions tab
   - Click failed workflow
   - Read error message

2. **Common fixes**:
   - ❌ Build fails → Run `npm run build:production` locally first
   - ❌ SVN error → Check GitHub secrets (SVN_USERNAME, SVN_PASSWORD)
   - ❌ Permission denied → Verify WordPress.org SVN access

### "Build fails locally" - What to do?

```powershell
# Nuclear option: delete everything and reinstall
rm -r -force node_modules
rm -r -force admin/node_modules
rm -r -force admin/dist
npm install
cd admin
npm install
npm run build
cd ..
```

### "Can't push to GitHub" - What to do?

```powershell
# Check remote
git remote -v

# If no remote, add it:
git remote add origin https://github.com/YOUR-USERNAME/tracksure.git

# If wrong remote, update:
git remote set-url origin https://github.com/YOUR-USERNAME/tracksure.git
```

---

## ✅ Completion Checklist

### Initial Setup (One Time)

- [ ] Git initialized (`git init`)
- [ ] GitHub repository created
- [ ] Code pushed to GitHub
- [ ] Build tested locally (`npm run build:production`)
- [ ] ZIP tested locally (`npm run zip:plugin`)

### WordPress.org Setup (One Time)

- [ ] WordPress.org account created
- [ ] Plugin submitted to WordPress.org
- [ ] Plugin approved (received SVN access)
- [ ] GitHub secrets added (SVN_USERNAME, SVN_PASSWORD)

### Before Each Release

- [ ] Version updated in 3 files
- [ ] Changelog updated
- [ ] Code validated (`npm run validate`)
- [ ] Build tested (`npm run prepare:release`)
- [ ] Changes committed
- [ ] Tag created and pushed
- [ ] GitHub Actions completed successfully
- [ ] WordPress.org updated (check in 30-60 min)

---

## 🎉 You're Ready!

**Everything is configured and ready to go!**

**What to do next**:

1. ✅ Follow Step 1-3 to push code to GitHub
2. ✅ Follow Step 4-5 to enable auto-deployment
3. ✅ Follow Step 6 for your first release

**Questions?** Read:

- [GITHUB_SETUP.md](GITHUB_SETUP.md) - Detailed instructions
- [DEPLOYMENT_SUMMARY.md](DEPLOYMENT_SUMMARY.md) - Quick overview

---

**Last Updated**: January 16, 2026  
**Configuration Status**: ✅ COMPLETE
