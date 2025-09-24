# PressStack Release Workflow

## 🚨 Issue Prevention System

This document outlines the process to prevent version mismatches and ensure proper release management.

## 📋 Pre-Release Checklist

### 1. Version Consistency Check
Before any release, verify these files have matching version numbers:

```bash
# Check main plugin file
grep "Version:" press-releases-manager.php

# Check changelog
grep "## \[" CHANGELOG.md | head -1

# Check if any uncommitted changes exist
git status --porcelain
```

**Required**: All version numbers must match before proceeding.

### 2. Git Status Verification

```bash
# Ensure you're on main branch
git branch --show-current

# Check for uncommitted changes
git status

# Verify latest changes are committed
git log --oneline -5
```

### 3. Changelog Validation

- ✅ New version entry exists in CHANGELOG.md
- ✅ Version number matches plugin header
- ✅ Release date is correct
- ✅ All changes documented
- ✅ Breaking changes noted (if any)

## 🔄 Release Process

### Step 1: Pre-Development
```bash
# Always start from clean main branch
git checkout main
git pull origin main
git status  # Should be clean
```

### Step 2: Development
```bash
# Create feature branch for significant changes
git checkout -b feature/version-x.x.x

# Or work directly on main for minor fixes
# But ALWAYS commit regularly
```

### Step 3: Version Update Process
When ready to release:

1. **Update version in plugin file**:
```php
// press-releases-manager.php line 6
* Version: X.X.X
```

2. **Update CHANGELOG.md**:
```markdown
## [X.X.X] - YYYY-MM-DD
### Added/Changed/Fixed
- Description of changes
```

3. **Commit immediately**:
```bash
git add press-releases-manager.php CHANGELOG.md
git commit -m "Version X.X.X: Brief description"
```

### Step 4: Release Execution
```bash
# Final verification
./version-check.sh  # (script we'll create)

# Push to GitHub
git push origin main

# Create GitHub release
# Use GitHub UI or gh CLI
```

## 🛠️ Automated Tools

### Version Check Script
I'll create a script to verify version consistency:

```bash
#!/bin/bash
# version-check.sh
# Run this before every release
```

### Git Hooks
- Pre-commit hook to check version consistency
- Pre-push hook to verify changelog

### GitHub Actions (Future)
- Automated version validation on PR
- Release notes generation
- Tag creation automation

## 📝 Release Templates

### Commit Message Template
```
🚀 Version X.X.X: Brief Feature Description

### Major Changes
- Feature/fix description
- Impact on users
- Technical details

### Technical Notes
- Implementation details
- Breaking changes (if any)
- Migration notes (if needed)

🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### GitHub Release Template
```markdown
## 🚀 What's New in vX.X.X

### Key Features
- Major feature descriptions

### Bug Fixes
- Critical fixes

### Technical Improvements
- Performance enhancements
- Security updates

### Compatibility
- WordPress version support
- PHP version requirements

[Full Changelog](CHANGELOG.md#xxx---yyyy-mm-dd)
```

## 🚨 Emergency Procedures

### If Version Mismatch Detected
1. **Stop immediately** - Do not proceed with release
2. **Identify discrepancy** - Which file has wrong version?
3. **Fix and commit** - Update incorrect version
4. **Re-run checks** - Verify consistency
5. **Proceed with release**

### If Accidental Release
1. **Don't panic** - We can fix this
2. **Create hotfix** - Address the issue
3. **Document** - Add to changelog
4. **Release patch** - Quick version bump

## 📊 Version Strategy

### Semantic Versioning (MAJOR.MINOR.PATCH)
- **MAJOR**: Breaking changes, major feature overhauls
- **MINOR**: New features, significant enhancements
- **PATCH**: Bug fixes, minor improvements

### Current Strategy
- Major releases: New core features
- Minor releases: Enhancements, new options
- Patch releases: Bug fixes, security updates

## 🎯 Success Metrics

### Release Quality
- Zero version mismatches
- Complete changelog entries
- Working auto-updates
- User satisfaction

### Process Efficiency
- Faster release cycles
- Fewer hotfixes needed
- Better documentation
- Reduced manual errors

---

## 🔄 Next Steps

1. Implement version-check.sh script
2. Set up git hooks
3. Create GitHub Action workflows
4. Train team on new process
5. Monitor and improve

This workflow ensures professional release management and prevents the issues we just experienced.