#!/bin/bash

# TrackSure WordPress.org Deployment Script
# Automatically deploys plugin to WordPress.org SVN repository

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_SLUG="tracksure"
SVN_URL="https://plugins.svn.wordpress.org/$PLUGIN_SLUG"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SVN_DIR="$PLUGIN_DIR/.svn-temp"

# Get version from argument or package.json
VERSION=$1
if [ -z "$VERSION" ]; then
    VERSION=$(node -p "require('./package.json').version")
fi

echo -e "${GREEN}TrackSure WordPress.org Deployment${NC}"
echo -e "${GREEN}====================================${NC}\n"
echo "Plugin: $PLUGIN_SLUG"
echo "Version: $VERSION"
echo "SVN URL: $SVN_URL"
echo ""

# Check if SVN credentials are set
if [ -z "$SVN_USERNAME" ] || [ -z "$SVN_PASSWORD" ]; then
    echo -e "${YELLOW}SVN credentials not found in environment.${NC}"
    read -p "Enter WordPress.org username: " SVN_USERNAME
    read -sp "Enter WordPress.org password: " SVN_PASSWORD
    echo ""
fi

# Step 1: Build production version
echo -e "\n${YELLOW}[1/6] Building production version...${NC}"
npm run build:production

# Step 2: Checkout SVN repository
echo -e "\n${YELLOW}[2/6] Checking out SVN repository...${NC}"
if [ -d "$SVN_DIR" ]; then
    echo "SVN directory exists, updating..."
    cd "$SVN_DIR"
    svn update --username "$SVN_USERNAME" --password "$SVN_PASSWORD" --non-interactive
else
    echo "Checking out fresh copy..."
    svn co "$SVN_URL" "$SVN_DIR" --username "$SVN_USERNAME" --password "$SVN_PASSWORD" --non-interactive
    cd "$SVN_DIR"
fi

# Step 3: Clear trunk and copy new files
echo -e "\n${YELLOW}[3/6] Updating trunk...${NC}"
rm -rf trunk/*
rsync -av --exclude-from="$PLUGIN_DIR/.svnignore" "$PLUGIN_DIR/" trunk/ \
    --exclude='.svn-temp' \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='admin/node_modules'

# Step 4: Add/remove files in SVN
echo -e "\n${YELLOW}[4/6] Syncing SVN changes...${NC}"
cd trunk

# Add new files
svn add --force * --auto-props --parents --depth infinity -q 2>/dev/null || true

# Remove deleted files
svn status | grep '^\!' | sed 's/! *//' | xargs -I% svn rm % 2>/dev/null || true

cd ..

# Step 5: Create tag
echo -e "\n${YELLOW}[5/6] Creating version tag...${NC}"
if [ ! -d "tags/$VERSION" ]; then
    svn copy trunk "tags/$VERSION"
    echo "Tag $VERSION created"
else
    echo "Tag $VERSION already exists, skipping..."
fi

# Step 6: Commit changes
echo -e "\n${YELLOW}[6/6] Committing to WordPress.org...${NC}"
svn status
echo ""
read -p "Commit these changes? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    svn ci -m "Release version $VERSION" --username "$SVN_USERNAME" --password "$SVN_PASSWORD" --non-interactive
    echo -e "\n${GREEN}✓ Successfully deployed version $VERSION to WordPress.org!${NC}"
    echo -e "${GREEN}✓ Plugin will be available at: https://wordpress.org/plugins/$PLUGIN_SLUG/${NC}"
else
    echo -e "\n${RED}Deployment cancelled.${NC}"
    exit 1
fi

# Cleanup
echo -e "\n${YELLOW}Cleaning up temporary files...${NC}"
cd "$PLUGIN_DIR"
# Uncomment to remove SVN directory after deployment
# rm -rf "$SVN_DIR"

echo -e "\n${GREEN}Deployment complete!${NC}"
echo -e "${GREEN}Check your plugin at: https://wordpress.org/plugins/$PLUGIN_SLUG/${NC}\n"
