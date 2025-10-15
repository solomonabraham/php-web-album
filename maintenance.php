<?php
/**
 * Wedding Gallery - Maintenance Tool
 * Cleans up orphaned thumbnails and syncs new images
 * Run manually or via cron job
 */

// Prevent direct access from web (optional security)
// Uncomment to require command line only:
// if (php_sapi_name() !== 'cli') die('CLI only');

set_time_limit(0); // No timeout
ini_set('memory_limit', '1G');

require_once __DIR__ . '/ErrorLogger.php';
$config = include __DIR__ . "/config.php";

$mediaDir = $config['mediaDir'];
$thumbDir = $mediaDir . '/thumbnails';
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
output("Wedding Gallery Maintenance Tool", 'info');
output("===========================================", 'info');
output("");

// ============================================
// 1. SCAN MEDIA DIRECTORY
// ============================================
output("Step 1: Scanning media directory...", 'info');

$mediaFiles = [];
$thumbFolderName = basename($thumbDir);

if (!is_dir($mediaDir)) {
    output("ERROR: Media directory not found: $mediaDir", 'error');
    exit(1);
}

$subfolders = glob($mediaDir . '/*', GLOB_ONLYDIR);
if ($subfolders === false) $subfolders = [];
$subfolders = array_filter($subfolders, fn($f) => basename($f) !== $thumbFolderName && !str_starts_with(basename($f), '.'));

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
// 2. SCAN THUMBNAIL DIRECTORY
// ============================================
output("Step 2: Scanning thumbnail directory...", 'info');

$existingThumbs = [];

if (!is_dir($thumbDir)) {
    mkdir($thumbDir, 0755, true);
    output("Created thumbnail directory", 'success');
} else {
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
}

output("Found " . count($existingThumbs) . " existing thumbnails", 'success');
output("");

// ============================================
// 3. FIND ORPHANED THUMBNAILS
// ============================================
output("Step 3: Finding orphaned thumbnails...", 'info');

$orphanedThumbs = [];

foreach ($existingThumbs as $key => $thumbPath) {
    list($albumName, $thumbFile) = explode('/', $key, 2);
    
    // Check if original media file exists
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

if (count($orphanedThumbs) > 0) {
    output("Found " . count($orphanedThumbs) . " orphaned thumbnails", 'warning');
    
    // Delete orphaned thumbnails
    $deleted = 0;
    foreach ($orphanedThumbs as $thumbPath) {
        if (@unlink($thumbPath)) {
            $deleted++;
        }
    }
    
    output("Deleted $deleted orphaned thumbnails", 'success');
    
    // Clean up empty album folders
    $thumbSubfolders = glob($thumbDir . '/*', GLOB_ONLYDIR);
    foreach ($thumbSubfolders as $thumbAlbumPath) {
        $files = @scandir($thumbAlbumPath);
        if ($files && count($files) === 2) { // Only . and ..
            @rmdir($thumbAlbumPath);
            output("Removed empty folder: " . basename($thumbAlbumPath), 'success');
        }
    }
} else {
    output("No orphaned thumbnails found", 'success');
}

output("");

// ============================================
// 4. FIND MISSING THUMBNAILS
// ============================================
output("Step 4: Finding missing thumbnails...", 'info');

$missingThumbs = [];

foreach ($mediaFiles as $key => $mediaInfo) {
    $thumbKey = $mediaInfo['album'] . '/' . $mediaInfo['thumbFile'];
    
    if (!isset($existingThumbs[$thumbKey])) {
        $missingThumbs[] = $mediaInfo;
    }
}

if (count($missingThumbs) > 0) {
    output("Found " . count($missingThumbs) . " missing thumbnails", 'warning');
    output("Generating thumbnails (this may take a while)...", 'info');
    
    // Generate missing thumbnails
    $generated = 0;
    $errors = 0;
    
    foreach ($missingThumbs as $index => $mediaInfo) {
        $albumName = $mediaInfo['album'];
        $thumbAlbumDir = "$thumbDir/$albumName";
        
        if (!is_dir($thumbAlbumDir)) {
            mkdir($thumbAlbumDir, 0755, true);
        }
        
        $thumbPath = "$thumbAlbumDir/{$mediaInfo['thumbFile']}";
        
        // Progress indicator
        if ($index % 10 === 0) {
            output("Progress: " . ($index + 1) . "/" . count($missingThumbs), 'info');
        }
        
        if ($mediaInfo['type'] === 'image') {
            if (createThumbnail($mediaInfo['path'], $thumbPath)) {
                $generated++;
            } else {
                $errors++;
                output("Failed: {$mediaInfo['album']}/{$mediaInfo['file']}", 'error');
            }
        } elseif ($mediaInfo['type'] === 'video') {
            if (createVideoThumbnail($mediaInfo['path'], $thumbPath)) {
                $generated++;
            } else {
                $errors++;
                output("Failed: {$mediaInfo['album']}/{$mediaInfo['file']}", 'error');
            }
        }
    }
    
    output("Generated $generated thumbnails", 'success');
    if ($errors > 0) {
        output("Failed to generate $errors thumbnails", 'error');
    }
} else {
    output("All thumbnails are up to date", 'success');
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
output("Orphaned cleaned: " . count($orphanedThumbs), 'info');
output("Generated: " . ($generated ?? 0), 'info');
output("");

ErrorLogger::info('Maintenance completed', [
    'media_files' => count($mediaFiles),
    'orphaned_cleaned' => count($orphanedThumbs),
    'generated' => $generated ?? 0
]);

// ============================================
// HELPER FUNCTIONS
// ============================================

function createThumbnail($source, $dest, $maxWidth = 1200, $maxHeight = 900) {
    if (!file_exists($source)) return false;
    
    try {
        $imageInfo = @getimagesize($source);
        if (!$imageInfo) return false;
        
        list($width, $height, $type) = $imageInfo;
        if ($width == 0 || $height == 0) return false;
        
        $ratio = min($maxWidth/$width, $maxHeight/$height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        if ($thumb === false) return false;
        
        imagesetinterpolation($thumb, IMG_BICUBIC_FIXED);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $img = @imagecreatefromgif($source);
                break;
            case IMAGETYPE_WEBP:
                $img = @imagecreatefromwebp($source);
                break;
            default:
                imagedestroy($thumb);
                return false;
        }
        
        if ($img === false) {
            imagedestroy($thumb);
            return false;
        }
        
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $result = imagejpeg($thumb, $dest, 85);
        
        imagedestroy($img);
        imagedestroy($thumb);
        
        return $result;
        
    } catch (Exception $e) {
        return false;
    }
}

function createVideoThumbnail($source, $dest) {
    if (!file_exists($source)) return false;
    
    $ffmpegPath = trim(shell_exec('which ffmpeg 2>/dev/null'));
    if (empty($ffmpegPath)) return false;
    
    $command = sprintf(
        '%s -i %s -ss 00:00:01.000 -vframes 1 -vf scale=1200:-1 -q:v 2 -y %s 2>&1',
        escapeshellarg($ffmpegPath),
        escapeshellarg($source),
        escapeshellarg($dest)
    );
    
    shell_exec($command);
    
    return file_exists($dest) && filesize($dest) > 0;
}

output("Done! You can now refresh your gallery.", 'success');
?>