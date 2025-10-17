<?php
/**
 * Wedding Gallery Configuration Editor (Web Editable)
 * Uses ConfigManager to read/write config.json
 * NOTE: This file is NOT secure and should be protected (e.g., via .htaccess or by implementing login logic).
 */

require_once __DIR__ . '/ConfigManager.php';
require_once __DIR__ . '/ErrorLogger.php'; 

$configManager = ConfigManager::getInstance();
$config = $configManager->getAll();
$message = '';
$error = '';

// --- Helper Functions for Usability ---
function bytesToMB($bytes) {
    return round($bytes / 1024 / 1024, 0);
}
function mbToBytes($mb) {
    return (int)($mb * 1024 * 1024);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Copy current config
    $newConfig = $config;
    
    // --- 1. GALLERY INFORMATION ---
    $newConfig['galleryTitle'] = $_POST['galleryTitle'] ?? $newConfig['galleryTitle'];
    $newConfig['welcomeMessage'] = $_POST['welcomeMessage'] ?? $newConfig['welcomeMessage'];
    $newConfig['weddingDate'] = $_POST['weddingDate'] ?? $newConfig['weddingDate'];

    // --- 2. SECURITY & THEME ---
    $newConfig['requirePassword'] = isset($_POST['requirePassword']);
    $newConfig['theme'] = $_POST['theme'] ?? $newConfig['theme'];

    // --- 3. DIRECTORIES ---
    $newConfig['mediaDirRelative'] = $_POST['mediaDirRelative'] ?? $newConfig['mediaDirRelative'];

    // --- 4. FILE TYPES (Handle as comma-separated strings) ---
    $newConfig['imageExtensions'] = array_map('trim', explode(',', $_POST['imageExtensions']));
    $newConfig['videoExtensions'] = array_map('trim', explode(',', $_POST['videoExtensions']));

    // --- 5. GALLERY DISPLAY & CACHE (Integer Fields) ---
    $newConfig['itemsPerPage'] = (int)($_POST['itemsPerPage'] ?? $newConfig['itemsPerPage']);
    $newConfig['maxFileSize'] = mbToBytes((int)($_POST['maxFileSize_mb'] ?? bytesToMB($newConfig['maxFileSize'])));
    $newConfig['cacheDuration'] = (int)($_POST['cacheDuration'] ?? $newConfig['cacheDuration']);

    // --- 6. AUTO-SYNC SETTINGS (Boolean/Integer Fields) ---
    $newConfig['autoSyncEnabled'] = isset($_POST['autoSyncEnabled']);
    $newConfig['autoSyncLimit'] = (int)($_POST['autoSyncLimit'] ?? $newConfig['autoSyncLimit']);
    $newConfig['autoCleanupEnabled'] = isset($_POST['autoCleanupEnabled']);
    $newConfig['autoCleanupInterval'] = (int)($_POST['autoCleanupInterval'] ?? $newConfig['autoCleanupInterval']);
    $newConfig['autoCleanupLimit'] = (int)($_POST['autoCleanupLimit'] ?? $newConfig['autoCleanupLimit']);
    
    // --- 7. IMAGE GENERATION SETTINGS (Integer Fields) ---
    // Thumbnails
    $newConfig['thumbnailWidth'] = (int)($_POST['thumbnailWidth'] ?? $newConfig['thumbnailWidth']);
    $newConfig['thumbnailHeight'] = (int)($_POST['thumbnailHeight'] ?? $newConfig['thumbnailHeight']);
    $newConfig['thumbnailQuality'] = (int)($_POST['thumbnailQuality'] ?? $newConfig['thumbnailQuality']);
    // Web-Optimized
    $newConfig['webOptimizedWidth'] = (int)($_POST['webOptimizedWidth'] ?? $newConfig['webOptimizedWidth']);
    $newConfig['webOptimizedHeight'] = (int)($_POST['webOptimizedHeight'] ?? $newConfig['webOptimizedHeight']);
    $newConfig['webOptimizedQuality'] = (int)($_POST['webOptimizedQuality'] ?? $newConfig['webOptimizedQuality']);

    // --- 8. SLIDER CONFIGURATION (Nested Fields) ---
    $sliderConfig = $newConfig['slider'] ?? [];
    $sliderConfig['enabled'] = isset($_POST['slider_enabled']);
    $sliderConfig['autoplay'] = isset($_POST['slider_autoplay']);
    $sliderConfig['autoplayDelay'] = (int)($_POST['slider_autoplayDelay'] ?? 5000);
    $sliderConfig['effect'] = $_POST['slider_effect'] ?? 'fade';
    $sliderConfig['speed'] = (int)($_POST['slider_speed'] ?? 1200);
    $sliderConfig['showNavigation'] = isset($_POST['slider_showNavigation']);
    $sliderConfig['showPagination'] = isset($_POST['slider_showPagination']);
    $sliderConfig['loop'] = isset($_POST['slider_loop']);
    $sliderConfig['slidesCount'] = (int)($_POST['slider_slidesCount'] ?? 10);
    $sliderConfig['showTitle'] = isset($_POST['slider_showTitle']);
    $sliderConfig['showDate'] = isset($_POST['slider_showDate']);
    $sliderConfig['showMessage'] = isset($_POST['slider_showMessage']);
    $sliderConfig['overlayOpacity'] = (float)($_POST['slider_overlayOpacity'] ?? 0.5);
    $newConfig['slider'] = $sliderConfig;

    if ($configManager->save($newConfig)) {
        // Clear main gallery cache to force gallery refresh
        $cacheFile = __DIR__ . '/cache/gallery_cache.json';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
        ErrorLogger::info('Configuration updated via web editor');
        $message = 'Configuration saved successfully! Gallery cache cleared.';
        // Reload config to get the newly resolved paths for display
        $config = ConfigManager::getInstance()->getAll(); 
    } else {
        $error = 'Failed to save configuration. Check file permissions on config.json.';
        ErrorLogger::error('Failed to save config via web editor');
    }
}

$currentMaxFileSizeMB = bytesToMB($config['maxFileSize'] ?? 524288000);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gallery Config Editor</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f7f7f7; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        h2 { margin-top: 30px; border-left: 5px solid #d4af37; padding-left: 10px; margin-bottom: 15px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="url"], input[type="password"], textarea, select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        input[type="checkbox"] { margin-right: 10px; transform: scale(1.2); }
        .input-group { display: flex; gap: 20px; }
        .input-group > div { flex: 1; }
        button { background: #d4af37; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 30px; font-size: 16px; font-weight: bold; }
        button:hover { opacity: 0.9; }
        .message { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .note { font-size: 0.85em; color: #666; margin-top: 5px; padding-left: 5px; }
        .slider-section { border: 1px solid #eee; padding: 15px; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>💍 Gallery Configuration Editor</h1>

        <?php if ($message): ?><div class="message success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <p class="note" style="border: 1px solid #ffcc00; padding: 10px; background-color: #fffacd;">
            🚨 **Security Warning:** The password fields (`galleryPassword` and `useHashedPassword`) are intentionally excluded from this web editor. Please manage these highly sensitive settings directly in `config.json`.
        </p>

        <form method="POST">
            
            <h2>Basic Gallery Information</h2>
            
            <label for="galleryTitle">Gallery Title</label>
            <input type="text" id="galleryTitle" name="galleryTitle" value="<?= htmlspecialchars($config['galleryTitle'] ?? '') ?>" required>

            <label for="weddingDate">Wedding Date</label>
            <input type="text" id="weddingDate" name="weddingDate" value="<?= htmlspecialchars($config['weddingDate'] ?? '') ?>">

            <label for="welcomeMessage">Welcome Message</label>
            <textarea id="welcomeMessage" name="welcomeMessage" rows="4"><?= htmlspecialchars($config['welcomeMessage'] ?? '') ?></textarea>
            
            <h2>Security & Theme</h2>
            <div class="input-group">
                <div>
                    <label>
                        <input type="checkbox" name="requirePassword" <?= ($config['requirePassword'] ?? false) ? 'checked' : '' ?>>
                        Enable Password Protection
                    </label>
                    <p class="note">Password details must be set in config.json.</p>
                </div>
                <div>
                    <label for="theme">Theme</label>
                    <select id="theme" name="theme">
                        <option value="light" <?= ($config['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= ($config['theme'] ?? 'light') === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
            </div>
            
            <h2>Directories & File Formats</h2>

            <div class="input-group">
                <div>
                    <label for="mediaDirRelative">Media Directory (Relative Path)</label>
                    <input type="text" id="mediaDirRelative" name="mediaDirRelative" value="<?= htmlspecialchars($config['mediaDirRelative'] ?? './media') ?>">
                    <p class="note">Current Absolute Path: <code><?= htmlspecialchars($config['mediaDir'] ?? 'N/A') ?></code></p>
                </div>
                <div>
                    <label for="itemsPerPage">Items Per Page (Gallery Grid)</label>
                    <input type="number" id="itemsPerPage" name="itemsPerPage" value="<?= htmlspecialchars($config['itemsPerPage'] ?? 50) ?>" min="1">
                </div>
            </div>

            <label for="imageExtensions">Allowed Image Extensions (Comma-separated)</label>
            <input type="text" id="imageExtensions" name="imageExtensions" value="<?= htmlspecialchars(implode(', ', $config['imageExtensions'] ?? [])) ?>">

            <label for="videoExtensions">Allowed Video Extensions (Comma-separated)</label>
            <input type="text" id="videoExtensions" name="videoExtensions" value="<?= htmlspecialchars(implode(', ', $config['videoExtensions'] ?? [])) ?>">

            <h2>Auto-Sync, Cache & Performance</h2>

            <div class="input-group">
                <div>
                    <label for="cacheDuration">Cache Duration (seconds)</label>
                    <input type="number" id="cacheDuration" name="cacheDuration" value="<?= htmlspecialchars($config['cacheDuration'] ?? 3600) ?>" min="60">
                </div>
                <div>
                    <label for="maxFileSize_mb">Max File Size (MB) for Processing</label>
                    <input type="number" id="maxFileSize_mb" name="maxFileSize_mb" value="<?= htmlspecialchars($currentMaxFileSizeMB) ?>" min="1">
                    <p class="note">Original Max Size: <?= htmlspecialchars(number_format($config['maxFileSize'] ?? 0)) ?> bytes</p>
                </div>
            </div>
            
            <div class="input-group">
                <div>
                    <label>
                        <input type="checkbox" name="autoSyncEnabled" <?= ($config['autoSyncEnabled'] ?? true) ? 'checked' : '' ?>>
                        Enable Auto Thumbnail Generation (on page load)
                    </label>
                </div>
                <div>
                    <label for="autoSyncLimit">Max Images to Generate Per Page Load</label>
                    <input type="number" id="autoSyncLimit" name="autoSyncLimit" value="<?= htmlspecialchars($config['autoSyncLimit'] ?? 20) ?>" min="1">
                </div>
            </div>

            <div class="input-group">
                <div>
                    <label>
                        <input type="checkbox" name="autoCleanupEnabled" <?= ($config['autoCleanupEnabled'] ?? true) ? 'checked' : '' ?>>
                        Enable Auto Cleanup of Orphaned Images
                    </label>
                </div>
                <div>
                    <label for="autoCleanupInterval">Cleanup Check Interval (seconds)</label>
                    <input type="number" id="autoCleanupInterval" name="autoCleanupInterval" value="<?= htmlspecialchars($config['autoCleanupInterval'] ?? 3600) ?>" min="60">
                </div>
                <div>
                    <label for="autoCleanupLimit">Max Images to Clean Per Check</label>
                    <input type="number" id="autoCleanupLimit" name="autoCleanupLimit" value="<?= htmlspecialchars($config['autoCleanupLimit'] ?? 5) ?>" min="1">
                </div>
            </div>

            <h2>Image Generation Settings</h2>

            <h3>Thumbnails (Gallery Grid)</h3>
            <div class="input-group">
                <div>
                    <label for="thumbnailWidth">Thumbnail Width (Max px)</label>
                    <input type="number" id="thumbnailWidth" name="thumbnailWidth" value="<?= htmlspecialchars($config['thumbnailWidth'] ?? 1200) ?>" min="100">
                </div>
                <div>
                    <label for="thumbnailHeight">Thumbnail Height (Max px)</label>
                    <input type="number" id="thumbnailHeight" name="thumbnailHeight" value="<?= htmlspecialchars($config['thumbnailHeight'] ?? 900) ?>" min="100">
                </div>
                <div>
                    <label for="thumbnailQuality">Thumbnail JPEG Quality (0-100)</label>
                    <input type="number" id="thumbnailQuality" name="thumbnailQuality" value="<?= htmlspecialchars($config['thumbnailQuality'] ?? 85) ?>" min="60" max="100">
                </div>
            </div>

            <h3>Web-Optimized (Lightbox Viewing)</h3>
            <div class="input-group">
                <div>
                    <label for="webOptimizedWidth">Web-Optimized Width (Max px)</label>
                    <input type="number" id="webOptimizedWidth" name="webOptimizedWidth" value="<?= htmlspecialchars($config['webOptimizedWidth'] ?? 2000) ?>" min="500">
                </div>
                <div>
                    <label for="webOptimizedHeight">Web-Optimized Height (Max px)</label>
                    <input type="number" id="webOptimizedHeight" name="webOptimizedHeight" value="<?= htmlspecialchars($config['webOptimizedHeight'] ?? 2000) ?>" min="500">
                </div>
                <div>
                    <label for="webOptimizedQuality">Web-Optimized JPEG Quality (0-100)</label>
                    <input type="number" id="webOptimizedQuality" name="webOptimizedQuality" value="<?= htmlspecialchars($config['webOptimizedQuality'] ?? 82) ?>" min="60" max="100">
                </div>
            </div>

            <h2>Hero Slider Configuration</h2>
            <div class="slider-section">
                
                <div class="input-group">
                    <div>
                        <label>
                            <input type="checkbox" name="slider_enabled" <?= ($config['slider']['enabled'] ?? true) ? 'checked' : '' ?>>
                            Enable Slider
                        </label>
                        <label>
                            <input type="checkbox" name="slider_autoplay" <?= ($config['slider']['autoplay'] ?? true) ? 'checked' : '' ?>>
                            Autoplay
                        </label>
                        <label>
                            <input type="checkbox" name="slider_loop" <?= ($config['slider']['loop'] ?? true) ? 'checked' : '' ?>>
                            Loop Slides
                        </label>
                    </div>
                    <div>
                        <label for="slider_autoplayDelay">Autoplay Delay (ms)</label>
                        <input type="number" id="slider_autoplayDelay" name="slider_autoplayDelay" value="<?= htmlspecialchars($config['slider']['autoplayDelay'] ?? 5000) ?>" min="1000">
                    </div>
                    <div>
                        <label for="slider_speed">Transition Speed (ms)</label>
                        <input type="number" id="slider_speed" name="slider_speed" value="<?= htmlspecialchars($config['slider']['speed'] ?? 1200) ?>" min="100">
                    </div>
                    <div>
                        <label for="slider_slidesCount">Number of Slides</label>
                        <input type="number" id="slider_slidesCount" name="slider_slidesCount" value="<?= htmlspecialchars($config['slider']['slidesCount'] ?? 10) ?>" min="1" max="100">
                    </div>
                </div>

                <div class="input-group" style="margin-top: 15px;">
                    <div>
                        <label for="slider_effect">Effect</label>
                        <select id="slider_effect" name="slider_effect">
                            <option value="fade" <?= ($config['slider']['effect'] ?? 'fade') === 'fade' ? 'selected' : '' ?>>fade</option>
                            <option value="slide" <?= ($config['slider']['effect'] ?? 'fade') === 'slide' ? 'selected' : '' ?>>slide</option>
                            <option value="cube" <?= ($config['slider']['effect'] ?? 'fade') === 'cube' ? 'selected' : '' ?>>cube</option>
                            <option value="coverflow" <?= ($config['slider']['effect'] ?? 'fade') === 'coverflow' ? 'selected' : '' ?>>coverflow</option>
                            <option value="flip" <?= ($config['slider']['effect'] ?? 'fade') === 'flip' ? 'selected' : '' ?>>flip</option>
                        </select>
                    </div>
                    <div>
                        <label for="slider_overlayOpacity">Overlay Opacity (0.0 - 1.0)</label>
                        <input type="number" id="slider_overlayOpacity" name="slider_overlayOpacity" value="<?= htmlspecialchars($config['slider']['overlayOpacity'] ?? 0.5) ?>" step="0.1" min="0" max="1">
                    </div>
                </div>
                
                <h3 style="margin-top: 15px; font-size: 1.1em;">Visibility</h3>
                <div class="input-group">
                    <div>
                        <label>
                            <input type="checkbox" name="slider_showNavigation" <?= ($config['slider']['showNavigation'] ?? false) ? 'checked' : '' ?>>
                            Show Navigation Arrows
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="slider_showPagination" <?= ($config['slider']['showPagination'] ?? true) ? 'checked' : '' ?>>
                            Show Pagination Dots
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="slider_showTitle" <?= ($config['slider']['showTitle'] ?? true) ? 'checked' : '' ?>>
                            Show Gallery Title
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="slider_showDate" <?= ($config['slider']['showDate'] ?? true) ? 'checked' : '' ?>>
                            Show Wedding Date
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="slider_showMessage" <?= ($config['slider']['showMessage'] ?? true) ? 'checked' : '' ?>>
                            Show Welcome Message
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit">💾 Save All Configuration</button>
        </form>
    </div>
</body>
</html>