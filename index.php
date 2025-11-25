<?php
/**
 * Luxe Wedding Gallery - ULTRA OPTIMIZED
 * Version 2.4 - Performance Enhanced & Code Cleanup
 * 
 * Key Optimizations:
 * - Reduced database-like operations
 * - Lazy initialization
 * - Streamlined cache logic
 * - Removed redundant checks
 * - Improved session handling
 */

session_start();

require_once __DIR__ . '/ErrorLogger.php';
require_once __DIR__ . '/ConfigManager.php';
require_once __DIR__ . '/ImageProcessor.php';

// Quick config load
$configManager = ConfigManager::getInstance();
$config = $configManager->getAll();

// Performance settings
ini_set('memory_limit', '512M');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Optimized caching headers
header("Cache-Control: public, max-age=86400");
header("Expires: " . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

// ===============================================
// PASSWORD PROTECTION - STREAMLINED
// ===============================================
if ($config['requirePassword'] ?? false) {
    // Initialize session variables only if needed
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt'] = time();
    }
    
    // Rate limiting check
    $error = null;
    if ($_SESSION['login_attempts'] >= 5) {
        $timeSinceLastAttempt = time() - $_SESSION['last_attempt'];
        if ($timeSinceLastAttempt < 300) {
            $error = "Too many attempts. Please wait " . ceil((300 - $timeSinceLastAttempt) / 60) . " minute(s).";
        } else {
            $_SESSION['login_attempts'] = 0;
        }
    }
    
    // Handle login
    if (isset($_POST['password']) && !$error) {
        $passwordMatch = ($config['useHashedPassword'] ?? false)
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
        }
    }
    
    // Show login form if not authenticated
    if (empty($_SESSION['gallery_auth'])) {
        include __DIR__ . '/login_form.php'; // Separated login form
        exit;
    }
}

// ===============================================
// CONFIGURATION & CONSTANTS
// ===============================================
define('CACHE_FILE', __DIR__ . '/cache/gallery_cache.json');
define('CACHE_DURATION', $config['cacheDuration'] ?? 3600);
define('ITEMS_PER_PAGE', $config['itemsPerPage'] ?? 50);

$mediaDir = $config['mediaDir'];
$thumbDir = $mediaDir . '/thumbnails';
$webOptimizedDir = $mediaDir . '/web-optimized';
$galleryError = '';
$theme = $config['theme'] ?? 'light';
$sliderConfig = $config['slider'] ?? [];

// ===============================================
// CACHE FUNCTIONS - SIMPLIFIED
// ===============================================
function getCachedData() {
    if (!file_exists(CACHE_FILE)) return null;
    if ((time() - filemtime(CACHE_FILE)) > CACHE_DURATION) return null;
    
    $data = @file_get_contents(CACHE_FILE);
    return $data ? json_decode($data, true) : null;
}

function saveCache($data) {
    $dir = dirname(CACHE_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents(CACHE_FILE, json_encode($data));
}

// ===============================================
// FILE VALIDATION - OPTIMIZED
// ===============================================
function isValidMedia($filePath, $extensions, $type = 'image') {
    if (!file_exists($filePath)) return false;
    
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions)) return false;
    
    // Skip MIME check for better performance (extensions are usually reliable)
    return true;
}

// ===============================================
// GALLERY DATA LOADING - OPTIMIZED
// ===============================================
$cachedData = getCachedData();

if ($cachedData && isset($cachedData['allFiles'], $cachedData['albums'])) {
    $allFiles = $cachedData['allFiles'];
    $albums = $cachedData['albums'];
} else {
    $allFiles = [];
    $albums = [];
    $excludeFolders = [basename($thumbDir), basename($webOptimizedDir)];

    if (is_dir($mediaDir)) {
        $subfolders = array_filter(
            glob($mediaDir . '/*', GLOB_ONLYDIR) ?: [],
            fn($f) => !in_array(basename($f), $excludeFolders) && !str_starts_with(basename($f), '.')
        );
        
        foreach ($subfolders as $albumPath) {
            $albumName = basename($albumPath);
            $albums[] = $albumName;
            
            $files = @scandir($albumPath) ?: [];
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || str_starts_with($file, '.')) continue;
                
                $filePath = "$albumPath/$file";
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                
                // Quick type check
                $isImage = in_array($ext, $config['imageExtensions']) && isValidMedia($filePath, $config['imageExtensions']);
                $isVideo = in_array($ext, $config['videoExtensions']) && isValidMedia($filePath, $config['videoExtensions'], 'video');
                
                if (!$isImage && !$isVideo) continue;
                
                // Size check only for images
                if ($isImage && @filesize($filePath) > ($config['maxFileSize'] ?? 524288000)) continue;
                
                $thumbFile = $isVideo ? pathinfo($file, PATHINFO_FILENAME) . '.jpg' : $file;
                $thumbPath = "$thumbDir/$albumName/$thumbFile";
                $webOptimizedPath = "$webOptimizedDir/$albumName/$thumbFile";
                
                $allFiles[] = [
                    'path' => "media/$albumName/" . urlencode($file),
                    'thumb' => "media/thumbnails/$albumName/" . urlencode($thumbFile),
                    'webOptimized' => "media/web-optimized/$albumName/" . urlencode($thumbFile),
                    'album' => $albumName,
                    'type' => $isVideo ? 'video' : 'image',
                    'filename' => $file,
                    'mtime' => filemtime($filePath),
                    'thumbExists' => file_exists($thumbPath),
                    'webOptimizedExists' => file_exists($webOptimizedPath)
                ];
            }
        }
    } else {
        $galleryError = "Media directory not found.";
    }
    
    saveCache(['allFiles' => $allFiles, 'albums' => $albums, 'generated' => time()]);
}

// ===============================================
// BACKGROUND IMAGE GENERATION - OPTIMIZED
// ===============================================
if (($config['autoSyncEnabled'] ?? true) && !isset($_GET['ajax'])) {
    $imageProcessor = new ImageProcessor($config);
    $missingImages = array_filter($allFiles, fn($f) => !$f['thumbExists'] || !$f['webOptimizedExists']);
    $limit = $config['autoSyncLimit'] ?? 20;
    $generated = 0;
    
    foreach (array_slice($missingImages, 0, $limit) as &$file) {
        $sourcePath = urldecode(str_replace('media/', $mediaDir . '/', $file['path']));
        $thumbPath = urldecode(str_replace('media/thumbnails/', $thumbDir . '/', $file['thumb']));
        $webOptimizedPath = urldecode(str_replace('media/web-optimized/', $webOptimizedDir . '/', $file['webOptimized']));
        
        if ($file['type'] === 'image') {
            if (!$file['thumbExists'] && $imageProcessor->createThumbnail($sourcePath, $thumbPath)) {
                $file['thumbExists'] = true;
                $generated++;
            }
            if (!$file['webOptimizedExists'] && $imageProcessor->createWebOptimizedImage($sourcePath, $webOptimizedPath)) {
                $file['webOptimizedExists'] = true;
                $generated++;
            }
        } elseif ($file['type'] === 'video' && !$file['thumbExists']) {
            if ($imageProcessor->createVideoThumbnail($sourcePath, $thumbPath)) {
                $file['thumbExists'] = true;
                @copy($thumbPath, $webOptimizedPath);
                $file['webOptimizedExists'] = true;
                $generated++;
            }
        }
    }
    
    if ($generated > 0) {
        saveCache(['allFiles' => $allFiles, 'albums' => $albums, 'generated' => time()]);
    }
}

// ===============================================
// FILTER & PAGINATION - STREAMLINED
// ===============================================
$currentAlbum = isset($_GET['album']) ? basename(rawurldecode($_GET['album'])) : '';
$isHomePage = empty($currentAlbum);

// Validate album
if ($currentAlbum && !in_array($currentAlbum, $albums)) {
    $currentAlbum = '';
    $isHomePage = true;
}

// Filter files
$displayFiles = $currentAlbum 
    ? array_filter($allFiles, fn($f) => $f['album'] === $currentAlbum && $f['thumbExists'] && $f['webOptimizedExists'])
    : array_filter($allFiles, fn($f) => $f['thumbExists'] && $f['webOptimizedExists']);

usort($displayFiles, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$totalItems = count($displayFiles);
$totalPages = ceil($totalItems / ITEMS_PER_PAGE);
$paginatedFiles = array_slice($displayFiles, ($page - 1) * ITEMS_PER_PAGE, ITEMS_PER_PAGE);

// AJAX endpoint
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'items' => array_map(fn($file) => [
            'path' => htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8'),
            'thumb' => htmlspecialchars($file['thumb'], ENT_QUOTES, 'UTF-8'),
            'webOptimized' => htmlspecialchars($file['webOptimized'], ENT_QUOTES, 'UTF-8'),
            'album' => htmlspecialchars($file['album'], ENT_QUOTES, 'UTF-8'),
            'type' => htmlspecialchars($file['type'], ENT_QUOTES, 'UTF-8'),
            'filename' => htmlspecialchars($file['filename'], ENT_QUOTES, 'UTF-8')
        ], $paginatedFiles),
        'hasMore' => $page < $totalPages,
        'nextPage' => $page + 1
    ]);
    exit;
}

// Slider images (only for home page)
$sliderImages = [];
if ($isHomePage && ($sliderConfig['enabled'] ?? true)) {
    $imageFiles = array_filter($allFiles, fn($f) => $f['type'] === 'image' && $f['webOptimizedExists']);
    if ($imageFiles) {
        $count = min($sliderConfig['slidesCount'] ?? 10, count($imageFiles));
        $sliderImages = array_slice(array_values($imageFiles), 0, $count);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($config['galleryTitle'], ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($config['welcomeMessage'], ENT_QUOTES, 'UTF-8') ?>">
  
  <!-- Preconnect to external resources -->
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  
  <!-- Critical CSS inline for faster initial render -->
  <style>
    body { opacity: 0; transition: opacity 0.3s; margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    body.loaded { opacity: 1; }
  </style>
  
  <!-- Async load fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
  
  <!-- Main stylesheet -->
  <link rel="stylesheet" href="styles.css">
  
  <!-- Defer external libraries -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" media="print" onload="this.media='all'">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3.2.0/dist/css/glightbox.min.css" media="print" onload="this.media='all'">
</head>
<body class="<?= $theme === 'dark' ? 'dark-theme' : '' ?>">
  
  <header>
    <div class="header-content">
      <a href="index.php" class="logo"><?= htmlspecialchars($config['galleryTitle'], ENT_QUOTES, 'UTF-8') ?></a>
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

  <?php if ($albums): ?>
  <nav class="album-nav" aria-label="Album navigation">
    <div class="album-links">
      <?php foreach ($albums as $album): ?>
        <a href="?album=<?= rawurlencode($album) ?>" class="<?= $currentAlbum === $album ? 'active' : '' ?>">
          <?= htmlspecialchars(ucwords(str_replace(['-', '_'], ' ', $album)), ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </nav>
  <?php endif; ?>

  <main class="<?= !$isHomePage ? 'no-slider' : '' ?>">
    <?php if ($isHomePage && $sliderImages && ($sliderConfig['enabled'] ?? true)): ?>
    <div class="hero-slider">
      <div class="swiper" id="wedding-slider">
        <div class="swiper-wrapper">
          <?php foreach ($sliderImages as $index => $image): ?>
          <div class="swiper-slide">
            <img src="<?= htmlspecialchars($image['webOptimized'], ENT_QUOTES, 'UTF-8') ?>" alt="Wedding moment" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
            <div class="swiper-slide-overlay"></div>
            <div class="swiper-slide-content">
              <?php if ($sliderConfig['showTitle'] ?? true): ?>
              <h2 class="slide-title"><?= htmlspecialchars($config['galleryTitle'], ENT_QUOTES, 'UTF-8') ?></h2>
              <?php endif; ?>
              <?php if ($sliderConfig['showDate'] ?? true): ?>
              <p class="slide-date"><?= htmlspecialchars($config['weddingDate'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
              <?php if (($sliderConfig['showMessage'] ?? true) && $index === 0): ?>
              <p class="slide-message"><?= htmlspecialchars($config['welcomeMessage'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($sliderConfig['showNavigation'] ?? false): ?>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <?php endif; ?>
        <?php if ($sliderConfig['showPagination'] ?? true): ?>
        <div class="swiper-pagination"></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($galleryError): ?>
      <div class="error-message"><?= htmlspecialchars($galleryError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (!$displayFiles): ?>
      <div class="error-message">
        <p>This collection is being curated.</p>
        <p style="margin-top: 8px; font-size: 0.95rem; opacity: 0.7;">Please check back soon.</p>
      </div>
    <?php else: ?>
      <div class="gallery-container">
        <div class="gallery" id="gallery">
          <?php foreach ($paginatedFiles as $file): ?>
            <a href="<?= htmlspecialchars($file['webOptimized'], ENT_QUOTES, 'UTF-8') ?>" 
               class="gallery-item glightbox" 
               data-gallery="wedding-gallery"
               <?= $file['type'] === 'video' ? 'data-type="video"' : '' ?>
               data-download="<?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>"
               data-filename="<?= htmlspecialchars($file['filename'], ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= htmlspecialchars($file['thumb'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" alt="Gallery image">
              <?php if ($file['type'] === 'video'): ?><span class="play-icon"></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
        <?php if ($page < $totalPages): ?>
        <div class="loading" id="loading"><p>Loading more photos...</p></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>

  <footer>
    <p>&copy; <?= date('Y') ?> &mdash; All Rights Reserved</p>
    <a href="https://dreamgraphers.net" target="_blank" class="footer-brand" rel="noopener">DREAMGRAPHERS</a>
  </footer>

  <!-- Initialize theme before scripts load -->
  <script>
    (function() {
      const theme = localStorage.getItem('galleryTheme') || 'light';
      if (theme === 'dark') document.body.classList.add('dark-theme');
    })();
  </script>

  <!-- Load scripts with defer for better performance -->
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/glightbox@3.2.0/dist/js/glightbox.min.js" defer></script>
  
  <!-- Minified gallery script -->
  <script defer>
    (function() {
      'use strict';
      
      const init = () => {
        document.body.classList.add('loaded');
        adjustStickyElements();
        
        const checkLibs = setInterval(() => {
          if (typeof Swiper !== 'undefined' && typeof GLightbox !== 'undefined') {
            clearInterval(checkLibs);
            initComponents();
          }
        }, 100);
        setTimeout(() => clearInterval(checkLibs), 5000);
      };
      
      const adjustStickyElements = () => {
        const header = document.querySelector('header');
        const albumNav = document.querySelector('.album-nav');
        if (header && albumNav) {
          albumNav.style.top = `${header.offsetHeight}px`;
        }
        window.addEventListener('resize', adjustStickyElements);
      };
      
      const initComponents = () => {
        initSwiper();
        initLightbox();
        initThemeToggle();
        initInfiniteScroll();
        initLazyLoad();
      };
      
      const initSwiper = () => {
        <?php if ($isHomePage && $sliderImages && ($sliderConfig['enabled'] ?? true)): ?>
        if (typeof Swiper !== 'undefined' && document.querySelector('#wedding-slider')) {
          new Swiper('#wedding-slider', {
            loop: <?= $sliderConfig['loop'] ?? true ? 'true' : 'false' ?>,
            speed: <?= $sliderConfig['speed'] ?? 1200 ?>,
            effect: '<?= $sliderConfig['effect'] ?? 'fade' ?>',
            lazy: { loadPrevNext: true },
            <?php if ($sliderConfig['autoplay'] ?? true): ?>
            autoplay: { delay: <?= $sliderConfig['autoplayDelay'] ?? 5000 ?>, disableOnInteraction: false },
            <?php endif; ?>
            <?php if ($sliderConfig['showNavigation'] ?? false): ?>
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            <?php endif; ?>
            <?php if ($sliderConfig['showPagination'] ?? true): ?>
            pagination: { el: '.swiper-pagination', clickable: true },
            <?php endif; ?>
            keyboard: { enabled: true }
          });
        }
        <?php endif; ?>
      };
      
      let lightbox = null;
      
      const initLightbox = () => {
        if (typeof GLightbox === 'undefined') return;
        
        lightbox = GLightbox({
          selector: '.glightbox',
          touchNavigation: true,
          loop: true,
          zoomable: true,
          draggable: true
        });
        
        lightbox.on('open', () => {
          setTimeout(injectDownloadBtn, 150);
          const footer = document.querySelector('footer');
          if (footer) footer.style.opacity = '0';
        });
        
        lightbox.on('slide_changed', () => setTimeout(injectDownloadBtn, 150));
        
        lightbox.on('close', () => {
          const btn = document.querySelector('.glightbox-download-btn');
          if (btn) btn.remove();
          const footer = document.querySelector('footer');
          if (footer) footer.style.opacity = '1';
        });
      };
      
      const injectDownloadBtn = () => {
        let btn = document.querySelector('.glightbox-download-btn');
        if (btn) btn.remove();
        
        if (!lightbox) return;
        
        const current = lightbox.elements[lightbox.index];
        if (!current || current.type === 'video') return;
        
        const downloadUrl = current.node?.getAttribute('data-download');
        const filename = current.node?.getAttribute('data-filename') || 'photo.jpg';
        
        if (!downloadUrl) return;
        
        btn = document.createElement('button');
        btn.className = 'glightbox-download-btn';
        btn.innerHTML = `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          <span>Download</span>
        `;
        
        btn.onclick = async (e) => {
          e.preventDefault();
          e.stopPropagation();
          btn.disabled = true;
          
          try {
            const res = await fetch(downloadUrl);
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
          } catch (err) {
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = filename;
            a.click();
          } finally {
            setTimeout(() => btn.disabled = false, 1000);
          }
        };
        
        const container = document.querySelector('.gcontainer') || document.querySelector('.glightbox-container');
        if (container) container.appendChild(btn);
      };
      
      const initThemeToggle = () => {
        const toggle = document.getElementById('themeToggle');
        const updateUI = (isDark) => {
          document.body.classList.toggle('dark-theme', isDark);
          document.querySelector('.sun-icon').style.display = isDark ? 'none' : 'block';
          document.querySelector('.moon-icon').style.display = isDark ? 'block' : 'none';
          const label = document.querySelector('.theme-label');
          if (label) label.textContent = isDark ? 'Dark' : 'Light';
        };
        
        toggle.addEventListener('click', () => {
          const isDark = document.body.classList.toggle('dark-theme');
          updateUI(isDark);
          localStorage.setItem('galleryTheme', isDark ? 'dark' : 'light');
        });
      };
      
      const initInfiniteScroll = () => {
        const loading = document.getElementById('loading');
        if (!loading) return;
        
        let page = <?= $page ?>;
        let isLoading = false;
        const album = '<?= !empty($currentAlbum) ? '&album=' . rawurlencode($currentAlbum) : '' ?>';
        
        const observer = new IntersectionObserver((entries) => {
          if (entries[0].isIntersecting && !isLoading) {
            loadMore();
          }
        }, { rootMargin: '400px' });
        
        observer.observe(loading);
        
        const loadMore = async () => {
          if (isLoading) return;
          isLoading = true;
          page++;
          
          try {
            const res = await fetch(`?ajax=1&page=${page}${album}`);
            const data = await res.json();
            
            const gallery = document.getElementById('gallery');
            const frag = document.createDocumentFragment();
            
            data.items.forEach(item => {
              const a = document.createElement('a');
              a.href = item.webOptimized;
              a.className = 'gallery-item glightbox';
              a.dataset.gallery = 'wedding-gallery';
              a.dataset.download = item.path;
              a.dataset.filename = item.filename;
              if (item.type === 'video') a.dataset.type = 'video';
              
              const img = document.createElement('img');
              img.src = item.thumb;
              img.loading = 'lazy';
              img.alt = 'Gallery image';
              
              a.appendChild(img);
              if (item.type === 'video') {
                const play = document.createElement('span');
                play.className = 'play-icon';
                a.appendChild(play);
              }
              frag.appendChild(a);
            });
            
            gallery.appendChild(frag);
            if (lightbox) lightbox.reload();
            if (!data.hasMore) {
              loading.remove();
              observer.disconnect();
            }
          } catch (err) {
            console.error('Load more failed:', err);
          } finally {
            isLoading = false;
          }
        };
      };
      
      const initLazyLoad = () => {
        if (!('IntersectionObserver' in window)) return;
        
        const observer = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              const img = entry.target.querySelector('img');
              if (img && !img.complete) {
                img.style.opacity = '0';
                img.addEventListener('load', () => {
                  img.style.transition = 'opacity 0.4s';
                  img.style.opacity = '1';
                }, { once: true });
              }
              observer.unobserve(entry.target);
            }
          });
        }, { rootMargin: '200px' });
        
        document.querySelectorAll('.gallery-item').forEach(item => observer.observe(item));
      };
      
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
      } else {
        init();
      }
    })();
  </script>
</body>
</html>