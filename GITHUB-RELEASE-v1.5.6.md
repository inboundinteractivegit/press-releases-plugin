# GitHub Release Details for v1.5.6

## Release Title
```
🔧 PressStack v1.5.6: Auto-Updater Fixes - No More Force Checking Required
```

## Tag Version
```
v1.5.6
```

## Release Notes (Copy this entire section)
```
## 🔧 Auto-Updater Fixes - Seamless Updates Restored

**Important maintenance update** - This release fixes the auto-updater issues that were requiring users to manually "Force Check" for updates.

### 🚀 What's Fixed

#### Auto-Update Detection Issues
- ✅ **Faster update detection** - Reduced cache time from 1 hour to 15 minutes
- ✅ **Enhanced version parsing** - Better handling of GitHub release tags
- ✅ **Fixed empty cache issues** - Eliminated false cache hits that prevented updates
- ✅ **Improved error handling** - Better GitHub API failure recovery
- ✅ **Enhanced debugging** - Added logging for troubleshooting update issues

### 📋 Technical Improvements

#### Cache & Performance
- **Reduced update check cache**: 1 hour → 15 minutes for faster detection
- **Added empty value validation**: Prevents false cache hits
- **Enhanced version cleaning**: Regex-based tag format normalization
- **Improved API handling**: Better error recovery and logging

#### Debugging & Reliability
- **Debug logging added**: When `WP_DEBUG` is enabled, logs update check details
- **Better error messages**: More informative failure reporting
- **Enhanced force check**: More reliable manual update checking

### 🎯 User Experience Improvements

**Before v1.5.6:**
- ❌ Users had to manually "Force Check" for updates
- ❌ Updates could take up to 1 hour to be detected
- ❌ Silent failures with no debugging info

**After v1.5.6:**
- ✅ **Automatic update detection** within 15 minutes
- ✅ **No manual intervention** required
- ✅ **Reliable update notifications** in WordPress admin

### 🔄 What's NOT Changed

- ✅ All existing plugin functionality works exactly the same
- ✅ No breaking changes to press release features
- ✅ No database changes required
- ✅ No user configuration needed

### 📊 Impact

This release specifically addresses the update detection issues:

- **Site administrators** will now see update notifications automatically
- **Plugin updates** will be seamless and reliable
- **User experience** is significantly improved

### 🛠️ For Developers

If experiencing update issues, enable `WP_DEBUG` to see update detection logs:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

---

## 📈 Update Process

This is a **patch release** (1.5.5 → 1.5.6):
- Safe to update immediately
- Fixes auto-updater for future releases
- No functional changes to press release features

**After this update, future versions will be detected automatically within 15 minutes!**

## Changelog
See [CHANGELOG.md](CHANGELOG.md#156---2025-09-24) for complete technical details.
```

---

## Instructions to Create GitHub Release:

1. **Go to**: https://github.com/inboundinteractivegit/press-releases-plugin/releases/new

2. **Fill in these fields**:
   - **Tag version**: `v1.5.6`
   - **Release title**: `🔧 PressStack v1.5.6: Auto-Updater Fixes - No More Force Checking Required`
   - **Target**: `main` (should be selected by default)

3. **Description**: Copy everything between the triple backticks in the "Release Notes" section above

4. **Settings**:
   - ☐ Leave "This is a pre-release" unchecked
   - ☑ Check "Generate release notes automatically" (optional, for additional context)

5. **Click**: "Publish release"

## After Publishing:
- Users will get automatic update notifications within 15 minutes
- No more need for manual "Force Check" clicks
- Future releases will be detected seamlessly