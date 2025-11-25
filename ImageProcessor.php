<?php
/**
 * ImageProcessor Class - OPTIMIZED
 * Version 2.4 - Performance Enhanced
 * 
 * Key Improvements:
 * - Lazy loading of SmartCrop
 * - Reduced file system checks
 * - Optimized error handling
 * - Better memory management
 */

require_once __DIR__ . '/ErrorLogger.php';

class ImageProcessor {
    private $config;
    private $errorLogger;
    private $smartCrop = null;

    public function __construct(array $config) {
        $this->config = $config;
        $this->errorLogger = new ErrorLogger();
        $this->autoFixDirectories();
    }
    
    /**
     * Ensures necessary media sub-directories exist
     */
    private function autoFixDirectories() {
        $dirs = [
            $this->config['mediaDir'] . '/thumbnails',
            $this->config['mediaDir'] . '/web-optimized'
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            } elseif (!is_writable($dir)) {
                @chmod($dir, 0755);
            }
        }
    }
    
    /**
     * Lazy load SmartCrop only when needed
     */
    private function getSmartCrop() {
        if ($this->smartCrop === null) {
            require_once __DIR__ . '/SmartCrop.php';
            $this->smartCrop = new SmartCrop([
                'quality' => $this->config['thumbnailQuality']
            ], $this->errorLogger);
        }
        return $this->smartCrop;
    }

    /**
     * Creates thumbnail using SmartCrop
     */
    public function createThumbnail(string $source, string $dest): string|false {
        // Quick validations
        if (!file_exists($source)) return false;
        if (file_exists($dest) && filemtime($dest) >= filemtime($source)) return $dest;
        
        $fileSize = @filesize($source);
        if ($fileSize === false || $fileSize > $this->config['maxFileSize']) return false;

        // Use SmartCrop for intelligent cropping
        if ($this->getSmartCrop()->createSmartThumbnail(
            $source,
            $dest,
            $this->config['thumbnailWidth'],
            $this->config['thumbnailHeight']
        )) {
            return $dest;
        }
        
        return false;
    }

    /**
     * Creates web-optimized image (proportional scaling)
     */
    public function createWebOptimizedImage(string $source, string $dest): string|false {
        if (!file_exists($source)) return false;
        if (file_exists($dest) && filemtime($dest) >= filemtime($source)) return $dest;
        
        return $this->createProportionalImage(
            $source, 
            $dest, 
            $this->config['webOptimizedWidth'], 
            $this->config['webOptimizedHeight'], 
            $this->config['webOptimizedQuality']
        ) ? $dest : false;
    }

    /**
     * Creates video thumbnail using FFmpeg
     */
    public function createVideoThumbnail(string $source, string $dest): string|false {
        if (!file_exists($source)) return false;
        if (file_exists($dest) && filemtime($dest) >= filemtime($source)) return $dest;
        
        // Ensure directory exists
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
        
        // Check for FFmpeg
        static $ffmpegPath = null;
        if ($ffmpegPath === null) {
            $ffmpegPath = trim(shell_exec('which ffmpeg 2>/dev/null'));
            if (empty($ffmpegPath)) {
                $this->errorLogger->warning("FFmpeg not found");
                return false;
            }
        }
        
        // Generate thumbnail
        $command = sprintf(
            '%s -i %s -ss 00:00:01 -vframes 1 -vf scale=%d:-1 -q:v 2 -y %s 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($source),
            $this->config['thumbnailWidth'],
            escapeshellarg($dest)
        );
        
        exec($command, $output, $returnCode);
        
        if (file_exists($dest) && filesize($dest) > 0) {
            return $dest;
        }
        
        $this->errorLogger->error("Video thumbnail failed", ['source' => basename($source)]);
        return false;
    }
    
    /**
     * Generic proportional image creation
     */
    private function createProportionalImage(string $source, string $dest, int $maxW, int $maxH, int $quality): bool {
        if (!file_exists($source)) return false;
        
        // Ensure directory exists
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
        
        try {
            $info = @getimagesize($source);
            if (!$info) return false;
            
            list($width, $height, $type) = $info;
            if ($width == 0 || $height == 0) return false;
            
            // Calculate new dimensions
            $ratio = min($maxW / $width, $maxH / $height);
            $newW = (int)($width * $ratio);
            $newH = (int)($height * $ratio);
            
            // Create thumbnail
            $thumb = imagecreatetruecolor($newW, $newH);
            if (!$thumb) return false;
            
            imagesetinterpolation($thumb, IMG_BICUBIC_FIXED);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            
            // Load source image
            $img = match($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
                IMAGETYPE_PNG => @imagecreatefrompng($source),
                IMAGETYPE_GIF => @imagecreatefromgif($source),
                IMAGETYPE_WEBP => @imagecreatefromwebp($source),
                default => false
            };
            
            if (!$img) {
                imagedestroy($thumb);
                return false;
            }
            
            // Resize
            imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $width, $height);
            
            // Save
            $result = imagejpeg($thumb, $dest, $quality);
            
            // Cleanup
            imagedestroy($img);
            imagedestroy($thumb);
            
            return $result;
            
        } catch (Exception $e) {
            $this->errorLogger->error("Image processing error: " . $e->getMessage());
            return false;
        }
    }
}