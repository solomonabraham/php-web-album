<?php
/**
 * Luxe Wedding Gallery - ULTRA OPTIMIZED with Web-Optimized Images
 * Version 2.3 - Refactored with ImageProcessor and SmartCrop
 *
 * MODIFIED: Now uses ImageProcessor.php for all image generation.
 */

session_start();

require_once __DIR__ . '/ErrorLogger.php';
require_once __DIR__ . '/ConfigManager.php';
require_once __DIR__ . '/ImageProcessor.php'; // NEW: Include ImageProcessor

$configManager = ConfigManager::getInstance();
$config = $configManager->getAll();

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
$webOptimizedDir = $mediaDir . '/web-optimized';
$imageExtensions = $config['imageExtensions'];
$videoExtensions = $config['videoExtensions'];
$galleryError = '';
$theme = $config['theme'] ?? 'light';

// Load slider configuration from config.json
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

// Constants needed for other parts of the code
define('CACHE_FILE', __DIR__ . '/cache/gallery_cache.json');
define('CACHE_DURATION', $config['cacheDuration']);
define('ITEMS_PER_PAGE', $config['itemsPerPage']);

// NEW: Instantiate ImageProcessor
$imageProcessor = new ImageProcessor($config);

if (!is_writable($thumbDir) || !is_writable($webOptimizedDir)) {
    $galleryError = "Image directories are not writable. Please check permissions.";
    ErrorLogger::critical("Image directories not writable", ['thumb' => $thumbDir, 'web' => $webOptimizedDir]);
}

// ===============================================
// CACHE SYSTEM FOR ULTRA-FAST LOADING
// ===============================================
function getCacheDir() {
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
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
    @file_put_contents(CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT));
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
    $webOptimizedFolderName = basename($webOptimizedDir);

    if (is_dir($mediaDir)) {
        $subfolders = glob($mediaDir . '/*', GLOB_ONLYDIR);
        if ($subfolders !== false) {
            $subfolders = array_filter($subfolders, fn($f) => 
                !in_array(basename($f), [$thumbFolderName, $webOptimizedFolderName]) && 
                !str_starts_with(basename($f), '.')
            );
            
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
                    
                    if ($isImage && $fileSize > $config['maxFileSize']) continue;
                    
                    if ($isImage || $isVideo) {
                        $thumbAlbumDir = "$thumbDir/$albumName";
                        $webOptimizedAlbumDir = "$webOptimizedDir/$albumName";
                        $thumbFile = $isVideo ? pathinfo($file, PATHINFO_FILENAME) . '.jpg' : $file;
                        $thumbPath = "$thumbAlbumDir/$thumbFile";
                        $webOptimizedPath = "$webOptimizedAlbumDir/$thumbFile";
                        
                        $allFiles[] = [
                            'path'  => "media/$albumName/" . urlencode($file),
                            'thumb' => "media/thumbnails/$albumName/" . urlencode($thumbFile),
                            'webOptimized' => "media/web-optimized/$albumName/" . urlencode($thumbFile),
                            'album' => $albumName,
                            'type'  => $isVideo ? 'video' : 'image',
                            'filename' => $file,
                            'mtime' => filemtime($filePath),
                            'thumbExists' => file_exists($thumbPath),
                            'webOptimizedExists' => file_exists($webOptimizedPath)
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

// Background thumbnail and web-optimized generation
$autoSyncEnabled = $config['autoSyncEnabled'] ?? true;
$autoSyncLimit = $config['autoSyncLimit'] ?? 20;
$autoCleanupEnabled = $config['autoCleanupEnabled'] ?? true;
$autoCleanupInterval = $config['autoCleanupInterval'] ?? 3600;
$autoCleanupLimit = $config['autoCleanupLimit'] ?? 5;

// Auto-generate thumbnails and web-optimized images
if ($autoSyncEnabled) {
    $missingImages = array_filter($allFiles, fn($f) => 
        !($f['thumbExists'] ?? true) || !($f['webOptimizedExists'] ?? true)
    );
    $generatedCount = 0;
    
    if (count($missingImages) > 0 && count($missingImages) < 100) {
        foreach ($missingImages as &$file) {
            if ($generatedCount >= $autoSyncLimit) break;
            
            $sourcePath = str_replace('media/', $mediaDir . '/', $file['path']);
            $sourcePath = urldecode($sourcePath);
            $thumbPath = str_replace('media/thumbnails/', $thumbDir . '/', $file['thumb']);
            $thumbPath = urldecode($thumbPath);
            $webOptimizedPath = str_replace('media/web-optimized/', $webOptimizedDir . '/', $file['webOptimized']);
            $webOptimizedPath = urldecode($webOptimizedPath);
            
            if ($file['type'] === 'image') {
                // Generate thumbnail if missing (uses SmartCrop via ImageProcessor)
                if (!($file['thumbExists'] ?? false)) {
                    if ($imageProcessor->createThumbnail($sourcePath, $thumbPath)) {
                        $file['thumbExists'] = true;
                        $generatedCount++;
                    }
                }
                
                // Generate web-optimized if missing
                if (!($file['webOptimizedExists'] ?? false) && $generatedCount < $autoSyncLimit) {
                    if ($imageProcessor->createWebOptimizedImage($sourcePath, $webOptimizedPath)) {
                        $file['webOptimizedExists'] = true;
                        $generatedCount++;
                    }
                }
            } elseif ($file['type'] === 'video') {
                // Generate thumbnail for video
                if (!($file['thumbExists'] ?? false)) {
                    if ($imageProcessor->createVideoThumbnail($sourcePath, $thumbPath)) {
                        $file['thumbExists'] = true;
                        // Copy thumbnail as web-optimized for videos
                        if (@copy($thumbPath, $webOptimizedPath)) {
                            $file['webOptimizedExists'] = true;
                        }
                        $generatedCount++;
                    }
                }
            }
        }
        
        if ($generatedCount > 0) {
            saveCachedGalleryData([
                'allFiles' => $allFiles,
                'albums' => $albums,
                'generated' => time()
            ]);
            ErrorLogger::info("Auto-generated $generatedCount images");
        }
    }
}

// Auto-cleanup orphaned images
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
        $cleanedCount = cleanOrphanedImages($allFiles, $thumbDir, $webOptimizedDir, $autoCleanupLimit);
        if ($cleanedCount >= 0) {
            @file_put_contents($cleanupFile, time());
            if ($cleanedCount > 0) {
                ErrorLogger::info("Auto-cleaned $cleanedCount orphaned images");
            }
        }
    }
}

// Function to clean orphaned images
function cleanOrphanedImages($allFiles, $thumbDir, $webOptimizedDir, $maxDelete = 5) {
    $validImages = [];
    foreach ($allFiles as $file) {
        $imageKey = $file['album'] . '/' . basename(urldecode($file['thumb']));
        $validImages[$imageKey] = true;
    }
    
    $deleted = 0;
    
    // Clean thumbnails
    $deleted += cleanOrphanedDirectory($thumbDir, $validImages, $maxDelete - $deleted);
    
    // Clean web-optimized
    if ($deleted < $maxDelete) {
        $deleted += cleanOrphanedDirectory($webOptimizedDir, $validImages, $maxDelete - $deleted);
    }
    
    return $deleted;
}

function cleanOrphanedDirectory($dir, $validImages, $maxDelete) {
    if (!is_dir($dir) || $maxDelete <= 0) return 0;
    
    $deleted = 0;
    $subfolders = glob($dir . '/*', GLOB_ONLYDIR);
    if ($subfolders === false) return 0;
    
    foreach ($subfolders as $albumPath) {
        if ($deleted >= $maxDelete) break;
        
        $albumName = basename($albumPath);
        $files = @scandir($albumPath);
        if ($files === false) continue;
        
        foreach ($files as $file) {
            if ($deleted >= $maxDelete) break;
            if ($file === '.' || $file === '..') continue;
            
            $imageKey = "$albumName/$file";
            if (!isset($validImages[$imageKey])) {
                $filePath = "$albumPath/$file";
                if (@unlink($filePath)) {
                    $deleted++;
                    ErrorLogger::info("Cleaned orphaned image: $imageKey");
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
    
$displayFiles = array_filter($displayFiles, fn($f) => 
    ($f['thumbExists'] ?? false) && ($f['webOptimizedExists'] ?? false)
);
usort($displayFiles, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

$albumCounts = [];
foreach ($albums as $album) {
    $albumCounts[$album] = count(array_filter($allFiles, fn($f) => 
        $f['album'] === $album && 
        ($f['thumbExists'] ?? false) && 
        ($f['webOptimizedExists'] ?? false)
    ));
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
                'webOptimized' => htmlspecialchars($file['webOptimized'], ENT_QUOTES, 'UTF-8'),
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
    $imageFiles = array_filter($allFiles, fn($f) => 
        $f['type'] === 'image' && 
        ($f['webOptimizedExists'] ?? false)
    );
    if (count($imageFiles) > 0) {
        $imageFiles = array_values($imageFiles);
        $count = min($sliderConfig['slidesCount'], count($imageFiles));
        // Use a mix of recent and random images for the slider
        $recentImages = array_slice($imageFiles, 0, floor($count / 2));
        $randomImages = array_slice($imageFiles, floor($count / 2));
        shuffle($randomImages);
        $sliderImages = array_merge($recentImages, array_slice($randomImages, 0, $count - count($recentImages)));
        
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
  
  <link rel="dns-prefetch" href="https://fonts.googleapis.com">
  <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
  
  <style>
    /* Prevent flash of unstyled content */
    body { opacity: 0; transition: opacity 0.3s ease; }
    body.loaded { opacity: 1; }
  </style>
  
  <link rel="preload" href="styles.css" as="style">
  <link rel="stylesheet" href="styles.css">
  
  <link rel="preload" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" as="style">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  
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
    <div class="hero-slider" data-overlay-opacity="<?= $sliderConfig['overlayOpacity'] ?>">
      <div class="swiper" id="wedding-slider">
        <div class="swiper-wrapper">
          <?php foreach ($sliderImages as $index => $image): ?>
          <div class="swiper-slide" data-swiper-autoplay="<?= $sliderConfig['autoplayDelay'] ?>">
            <img src="<?= htmlspecialchars($image['webOptimized'], ENT_QUOTES, 'UTF-8') ?>" alt="Wedding moment" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
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
            $originalPath = htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8');
            $webOptimizedPath = htmlspecialchars($file['webOptimized'], ENT_QUOTES, 'UTF-8');
            $thumbPath = htmlspecialchars($file['thumb'], ENT_QUOTES, 'UTF-8');
          ?>
            <a href="<?= $webOptimizedPath ?>" 
               class="gallery-item glightbox" 
               data-gallery="wedding-gallery"
               <?php if ($isVideo): ?>
               data-type="video"
               <?php endif; ?>
               data-download="<?= $originalPath ?>"
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

  <script>
    (function() {
      const savedTheme = localStorage.getItem('galleryTheme') || 'light';
      if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
      }
    })();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
  
  <script src="https://cdn.jsdelivr.net/npm/glightbox@3.2.0/dist/js/glightbox.min.js" defer></script>
  
  <script>
    // Gallery Script with Web-Optimized Images and Full-Quality Downloads
    (function() {
      'use strict';
      
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
      } else {
        init();
      }
      
      function init() {
        document.body.classList.add('loaded');
        adjustStickyElements(); // NEW: Adjust sticky positioning
        initGallery();
      }
      
      // NEW: Adjust sticky navigation and footer
      function adjustStickyElements() {
        // Adjust album navigation to stick below header
        const header = document.querySelector('header');
        const albumNav = document.querySelector('.album-nav');
        
        if (header && albumNav) {
          const headerHeight = header.offsetHeight;
          albumNav.style.top = `${headerHeight}px`;
        }
        
        // Adjust content padding for sticky footer
        const footer = document.querySelector('footer');
        const main = document.querySelector('main');
        
        if (footer && main) {
          const footerHeight = footer.offsetHeight;
          const currentPadding = parseInt(window.getComputedStyle(main).paddingBottom);
          if (currentPadding < footerHeight) {
            main.style.paddingBottom = `${footerHeight + 20}px`;
          }
        }
        
        // Re-adjust on window resize
        window.addEventListener('resize', adjustStickyElements);
      }
      
      function initGallery() {
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
      
      function initSwiper() {
        <?php if ($isHomePage && !empty($sliderImages) && $sliderConfig['enabled']): ?>
        if (typeof Swiper !== 'undefined' && document.querySelector('#wedding-slider')) {
          new Swiper('#wedding-slider', {
            loop: <?= $sliderConfig['loop'] ? 'true' : 'false' ?>,
            speed: <?= $sliderConfig['speed'] ?>,
            effect: '<?= $sliderConfig['effect'] ?>',
            lazy: { loadPrevNext: true, loadPrevNextAmount: 2 },
            <?php if ($sliderConfig['autoplay']): ?>
            autoplay: {
              delay: <?= $sliderConfig['autoplayDelay'] ?>,
              disableOnInteraction: false,
              pauseOnMouseEnter: true
            },
            <?php endif; ?>
            <?php if ($sliderConfig['showNavigation']): ?>
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            <?php endif; ?>
            <?php if ($sliderConfig['showPagination']): ?>
            pagination: { el: '.swiper-pagination', clickable: true, dynamicBullets: true },
            <?php endif; ?>
            keyboard: { enabled: true, onlyInViewport: true },
            grabCursor: true
          });
        }
        <?php endif; ?>
      }
      
      let lightboxInstance = null;
      let downloadButton = null;
      
      function initGLightbox() {
        if (typeof GLightbox === 'undefined') return;
        
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
            autoplayVideos: false
          });
          
          // Hide footer when lightbox opens
          const footer = document.querySelector('footer');
          
          lightboxInstance.on('open', () => {
            setTimeout(() => injectDownloadButton(), 150);
            if (footer) {
              footer.style.opacity = '0';
              footer.style.pointerEvents = 'none';
              footer.style.transition = 'opacity 0.3s ease';
            }
          });
          
          lightboxInstance.on('slide_changed', () => setTimeout(() => injectDownloadButton(), 150));
          
          lightboxInstance.on('close', () => {
            removeDownloadButton();
            if (footer) {
              footer.style.opacity = '1';
              footer.style.pointerEvents = 'auto';
            }
          });
          
        } catch (error) {
          console.error('GLightbox error:', error);
        }
      }
      
      function injectDownloadButton() {
        removeDownloadButton();
        
        if (!lightboxInstance) return;
        
        const currentIndex = lightboxInstance.index;
        const slideElements = lightboxInstance.elements;
        
        if (!slideElements || !slideElements[currentIndex]) return;
        
        const currentSlideData = slideElements[currentIndex];
        
        if (currentSlideData.type === 'video') return;
        
        const originalNode = currentSlideData.node;
        const downloadUrl = originalNode ? originalNode.getAttribute('data-download') : null;
        const filename = originalNode ? originalNode.getAttribute('data-filename') : null;
        
        if (!downloadUrl) return;
        
        const finalFilename = filename || extractFilename(downloadUrl);
        
        downloadButton = document.createElement('button');
        downloadButton.className = 'glightbox-download-btn';
        downloadButton.type = 'button';
        downloadButton.setAttribute('aria-label', 'Download full quality image');
        downloadButton.innerHTML = `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          <span class="btn-text">Download Original</span>
          <span class="btn-loading" style="display: none;">
            <svg class="spinner" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"></circle>
              <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round"></path>
            </svg>
          </span>
        `;
        
        downloadButton.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          handleDownload(downloadUrl, finalFilename);
        });
        
        const lightboxContainer = document.querySelector('.gcontainer') || 
                                 document.querySelector('.glightbox-container');
        
        if (lightboxContainer) {
          lightboxContainer.appendChild(downloadButton);
          
          requestAnimationFrame(() => {
            downloadButton.style.opacity = '0';
            downloadButton.style.transform = 'translateX(-50%) translateY(20px)';
            setTimeout(() => {
              downloadButton.style.transition = 'all 0.3s ease';
              downloadButton.style.opacity = '1';
              downloadButton.style.transform = 'translateX(-50%) translateY(0)';
            }, 50);
          });
        }
      }
      
      function removeDownloadButton() {
        if (downloadButton) {
          downloadButton.remove();
          downloadButton = null;
        }
      }
      
      function extractFilename(url) {
        try {
          const path = url.split('?')[0];
          const filename = path.split('/').pop();
          return decodeURIComponent(filename || 'photo.jpg');
        } catch (error) {
          return 'photo.jpg';
        }
      }
      
      async function handleDownload(url, filename) {
        const btnText = downloadButton.querySelector('.btn-text');
        const btnLoading = downloadButton.querySelector('.btn-loading');
        const btnIcon = downloadButton.querySelector('svg:not(.spinner)');
        
        downloadButton.disabled = true;
        btnText.style.display = 'none';
        btnIcon.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        
        try {
          const response = await fetch(url);
          
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          
          const blob = await response.blob();
          const blobUrl = window.URL.createObjectURL(blob);
          
          const link = document.createElement('a');
          link.href = blobUrl;
          link.download = filename;
          link.style.display = 'none';
          
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          
          setTimeout(() => window.URL.revokeObjectURL(blobUrl), 100);
          
          showDownloadSuccess();
          
        } catch (fetchError) {
          console.warn('Fetch failed, trying fallback:', fetchError.message);
          
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
            
            showDownloadSuccess();
            
          } catch (linkError) {
            console.error('All download methods failed:', linkError);
            showDownloadError();
          }
        } finally {
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
              a.href = item.webOptimized;
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
            
            if (lightboxInstance) {
              lightboxInstance.reload();
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
        }, { rootMargin: '200px 0px', threshold: 0.01 });
        
        document.querySelectorAll('.gallery-item').forEach(item => {
          imageObserver.observe(item);
        });
      }
      
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