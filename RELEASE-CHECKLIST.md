# 🚀 PressStack Release Checklist

Use this checklist before every release to ensure consistency and quality.

## 📋 Pre-Release Verification

### 1. Automated Check
```bash
# Run the version consistency check
./version-check.sh
```

**Status**: ☐ Script passes all checks

### 2. Manual Verification

#### Version Numbers
- ☐ Plugin header version matches changelog
- ☐ New version number follows semantic versioning
- ☐ Version is higher than previous release

#### Code Quality
- ☐ No TODO/FIXME comments in release code
- ☐ No debug statements (`console.log`, `var_dump`, etc.)
- ☐ All functions properly documented
- ☐ Security best practices followed

#### Files & Documentation
- ☐ `CHANGELOG.md` updated with new version
- ☐ `README.md` reflects current features
- ☐ Release date is correct in changelog
- ☐ All required files present:
  - ☐ `press-releases-manager.php`
  - ☐ `plugin-updater.php`
  - ☐ `press-releases.js`
  - ☐ `press-releases.css`
  - ☐ `CHANGELOG.md`
  - ☐ `README.md`

#### Testing
- ☐ Plugin loads without errors
- ☐ Core functionality tested:
  - ☐ Add new press release
  - ☐ Add individual URLs
  - ☐ Bulk import URLs
  - ☐ Frontend display works
  - ☐ AJAX loading functions
- ☐ Security features working:
  - ☐ Rate limiting active
  - ☐ Input validation working
  - ☐ Nonce verification working

#### Compatibility
- ☐ WordPress version compatibility tested
- ☐ PHP version requirements verified
- ☐ No PHP errors or warnings
- ☐ Works with common themes/plugins

## 🔄 Git Workflow

### Before Committing
- ☐ All changes staged: `git add .`
- ☐ No unintended files in staging
- ☐ Meaningful commit message prepared

### Commit Message Template
```
🚀 Version X.X.X: [Brief Description]

### Major Changes
- Feature/improvement descriptions
- Bug fixes
- Performance enhancements

### Technical Details
- Implementation notes
- Breaking changes (if any)
- Migration guidance (if needed)

🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### Git Commands
```bash
# Stage changes
git add .

# Commit with detailed message
git commit -m "[Use template above]"

# Push to GitHub
git push origin main
```

**Status**: ☐ Changes committed and pushed

## 📦 GitHub Release

### Release Information
- ☐ Tag version: `vX.X.X` (with 'v' prefix)
- ☐ Release title: `🚀 PressStack vX.X.X: [Brief Description]`
- ☐ Target branch: `main`

### Release Description Template
```markdown
## 🚀 What's New in vX.X.X

### Key Features
- Major new features
- Significant improvements

### Bug Fixes
- Critical issues resolved
- User-reported problems fixed

### Technical Improvements
- Performance enhancements
- Security updates
- Code quality improvements

### Compatibility
- WordPress X.X+ supported
- PHP X.X+ required

[Full Changelog](CHANGELOG.md#xxx---yyyy-mm-dd)

---
Download the latest version and update your plugin!
```

### GitHub Release Steps
1. ☐ Go to GitHub repository releases page
2. ☐ Click "Create a new release"
3. ☐ Enter tag version (vX.X.X)
4. ☐ Add release title
5. ☐ Copy release description from template
6. ☐ Review all information
7. ☐ Click "Publish release"

**Status**: ☐ GitHub release published

## 📢 Post-Release

### Verification
- ☐ GitHub release appears correctly
- ☐ Auto-updater detects new version
- ☐ Download links work
- ☐ Installation from GitHub works

### Communication
- ☐ Social media posts ready (Twitter, LinkedIn)
- ☐ Community notifications sent (if applicable)
- ☐ Users notified of critical fixes (if applicable)

### Monitoring
- ☐ Monitor for user reports
- ☐ Check for compatibility issues
- ☐ Track download/update metrics

## 🚨 Emergency Rollback

If issues are discovered after release:

### Immediate Actions
1. ☐ Document the issue
2. ☐ Determine severity (critical/major/minor)
3. ☐ Create hotfix branch if needed
4. ☐ Prepare patch version

### Hotfix Process
```bash
# Create hotfix branch
git checkout -b hotfix/vX.X.Y

# Make minimal fix
# Update version to X.X.Y
# Update changelog

# Test fix
./version-check.sh

# Commit and release
git commit -m "🔥 Hotfix vX.X.Y: [Issue description]"
git push origin hotfix/vX.X.Y

# Create pull request and merge
# Follow release process for patch version
```

## 📊 Release Success Metrics

### Technical Quality
- ☐ Zero critical bugs reported in first 48 hours
- ☐ No version mismatch issues
- ☐ Auto-updater works correctly
- ☐ All advertised features work as expected

### User Satisfaction
- ☐ Positive user feedback
- ☐ No major feature regressions
- ☐ Performance maintained or improved
- ☐ Documentation is accurate

---

## 🎯 Continuous Improvement

After each release, review:
- What went well?
- What could be improved?
- Any process gaps to address?
- Tools or automation to add?

Update this checklist based on lessons learned.