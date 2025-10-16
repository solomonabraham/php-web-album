# 💍 Luxe Wedding Gallery

A beautiful, modern, and highly optimized PHP-based wedding photo gallery with advanced features including lightbox viewing, custom downloads, theme switching, and automatic thumbnail management.

![Version](https://img.shields.io/badge/version-2.2-blue)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple)
![License](https://img.shields.io/badge/license-MIT-green)

## ✨ Features

### Core Features
- **📸 Beautiful Photo Gallery** - Responsive mosaic layout with dynamic grid patterns
- **🎬 Fullscreen Hero Slider** - Stunning fullscreen slider with Ken Burns effects
- **📌 Sticky Navigation** - Header and album menu stay visible while scrolling
- **🎥 Video Support** - Display videos with thumbnail previews and inline playback
- **🔒 Password Protection** - Optional password protection with rate limiting
- **🌓 Dark/Light Theme** - Seamless theme switching with localStorage persistence
- **📱 Fully Responsive** - Optimized for all devices (mobile, tablet, desktop)
- **⚡ Ultra-Fast Performance** - Aggressive caching, lazy loading, and optimization
- **♾️ Infinite Scroll** - Smooth pagination with automatic content loading

### Advanced Features
- **💾 Native Download Button** - Custom download functionality with full-quality originals
- **🔍 Lightbox Gallery** - GLightbox integration with zoom and navigation
- **⚡ Web-Optimized Images** - Fast-loading optimized images in lightbox, full quality for downloads
- **📁 Album Organization** - Organize photos into multiple albums
- **🖼️ Smart Thumbnails** - Automatic thumbnail generation with cleanup
- **📊 Auto-Sync** - Background image generation on page load
- **🧹 Maintenance Tools** - Built-in maintenance script for optimization
- **📝 Error Logging** - Comprehensive logging system for debugging
- **🎯 SEO Optimized** - Proper meta tags and semantic HTML

## 🚀 Requirements

### Server Requirements
- **PHP**: 8.0 or higher
- **GD Library**: For image processing
- **Memory**: Minimum 512MB (recommended 1GB+)
- **Disk Space**: Varies based on media size

### Optional Requirements
- **FFmpeg**: For video thumbnail generation
- **Mod_rewrite**: For clean URLs (optional)

### Supported File Formats
- **Images**: JPG, JPEG, PNG, GIF, WebP
- **Videos**: MP4, WebM, OGG, MOV

## 📦 Installation

### Step 1: Upload Files
Upload all files to your web server:
```
wedding-gallery/
├── index.php
├── config.php
├── ErrorLogger.php
├── maintenance.php
├── styles.css
├── media/
│   └── thumbnails/
├── cache/
└── logs/
```

### Step 2: Set Permissions
```bash
chmod 755 index.php config.php maintenance.php
chmod 755 media/
chmod 755 cache/
chmod 755 logs/
```

### Step 3: Create Media Folders
Create album folders inside the `media` directory:
```bash
mkdir media/ceremony
mkdir media/reception
mkdir media/portraits
```

### Step 4: Upload Photos
Upload your photos/videos into the album folders:
```
media/
├── ceremony/
│   ├── photo1.jpg
│   ├── photo2.jpg
│   └── video1.mp4
├── reception/
│   ├── photo3.jpg
│   └── photo4.jpg
└── portraits/
    └── photo5.jpg
```

### Step 5: Configure Settings
Edit `config.php` to customize your gallery (see Configuration section).

### Step 6: Access Gallery
Navigate to your gallery URL in a web browser:
```
https://yourdomain.com/gallery/
```

## ⚙️ Configuration

Edit `config.php` to customize your gallery:

### Basic Settings
```php
'galleryTitle' => 'Your Names',
'welcomeMessage' => 'Your welcome message',
'weddingDate' => 'Your Wedding Date',
```

### Password Protection
```php
'requirePassword' => true,
'galleryPassword' => 'yourpassword',
'useHashedPassword' => false, // Set true for better security
```

To use hashed password:
```bash
php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
```
Copy the output and paste it as `galleryPassword`, then set `useHashedPassword` to `true`.

### Theme Settings
```php
'theme' => 'light', // 'light' or 'dark'
```

### Slider Configuration
```php
'slider' => [
    'enabled' => true,
    'autoplay' => true,
    'autoplayDelay' => 5000,
    'effect' => 'fade', // 'fade', 'slide', 'cube', 'coverflow', 'flip'
    'speed' => 1200,
    'showNavigation' => true,
    'showPagination' => true,
    'loop' => true,
    'slidesCount' => 10,
    'showTitle' => true,
    'showDate' => true,
    'showMessage' => true,
    'overlayOpacity' => 0.5,
],
```

### Performance Settings
```php
'autoSyncEnabled' => true,        // Auto-generate images
'autoSyncLimit' => 20,            // Max images per page load
'autoCleanupEnabled' => true,     // Auto-cleanup orphaned images
'autoCleanupInterval' => 3600,    // Cleanup interval (seconds)
'itemsPerPage' => 50,             // Photos per page
'thumbnailWidth' => 1200,
'thumbnailHeight' => 900,
'thumbnailQuality' => 85,
```

### Web-Optimized Image Settings
The gallery uses **three versions** of each image for optimal performance:

```php
// Thumbnails - for gallery grid (fast loading)
'thumbnailWidth' => 1200,
'thumbnailHeight' => 900,
'thumbnailQuality' => 85,

// Web-Optimized - for lightbox viewing (balanced)
'webOptimizedWidth' => 2000,
'webOptimizedHeight' => 2000,
'webOptimizedQuality' => 82,

// Originals - for downloads (full quality)
// Stored in your album folders
```

**How it works:**
1. **Gallery Grid**: Shows small thumbnails for fast scrolling
2. **Lightbox View**: Displays web-optimized images (good quality, fast loading)
3. **Downloads**: Provides full-quality original files

This approach ensures:
- ⚡ Lightning-fast gallery browsing
- 👀 Beautiful lightbox viewing experience  
- 📥 High-quality downloads for users

## 📂 Directory Structure

```
wedding-gallery/
├── index.php              # Main gallery application
├── config.php             # Configuration file
├── ErrorLogger.php        # Logging system
├── maintenance.php        # Maintenance tool
├── styles.css             # Stylesheet
├── media/                 # Media directory
│   ├── album1/           # Album folders (originals)
│   ├── album2/
│   ├── thumbnails/       # Auto-generated thumbnails
│   └── web-optimized/    # Auto-generated web-optimized images
├── cache/                # Cache directory
│   ├── gallery_cache.json
│   └── last_cleanup.txt
└── logs/                 # Error logs
    └── gallery.log
```

## 🎯 Usage

### Adding New Photos
1. Upload photos to an existing album folder or create a new one
2. Thumbnails will be generated automatically on next page load
3. Or run `maintenance.php` to generate all thumbnails immediately

### Creating Albums
Simply create a new folder in the `media` directory:
```bash
mkdir media/new-album-name
```
The album will appear automatically in the navigation.

### Album Naming
- Folder names are converted to titles (e.g., `my-wedding` → `My Wedding`)
- Use hyphens or underscores for spaces
- Avoid special characters

## 🛠️ Maintenance

### Running Maintenance Script

#### Via Web Browser
Navigate to:
```
https://yourdomain.com/gallery/maintenance.php
```

#### Via Command Line (Recommended)
```bash
php maintenance.php
```

### What Maintenance Does
1. **Scans Media Directory** - Finds all photos and videos
2. **Scans Image Directories** - Identifies existing thumbnails and web-optimized images
3. **Finds Orphaned Images** - Detects images without originals
4. **Cleans Up** - Deletes orphaned thumbnails and web-optimized images
5. **Generates Missing Thumbnails** - Creates thumbnails for new media
6. **Generates Missing Web-Optimized** - Creates optimized images for lightbox
7. **Clears Cache** - Refreshes gallery cache

### Automated Maintenance (Cron)
Set up a cron job for automatic maintenance:
```bash
# Run daily at 3 AM
0 3 * * * /usr/bin/php /path/to/gallery/maintenance.php
```

## 🎨 Customization

### Changing Colors
Edit CSS custom properties in `styles.css`:
```css
:root {
  --color-primary: #d4af37;        /* Gold accent color */
  --color-primary-dark: #c19d2e;
  --color-bg: #fdfbf7;             /* Background color */
  /* ... more variables */
}
```

### Adjusting Layout
Modify gallery grid in `styles.css`:
```css
.gallery {
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: var(--spacing-sm);
}
```

### Custom Fonts
Replace fonts in `index.php` head section:
```html
<link href="https://fonts.googleapis.com/css2?family=Your+Font&display=swap" rel="stylesheet">
```

Then update CSS:
```css
:root {
  --font-serif: 'Your Serif Font', serif;
  --font-sans: 'Your Sans Font', sans-serif;
}
```

## 🔧 Troubleshooting

### Thumbnails Not Generating
**Solution:**
1. Check folder permissions: `chmod 755 media/thumbnails media/web-optimized`
2. Verify GD library: `php -m | grep gd`
3. Increase memory limit in `config.php`
4. Run maintenance script manually

### Web-Optimized Images Not Showing
**Solution:**
1. Run maintenance script to generate missing images
2. Check permissions: `chmod 755 media/web-optimized`
3. Verify the directory exists and is writable
4. Check error logs in `logs/gallery.log`

### Video Thumbnails Not Working
**Solution:**
1. Install FFmpeg: `sudo apt-get install ffmpeg`
2. Verify installation: `which ffmpeg`
3. Check server permissions

### Gallery Shows "Not Writable" Error
**Solution:**
```bash
chmod 755 media/thumbnails
chown www-data:www-data media/thumbnails
```

### Images Not Loading
**Solution:**
1. Check file extensions are supported
2. Verify file permissions: `chmod 644 media/album/*.jpg`
3. Check PHP memory limit
4. Review error logs in `logs/gallery.log`

### Password Not Working
**Solution:**
1. Check `galleryPassword` in `config.php`
2. If using hashed password, verify `useHashedPassword` is `true`
3. Clear browser cookies/cache
4. Regenerate password hash

### Slow Performance
**Solution:**
1. Enable caching in `config.php`
2. Reduce `itemsPerPage`
3. Lower `thumbnailQuality`
4. Run maintenance to clean up
5. Enable opcode caching (OPcache)

## 📊 Performance Tips

1. **Optimize Images Before Upload**
   - Resize large images to max 4000px width
   - Use JPEG for photos, PNG for graphics
   - Compress images before uploading

2. **Web-Optimized Settings**
   - Default settings (2000px, 82% quality) provide excellent balance
   - Lower `webOptimizedQuality` to 75-80 for smaller file sizes
   - Increase to 85-90 for better quality (larger files)

3. **Enable Server Caching**
   - Use .htaccess for browser caching
   - Enable gzip compression
   - Use CDN for static assets

4. **Configure PHP**
   ```ini
   memory_limit = 1G
   max_execution_time = 300
   upload_max_filesize = 100M
   post_max_size = 100M
   ```

5. **Database-Free Architecture**
   - No database required
   - File-based caching
   - Minimal server overhead

6. **Image Quality Guide**
   - **Thumbnails**: 85% (visible in grid, needs to be crisp)
   - **Web-Optimized**: 82% (lightbox viewing, best balance)
   - **Originals**: 100% (downloads, preserve all quality)

## 🔐 Security Best Practices

1. **Use Hashed Passwords**
   - Always set `useHashedPassword` to `true`
   - Use strong passwords (12+ characters)

2. **Secure File Permissions**
   ```bash
   chmod 644 *.php
   chmod 755 media/
   chmod 755 cache/
   chmod 755 logs/
   ```

3. **Disable Directory Listing**
   Add to `.htaccess`:
   ```apache
   Options -Indexes
   ```

4. **Rate Limiting**
   - Built-in login rate limiting (5 attempts)
   - 5-minute lockout after failed attempts

5. **Hide Sensitive Files**
   ```apache
   <FilesMatch "^(config\.php|ErrorLogger\.php)$">
     Order allow,deny
     Deny from all
   </FilesMatch>
   ```

## 🌐 Browser Support

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## 📄 License

This project is open source and available under the MIT License.

## 🙏 Credits

### Libraries Used
- [Swiper](https://swiperjs.com/) - Touch slider
- [GLightbox](https://biati-digital.github.io/glightbox/) - Lightbox gallery
- [Google Fonts](https://fonts.google.com/) - Typography

### Developed By
**DREAMGRAPHERS**
- Website: [dreamgraphers.net](https://dreamgraphers.net)

## 📞 Support

For issues, questions, or feature requests:
1. Check the troubleshooting section
2. Review error logs in `logs/gallery.log`
3. Consult the configuration documentation

## 🔄 Updates

### Version 2.2.2 (Current)
- **Sticky Header**: Header stays at top with backdrop blur effect
- **Sticky Album Navigation**: Album menu sticks below header
- **Fixed Footer**: Footer always visible at bottom with auto-hide in lightbox
- **Smart Positioning**: JavaScript auto-adjusts sticky elements
- **Enhanced Mobile**: Optimized sticky behavior for mobile devices
- **Backdrop Blur**: Modern glass-morphism effects on navigation elements

### Version 2.2.1
- **Fullscreen Slider**: 100vh slider on all devices for dramatic impact
- **Enhanced Ken Burns Effect**: Slow zoom with subtle pan (15s duration)
- **Animated Overlays**: Subtle pulse effect on gradient overlay
- **Enhanced Navigation**: Larger buttons with pulse and glow effects
- **Scroll Indicator**: Animated scroll hint at bottom of slider
- **Centered Content**: Text centered on screen for better composition
- **Dramatic Animations**: Bounce and scale effects on text entrance
- **Active Slide Transitions**: Fade effects between slides

### Version 2.2
- **Web-Optimized Images**: Separate optimized images for lightbox viewing
- Fast-loading gallery with high-quality lightbox experience
- Full-quality originals provided for downloads
- Three-tier image system: thumbnails, web-optimized, originals
- Enhanced maintenance script for multi-version image management

### Version 2.1
- Enhanced lightbox with custom download button
- Improved thumbnail generation system
- Better error handling and logging
- Performance optimizations
- Dark theme improvements

---

**Enjoy your beautiful wedding gallery! 💐**