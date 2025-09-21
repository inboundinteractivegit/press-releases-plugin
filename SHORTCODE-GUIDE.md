# 📋 Press Releases Shortcode Guide

## 🚀 **Quick Start**

### **Basic Usage (Copy & Paste)**
```
[press_releases]
```
This displays all press releases in accordion format.

## 🛠️ **Easy Shortcode Builder**

### **Access the Builder:**
1. Go to **WordPress Admin**
2. Navigate to **Press Releases → Shortcode Builder**
3. Select your options
4. Click **"Generate Shortcode"**
5. Copy and paste the result!

## 📖 **All Available Options**

### **Display Options**
```
[press_releases limit="5"]                    # Show only 5 press releases
[press_releases orderby="title"]              # Order by title A-Z
[press_releases order="ASC"]                  # Oldest first
[press_releases show_date="no"]               # Hide dates
[press_releases show_count="no"]              # Hide URL counts
[press_releases show_description="no"]        # Hide descriptions
[press_releases excerpt_length="20"]          # Limit description to 20 words
[press_releases search="no"]                  # Remove search box
[press_releases title_tag="h2"]               # Use H2 tags for titles
```

### **Advanced Filtering**
```
[press_releases specific_releases="1,5,10"]   # Show only press releases with IDs 1, 5, and 10
[press_releases exclude_releases="2,7"]       # Hide press releases with IDs 2 and 7
```

### **Combined Examples**
```
# Show 3 newest press releases without search box
[press_releases limit="3" search="no"]

# Show only press releases 1 and 5, with short descriptions
[press_releases specific_releases="1,5" excerpt_length="15"]

# Show all press releases, ordered by title, without dates
[press_releases orderby="title" order="ASC" show_date="no"]
```

## 🎯 **Common Use Cases**

### **Homepage Widget**
```
[press_releases limit="3" excerpt_length="25" search="no"]
```
Perfect for showing latest 3 press releases with brief descriptions.

### **Full Press Release Archive**
```
[press_releases search="yes"]
```
Complete list with search functionality.

### **Specific Campaign Display**
```
[press_releases specific_releases="12,15,18" show_date="no"]
```
Show only specific press releases for a campaign.

### **Clean Minimal Display**
```
[press_releases show_date="no" show_count="no" show_description="no"]
```
Just titles and URLs, no extra information.

## 📋 **Parameter Reference**

| Parameter | Options | Default | Description |
|-----------|---------|---------|-------------|
| `limit` | Number or -1 | -1 | How many press releases to show (-1 = all) |
| `orderby` | date, title, modified, menu_order | date | How to sort press releases |
| `order` | ASC, DESC | DESC | Sort direction |
| `show_date` | yes, no | yes | Display publication date |
| `show_count` | yes, no | yes | Show URL count |
| `show_description` | yes, no | yes | Display content/description |
| `excerpt_length` | Number | 0 | Limit description words (0 = full) |
| `search` | yes, no | yes | Include search box |
| `title_tag` | h1, h2, h3, h4, h5, h6 | h3 | HTML tag for titles |
| `specific_releases` | ID numbers | Empty | Show only these press releases |
| `exclude_releases` | ID numbers | Empty | Hide these press releases |

## 💡 **Pro Tips**

### **Finding Press Release IDs**
1. Go to **Press Releases** in WordPress admin
2. Hover over a press release title
3. Look for `post=123` in the URL - that's the ID!

### **Testing Shortcodes**
- Always test on a draft page first
- Use the preview function before publishing
- Try the basic `[press_releases]` before adding parameters

### **Multiple Shortcodes**
You can use different shortcodes on different pages:
- **Homepage:** `[press_releases limit="3"]`
- **Archive Page:** `[press_releases search="yes"]`
- **Campaign Page:** `[press_releases specific_releases="1,2,3"]`

### **Styling**
- The display automatically matches your theme
- For custom styling, add CSS targeting `.press-releases-container`
- All styling is responsive and mobile-friendly

## 🔧 **Troubleshooting**

### **Shortcode Not Working?**
- Check spelling: `[press_releases]` not `[press-releases]`
- Ensure quotes are straight quotes: `"yes"` not `"yes"`
- Make sure you have press releases published

### **Nothing Showing?**
- Verify you have published press releases
- Check if you're excluding the wrong IDs
- Try the basic `[press_releases]` first

### **Wrong Order/Display?**
- Double-check parameter values
- Use the Shortcode Builder to generate correct syntax
- Test parameters one at a time

## 📞 **Quick Reference Card**

```
BASIC:           [press_releases]
LIMIT:           [press_releases limit="5"]
NO SEARCH:       [press_releases search="no"]
TITLE ONLY:      [press_releases show_date="no" show_count="no" show_description="no"]
SPECIFIC:        [press_releases specific_releases="1,2,3"]
SHORT DESC:      [press_releases excerpt_length="20"]
```

**🎯 Need a custom shortcode? Use the Shortcode Builder in your WordPress admin!**