<?php
/**
 * Wedding Gallery - System Check & Diagnostic Tool
 * Comprehensive system requirements and configuration checker
 * Version 1.0
 *
 * MODIFIED: Uses ConfigManager instead of config.php include.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/ConfigManager.php'; // NEW: Include ConfigManager

// Load configuration using the manager
$configManager = ConfigManager::getInstance();
$config = $configManager->getAll();

if (!$config) {
    die("ERROR: Configuration data could not be loaded from ConfigManager!");
}

// Initialize results
$checks = [];
$warnings = [];
$errors = [];
$info = [];

// ============================================
// HELPER FUNCTIONS
// ============================================

function checkStatus($condition, $message, $type = 'check') {
    global $checks, $warnings, $errors;
    
    if ($type === 'check') {
        $checks[] = [
            'status' => $condition,
            'message' => $message
        ];
        
        if (!$condition) {
            $errors[] = $message;
        }
    } elseif ($type === 'warning') {
        $warnings[] = [
            'status' => $condition,
            'message' => $message
        ];
    }
    
    return $condition;
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getColorClass($status) {
    return $status ? 'success' : 'error';
}

function getIcon($status) {
    return $status ? '✓' : '✗';
}

// ============================================
// SYSTEM CHECKS
// ============================================

// PHP Version
$phpVersion = phpversion();
$phpVersionOk = version_compare($phpVersion, '8.0.0', '>=');
checkStatus($phpVersionOk, "PHP Version: $phpVersion (Minimum: 8.0.0)");

// Required Extensions
$gdInstalled = extension_loaded('gd');
checkStatus($gdInstalled, "GD Library: " . ($gdInstalled ? 'Installed' : 'Not Installed'));

$mbstringInstalled = extension_loaded('mbstring');
checkStatus($mbstringInstalled, "mbstring Extension: " . ($mbstringInstalled ? 'Installed' : 'Not Installed'), 'warning');

$jsonInstalled = extension_loaded('json');
checkStatus($jsonInstalled, "JSON Extension: " . ($jsonInstalled ? 'Installed' : 'Not Installed'));

// Memory Limit
$memoryLimit = ini_get('memory_limit');
$memoryBytes = 0;
if (preg_match('/^(\d+)(.)$/', $memoryLimit, $matches)) {
    $memoryBytes = $matches[1];
    switch ($matches[2]) {
        case 'G': $memoryBytes *= 1024;
        case 'M': $memoryBytes *= 1024;
        case 'K': $memoryBytes *= 1024;
    }
}
$memoryOk = $memoryBytes >= (512 * 1024 * 1024) || $memoryLimit === '-1';
checkStatus($memoryOk, "Memory Limit: $memoryLimit (Recommended: 512M+)", $memoryOk ? 'check' : 'warning');

// Max Execution Time
$maxExecTime = ini_get('max_execution_time');
$execTimeOk = $maxExecTime >= 60 || $maxExecTime == 0;
checkStatus($execTimeOk, "Max Execution Time: {$maxExecTime}s (Recommended: 60s+)", $execTimeOk ? 'check' : 'warning');

// Upload Limits
$uploadMaxSize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$info[] = "Upload Max Filesize: $uploadMaxSize";
$info[] = "POST Max Size: $postMaxSize";

// ============================================
// DIRECTORY CHECKS
// ============================================

$mediaDir = $config['mediaDir'] ?? __DIR__ . '/media';
$thumbDir = $mediaDir . '/thumbnails';
$webOptimizedDir = $mediaDir . '/web-optimized';
$cacheDir = __DIR__ . '/cache';
$logsDir = __DIR__ . '/logs';

// Media Directory
$mediaDirExists = is_dir($mediaDir);
checkStatus($mediaDirExists, "Media Directory Exists: " . ($mediaDirExists ? 'Yes' : 'No'));

if ($mediaDirExists) {
    $mediaDirWritable = is_writable($mediaDir);
    checkStatus($mediaDirWritable, "Media Directory Writable: " . ($mediaDirWritable ? 'Yes' : 'No'));
    
    $mediaDirPerms = substr(sprintf('%o', fileperms($mediaDir)), -4);
    $info[] = "Media Directory Permissions: $mediaDirPerms";
}

// Thumbnails Directory
$thumbDirExists = is_dir($thumbDir);
checkStatus($thumbDirExists, "Thumbnails Directory Exists: " . ($thumbDirExists ? 'Yes' : 'No'), 'warning');

if ($thumbDirExists) {
    $thumbDirWritable = is_writable($thumbDir);
    checkStatus($thumbDirWritable, "Thumbnails Directory Writable: " . ($thumbDirWritable ? 'Yes' : 'No'));
    
    $thumbDirPerms = substr(sprintf('%o', fileperms($thumbDir)), -4);
    $info[] = "Thumbnails Directory Permissions: $thumbDirPerms";
}

// Web-Optimized Directory
$webOptimizedDirExists = is_dir($webOptimizedDir);
checkStatus($webOptimizedDirExists, "Web-Optimized Directory Exists: " . ($webOptimizedDirExists ? 'Yes' : 'No'), 'warning');

if ($webOptimizedDirExists) {
    $webOptimizedDirWritable = is_writable($webOptimizedDir);
    checkStatus($webOptimizedDirWritable, "Web-Optimized Directory Writable: " . ($webOptimizedDirWritable ? 'Yes' : 'No'));
    
    $webOptimizedDirPerms = substr(sprintf('%o', fileperms($webOptimizedDir)), -4);
    $info[] = "Web-Optimized Directory Permissions: $webOptimizedDirPerms";
}

// Cache Directory
$cacheDirExists = is_dir($cacheDir);
checkStatus($cacheDirExists, "Cache Directory Exists: " . ($cacheDirExists ? 'Yes' : 'No'), 'warning');

if ($cacheDirExists) {
    $cacheDirWritable = is_writable($cacheDir);
    checkStatus($cacheDirWritable, "Cache Directory Writable: " . ($cacheDirWritable ? 'Yes' : 'No'));
}

// Logs Directory
$logsDirExists = is_dir($logsDir);
checkStatus($logsDirExists, "Logs Directory Exists: " . ($logsDirExists ? 'Yes' : 'No'), 'warning');

if ($logsDirExists) {
    $logsDirWritable = is_writable($logsDir);
    checkStatus($logsDirWritable, "Logs Directory Writable: " . ($logsDirWritable ? 'Yes' : 'No'), 'warning');
}

// ============================================
// FILE CHECKS
// ============================================

$requiredFiles = [
    'index.php',
    'ConfigManager.php', // Updated to ConfigManager
    'ErrorLogger.php',
    'maintenance.php',
    'styles.css',
    'SmartCrop.php', // Added SmartCrop
    'ImageProcessor.php' // Added ImageProcessor
];

foreach ($requiredFiles as $file) {
    $filePath = __DIR__ . '/' . $file;
    $fileExists = file_exists($filePath);
    checkStatus($fileExists, "Required File '$file': " . ($fileExists ? 'Found' : 'Missing'));
    
    if ($fileExists) {
        $fileReadable = is_readable($filePath);
        checkStatus($fileReadable, "File '$file' Readable: " . ($fileReadable ? 'Yes' : 'No'));
    }
}

// ============================================
// CONFIGURATION CHECKS
// ============================================

// Check critical config values
$configChecks = [
    'galleryTitle' => isset($config['galleryTitle']) && !empty($config['galleryTitle']),
    'mediaDir' => isset($config['mediaDir']),
    'imageExtensions' => isset($config['imageExtensions']) && is_array($config['imageExtensions']),
    'videoExtensions' => isset($config['videoExtensions']) && is_array($config['videoExtensions']),
    'thumbnailWidth' => isset($config['thumbnailWidth']) && $config['thumbnailWidth'] > 0,
    'thumbnailHeight' => isset($config['thumbnailHeight']) && $config['thumbnailHeight'] > 0,
];

foreach ($configChecks as $key => $status) {
    checkStatus($status, "Config '$key': " . ($status ? 'Valid' : 'Invalid'));
}

// Password Configuration
if ($config['requirePassword'] ?? false) {
    $passwordSet = !empty($config['galleryPassword']);
    checkStatus($passwordSet, "Password Protection: " . ($passwordSet ? 'Configured' : 'Enabled but No Password Set'));
    
    if ($passwordSet) {
        $useHashed = $config['useHashedPassword'] ?? false;
        $info[] = "Password Type: " . ($useHashed ? 'Hashed (Secure)' : 'Plain Text (Consider using hashed)');
    }
} else {
    $info[] = "Password Protection: Disabled";
}

// ============================================
// IMAGE PROCESSING CHECKS
// ============================================

if ($gdInstalled) {
    $gdInfo = gd_info();
    $info[] = "GD Version: " . ($gdInfo['GD Version'] ?? 'Unknown');
    
    // Supported formats
    $jpegSupport = $gdInfo['JPEG Support'] ?? false;
    checkStatus($jpegSupport, "GD JPEG Support: " . ($jpegSupport ? 'Yes' : 'No'));
    
    $pngSupport = $gdInfo['PNG Support'] ?? false;
    checkStatus($pngSupport, "GD PNG Support: " . ($pngSupport ? 'Yes' : 'No'));
    
    $gifSupport = $gdInfo['GIF Create Support'] ?? false;
    checkStatus($gifSupport, "GD GIF Support: " . ($gifSupport ? 'Yes' : 'No'), 'warning');
    
    $webpSupport = $gdInfo['WebP Support'] ?? false;
    checkStatus($webpSupport, "GD WebP Support: " . ($webpSupport ? 'Yes' : 'No'), 'warning');
}

// ============================================
// VIDEO PROCESSING CHECKS
// ============================================

$ffmpegPath = trim(shell_exec('which ffmpeg 2>/dev/null'));
$ffmpegInstalled = !empty($ffmpegPath);
checkStatus($ffmpegInstalled, "FFmpeg: " . ($ffmpegInstalled ? "Installed ($ffmpegPath)" : 'Not Installed (Video thumbnails will not work)'), 'warning');

if ($ffmpegInstalled) {
    $ffmpegVersion = trim(shell_exec('ffmpeg -version 2>/dev/null | head -n 1'));
    $info[] = "FFmpeg Version: $ffmpegVersion";
}

// ============================================
// MEDIA CONTENT CHECKS
// ============================================

if ($mediaDirExists) {
    $albums = [];
    $totalImages = 0;
    $totalVideos = 0;
    $totalThumbnails = 0;
    $totalWebOptimized = 0;
    
    $subfolders = glob($mediaDir . '/*', GLOB_ONLYDIR);
    if ($subfolders !== false) {
        $thumbFolderName = basename($thumbDir);
        $webOptimizedFolderName = basename($webOptimizedDir);
        
        $subfolders = array_filter($subfolders, fn($f) => 
            !in_array(basename($f), [$thumbFolderName, $webOptimizedFolderName]) && 
            !str_starts_with(basename($f), '.')
        );
        
        foreach ($subfolders as $albumPath) {
            $albumName = basename($albumPath);
            $files = @scandir($albumPath);
            
            if ($files !== false) {
                $imageCount = 0;
                $videoCount = 0;
                
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || str_starts_with($file, '.')) continue;
                    
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, $config['imageExtensions'])) {
                        $imageCount++;
                    } elseif (in_array($ext, $config['videoExtensions'])) {
                        $videoCount++;
                    }
                }
                
                $albums[$albumName] = [
                    'images' => $imageCount,
                    'videos' => $videoCount,
                    'total' => $imageCount + $videoCount
                ];
                
                $totalImages += $imageCount;
                $totalVideos += $videoCount;
            }
        }
    }
    
    // Count thumbnails
    if ($thumbDirExists) {
        $thumbSubfolders = glob($thumbDir . '/*', GLOB_ONLYDIR);
        if ($thumbSubfolders !== false) {
            foreach ($thumbSubfolders as $thumbAlbumPath) {
                $thumbFiles = @scandir($thumbAlbumPath);
                if ($thumbFiles !== false) {
                    $totalThumbnails += count(array_filter($thumbFiles, fn($f) => !in_array($f, ['.', '..'])));
                }
            }
        }
    }
    
    // Count web-optimized
    if ($webOptimizedDirExists) {
        $webOptimizedSubfolders = glob($webOptimizedDir . '/*', GLOB_ONLYDIR);
        if ($webOptimizedSubfolders !== false) {
            foreach ($webOptimizedSubfolders as $webOptimizedAlbumPath) {
                $webOptimizedFiles = @scandir($webOptimizedAlbumPath);
                if ($webOptimizedFiles !== false) {
                    $totalWebOptimized += count(array_filter($webOptimizedFiles, fn($f) => !in_array($f, ['.', '..'])));
                }
            }
        }
    }
    
    $info[] = "Total Albums: " . count($albums);
    $info[] = "Total Images: $totalImages";
    $info[] = "Total Videos: $totalVideos";
    $info[] = "Total Thumbnails: $totalThumbnails";
    $info[] = "Total Web-Optimized: $totalWebOptimized";
    
    $totalMedia = $totalImages + $totalVideos;
    if ($totalMedia > 0) {
        $thumbCoverage = round(($totalThumbnails / $totalMedia) * 100, 1);
        $webOptimizedCoverage = round(($totalWebOptimized / $totalMedia) * 100, 1);
        
        $thumbComplete = $thumbCoverage >= 100;
        $webOptimizedComplete = $webOptimizedCoverage >= 100;
        
        checkStatus($thumbComplete, "Thumbnail Coverage: {$thumbCoverage}% ({$totalThumbnails}/{$totalMedia})", $thumbComplete ? 'check' : 'warning');
        checkStatus($webOptimizedComplete, "Web-Optimized Coverage: {$webOptimizedCoverage}% ({$totalWebOptimized}/{$totalMedia})", $webOptimizedComplete ? 'check' : 'warning');
    }
}

// ============================================
// CACHE & PERFORMANCE CHECKS
// ============================================

$cacheFile = __DIR__ . '/cache/gallery_cache.json';
$cacheExists = file_exists($cacheFile);
$info[] = "Cache File: " . ($cacheExists ? 'Generated' : 'Not Generated');

if ($cacheExists) {
    $cacheAge = time() - filemtime($cacheFile);
    $cacheAgeMinutes = round($cacheAge / 60);
    $cacheDuration = $config['cacheDuration'] ?? 3600;
    $cacheValid = $cacheAge < $cacheDuration;
    
    $info[] = "Cache Age: {$cacheAgeMinutes} minutes (" . ($cacheValid ? 'Valid' : 'Expired') . ")";
    $info[] = "Cache Size: " . formatBytes(filesize($cacheFile));
}

// ============================================
// SECURITY CHECKS
// ============================================

$htaccessExists = file_exists(__DIR__ . '/.htaccess');
$info[] = ".htaccess: " . ($htaccessExists ? 'Present' : 'Not Found (Consider adding for security)');

// Check if sensitive files are accessible
$sensitiveFiles = ['ConfigManager.php', 'ErrorLogger.php'];
$info[] = "Security Note: Ensure ConfigManager.php and ErrorLogger.php are not directly accessible via browser";

// ============================================
// RECOMMENDATIONS
// ============================================

$recommendations = [];

if (!$thumbDirExists || !$webOptimizedDirExists) {
    $recommendations[] = "Run maintenance.php to create missing directories";
}

if ($totalMedia > 0 && ($totalThumbnails < $totalMedia || $totalWebOptimized < $totalMedia)) {
    $recommendations[] = "Run maintenance.php to generate missing thumbnails and web-optimized images";
}

if (!$ffmpegInstalled && $totalVideos > 0) {
    $recommendations[] = "Install FFmpeg to enable video thumbnail generation";
}

if ($config['requirePassword'] && !($config['useHashedPassword'] ?? false)) {
    $recommendations[] = "Use hashed passwords for better security (set useHashedPassword to true)";
}

if (!$memoryOk) {
    $recommendations[] = "Increase PHP memory_limit to at least 512M in php.ini";
}

if (!$execTimeOk) {
    $recommendations[] = "Increase max_execution_time to at least 60 seconds in php.ini";
}

if (!$htaccessExists) {
    $recommendations[] = "Create .htaccess file to enhance security and performance";
}

if (count($albums ?? []) === 0) {
    $recommendations[] = "Create album folders in the media directory and upload photos";
}

// Calculate overall status
$totalChecks = count($checks);
$passedChecks = count(array_filter($checks, fn($c) => $c['status']));
$overallStatus = $passedChecks === $totalChecks ? 'excellent' : ($passedChecks >= $totalChecks * 0.8 ? 'good' : 'needs_attention');

// ============================================
// AUTO-FIX CAPABILITIES
// ============================================

$autoFixAvailable = [];

if (!$cacheDirExists) {
    $autoFixAvailable[] = [
        'issue' => 'Cache directory missing',
        'action' => 'create_cache_dir',
        'label' => 'Create Cache Directory'
    ];
}

if (!$logsDirExists) {
    $autoFixAvailable[] = [
        'issue' => 'Logs directory missing',
        'action' => 'create_logs_dir',
        'label' => 'Create Logs Directory'
    ];
}

if (!$thumbDirExists) {
    $autoFixAvailable[] = [
        'issue' => 'Thumbnails directory missing',
        'action' => 'create_thumb_dir',
        'label' => 'Create Thumbnails Directory'
    ];
}

if (!$webOptimizedDirExists) {
    $autoFixAvailable[] = [
        'issue' => 'Web-optimized directory missing',
        'action' => 'create_webopt_dir',
        'label' => 'Create Web-Optimized Directory'
    ];
}

// Handle auto-fix actions
if (isset($_POST['autofix'])) {
    $action = $_POST['autofix'];
    $fixResult = '';
    
    switch ($action) {
        case 'create_cache_dir':
            if (@mkdir($cacheDir, 0755, true)) {
                $fixResult = 'success:Cache directory created successfully';
            } else {
                $fixResult = 'error:Failed to create cache directory';
            }
            break;
            
        case 'create_logs_dir':
            if (@mkdir($logsDir, 0755, true)) {
                $fixResult = 'success:Logs directory created successfully';
            } else {
                $fixResult = 'error:Failed to create logs directory';
            }
            break;
            
        case 'create_thumb_dir':
            if (@mkdir($thumbDir, 0755, true)) {
                $fixResult = 'success:Thumbnails directory created successfully';
            } else {
                $fixResult = 'error:Failed to create thumbnails directory';
            }
            break;
            
        case 'create_webopt_dir':
            if (@mkdir($webOptimizedDir, 0755, true)) {
                $fixResult = 'success:Web-optimized directory created successfully';
            } else {
                $fixResult = 'error:Failed to create web-optimized directory';
            }
            break;
            
        case 'fix_permissions':
            $fixed = 0;
            $failed = 0;
            
            $dirsToFix = [
                $mediaDir,
                $thumbDir,
                $webOptimizedDir,
                $cacheDir,
                $logsDir
            ];
            
            foreach ($dirsToFix as $dir) {
                if (is_dir($dir)) {
                    if (@chmod($dir, 0755)) {
                        $fixed++;
                    } else {
                        $failed++;
                    }
                }
            }
            
            if ($fixed > 0) {
                $fixResult = "success:Fixed permissions for $fixed directories";
            } else {
                $fixResult = "error:Failed to fix directory permissions";
            }
            break;
            
        case 'clear_cache':
            $cacheFile = __DIR__ . '/cache/gallery_cache.json';
            if (file_exists($cacheFile)) {
                if (@unlink($cacheFile)) {
                    $fixResult = 'success:Cache cleared successfully';
                } else {
                    $fixResult = 'error:Failed to clear cache';
                }
            } else {
                $fixResult = 'info:No cache file to clear';
            }
            break;
    }
    
    if ($fixResult) {
        list($fixType, $fixMessage) = explode(':', $fixResult, 2);
        header("Location: ?fix_result=" . urlencode($fixResult));
        exit;
    }
}

// Display fix result
$fixResultMessage = '';
$fixResultType = '';
if (isset($_GET['fix_result'])) {
    list($fixResultType, $fixResultMessage) = explode(':', $_GET['fix_result'], 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Check - Wedding Gallery</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        
        .status-excellent {
            background: #4caf50;
            color: white;
        }
        
        .status-good {
            background: #ff9800;
            color: white;
        }
        
        .status-needs_attention {
            background: #f44336;
            color: white;
        }
        
        .content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 35px;
        }
        
        .section h2 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .check-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .check-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .check-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .success {
            background: #4caf50;
            color: white;
        }
        
        .error {
            background: #f44336;
            color: white;
        }
        
        .warning {
            background: #ff9800;
            color: white;
        }
        
        .info-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        .album-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .album-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .album-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #333;
        }
        
        .album-stats {
            font-size: 0.9rem;
            color: #666;
        }
        
        .recommendation {
            padding: 15px;
            margin-bottom: 12px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }
        
        .recommendation::before {
            content: '💡 ';
            margin-right: 8px;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }
        
        .summary-value {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .autofix-section {
            background: #f0f4ff;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .autofix-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .autofix-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .autofix-description {
            flex: 1;
            margin-right: 15px;
        }
        
        .autofix-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .autofix-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .autofix-btn:active {
            transform: translateY(0);
        }
        
        .fix-result {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        
        .fix-result.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .fix-result.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .fix-result.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .fix-result::before {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 10px;
            border-radius: 50%;
        }
        
        .fix-result.success::before {
            content: '✓';
            background: #28a745;
            color: white;
            text-align: center;
            line-height: 20px;
            font-size: 0.8rem;
        }
        
        .fix-result.error::before {
            content: '✗';
            background: #dc3545;
            color: white;
            text-align: center;
            line-height: 20px;
            font-size: 0.8rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .quick-action-btn {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 15px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        
        .quick-action-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8rem;
            }
            
            .content {
                padding: 20px;
            }
            
            .album-list {
                grid-template-columns: 1fr;
            }
            
            .autofix-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .autofix-description {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 System Diagnostic Check</h1>
            <p>Wedding Gallery v2.3 - System Requirements & Configuration</p>
            <span class="status-badge status-<?= $overallStatus ?>">
                <?php
                    echo match($overallStatus) {
                        'excellent' => '✓ All Systems Go',
                        'good' => '⚠ Minor Issues',
                        'needs_attention' => '✗ Needs Attention',
                        default => 'Unknown'
                    };
                ?>
            </span>
        </div>
        
        <div class="content">
            <?php if ($fixResultMessage): ?>
            <div class="fix-result <?= $fixResultType ?>">
                <?= htmlspecialchars($fixResultMessage) ?>
            </div>
            <?php endif; ?>
            
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-value"><?= $passedChecks ?>/<?= $totalChecks ?></div>
                    <div class="summary-label">Checks Passed</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?= count($warnings) ?></div>
                    <div class="summary-label">Warnings</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?= count($errors) ?></div>
                    <div class="summary-label">Critical Issues</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?= count($albums ?? []) ?></div>
                    <div class="summary-label">Albums Found</div>
                </div>
            </div>
            
            <?php if (!empty($autoFixAvailable)): ?>
            <div class="autofix-section">
                <h3>🔧 Quick Fixes Available</h3>
                <p style="margin-bottom: 20px; color: #666;">The following issues can be fixed automatically:</p>
                
                <?php foreach ($autoFixAvailable as $fix): ?>
                <form method="post" style="margin: 0;">
                    <div class="autofix-item">
                        <div class="autofix-description">
                            <strong><?= htmlspecialchars($fix['issue']) ?></strong>
                        </div>
                        <button type="submit" name="autofix" value="<?= htmlspecialchars($fix['action']) ?>" class="autofix-btn">
                            <?= htmlspecialchars($fix['label']) ?>
                        </button>
                    </div>
                </form>
                <?php endforeach; ?>
                
                <form method="post" style="margin: 0;">
                    <div class="autofix-item">
                        <div class="autofix-description">
                            <strong>Fix directory permissions</strong>
                        </div>
                        <button type="submit" name="autofix" value="fix_permissions" class="autofix-btn">
                            Fix Permissions
                        </button>
                    </div>
                </form>
                
                <form method="post" style="margin: 0;">
                    <div class="autofix-item">
                        <div class="autofix-description">
                            <strong>Clear cache file</strong>
                        </div>
                        <button type="submit" name="autofix" value="clear_cache" class="autofix-btn">
                            Clear Cache
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <h2>⚡ Quick Actions</h2>
                <div class="quick-actions">
                    <a href="index.php" class="quick-action-btn">🏠 Go to Gallery</a>
                    <a href="maintenance.php" class="quick-action-btn">🔧 Run Maintenance</a>
                    <a href="?refresh=1" class="quick-action-btn">🔄 Refresh Check</a>
                    <a href="config_editor.php" class="quick-action-btn">⚙️ Edit Config</a>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="section">
                <h2>❌ Critical Issues</h2>
                <?php foreach ($errors as $error): ?>
                    <div class="check-item">
                        <div class="check-icon error">✗</div>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <h2>⚙️ System Requirements</h2>
                <?php foreach ($checks as $check): ?>
                    <div class="check-item">
                        <div class="check-icon <?= getColorClass($check['status']) ?>">
                            <?= getIcon($check['status']) ?>
                        </div>
                        <span><?= htmlspecialchars($check['message']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($warnings)): ?>
            <div class="section">
                <h2>⚠️ Warnings</h2>
                <?php foreach ($warnings as $warning): ?>
                    <div class="check-item">
                        <div class="check-icon <?= getColorClass($warning['status']) ?>">
                            <?= $warning['status'] ? '✓' : '!' ?>
                        </div>
                        <span><?= htmlspecialchars($warning['message']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($info)): ?>
            <div class="section">
                <h2>ℹ️ System Information</h2>
                <?php foreach ($info as $infoItem): ?>
                    <div class="info-item"><?= htmlspecialchars($infoItem) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($albums)): ?>
            <div class="section">
                <h2>📁 Album Overview</h2>
                <div class="album-list">
                    <?php foreach ($albums as $albumName => $stats): ?>
                        <div class="album-card">
                            <div class="album-name"><?= htmlspecialchars(ucwords(str_replace(['-', '_'], ' ', $albumName))) ?></div>
                            <div class="album-stats">
                                📷 <?= $stats['images'] ?> images<br>
                                🎥 <?= $stats['videos'] ?> videos<br>
                                📊 <?= $stats['total'] ?> total
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($recommendations)): ?>
            <div class="section">
                <h2>💡 Recommendations</h2>
                <?php foreach ($recommendations as $recommendation): ?>
                    <div class="recommendation"><?= htmlspecialchars($recommendation) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>System check completed at <?= date('Y-m-d H:i:s') ?></p>
            <p style="margin-top: 10px;">
                <a href="index.php">← Back to Gallery</a> | 
                <a href="maintenance.php">Run Maintenance</a> | 
                <a href="?refresh=1">Refresh Check</a>
            </p>
            <p style="margin-top: 15px;">
                Developed by <a href="https://dreamgraphers.net" target="_blank">DREAMGRAPHERS</a>
            </p>
        </div>
    </div>
</body>
</html>