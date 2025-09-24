#!/bin/bash

# PressStack Version Consistency Check
# Run this before every release to prevent version mismatches

set -e  # Exit on any error

echo "🔍 PressStack Version Consistency Check"
echo "======================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get version from plugin file
PLUGIN_VERSION=$(grep -o "Version: [0-9]\+\.[0-9]\+\.[0-9]\+" press-releases-manager.php | cut -d' ' -f2)
echo -e "${BLUE}Plugin File Version:${NC} $PLUGIN_VERSION"

# Get version from changelog (first unreleased version)
CHANGELOG_VERSION=$(grep -o "## \[[0-9]\+\.[0-9]\+\.[0-9]\+\]" CHANGELOG.md | head -1 | grep -o "[0-9]\+\.[0-9]\+\.[0-9]\+")
echo -e "${BLUE}Changelog Version:${NC} $CHANGELOG_VERSION"

# Get latest git tag
LATEST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || echo "No tags found")
echo -e "${BLUE}Latest Git Tag:${NC} $LATEST_TAG"

# Check git status
if [[ -n $(git status --porcelain) ]]; then
    echo -e "${YELLOW}⚠️  Warning: Uncommitted changes detected${NC}"
    git status --short
    echo ""
fi

# Version consistency check
echo ""
echo "🔍 Consistency Check:"
echo "===================="

if [[ "$PLUGIN_VERSION" == "$CHANGELOG_VERSION" ]]; then
    echo -e "${GREEN}✅ Plugin file and changelog versions match${NC}"
    VERSION_MATCH=true
else
    echo -e "${RED}❌ Version mismatch detected!${NC}"
    echo -e "   Plugin file: $PLUGIN_VERSION"
    echo -e "   Changelog:   $CHANGELOG_VERSION"
    VERSION_MATCH=false
fi

# Check if this is a new version
if [[ "$LATEST_TAG" == "v$PLUGIN_VERSION" ]] || [[ "$LATEST_TAG" == "$PLUGIN_VERSION" ]]; then
    echo -e "${YELLOW}⚠️  Warning: This version already exists as a git tag${NC}"
    NEW_VERSION=false
else
    echo -e "${GREEN}✅ This appears to be a new version${NC}"
    NEW_VERSION=true
fi

# Check changelog date
TODAY=$(date +%Y-%m-%d)
CHANGELOG_DATE=$(grep -A1 "## \[$PLUGIN_VERSION\]" CHANGELOG.md | grep -o "[0-9]\{4\}-[0-9]\{2\}-[0-9]\{2\}" || echo "No date found")

if [[ "$CHANGELOG_DATE" == "$TODAY" ]]; then
    echo -e "${GREEN}✅ Changelog date is current${NC}"
elif [[ "$CHANGELOG_DATE" == "No date found" ]]; then
    echo -e "${YELLOW}⚠️  Warning: No release date found in changelog${NC}"
else
    echo -e "${YELLOW}⚠️  Warning: Changelog date ($CHANGELOG_DATE) is not today ($TODAY)${NC}"
fi

echo ""
echo "📋 Pre-Release Checklist:"
echo "========================"

# Required files check
REQUIRED_FILES=("press-releases-manager.php" "CHANGELOG.md" "README.md" "plugin-updater.php")
for file in "${REQUIRED_FILES[@]}"; do
    if [[ -f "$file" ]]; then
        echo -e "${GREEN}✅ $file exists${NC}"
    else
        echo -e "${RED}❌ $file missing${NC}"
    fi
done

# Check for common issues
if grep -q "TODO\|FIXME\|XXX" *.php *.md 2>/dev/null; then
    echo -e "${YELLOW}⚠️  Warning: TODO/FIXME comments found${NC}"
    grep -n "TODO\|FIXME\|XXX" *.php *.md 2>/dev/null || true
fi

# WordPress compatibility check
WP_TESTED=$(grep "Tested up to:" press-releases-manager.php | cut -d':' -f2 | xargs)
PHP_REQUIRED=$(grep "Requires PHP:" press-releases-manager.php | cut -d':' -f2 | xargs)

echo -e "${BLUE}WordPress Compatibility:${NC} $WP_TESTED"
echo -e "${BLUE}PHP Requirement:${NC} $PHP_REQUIRED"

echo ""
echo "🎯 Final Status:"
echo "==============="

if [[ "$VERSION_MATCH" == true ]] && [[ "$NEW_VERSION" == true ]]; then
    echo -e "${GREEN}🚀 Ready for release!${NC}"
    echo ""
    echo -e "${BLUE}Next steps:${NC}"
    echo "1. git add ."
    echo "2. git commit -m '🚀 Version $PLUGIN_VERSION: [Description]'"
    echo "3. git push origin main"
    echo "4. Create GitHub release with tag v$PLUGIN_VERSION"
    exit 0
else
    echo -e "${RED}❌ Not ready for release${NC}"
    echo ""
    echo -e "${BLUE}Required actions:${NC}"

    if [[ "$VERSION_MATCH" == false ]]; then
        echo "- Fix version mismatch between plugin file and changelog"
    fi

    if [[ "$NEW_VERSION" == false ]]; then
        echo "- Update version number to create new release"
    fi

    exit 1
fi