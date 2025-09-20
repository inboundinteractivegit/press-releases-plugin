# Press Releases Manager - WordPress Plugin

A powerful WordPress plugin for managing press releases with 500+ URLs using accordion interface and AJAX loading for optimal performance.

## 📁 Files Overview

- **`press-releases-manager.php`** - Main WordPress plugin file
- **`press-releases.css`** - Styling for accordion interface
- **`press-releases.js`** - JavaScript for AJAX and interactions
- **`press-releases-preview.html`** - Live preview demo

## 🚀 Installation

1. **Upload to WordPress:**
   ```
   /wp-content/plugins/press-releases-manager/
   ├── press-releases-manager.php
   ├── press-releases.css
   ├── press-releases.js
   └── README.md
   ```

2. **Activate Plugin:**
   - Go to WordPress Admin → Plugins
   - Find "Press Releases Manager"
   - Click "Activate"

## 📝 How to Use

### Adding Press Releases

1. **Navigate to Press Releases:**
   - WordPress Admin → Press Releases → Add New

2. **Create Press Release:**
   - Add title (e.g., "Product Launch Campaign")
   - Add description/content
   - Use bulk URL import box

3. **Bulk Import URLs:**
   ```
   https://techcrunch.com/article1
   https://forbes.com/news2, Forbes Business Coverage
   https://reuters.com/story3
   ... (paste all 500+ URLs)
   ```

4. **Save:** All URLs imported instantly!

### Displaying Press Releases

Add shortcode to any page/post:
```
[press_releases]
```

**Optional parameters:**
```
[press_releases limit="5" orderby="date" order="DESC"]
```

## ✨ Key Features

- ⚡ **Fast Loading:** AJAX loads URLs only when needed
- 📱 **Mobile Responsive:** Works on all devices
- 📋 **Copy Functions:** Copy individual or all URLs
- 🔍 **SEO Friendly:** All URLs crawlable by Google
- 💾 **Bulk Import:** Easy paste of 500+ URLs
- 🎨 **Professional Design:** Clean accordion interface

## 🔧 Technical Details

### Database Structure
- **Custom Post Type:** `press_release`
- **Custom Table:** `wp_press_release_urls`
- **Fields:** `press_release_id`, `url`, `title`, `date_added`

### AJAX Endpoints
- **Action:** `load_press_release_urls`
- **Security:** WordPress nonce verification
- **Response:** HTML formatted URL list

### CSS Classes
- `.press-releases-container` - Main wrapper
- `.press-release-item` - Individual press release
- `.accordion-header` - Clickable header
- `.accordion-content` - Collapsible content
- `.urls-list` - URL listing area

## 📊 Performance Benefits

1. **Initial Page Load:** Only headers load (fast)
2. **On-Demand Loading:** URLs load when accordion opens
3. **Caching:** Loaded URLs stay in DOM
4. **Mobile Optimized:** Responsive design
5. **SEO Optimized:** All content crawlable

## 🛠️ Customization Options

### Styling
Edit `press-releases.css` to match your theme:
- Colors, fonts, spacing
- Mobile breakpoints
- Animation timings

### Functionality
Edit `press-releases.js` for:
- Search functionality (commented out)
- Keyboard navigation (commented out)
- Additional features

## 📱 Mobile Features

- Touch-friendly accordions
- Optimized layout for small screens
- Easy copy buttons
- Scrollable URL lists

## 🔐 Security Features

- WordPress nonce verification
- Sanitized input/output
- SQL injection protection
- XSS prevention

## 🎯 Use Cases

Perfect for:
- **PR Agencies:** Managing multiple press releases
- **Corporations:** Organizing media coverage
- **SEO Teams:** Tracking backlink campaigns
- **Marketing:** Monitoring brand mentions

## 📞 Support

For issues or customizations:
1. Check the preview file first
2. Verify all files are uploaded correctly
3. Ensure plugin is activated
4. Test with default WordPress theme

## 🔄 Updates

Version 1.0 - Initial release
- Accordion interface
- AJAX loading
- Bulk URL import
- Mobile responsive
- Copy functionality
