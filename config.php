<?php
/**
 * Wedding Gallery Configuration
 * Easy customization - Change settings below
 */

return [
    // ============================================
    // GALLERY INFORMATION
    // ============================================
    "galleryTitle"   => "Melvin & Elizabath",
    "welcomeMessage" => "Welcome to our special day! Thank you for being part of our celebration. Browse and enjoy the photos & videos from our wedding.",
    "weddingDate"    => "September 13, 2025",
    
    // ============================================
    // PASSWORD PROTECTION
    // ============================================
    'requirePassword' => false,
    'galleryPassword' => 'wedding2024',  // Change this!
    'useHashedPassword' => false,        // Set to true for better security
    // To use hashed password:
    // 1. Set 'useHashedPassword' => true
    // 2. Run this in terminal: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
    // 3. Copy the output and paste it as 'galleryPassword'
    
    // ============================================
    // THEME
    // ============================================
    'theme' => 'light',  // 'light' or 'dark'
    
    // ============================================
    // DIRECTORIES
    // ============================================
    'mediaDir' => __DIR__ . '/media',
    
    // ============================================
    // FILE TYPES
    // ============================================
    'imageExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'videoExtensions' => ['mp4', 'webm', 'ogg', 'mov'],
    
    // ============================================
    // SLIDER CONFIGURATION
    // ============================================
    'slider' => [
        'enabled' => true,                       // Show slider on home page
        'autoplay' => true,                      // Auto-advance slides
        'autoplayDelay' => 5000,                 // Milliseconds between slides
        'effect' => 'fade',                      // 'fade', 'slide', 'cube', 'coverflow', 'flip'
        'speed' => 1200,                         // Transition speed in ms
        'showNavigation' => false,               // Show prev/next arrows
        'showPagination' => true,                // Show dots
        'loop' => true,                          // Loop slides
        'slidesCount' => 10,                     // Number of images in slider
        'showTitle' => true,                     // Show couple names
        'showDate' => true,                      // Show wedding date
        'showMessage' => true,                   // Show welcome message on first slide
        'overlayOpacity' => 0.5,                 // 0-1, darkness of overlay
    ],
    
    // ============================================
    // GALLERY DISPLAY
    // ============================================
    'itemsPerPage' => 50,                        // Photos per page (pagination)
    
    // ============================================
    // AUTO-SYNC SETTINGS
    // ============================================
    'autoSyncEnabled' => true,                   // Automatic thumbnail generation on page load
    'autoSyncLimit' => 20,                       // Max images to generate per page load
    'autoCleanupEnabled' => true,                // Automatic cleanup of orphaned images
    'autoCleanupInterval' => 3600,               // Cleanup check interval (seconds)
    'autoCleanupLimit' => 5,                     // Max images to clean per check
    
    // ============================================
    // THUMBNAIL SETTINGS (for gallery grid)
    // ============================================
    'thumbnailWidth' => 1200,
    'thumbnailHeight' => 900,
    'thumbnailQuality' => 85,
    
    // ============================================
    // WEB-OPTIMIZED SETTINGS (for lightbox viewing)
    // These are shown in the lightbox for fast loading
    // Originals are used for downloads
    // ============================================
    'webOptimizedWidth' => 2000,                 // Max width for lightbox images
    'webOptimizedHeight' => 2000,                // Max height for lightbox images
    'webOptimizedQuality' => 82,                 // JPEG quality (60-95, 82 recommended)
    
    // ============================================
    // CACHE SETTINGS
    // ============================================
    'cacheDuration' => 3600,                     // 1 hour in seconds
    
    // ============================================
    // PERFORMANCE
    // ============================================
    'maxFileSize' => 500 * 1024 * 1024,          // 500MB max per file
];