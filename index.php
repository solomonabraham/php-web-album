<?php
/**
 * Luxe Wedding Gallery - ULTRA OPTIMIZED with LightGallery
 * Version 2.1 - Enhanced with LightGallery & Native Downloads
 */

session_start();

require_once __DIR__ . '/ErrorLogger.php';

$config = include __DIR__ . "/config.php";
ini_set('memory_limit', '512M');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ===============================================
// AGGRESSIVE CACHING HEADERS FOR PERFORMANCE
// ===============================================
header("Cache-Control: public, max-age=86400, must-revalidate");
header("Expires: " . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

ErrorLogger::info('Gallery accessed', ['user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']);

// --- PASSWORD PROTECTION ---
if ($config['requirePassword']) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt'] = time();
    }
    
    if ($_SESSION['login_attempts'] >= 5) {
        $timeSinceLastAttempt = time() - $_SESSION['last_attempt'];
        if ($timeSinceLastAttempt < 300) {
            $remainingTime = 300 - $timeSinceLastAttempt;
            $error = "Too many attempts. Please wait " . ceil($remainingTime / 60) . " minute(s).";
        } else {
            $_SESSION['login_attempts'] = 0;
        }
    }
    
    if (isset($_POST['password']) && !isset($error)) {
        $passwordMatch = (isset($config['useHashedPassword']) && $config['useHashedPassword'])
            ? password_verify($_POST['password'], $config['galleryPassword'])
            : $_POST['password'] === $config['galleryPassword'];
            
        if ($passwordMatch) {
            session_regenerate_id(true);
            $_SESSION['gallery_auth'] = true;
            $_SESSION['login_attempts'] = 0;
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
            $error = "Incorrect password";
            ErrorLogger::warning('Failed login attempt', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        }
    }
    
    if (empty($_SESSION['gallery_auth'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Gallery Access</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Montserrat', sans-serif;
                    background: linear-gradient(135deg, #fdfbf7 0%, #f5ebe0 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .login-box {
                    background: white;
                    padding: 60px 50px;
                    border-radius: 2px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.08);
                    max-width: 440px;
                    width: 90%;
                }
                h2 {
                    font-family: 'Cormorant Garamond', serif;
                    font-size: 2.2rem;
                    font-weight: 300;
                    color: #1a1a1a;
                    text-align: center;
                    margin-bottom: 40px;
                    letter-spacing: 3px;
                    text-transform: uppercase;
                }
                input[type="password"] {
                    width: 100%;
                    padding: 16px 20px;
                    border: 1px solid #e8e8e8;
                    border-radius: 2px;
                    font-size: 0.95rem;
                    margin-bottom: 20px;
                }
                button {
                    width: 100%;
                    padding: 16px 20px;
                    background: linear-gradient(135deg, #d4af37 0%, #c19d2e 100%);
                    color: white;
                    border: none;
                    border-radius: 2px;
                    font-size: 0.9rem;
                    font-weight: 500;
                    letter-spacing: 2px;
                    text-transform: uppercase;
                    cursor: pointer;
                }
                .error {
                    color: #c9302c;
                    font-size: 0.85rem;
                    text-align: center;
                    margin-top: 20px;
                    padding: 12px;
                    background: rgba(201,48,44,0.05);
                    border-radius: 2px;
                }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>Private Gallery</h2>
                <form method="post">
                    <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
                    <button type="submit">Enter Gallery</button>
                </form>
                <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// --- CONFIG & CONSTANTS ---
$galleryTitle = $config['galleryTitle'];
$welcomeMessage = $config['welcomeMessage'];
$weddingDate = $config['weddingDate'];
$mediaDir = $config['mediaDir'];
$thumbDir = $mediaDir . '/thumbnails';
$imageExtensions = $config['imageExtensions'];
$videoExtensions = $config['videoExtensions'];
$galleryError = '';
$theme = $config['theme'] ?? 'light';

// Load slider configuration from config.php
$sliderConfig = $config['slider'] ?? [
    'enabled' => true,
    'autoplay' => true,
    'autoplayDelay' => 5000,
    'effect' => 'fade',
    'speed' => 1200,
    'showNavigation' => true,
    'showPagination' => true,
    'loop' => true,
    'slidesCount' => 10,
    'showTitle' => true,
    'showDate' => true,
    'showMessage' => true,
    'overlayOpacity' => 0.5,
];

define('MAX_FILE_SIZE', $config['maxFileSize']);
define('THUMB_WIDTH', $config['thumbnailWidth']);
define('THUMB_HEIGHT', $config['thumbnailHeight']);
define('THUMB_QUALITY', $config['thumbnailQuality']);
define('CACHE_FILE', __DIR__ . '/cache/gallery_cache.json');
define('CACHE_DURATION', $config['cacheDuration']);
define('ITEMS_PER_PAGE', $config['itemsPerPage']);

// ===============================================
// CACHE SYSTEM FOR ULTRA-FAST LOADING
// ===============================================
function getCacheDir() {
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    return $cacheDir;
}

function getCachedGalleryData() {
    if (!file_exists(CACHE_FILE)) return null;
    
    $cacheAge = time() - filemtime(CACHE_FILE);
    if ($cacheAge > CACHE_DURATION) return null;
    
    $data = json_decode(file_get_contents(CACHE_FILE), true);
    return $data ?: null;
}

function saveCachedGalleryData($data) {
    getCacheDir();
    file_put_contents(CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// --- HELPER FUNCTIONS ---
function isValidImageFile($filePath, $allowedExtensions) {
    if (!file_exists($filePath)) return false;
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) return false;
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    return in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
}

function isValidVideoFile($filePath, $allowedExtensions) {
    if (!file_exists($filePath)) return false;
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) return false;
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    return str_starts_with($mimeType, 'video/');
}

// Auto-fix thumbnail directory
if (!is_dir($thumbDir)) {
    mkdir($thumbDir, 0755, true);
}

if (!is_writable($thumbDir)) {
    @chmod($thumbDir, 0755);
}

if (!is_writable($thumbDir)) {
    $galleryError = "Thumbnail directory is not writable. Please run: chmod 755 " . $thumbDir;
    ErrorLogger::critical("Thumbnail directory not writable", ['path' => $thumbDir]);
}

// ===============================================
// OPTIMIZED THUMBNAIL CREATION
// ===============================================
function createThumbnail($source, $dest, $maxWidth = THUMB_WIDTH, $maxHeight = THUMB_HEIGHT) {
    if (!file_exists($source)) return false;
    
    if (file_exists($dest) && filemtime($dest) >= filemtime($source)) {
        return $dest;
    }
    
    $fileSize = @filesize($source);
    if ($fileSize === false || $fileSize > MAX_FILE_SIZE) return false;
    
    if (!file_exists(dirname($dest))) {
        if (!mkdir(dirname($dest), 0755, true)) return false;
    }
    
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
        
        $result = imagejpeg($thumb, $dest, THUMB_QUALITY);
        
        imagedestroy($img);
        imagedestroy($thumb);
        
        return $result ? $dest : false;
        
    } catch (Exception $e) {
        ErrorLogger::error("Thumbnail error: " . $e->getMessage());
        return false;
    }
}

function createVideoThumbnail($source, $dest) {
    if (!file_exists($source)) return false;
    
    if (file_exists($dest) && filemtime($dest) >= filemtime($source)) {
        return $dest;
    }
    
    if (!file_exists(dirname($dest))) {
        if (!mkdir(dirname($dest), 0755, true)) return false;
    }
    
    $ffmpegPath = trim(shell_exec('which ffmpeg 2>/dev/null'));
    if (empty($ffmpegPath)) {
        ErrorLogger::warning("FFmpeg not found - cannot create video thumbnails");
        return false;
    }
    
    $command = sprintf(
        '%s -i %s -ss 00:00:01.000 -vframes 1 -vf scale=1200:-1 -q:v 2 -y %s 2>&1',
        escapeshellarg($ffmpegPath),
        escapeshellarg($source),
        escapeshellarg($dest)
    );
    
    $output = shell_exec($command);
    
    if (file_exists($dest) && filesize($dest) > 0) {
        return $dest;
    }
    
    ErrorLogger::error("Video thumbnail failed", ['source' => $source, 'output' => $output]);
    return false;
}

// ===============================================
// OPTIMIZED FILE SCANNING WITH CACHING
// ===============================================
$cachedData = getCachedGalleryData();

if ($cachedData && isset($cachedData['allFiles'], $cachedData['albums'])) {
    $allFiles = $cachedData['allFiles'];
    $albums = $cachedData['albums'];
} else {
    $allFiles = [];
    $albums = [];
    $thumbFolderName = basename($thumbDir);

    if (is_dir($mediaDir)) {
        $subfolders = glob($mediaDir . '/*', GLOB_ONLYDIR);
        if ($subfolders !== false) {
            $subfolders = array_filter($subfolders, fn($f) => basename($f) !== $thumbFolderName && !str_starts_with(basename($f), '.'));
            
            foreach ($subfolders as $albumPath) {
                $albumName = basename($albumPath);
                $albums[] = $albumName;
                
                $files = @scandir($albumPath);
                if ($files === false) continue;
                
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || str_starts_with($file, '.')) continue;
                    
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $filePath = "$albumPath/$file";
                    
                    $fileSize = @filesize($filePath);
                    if ($fileSize === false) continue;
                    
                    $isImage = in_array($ext, $imageExtensions) && isValidImageFile($filePath, $imageExtensions);
                    $isVideo = in_array($ext, $videoExtensions) && isValidVideoFile($filePath, $videoExtensions);
                    
                    if ($isImage && $fileSize > MAX_FILE_SIZE) continue;
                    
                    if ($isImage || $isVideo) {
                        $thumbAlbumDir = "$thumbDir/$albumName";
                        $thumbFile = $isVideo ? pathinfo($file, PATHINFO_FILENAME) . '.jpg' : $file;
                        $thumbPath = "$thumbAlbumDir/$thumbFile";
                        
                        $allFiles[] = [
                            'path'  => "media/$albumName/" . urlencode($file),
                            'thumb' => "media/thumbnails/$albumName/" . urlencode($thumbFile),
                            'album' => $albumName,
                            'type'  => $isVideo ? 'video' : 'image',
                            'filename' => $file,
                            'mtime' => filemtime($filePath),
                            'thumbExists' => file_exists($thumbPath)
                        ];
                    }
                }
            }
        }
    } else {
        $galleryError = "Media directory not found.";
        ErrorLogger::critical("Media directory not found", ['path' => $mediaDir]);
    }
    
    saveCachedGalleryData([
        'allFiles' => $allFiles,
        'albums' => $albums,
        'generated' => time()
    ]);
}

// Background thumbnail generation and cleanup
$autoSyncEnabled = $config['autoSyncEnabled'] ?? true;
$autoSyncLimit = $config['autoSyncLimit'] ?? 20;
$autoCleanupEnabled = $config['autoCleanupEnabled'] ?? true;
$autoCleanupInterval = $config['autoCleanupInterval'] ?? 3600;
$autoCleanupLimit = $config['autoCleanupLimit'] ?? 5;

// Auto-generate thumbnails
if ($autoSyncEnabled) {
    $missingThumbs = array_filter($allFiles, fn($f) => !($f['thumbExists'] ?? true));
    $generatedCount = 0;
    
    // Generate thumbnails (prevent timeout with limit)
    if (count($missingThumbs) > 0 && count($missingThumbs) < 100) {
        foreach ($missingThumbs as &$file) {
            if ($generatedCount >= $autoSyncLimit) break;
            
            $sourcePath = str_replace('media/', $mediaDir . '/', $file['path']);
            $sourcePath = urldecode($sourcePath);
            $thumbPath = str_replace('media/thumbnails/', $thumbDir . '/', $file['thumb']);
            $thumbPath = urldecode($thumbPath);
            
            if ($file['type'] === 'image') {
                if (createThumbnail($sourcePath, $thumbPath)) {
                    $file['thumbExists'] = true;
                    $generatedCount++;
                }
            } elseif ($file['type'] === 'video') {
                if (createVideoThumbnail($sourcePath, $thumbPath)) {
                    $file['thumbExists'] = true;
                    $generatedCount++;
                }
            }
        }
        
        if ($generatedCount > 0) {
            saveCachedGalleryData([
                'allFiles' => $allFiles,
                'albums' => $albums,
                'generated' => time()
            ]);
            ErrorLogger::info("Auto-generated $generatedCount thumbnails");
        }
    }
}

// Auto-cleanup orphaned thumbnails
if ($autoCleanupEnabled) {
    $cleanupFile = __DIR__ . '/cache/last_cleanup.txt';
    $shouldCleanup = false;
    
    if (!file_exists($cleanupFile)) {
        $shouldCleanup = true;
    } else {
        $lastCleanup = (int)@file_get_contents($cleanupFile);
        if (time() - $lastCleanup > $autoCleanupInterval) {
            $shouldCleanup = true;
        }
    }
    
    if ($shouldCleanup) {
        // Quick orphaned thumbnail check
        $cleanedCount = cleanOrphanedThumbnails($allFiles, $thumbDir, $autoCleanupLimit);
        if ($cleanedCount >= 0) {
            @file_put_contents($cleanupFile, time());
            if ($cleanedCount > 0) {
                ErrorLogger::info("Auto-cleaned $cleanedCount orphaned thumbnails");
            }
        }
    }
}

// Function to clean orphaned thumbnails (lightweight)
function cleanOrphanedThumbnails($allFiles, $thumbDir, $maxDelete = 5) {
    global $mediaDir;
    
    if (!is_dir($thumbDir)) return 0;
    
    $validThumbs = [];
    foreach ($allFiles as $file) {
        $thumbKey = $file['album'] . '/' . basename(urldecode($file['thumb']));
        $validThumbs[$thumbKey] = true;
    }
    
    $deleted = 0;
    $thumbSubfolders = glob($thumbDir . '/*', GLOB_ONLYDIR);
    if ($thumbSubfolders === false) return 0;
    
    foreach ($thumbSubfolders as $thumbAlbumPath) {
        if ($deleted >= $maxDelete) break;
        
        $albumName = basename($thumbAlbumPath);
        $thumbFiles = @scandir($thumbAlbumPath);
        if ($thumbFiles === false) continue;
        
        foreach ($thumbFiles as $thumbFile) {
            if ($deleted >= $maxDelete) break;
            if ($thumbFile === '.' || $thumbFile === '..') continue;
            
            $thumbKey = "$albumName/$thumbFile";
            if (!isset($validThumbs[$thumbKey])) {
                $thumbPath = "$thumbAlbumPath/$thumbFile";
                if (@unlink($thumbPath)) {
                    $deleted++;
                    ErrorLogger::info("Cleaned orphaned thumbnail: $thumbKey");
                }
            }
        }
    }
    
    return $deleted;
}

// --- FILTER & SORT ---
$currentAlbum = $_GET['album'] ?? '';
$isHomePage = empty($currentAlbum);

if (!empty($currentAlbum)) {
    $currentAlbum = rawurldecode($currentAlbum);
    $currentAlbum = basename($currentAlbum);
    $currentAlbum = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $currentAlbum);
    
    $albumExists = false;
    foreach ($albums as $album) {
        if (strcasecmp($album, $currentAlbum) === 0) {
            $currentAlbum = $album;
            $albumExists = true;
            break;
        }
    }
    
    if (!$albumExists) {
        $currentAlbum = '';
        $isHomePage = true;
    }
}

$displayFiles = !empty($currentAlbum) 
    ? array_filter($allFiles, fn($f) => $f['album'] === $currentAlbum)
    : $allFiles;
    
$displayFiles = array_filter($displayFiles, fn($f) => $f['thumbExists'] ?? false);
usort($displayFiles, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

$albumCounts = [];
foreach ($albums as $album) {
    $albumCounts[$album] = count(array_filter($allFiles, fn($f) => $f['album'] === $album && ($f['thumbExists'] ?? false)));
}

// ===============================================
// PAGINATION
// ===============================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$totalItems = count($displayFiles);
$totalPages = ceil($totalItems / ITEMS_PER_PAGE);
$offset = ($page - 1) * ITEMS_PER_PAGE;
$paginatedFiles = array_slice($displayFiles, $offset, ITEMS_PER_PAGE);

// API endpoint for infinite scroll
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'items' => array_map(function($file) {
            return [
                'path' => htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8'),
                'thumb' => htmlspecialchars($file['thumb'], ENT_QUOTES, 'UTF-8'),
                'album' => htmlspecialchars($file['album'], ENT_QUOTES, 'UTF-8'),
                'type' => htmlspecialchars($file['type'], ENT_QUOTES, 'UTF-8'),
                'filename' => htmlspecialchars($file['filename'], ENT_QUOTES, 'UTF-8')
            ];
        }, $paginatedFiles),
        'hasMore' => $page < $totalPages,
        'nextPage' => $page + 1
    ]);
    exit;
}

// Get slider images (only for home page)
$sliderImages = [];
if ($isHomePage && !empty($allFiles) && $sliderConfig['enabled']) {
    $imageFiles = array_filter($allFiles, fn($f) => $f['type'] === 'image' && ($f['thumbExists'] ?? false));
    if (count($imageFiles) > 0) {
        $imageFiles = array_values($imageFiles);
        $count = min($sliderConfig['slidesCount'], count($imageFiles));
        $randomKeys = array_rand($imageFiles, $count);
        if (!is_array($randomKeys)) $randomKeys = [$randomKeys];
        foreach ($randomKeys as $key) {
            $sliderImages[] = $imageFiles[$key];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($galleryTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($welcomeMessage, ENT_QUOTES, 'UTF-8') ?>">
  
  <!-- DNS Prefetch & Preconnect for Performance -->
  <link rel="dns-prefetch" href="https://fonts.googleapis.com">
  <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  
  <!-- Fonts with display=swap for better performance -->
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
  
  <!-- Critical CSS - Inline for performance -->
  <style>
    /* Prevent flash of unstyled content */
    body { opacity: 0; transition: opacity 0.3s ease; }
    body.loaded { opacity: 1; }
  </style>
  
  <!-- Main Stylesheet -->
  <link rel="preload" href="styles.css" as="style">
  <link rel="stylesheet" href="styles.css">
  
  <!-- Swiper CSS -->
  <link rel="preload" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" as="style">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  
  <!-- GLightbox CSS -->
  <link rel="preload" href="https://cdn.jsdelivr.net/npm/glightbox@3.2.0/dist/css/glightbox.min.css" as="style">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3.2.0/dist/css/glightbox.min.css">
</head>
<body class="<?= $theme === 'dark' ? 'dark-theme' : '' ?>">
  <header>
    <div class="header-content">
      <a href="index.php" class="logo"><?= htmlspecialchars($galleryTitle, ENT_QUOTES, 'UTF-8') ?></a>
      <div class="header-right">
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
          <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
          </svg>
          <svg class="moon-icon" style="display:none;" viewBox="0 0 24 24" fill="currentColor">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <span class="theme-label">Light</span>
        </button>
      </div>
    </div>
  </header>

  <?php if (!empty($albums)): ?>
  <nav class="album-nav" aria-label="Album navigation">
    <div class="album-links">
      <?php foreach ($albums as $album):
        $cleanTitle = ucwords(str_replace(['-', '_'], ' ', $album));
        $isActive = ($currentAlbum === $album);
        ?>
        <a href="?album=<?= rawurlencode($album) ?>" class="<?= $isActive ? 'active' : '' ?>" aria-current="<?= $isActive ? 'page' : 'false' ?>">
          <?= htmlspecialchars($cleanTitle, ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </nav>
  <?php endif; ?>

  <main class="<?= !$isHomePage ? 'no-slider' : '' ?>">
    <?php if ($isHomePage && !empty($sliderImages) && $sliderConfig['enabled']): ?>
    <!-- WEDDING SLIDER -->
    <div class="hero-slider" data-overlay-opacity="<?= $sliderConfig['overlayOpacity'] ?>">
      <div class="swiper" id="wedding-slider">
        <div class="swiper-wrapper">
          <?php foreach ($sliderImages as $index => $image): ?>
          <div class="swiper-slide" data-swiper-autoplay="<?= $sliderConfig['autoplayDelay'] ?>">
            <img src="<?= htmlspecialchars($image['path'], ENT_QUOTES, 'UTF-8') ?>" alt="Wedding moment" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
            <div class="swiper-slide-overlay" style="opacity: <?= $sliderConfig['overlayOpacity'] ?>;"></div>
            <div class="swiper-slide-content">
              <?php if ($sliderConfig['showTitle']): ?>
              <h2 class="slide-title"><?= htmlspecialchars($galleryTitle, ENT_QUOTES, 'UTF-8') ?></h2>
              <?php endif; ?>
              
              <?php if ($sliderConfig['showDate']): ?>
              <p class="slide-date"><?= htmlspecialchars($weddingDate, ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
              
              <?php if ($sliderConfig['showMessage'] && $index === 0): ?>
              <p class="slide-message"><?= htmlspecialchars($welcomeMessage, ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        
        <?php if ($sliderConfig['showNavigation']): ?>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <?php endif; ?>
        
        <?php if ($sliderConfig['showPagination']): ?>
        <div class="swiper-pagination"></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($galleryError)): ?>
      <div class="error-message"><?= htmlspecialchars($galleryError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (empty($displayFiles)): ?>
      <div class="error-message">
        <p>This collection is being curated.</p>
        <p style="margin-top: 8px; font-size: 0.95rem; opacity: 0.7;">Please check back soon.</p>
      </div>
    <?php else: ?>
      <div class="gallery-container">
        <div class="gallery" id="gallery">
          <?php foreach ($paginatedFiles as $file): 
            $isVideo = $file['type'] === 'video';
            $filename = htmlspecialchars($file['filename'] ?? 'photo', ENT_QUOTES, 'UTF-8');
            $filePath = htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8');
            $thumbPath = htmlspecialchars($file['thumb'], ENT_QUOTES, 'UTF-8');
          ?>
            <a href="<?= $filePath ?>" 
               class="gallery-item glightbox" 
               data-gallery="wedding-gallery"
               <?php if ($isVideo): ?>
               data-type="video"
               <?php endif; ?>
               data-download="<?= $filePath ?>"
               data-filename="<?= $filename ?>">
              <img src="<?= $thumbPath ?>" loading="lazy" alt="Gallery image" decoding="async">
              <?php if ($isVideo): ?><span class="play-icon"></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
        
        <?php if ($page < $totalPages): ?>
        <div class="loading" id="loading">
          <p>Loading more photos...</p>
        </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>

  <footer>
    <p>&copy; <?= date('Y') ?> &mdash; All Rights Reserved</p>
    <a href="https://dreamgraphers.net" target="_blank" class="footer-brand" rel="noopener">DREAMGRAPHERS</a>
  </footer>

  <!-- Inline theme init to prevent flash -->
  <script>
    (function() {
      // Theme initialization - must run immediately
      const savedTheme = localStorage.getItem('galleryTheme') || 'light';
      if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
      }
    })();
  </script>

  <!-- Swiper JS -->
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
  
  <!-- GLightbox JS -->
  <script src="https://cdn.jsdelivr.net/npm/glightbox@3.2.0/dist/js/glightbox.min.js" defer></script>
  
  <script>
    // Optimized Gallery Script with GLightbox
    (function() {
      'use strict';
      
      // Wait for DOM
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
      } else {
        init();
      }
      
      function init() {
        // Mark as loaded (prevents FOUC)
        document.body.classList.add('loaded');
        initGallery();
      }
      
      function initGallery() {
        // Wait for libraries with timeout
        const checkLibraries = setInterval(() => {
          if (typeof Swiper !== 'undefined' && typeof GLightbox !== 'undefined') {
            clearInterval(checkLibraries);
            initializeComponents();
          }
        }, 100);
        
        setTimeout(() => clearInterval(checkLibraries), 5000);
      }
      
      function initializeComponents() {
        initSwiper();
        initGLightbox();
        initThemeToggle();
        initInfiniteScroll();
        initLazyLoading();
        initImageProtection();
      }
      
      // Initialize Swiper
      function initSwiper() {
        <?php if ($isHomePage && !empty($sliderImages) && $sliderConfig['enabled']): ?>
        if (typeof Swiper !== 'undefined' && document.querySelector('#wedding-slider')) {
          const swiperConfig = {
            loop: <?= $sliderConfig['loop'] ? 'true' : 'false' ?>,
            speed: <?= $sliderConfig['speed'] ?>,
            effect: '<?= $sliderConfig['effect'] ?>',
            lazy: {
              loadPrevNext: true,
              loadPrevNextAmount: 2
            },
            <?php if ($sliderConfig['autoplay']): ?>
            autoplay: {
              delay: <?= $sliderConfig['autoplayDelay'] ?>,
              disableOnInteraction: false,
              pauseOnMouseEnter: true
            },
            <?php endif; ?>
            <?php if ($sliderConfig['effect'] === 'fade'): ?>
            fadeEffect: {
              crossFade: true
            },
            <?php elseif ($sliderConfig['effect'] === 'cube'): ?>
            cubeEffect: {
              shadow: true,
              slideShadows: true,
              shadowOffset: 20,
              shadowScale: 0.94
            },
            <?php elseif ($sliderConfig['effect'] === 'coverflow'): ?>
            coverflowEffect: {
              rotate: 50,
              stretch: 0,
              depth: 100,
              modifier: 1,
              slideShadows: true
            },
            <?php elseif ($sliderConfig['effect'] === 'flip'): ?>
            flipEffect: {
              slideShadows: true,
              limitRotation: true
            },
            <?php endif; ?>
            <?php if ($sliderConfig['showNavigation']): ?>
            navigation: {
              nextEl: '.swiper-button-next',
              prevEl: '.swiper-button-prev',
            },
            <?php endif; ?>
            <?php if ($sliderConfig['showPagination']): ?>
            pagination: {
              el: '.swiper-pagination',
              clickable: true,
              dynamicBullets: true
            },
            <?php endif; ?>
            keyboard: {
              enabled: true,
              onlyInViewport: true
            },
            mousewheel: false,
            grabCursor: true
          };
          
          new Swiper('#wedding-slider', swiperConfig);
          console.log('Wedding slider initialized with effect: <?= $sliderConfig['effect'] ?>');
        }
        <?php endif; ?>
      }
      
      // Initialize GLightbox with Custom Download Button
      let lightboxInstance = null;
      let downloadButton = null;
      
      function initGLightbox() {
        if (typeof GLightbox === 'undefined') {
          console.error('GLightbox not loaded');
          return;
        }
        
        try {
          lightboxInstance = GLightbox({
            selector: '.glightbox',
            touchNavigation: true,
            loop: true,
            closeOnOutsideClick: true,
            zoomable: true,
            draggable: true,
            openEffect: 'fade',
            closeEffect: 'fade',
            slideEffect: 'slide',
            videosWidth: '960px',
            autoplayVideos: false,
            plyr: {
              config: {
                ratio: '16:9',
                youtube: { noCookie: true, rel: 0, showinfo: 0, iv_load_policy: 3 },
                vimeo: { byline: false, portrait: false, title: false }
              }
            }
          });
          
          // Event handlers for download button
          lightboxInstance.on('open', () => {
            setTimeout(() => injectDownloadButton(), 150);
          });
          
          lightboxInstance.on('slide_changed', () => {
            setTimeout(() => injectDownloadButton(), 150);
          });
          
          lightboxInstance.on('close', () => {
            removeDownloadButton();
          });
          
          console.log('GLightbox initialized with custom download button');
          
        } catch (error) {
          console.error('GLightbox initialization error:', error);
        }
      }
      
      // Inject download button into GLightbox
      function injectDownloadButton() {
        // Remove any existing button first
        removeDownloadButton();
        
        if (!lightboxInstance) {
          console.warn('Lightbox instance not available');
          return;
        }
        
        // Get current slide index from lightbox instance
        const currentIndex = lightboxInstance.index;
        console.log('Current slide index:', currentIndex);
        
        // Get the slide data from lightbox elements array
        const slideElements = lightboxInstance.elements;
        
        if (!slideElements || !slideElements[currentIndex]) {
          console.warn('No slide element found at index:', currentIndex);
          return;
        }
        
        const currentSlideData = slideElements[currentIndex];
        console.log('Current slide data:', currentSlideData);
        
        // Check if it's a video (don't show download for videos)
        if (currentSlideData.type === 'video') {
          console.log('Video slide detected, skipping download button');
          return;
        }
        
        // Get download URL and filename from the slide data
        const downloadUrl = currentSlideData.href;
        const originalNode = currentSlideData.node;
        const filename = originalNode ? originalNode.getAttribute('data-filename') : null;
        const finalFilename = filename || extractFilename(downloadUrl);
        
        console.log('Download URL:', downloadUrl);
        console.log('Filename:', finalFilename);
        
        // Create download button element
        downloadButton = document.createElement('button');
        downloadButton.className = 'glightbox-download-btn';
        downloadButton.type = 'button';
        downloadButton.setAttribute('aria-label', 'Download image');
        downloadButton.innerHTML = `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          <span class="btn-text">Download</span>
          <span class="btn-loading" style="display: none;">
            <svg class="spinner" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"></circle>
              <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round"></path>
            </svg>
          </span>
        `;
        
        // Add click handler with proper download logic
        downloadButton.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          handleDownload(downloadUrl, finalFilename);
        });
        
        // Inject into lightbox container
        const lightboxContainer = document.querySelector('.gcontainer') || 
                                 document.querySelector('.glightbox-container');
        
        if (lightboxContainer) {
          lightboxContainer.appendChild(downloadButton);
          console.log('Download button injected for:', finalFilename);
          
          // Add entrance animation
          requestAnimationFrame(() => {
            downloadButton.style.opacity = '0';
            downloadButton.style.transform = 'translateX(-50%) translateY(20px)';
            setTimeout(() => {
              downloadButton.style.transition = 'all 0.3s ease';
              downloadButton.style.opacity = '1';
              downloadButton.style.transform = 'translateX(-50%) translateY(0)';
            }, 50);
          });
        } else {
          console.error('Could not find lightbox container');
        }
      }
      
      // Remove download button
      function removeDownloadButton() {
        if (downloadButton) {
          downloadButton.remove();
          downloadButton = null;
        }
      }
      
      // Extract filename from URL
      function extractFilename(url) {
        try {
          const path = url.split('?')[0]; // Remove query parameters
          const filename = path.split('/').pop();
          return decodeURIComponent(filename || 'photo.jpg');
        } catch (error) {
          return 'photo.jpg';
        }
      }
      
      // Handle image download with fallback methods
      async function handleDownload(url, filename) {
        // Show loading state
        const btnText = downloadButton.querySelector('.btn-text');
        const btnLoading = downloadButton.querySelector('.btn-loading');
        const btnIcon = downloadButton.querySelector('svg:not(.spinner)');
        
        downloadButton.disabled = true;
        btnText.style.display = 'none';
        btnIcon.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        
        try {
          // Method 1: Try fetch API (works for same-origin and CORS-enabled images)
          console.log('Attempting fetch download for:', filename);
          
          const response = await fetch(url);
          
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          
          const blob = await response.blob();
          const blobUrl = window.URL.createObjectURL(blob);
          
          // Create temporary download link
          const link = document.createElement('a');
          link.href = blobUrl;
          link.download = filename;
          link.style.display = 'none';
          
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          
          // Clean up blob URL after a short delay
          setTimeout(() => {
            window.URL.revokeObjectURL(blobUrl);
          }, 100);
          
          console.log('Download successful via fetch API');
          showDownloadSuccess();
          
        } catch (fetchError) {
          console.warn('Fetch download failed, trying fallback:', fetchError.message);
          
          // Method 2: Fallback - Direct download (works for same-origin)
          try {
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.style.display = 'none';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            console.log('Download initiated via direct link');
            showDownloadSuccess();
            
          } catch (linkError) {
            console.error('All download methods failed:', linkError);
            showDownloadError();
          }
        } finally {
          // Reset button state
          setTimeout(() => {
            if (downloadButton) {
              downloadButton.disabled = false;
              btnText.style.display = 'inline';
              btnIcon.style.display = 'inline';
              btnLoading.style.display = 'none';
            }
          }, 1000);
        }
      }
      
      // Show success feedback
      function showDownloadSuccess() {
        if (!downloadButton) return;
        
        const btnText = downloadButton.querySelector('.btn-text');
        const originalText = btnText.textContent;
        
        btnText.textContent = 'Downloaded!';
        downloadButton.classList.add('success');
        
        setTimeout(() => {
          if (btnText) {
            btnText.textContent = originalText;
            downloadButton.classList.remove('success');
          }
        }, 2000);
      }
      
      // Show error feedback
      function showDownloadError() {
        if (!downloadButton) return;
        
        const btnText = downloadButton.querySelector('.btn-text');
        const originalText = btnText.textContent;
        
        btnText.textContent = 'Failed';
        downloadButton.classList.add('error');
        
        setTimeout(() => {
          if (btnText) {
            btnText.textContent = originalText;
            downloadButton.classList.remove('error');
          }
        }, 2000);
      }
      
      // Theme Toggle
      function initThemeToggle() {
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const sunIcon = document.querySelector('.sun-icon');
        const moonIcon = document.querySelector('.moon-icon');
        const themeLabel = document.querySelector('.theme-label');
        
        const currentTheme = localStorage.getItem('galleryTheme') || 'light';
        updateThemeUI(currentTheme === 'dark');
        
        themeToggle.addEventListener('click', () => {
          const isDark = body.classList.toggle('dark-theme');
          updateThemeUI(isDark);
          localStorage.setItem('galleryTheme', isDark ? 'dark' : 'light');
        });
        
        function updateThemeUI(isDark) {
          if (isDark) {
            body.classList.add('dark-theme');
            sunIcon.style.display = 'none';
            moonIcon.style.display = 'block';
            if (themeLabel) themeLabel.textContent = 'Dark';
          } else {
            body.classList.remove('dark-theme');
            sunIcon.style.display = 'block';
            moonIcon.style.display = 'none';
            if (themeLabel) themeLabel.textContent = 'Light';
          }
        }
      }
      
      // Infinite Scroll
      function initInfiniteScroll() {
        let currentPage = <?= $page ?>;
        let isLoading = false;
        const hasMore = <?= $page < $totalPages ? 'true' : 'false' ?>;
        const albumParam = '<?= !empty($currentAlbum) ? '&album=' . rawurlencode($currentAlbum) : '' ?>';
        
        if (!hasMore) return;
        
        const loading = document.getElementById('loading');
        if (!loading) return;
        
        const observer = new IntersectionObserver((entries) => {
          if (entries[0].isIntersecting && !isLoading) {
            loadMore();
          }
        }, { rootMargin: '400px' });
        
        observer.observe(loading);
        
        async function loadMore() {
          if (isLoading) return;
          isLoading = true;
          currentPage++;
          
          try {
            const response = await fetch(`?ajax=1&page=${currentPage}${albumParam}`);
            const data = await response.json();
            
            const gallery = document.getElementById('gallery');
            const fragment = document.createDocumentFragment();
            
            data.items.forEach(item => {
              const a = document.createElement('a');
              a.href = item.path;
              a.className = 'gallery-item glightbox';
              a.setAttribute('data-gallery', 'wedding-gallery');
              a.setAttribute('data-download', item.path);
              a.setAttribute('data-filename', item.filename);
              
              if (item.type === 'video') {
                a.setAttribute('data-type', 'video');
              }
              
              const img = document.createElement('img');
              img.src = item.thumb;
              img.loading = 'lazy';
              img.alt = 'Gallery image';
              img.decoding = 'async';
              
              a.appendChild(img);
              
              if (item.type === 'video') {
                const playIcon = document.createElement('span');
                playIcon.className = 'play-icon';
                a.appendChild(playIcon);
              }
              
              fragment.appendChild(a);
            });
            
            gallery.appendChild(fragment);
            
            // Reload GLightbox with new items
            if (lightboxInstance) {
              lightboxInstance.reload();
              console.log('GLightbox reloaded with new items');
            }
            
            if (!data.hasMore) {
              loading.remove();
              observer.disconnect();
            }
            
          } catch (error) {
            console.error('Error loading more:', error);
          } finally {
            isLoading = false;
          }
        }
      }
      
      // Lazy Loading
      function initLazyLoading() {
        if (!('IntersectionObserver' in window)) return;
        
        const imageObserver = new IntersectionObserver((entries, observer) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              const img = entry.target.querySelector('img');
              if (img && !img.complete) {
                img.style.opacity = '0';
                img.addEventListener('load', () => {
                  requestAnimationFrame(() => {
                    img.style.transition = 'opacity 0.4s ease';
                    img.style.opacity = '1';
                  });
                }, { once: true });
              }
              observer.unobserve(entry.target);
            }
          });
        }, { 
          rootMargin: '200px 0px',
          threshold: 0.01 
        });
        
        document.querySelectorAll('.gallery-item').forEach(item => {
          imageObserver.observe(item);
        });
      }
      
      // Enhance mosaic layout with random variations
      function enhanceMosaicLayout() {
        const galleryItems = document.querySelectorAll('.gallery-item');
        
        // Add staggered entrance animation
        galleryItems.forEach((item, index) => {
          item.style.opacity = '0';
          item.style.transform = 'translateY(20px)';
          
          setTimeout(() => {
            requestAnimationFrame(() => {
              item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
              item.style.opacity = '1';
              item.style.transform = 'translateY(0)';
            });
          }, index * 30); // Stagger by 30ms
        });
      }
      
      // Initialize mosaic enhancements
      if (document.querySelector('.gallery')) {
        enhanceMosaicLayout();
      }
      
      // Image Protection
      function initImageProtection() {
        document.addEventListener('contextmenu', (e) => {
          if (e.target.tagName === 'IMG') e.preventDefault();
        });
        
        document.addEventListener('dragstart', (e) => {
          if (e.target.tagName === 'IMG') e.preventDefault();
        });
      }
      
    })();
  </script>
</body>
</html>