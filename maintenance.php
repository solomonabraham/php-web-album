<?php
/**
 * Wedding Gallery - Maintenance Tool
 * Cleans up orphaned images and syncs new images
 * Now supports web-optimized images for faster lightbox loading
 * Run manually or via cron job
 * * MODIFIED: Now uses ImageProcessor.php for all image generation.
 */

set_time_limit(0); // No timeout
ini_set('memory_limit', '1G');

require_once __DIR__ . '/ErrorLogger.php';
require_once __DIR__ . '/ConfigManager.php';
require_once __DIR__ . '/ImageProcessor.php'; // NEW: Include ImageProcessor

$configManager = ConfigManager::getInstance();
$config = $configManager->getAll();

// NEW: Instantiate ImageProcessor
$imageProcessor = new ImageProcessor($config);

$mediaDir = $config['mediaDir']; // This is the absolute path resolved by ConfigManager
$thumbDir = $mediaDir . '/thumbnails';
$webOptimizedDir = $mediaDir . '/web-optimized';
$imageExtensions = $config['imageExtensions'];
$videoExtensions = $config['videoExtensions'];

// Output helper
function output($message, $type = 'info') {
    $colors = [
        'info' => "\033[0;36m",    // Cyan
        'success' => "\033[0;32m", // Green
        'warning' => "\033[0;33m", // Yellow
        'error' => "\033[0;31m",   // Red
        'reset' => "\033[0m"
    ];
    
    $isCli = php_sapi_name() === 'cli';
    $color = $isCli ? ($colors[$type] ?? $colors['info']) : '';
    $reset = $isCli ? $colors['reset'] : '';
    
    echo $color . $message . $reset . PHP_EOL;
}

output("===========================================", 'info');
output("Wedding Gallery Maintenance Tool v2.3", 'info');
output("===========================================", 'info');
output("");

// ============================================
// 1. SCAN MEDIA DIRECTORY
// ============================================
output("Step 1: Scanning media directory...", 'info');

$mediaFiles = [];
$thumbFolderName = basename($thumbDir);
$webOptimizedFolderName = basename($webOptimizedDir);

if (!is_dir($mediaDir)) {
    output("ERROR: Media directory not found: $mediaDir", 'error');
    exit(1);
}

$subfolders = glob($mediaDir . '/*', GLOB_ONLYDIR);
if ($subfolders === false) $subfolders = [];
$subfolders = array_filter($subfolders, fn($f) => 
    !in_array(basename($f), [$thumbFolderName, $webOptimizedFolderName]) && 
    !str_starts_with(basename($f), '.')
);

foreach ($subfolders as $albumPath) {
    $albumName = basename($albumPath);
    $files = @scandir($albumPath);
    if ($files === false) continue;
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || str_starts_with($file, '.')) continue;
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $filePath = "$albumPath/$file";
        
        if (!file_exists($filePath)) continue;
        
        $isImage = in_array($ext, $imageExtensions);
        $isVideo = in_array($ext, $videoExtensions);
        
        if ($isImage || $isVideo) {
            $thumbFile = $isVideo ? pathinfo($file, PATHINFO_FILENAME) . '.jpg' : $file;
            $mediaFiles["$albumName/$file"] = [
                'album' => $albumName,
                'file' => $file,
                'thumbFile' => $thumbFile,
                'type' => $isVideo ? 'video' : 'image',
                'path' => $filePath
            ];
        }
    }
}

output("Found " . count($mediaFiles) . " media files in " . count($subfolders) . " albums", 'success');
output("");

// ============================================
// 2. SCAN IMAGE DIRECTORIES
// ============================================
output("Step 2: Scanning thumbnail and web-optimized directories...", 'info');

$existingThumbs = [];
$existingWebOptimized = [];

// Scan thumbnails
$thumbSubfolders = glob($thumbDir . '/*', GLOB_ONLYDIR);
if ($thumbSubfolders === false) $thumbSubfolders = [];

foreach ($thumbSubfolders as $thumbAlbumPath) {
    $albumName = basename($thumbAlbumPath);
    $thumbFiles = @scandir($thumbAlbumPath);
    if ($thumbFiles === false) continue;
    
    foreach ($thumbFiles as $thumbFile) {
        if ($thumbFile === '.' || $thumbFile === '..') continue;
        $existingThumbs["$albumName/$thumbFile"] = "$thumbAlbumPath/$thumbFile";
    }
}

// Scan web-optimized
$webOptimizedSubfolders = glob($webOptimizedDir . '/*', GLOB_ONLYDIR);
if ($webOptimizedSubfolders === false) $webOptimizedSubfolders = [];

foreach ($webOptimizedSubfolders as $webOptimizedAlbumPath) {
    $albumName = basename($webOptimizedAlbumPath);
    $webOptimizedFiles = @scandir($webOptimizedAlbumPath);
    if ($webOptimizedFiles === false) continue;
    
    foreach ($webOptimizedFiles as $webOptimizedFile) {
        if ($webOptimizedFile === '.' || $webOptimizedFile === '..') continue;
        $existingWebOptimized["$albumName/$webOptimizedFile"] = "$webOptimizedAlbumPath/$webOptimizedFile";
    }
}

output("Found " . count($existingThumbs) . " thumbnails", 'success');
output("Found " . count($existingWebOptimized) . " web-optimized images", 'success');
output("");

// ============================================
// 3. FIND ORPHANED IMAGES
// ============================================
output("Step 3: Finding orphaned images...", 'info');

$orphanedThumbs = [];
$orphanedWebOptimized = [];

// Check thumbnails
foreach ($existingThumbs as $key => $thumbPath) {
    list($albumName, $thumbFile) = explode('/', $key, 2);
    
    $foundOriginal = false;
    foreach ($mediaFiles as $mediaKey => $mediaInfo) {
        if ($mediaInfo['album'] === $albumName && $mediaInfo['thumbFile'] === $thumbFile) {
            $foundOriginal = true;
            break;
        }
    }
    
    if (!$foundOriginal) {
        $orphanedThumbs[] = $thumbPath;
    }
}

// Check web-optimized
foreach ($existingWebOptimized as $key => $webOptimizedPath) {
    list($albumName, $webOptimizedFile) = explode('/', $key, 2);
    
    $foundOriginal = false;
    foreach ($mediaFiles as $mediaKey => $mediaInfo) {
        if ($mediaInfo['album'] === $albumName && $mediaInfo['thumbFile'] === $webOptimizedFile) {
            $foundOriginal = true;
            break;
        }
    }
    
    if (!$foundOriginal) {
        $orphanedWebOptimized[] = $webOptimizedPath;
    }
}

$totalOrphaned = count($orphanedThumbs) + count($orphanedWebOptimized);

if ($totalOrphaned > 0) {
    output("Found $totalOrphaned orphaned images (" . count($orphanedThumbs) . " thumbnails, " . count($orphanedWebOptimized) . " web-optimized)", 'warning');
    
    // Delete orphaned thumbnails
    $deleted = 0;
    foreach ($orphanedThumbs as $thumbPath) {
        if (@unlink($thumbPath)) {
            $deleted++;
        }
    }
    
    // Delete orphaned web-optimized
    foreach ($orphanedWebOptimized as $webOptimizedPath) {
        if (@unlink($webOptimizedPath)) {
            $deleted++;
        }
    }
    
    output("Deleted $deleted orphaned images", 'success');
    
    // Clean up empty album folders
    foreach ([$thumbDir, $webOptimizedDir] as $dir) {
        $subfolders = glob($dir . '/*', GLOB_ONLYDIR);
        foreach ($subfolders as $albumPath) {
            $files = @scandir($albumPath);
            if ($files && count($files) === 2) { // Only . and ..
                @rmdir($albumPath);
                output("Removed empty folder: " . basename($dir) . "/" . basename($albumPath), 'success');
            }
        }
    }
} else {
    output("No orphaned images found", 'success');
}

output("");

// ============================================
// 4. FIND MISSING IMAGES
// ============================================
output("Step 4: Finding missing thumbnails and web-optimized images...", 'info');

$missingThumbs = [];
$missingWebOptimized = [];

foreach ($mediaFiles as $key => $mediaInfo) {
    $imageKey = $mediaInfo['album'] . '/' . $mediaInfo['thumbFile'];
    
    // Check if thumbnail exists
    $thumbAlbumDir = "$thumbDir/{$mediaInfo['album']}";
    $thumbPath = "$thumbAlbumDir/{$mediaInfo['thumbFile']}";
    if (!file_exists($thumbPath) || @filemtime($thumbPath) < @filemtime($mediaInfo['path'])) {
        $missingThumbs[] = $mediaInfo;
    }
    
    // Check if web-optimized exists
    $webOptimizedAlbumDir = "$webOptimizedDir/{$mediaInfo['album']}";
    $webOptimizedPath = "$webOptimizedAlbumDir/{$mediaInfo['thumbFile']}";
    if (!file_exists($webOptimizedPath) || @filemtime($webOptimizedPath) < @filemtime($mediaInfo['path'])) {
        $missingWebOptimized[] = $mediaInfo;
    }
}

$totalMissing = count($missingThumbs) + count($missingWebOptimized);

if ($totalMissing > 0) {
    output("Found $totalMissing missing images (" . count($missingThumbs) . " thumbnails, " . count($missingWebOptimized) . " web-optimized)", 'warning');
    output("Generating images (this may take a while)...", 'info');
    
    $generatedThumbs = 0;
    $generatedWebOptimized = 0;
    $errors = 0;
    
    // Generate missing thumbnails
    foreach ($missingThumbs as $index => $mediaInfo) {
        $albumName = $mediaInfo['album'];
        $thumbAlbumDir = "$thumbDir/$albumName";
        
        if (!is_dir($thumbAlbumDir)) {
            @mkdir($thumbAlbumDir, 0755, true);
        }
        
        $thumbPath = "$thumbAlbumDir/{$mediaInfo['thumbFile']}";
        
        if ($index % 10 === 0) {
            output("Thumbnails: " . ($index + 1) . "/" . count($missingThumbs), 'info');
        }
        
        if ($mediaInfo['type'] === 'image') {
            if ($imageProcessor->createThumbnail($mediaInfo['path'], $thumbPath)) { // Use ImageProcessor
                $generatedThumbs++;
            } else {
                $errors++;
                output("Failed thumbnail: {$mediaInfo['album']}/{$mediaInfo['file']}", 'error');
            }
        } elseif ($mediaInfo['type'] === 'video') {
            if ($imageProcessor->createVideoThumbnail($mediaInfo['path'], $thumbPath)) { // Use ImageProcessor
                $generatedThumbs++;
            } else {
                $errors++;
                output("Failed thumbnail: {$mediaInfo['album']}/{$mediaInfo['file']}", 'error');
            }
        }
    }
    
    // Generate missing web-optimized
    foreach ($missingWebOptimized as $index => $mediaInfo) {
        $albumName = $mediaInfo['album'];
        $webOptimizedAlbumDir = "$webOptimizedDir/$albumName";
        
        if (!is_dir($webOptimizedAlbumDir)) {
            @mkdir($webOptimizedAlbumDir, 0755, true);
        }
        
        $webOptimizedPath = "$webOptimizedAlbumDir/{$mediaInfo['thumbFile']}";
        
        if ($index % 10 === 0) {
            output("Web-optimized: " . ($index + 1) . "/" . count($missingWebOptimized), 'info');
        }
        
        if ($mediaInfo['type'] === 'image') {
            if ($imageProcessor->createWebOptimizedImage($mediaInfo['path'], $webOptimizedPath)) { // Use ImageProcessor
                $generatedWebOptimized++;
            } else {
                $errors++;
                output("Failed web-optimized: {$mediaInfo['album']}/{$mediaInfo['file']}", 'error');
            }
        } elseif ($mediaInfo['type'] === 'video') {
            // For videos, copy thumbnail as web-optimized
            $thumbPath = "$thumbDir/$albumName/{$mediaInfo['thumbFile']}";
            if (file_exists($thumbPath)) {
                if (@copy($thumbPath, $webOptimizedPath)) {
                    $generatedWebOptimized++;
                } else {
                    $errors++;
                }
            }
        }
    }
    
    output("Generated $generatedThumbs thumbnails", 'success');
    output("Generated $generatedWebOptimized web-optimized images", 'success');
    if ($errors > 0) {
        output("Failed to generate $errors images", 'error');
    }
} else {
    output("All images are up to date", 'success');
}

output("");

// ============================================
// 5. CLEAR CACHE
// ============================================
output("Step 5: Clearing cache...", 'info');

$cacheFile = __DIR__ . '/cache/gallery_cache.json';
if (file_exists($cacheFile)) {
    @unlink($cacheFile);
    output("Cache cleared successfully", 'success');
} else {
    output("No cache file found", 'info');
}

output("");

// ============================================
// 6. SUMMARY
// ============================================
output("===========================================", 'info');
output("Maintenance Complete!", 'success');
output("===========================================", 'info');
output("Media files: " . count($mediaFiles), 'info');
output("Thumbnails: " . count($existingThumbs), 'info');
output("Web-optimized: " . count($existingWebOptimized), 'info');
output("Orphaned cleaned: $totalOrphaned", 'info');
output("Generated thumbnails: " . ($generatedThumbs ?? 0), 'info');
output("Generated web-optimized: " . ($generatedWebOptimized ?? 0), 'info');
output("");

ErrorLogger::info('Maintenance completed', [
    'media_files' => count($mediaFiles),
    'orphaned_cleaned' => $totalOrphaned,
    'generated_thumbs' => $generatedThumbs ?? 0,
    'generated_web_optimized' => $generatedWebOptimized ?? 0
]);
output("Done! You can now refresh your gallery.", 'success');
output("");
output("Note: The gallery now uses two image versions:", 'info');
output("  - Thumbnails: Fast loading for gallery grid", 'info');
output("  - Web-optimized: Good quality for lightbox viewing", 'info');
output("  - Originals: Full quality for downloads", 'info');
?>