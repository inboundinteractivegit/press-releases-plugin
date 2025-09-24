# 📋 PressStack Release Management System

## Quick Start

### Before Every Release
```bash
# Run this command to verify everything is ready
./version-check.sh
```

### Release Process
1. **Update code and version numbers**
2. **Run version check**: `./version-check.sh`
3. **Commit changes**: Follow checklist
4. **Push to GitHub**: `git push origin main`
5. **Create GitHub release**: Use provided templates

## 📁 Files Overview

### 🔧 Tools
- **`version-check.sh`** - Automated version consistency checker
- **`RELEASE-CHECKLIST.md`** - Step-by-step release checklist
- **`RELEASE-WORKFLOW.md`** - Detailed workflow documentation

### 📖 Templates
- **`GITHUB-RELEASE-v1.5.4.md`** - GitHub release templates and social media posts
- **`PRESS-RELEASE-v1.5.4.md`** - Professional press release template

## ⚡ Quick Commands

```bash
# Check if ready to release
./version-check.sh

# Check current version
grep "Version:" press-releases-manager.php

# Check git status
git status

# See recent commits
git log --oneline -5

# Create and push release
git add .
git commit -m "🚀 Version X.X.X: Description"
git push origin main
```

## 🎯 Key Benefits

✅ **Prevents version mismatches** - Never again release with wrong version numbers
✅ **Ensures consistency** - All files stay synchronized
✅ **Speeds up releases** - Automated checks save time
✅ **Reduces errors** - Checklists prevent forgotten steps
✅ **Professional quality** - Standardized release notes and messaging

## 🚨 Emergency Procedures

### If Version Check Fails
1. **Don't panic** - The system caught the issue!
2. **Read the error message** - It will tell you exactly what's wrong
3. **Fix the issue** - Update version numbers or changelog
4. **Run check again** - `./version-check.sh`
5. **Proceed when green** - ✅ Ready for release!

### If You Forget to Run Version Check
1. **Check GitHub release** - Does everything look correct?
2. **If yes** - You're fine, remember for next time
3. **If no** - Create a hotfix release to correct it

## 📈 Version Strategy

- **Major (X.0.0)**: Breaking changes, major overhauls
- **Minor (X.Y.0)**: New features, enhancements
- **Patch (X.Y.Z)**: Bug fixes, security updates

## 🔄 Workflow Summary

```
Local Development
     ↓
Version Check (./version-check.sh)
     ↓
Commit & Push
     ↓
GitHub Release
     ↓
User Updates
```

This system ensures every release is professional, consistent, and error-free!