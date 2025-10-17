<?php
/**
 * ConfigManager Class
 * Handles loading, saving, and accessing configuration from a JSON file.
 * This file replaces the original config.php
 * * MODIFIED: Path updated to look in the 'config/' subdirectory.
 */

class ConfigManager {
    private static $instance = null;
    private $config = [];
    private $jsonFile;
    private $baseDir;

    private function __construct() { // Removed parameter as it's now hardcoded for better organization
        $this->baseDir = __DIR__;
        // UPDATED: Set the new path to config/config.json
        $this->jsonFile = $this->baseDir . '/config/config.json'; 
        $this->load();
    }

    /**
     * Get the singleton instance of ConfigManager.
     * @return ConfigManager
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load configuration from the JSON file.
     * @return bool
     */
    private function load() {
        if (!file_exists($this->jsonFile)) {
            $this->config = $this->getDefaultConfig();
            // Note: Saving defaults will now attempt to create config/config.json
            $this->save(); 
            return true;
        }

        $content = file_get_contents($this->jsonFile);
        $data = json_decode($content, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->config = $this->getDefaultConfig();
            return false;
        }

        $this->config = $data;
        $this->resolvePaths();
        return true;
    }

    /**
     * Resolve relative paths to absolute paths using the base directory.
     * Populates the 'mediaDir' key with the absolute path needed by the app.
     */
    private function resolvePaths() {
        if (isset($this->config['mediaDirRelative'])) {
            // Note: The path resolution logic remains robust enough to handle the mediaDirRelative entry
            $this->config['mediaDir'] = realpath($this->baseDir . '/' . $this->config['mediaDirRelative']);
        }
    }

    /**
     * Save configuration to the JSON file.
     * @param array|null $newConfig
     * @return bool
     */
    public function save($newConfig = null) {
        if ($newConfig) {
            $this->config = $newConfig;
        }
        
        // Remove derived absolute path before saving to keep JSON file clean
        $dataToSave = $this->config;
        unset($dataToSave['mediaDir']);

        // Ensure a relative path exists for saving
        if (!isset($dataToSave['mediaDirRelative'])) {
            $dataToSave['mediaDirRelative'] = './media'; 
        }
        
        // Ensure the config directory exists before saving
        $configDir = dirname($this->jsonFile);
        if (!is_dir($configDir)) {
             @mkdir($configDir, 0755, true);
        }

        $jsonContent = json_encode($dataToSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonContent === false) {
             return false;
        }
        
        // Ensure file is writable
        return file_put_contents($this->jsonFile, $jsonContent, LOCK_EX) !== false;
    }

    /**
     * Get all configuration data.
     * @return array
     */
    public function getAll() {
        return $this->config;
    }

    /**
     * Get the default configuration (used if JSON is missing/invalid).
     * @return array
     */
    private function getDefaultConfig() {
        // This array serves as a reliable fallback if config.json is corrupted or missing.
        return [
            "galleryTitle" => "Melvin & Elizabath",
            "welcomeMessage" => "Welcome to our special day! Thank you for being part of our celebration. Browse and enjoy the photos & videos from our wedding.",
            "weddingDate" => "September 13, 2025",
            "requirePassword" => false,
            "galleryPassword" => "wedding2024",
            "useHashedPassword" => false,
            "theme" => "light",
            "mediaDirRelative" => "./media",
            "imageExtensions" => ["jpg", "jpeg", "png", "gif", "webp"],
            "videoExtensions" => ["mp4", "webm", "ogg", "mov"],
            "slider" => [
                "enabled" => true,
                "autoplay" => true,
                "autoplayDelay" => 5000,
                "effect" => "fade",
                "speed" => 1200,
                "showNavigation" => false,
                "showPagination" => true,
                "loop" => true,
                "slidesCount" => 10,
                "showTitle" => true,
                "showDate" => true,
                "showMessage" => true,
                "overlayOpacity" => 0.5
            ],
            "itemsPerPage" => 50,
            "autoSyncEnabled" => true,
            "autoSyncLimit" => 20,
            "autoCleanupEnabled" => true,
            "autoCleanupInterval" => 3600,
            "autoCleanupLimit" => 5,
            "thumbnailWidth" => 1200,
            "thumbnailHeight" => 900,
            "thumbnailQuality" => 85,
            "webOptimizedWidth" => 2000,
            "webOptimizedHeight" => 2000,
            "webOptimizedQuality" => 82,
            "cacheDuration" => 3600,
            "maxFileSize" => 524288000
        ];
    }
}