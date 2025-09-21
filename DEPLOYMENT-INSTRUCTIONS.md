# 🚀 Deployment Instructions - Press Releases Manager v1.1.0

## 📋 **Overview**
This document provides step-by-step instructions for updating the Press Releases Manager plugin from version 1.0 to 1.1.0 on the live website.

## ⚠️ **IMPORTANT: Backup First**
Before making any changes:
1. **Backup the current plugin folder**: `/wp-content/plugins/press-releases-manager/`
2. **Backup the database** (specifically the `wp_press_release_urls` table)
3. **Test on staging environment first** if available

## 📁 **Files to Download from GitHub**

### **Required Files (Must Replace):**
```
📂 From GitHub Repository: https://github.com/inboundinteractivegit/press-releases-plugin

📄 press-releases-manager.php    (UPDATED - Main plugin file)
📄 plugin-updater.php           (Existing - Auto-updater system)
📄 press-releases.css           (Existing - Styles)
📄 press-releases.js            (Existing - JavaScript)
📄 CHANGELOG.md                 (NEW - Version history)
```

### **Optional Files (For Reference):**
```
📄 README.md                    (Documentation)
📄 version-control.md           (Update strategy guide)
📄 press-releases-preview.html  (Demo file)
📄 DEPLOYMENT-INSTRUCTIONS.md   (This file)
```

## 🔧 **Step-by-Step Deployment**

### **Step 1: Access Website Files**
- **Via FTP/SFTP:** Connect to server
- **Via cPanel:** Use File Manager
- **Via WordPress Admin:** Use plugin upload (zip method)

### **Step 2: Navigate to Plugin Directory**
```
/wp-content/plugins/press-releases-manager/
```

### **Step 3: Replace Files**
**Replace these files with the GitHub versions:**

1. **press-releases-manager.php** ⚠️ **CRITICAL**
   - Contains version 1.1.0 update
   - Has proper GitHub username configured
   - Enables auto-updater functionality

2. **Add new file: CHANGELOG.md**
   - Version tracking for future reference

**Keep existing files as-is:**
- `plugin-updater.php` (unchanged)
- `press-releases.css` (unchanged)
- `press-releases.js` (unchanged)

### **Step 4: Verify Upload**
Check these files exist in `/wp-content/plugins/press-releases-manager/`:
- ✅ press-releases-manager.php (updated)
- ✅ plugin-updater.php
- ✅ press-releases.css
- ✅ press-releases.js
- ✅ CHANGELOG.md (new)

## 🧪 **Testing Checklist**

### **Immediate Tests (After Upload):**
- [ ] **Plugin Activation**: Go to WP Admin → Plugins, ensure "Press Releases Manager" shows as active
- [ ] **Version Check**: Plugin should show "Version 1.1.0"
- [ ] **No Errors**: Check for any PHP errors or warnings

### **Functionality Tests:**
- [ ] **Shortcode Display**: Test `[press_releases]` on a page
- [ ] **Accordion Function**: Click press release headers to expand/collapse
- [ ] **AJAX Loading**: URLs should load when accordion opens
- [ ] **Copy Buttons**: Test "Copy" and "Copy All URLs" buttons
- [ ] **Mobile View**: Check responsive design on mobile device

### **Admin Tests:**
- [ ] **Press Releases Menu**: Navigate to WP Admin → Press Releases
- [ ] **Add New**: Create a test press release
- [ ] **Bulk Import**: Test pasting URLs in the bulk import box
- [ ] **Save Function**: Ensure URLs save correctly

## 🔄 **Auto-Updater Verification**

### **Update Notification Test:**
1. Go to **WP Admin → Plugins**
2. Plugin should show **"Version 1.1.0"**
3. Future updates will appear here automatically

### **Update Check:**
- Updates will check GitHub repository: `inboundinteractivegit/press-releases-plugin`
- New versions tagged as `v1.2.0`, `v1.3.0`, etc. will trigger update notifications

## 🚨 **Troubleshooting**

### **Common Issues:**

**Issue: Plugin won't activate**
- **Solution**: Check file permissions (644 for files, 755 for directories)
- **Check**: Ensure all files uploaded correctly

**Issue: "Fatal error" messages**
- **Solution**: Re-upload `press-releases-manager.php`
- **Check**: PHP version compatibility (requires PHP 7.0+)

**Issue: Accordions not working**
- **Solution**: Clear any caching plugins
- **Check**: Browser console for JavaScript errors

**Issue: No update notifications**
- **Solution**: Updates will only show when new versions are released on GitHub
- **Current**: Plugin is now on latest version (1.1.0)

## 📞 **Post-Deployment**

### **Success Verification:**
1. ✅ Plugin shows "Version 1.1.0" in WordPress admin
2. ✅ All existing press releases still display correctly
3. ✅ All URLs still load and copy functions work
4. ✅ No error messages in WordPress admin or frontend
5. ✅ Auto-updater is ready for future versions

### **Next Steps:**
- **Monitor**: Check for any user reports of issues
- **Updates**: Future updates will be automatic via WordPress admin
- **Backup**: Regular backups recommended before future updates

## 📈 **Version History**
- **v1.0.0**: Initial installation (no version control)
- **v1.1.0**: Added version control and auto-updater ← **Current Update**
- **Future**: v1.2.0+ will update automatically

## 🆘 **Emergency Rollback**
If issues occur:
1. **Restore backup** of the plugin folder
2. **Deactivate and reactivate** the plugin
3. **Contact support** with specific error messages

---
**Deployment Date:** ___________
**Deployed By:** ___________
**Tested By:** ___________
**Status:** ⭐ Ready for Production