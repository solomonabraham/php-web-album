<?php
/**
 * ImageProcessor Class
 * Centralizes all image and video thumbnail generation logic.
 * Integrates SmartCrop for better visual results on the gallery grid.
 * * Version 2.3 - Refactored for OOP
 */

require_once __DIR__ . '/ErrorLogger.php';
require_once __DIR__ . '/SmartCrop.php';

class ImageProcessor {
    private $config;
    private $errorLogger;

    public function __construct(array $config) {
        $this->config = $config;
        // Instantiate ErrorLogger
        $this->errorLogger = new ErrorLogger(); 
        
        // Auto-fix directories (moved from index.php/maintenance.php)
        $this->autoFixDirectories();
    }
    
    /**
     * Ensures necessary media sub-directories exist and are writable.
     */
    private function autoFixDirectories() {
        $thumbDir = $this->config['mediaDir'] . '/thumbnails';
        $webOptimizedDir = $this->config['mediaDir'] . '/web-optimized';

        if (!is_dir($thumbDir)) {
            @mkdir($thumbDir, 0755, true);
        }

        if (!is_dir($webOptimizedDir)) {
            @mkdir($webOptimizedDir, 0755, true);
        }

        if (!is_writable($thumbDir)) {
            @chmod($thumbDir, 0755);
        }

        if (!is_writable($webOptimizedDir)) {
            @chmod($webOptimizedDir, 0755);
        }
    }

    /**
     * Creates the main thumbnail using SmartCrop logic (for the gallery grid).
     * @param string $source Path to original file.
     * @param string $dest Path to save thumbnail.
     * @return string|false Path to thumbnail on success, false on failure.
     */
    public function createThumbnail(string $source, string $dest): string|false {
        if (!file_exists($source)) return false;
        
        if (file_exists($dest) && filemtime($dest) >= filemtime($source)) {
            return $dest;
        }

        $fileSize = @filesize($source);
        if ($fileSize === false || $fileSize > $this->config['maxFileSize']) return false;

        $cropConfig = [
            'quality' => $this->config['thumbnailQuality'],
        ];

        // Instantiate SmartCrop and use it for content-aware thumbnail generation
        $smartCrop = new SmartCrop($cropConfig, $this->errorLogger);

        if ($smartCrop->createSmartThumbnail(
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
     * Creates a web-optimized image using proportional scaling (for lightbox).
     * @param string $source Path to original file.
     * @param string $dest Path to save web-optimized image.
     * @return string|false Path to image on success, false on failure.
     */
    public function createWebOptimizedImage(string $source, string $dest): string|false {
        if (!file_exists($source)) return false;
        
        if (file_exists($dest) && filemtime($dest) >= filemtime($source)) {
            return $dest;
        }
        
        // Use proportional resizing for web-optimized view
        if ($this->createProportionalImage(
            $source, 
            $dest, 
            $this->config['webOptimizedWidth'], 
            $this->config['webOptimizedHeight'], 
            $this->config['webOptimizedQuality']
        )) {
            return $dest;
        }
        
        return false;
    }

    /**
     * Creates a video thumbnail using FFmpeg.
     * @param string $source Path to original video file.
     * @param string $dest Path to save thumbnail (JPG).
     * @return string|false Path to thumbnail on success, false on failure.
     */
    public function createVideoThumbnail(string $source, string $dest): string|false {
        if (!file_exists($source)) return false;
        
        if (file_exists($dest) && filemtime($dest) >= filemtime($source)) {
            return $dest;
        }
        
        if (!is_dir(dirname($dest))) {
            if (!@mkdir(dirname($dest), 0755, true)) return false;
        }
        
        $ffmpegPath = trim(shell_exec('which ffmpeg 2>/dev/null'));
        if (empty($ffmpegPath)) {
            $this->errorLogger->warning("FFmpeg not found - cannot create video thumbnails");
            return false;
        }
        
        $command = sprintf(
            '%s -i %s -ss 00:00:01.000 -vframes 1 -vf scale=%d:-1 -q:v 2 -y %s 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($source),
            // Use thumbnail width for video previews
            $this->config['thumbnailWidth'], 
            escapeshellarg($dest)
        );
        
        shell_exec($command);
        
        if (file_exists($dest) && @filesize($dest) > 0) {
            return $dest;
        }
        
        $this->errorLogger->error("Video thumbnail failed", ['source' => $source, 'output' => $output ?? 'No output']);
        return false;
    }
    
    /**
     * Generic GD-based proportional image creation.
     */
    private function createProportionalImage(string $source, string $dest, int $maxWidth, int $maxHeight, int $quality): bool {
        if (!file_exists($source)) return false;
        
        if (!is_dir(dirname($dest))) {
            if (!@mkdir(dirname($dest), 0755, true)) return false;
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
            
            $result = imagejpeg($thumb, $dest, $quality);
            
            imagedestroy($img);
            imagedestroy($thumb);
            
            return $result;
            
        } catch (Exception $e) {
            $this->errorLogger->error("Proportional image error: " . $e->getMessage());
            return false;
        }
    }
}