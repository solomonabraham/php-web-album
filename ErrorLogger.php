<?php
/**
 * Simple Error Logger for Wedding Gallery
 * Logs errors to a file for debugging
 */

class ErrorLogger {
    private static $logFile;
    private static $enabled = true;
    
    /**
     * Initialize logger
     */
    private static function init() {
        if (self::$logFile === null) {
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            self::$logFile = $logDir . '/gallery.log';
        }
    }
    
    /**
     * Log an info message
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log an error message
     */
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log a critical message
     */
    public static function critical($message, $context = []) {
        self::log('CRITICAL', $message, $context);
    }
    
    /**
     * Main logging function
     */
    private static function log($level, $message, $context = []) {
        if (!self::$enabled) return;
        
        self::init();
        
        // Format timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Format context if provided
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        
        // Format log entry
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        
        // Write to log file
        @file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log errors to PHP error log
        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            error_log("Gallery $level: $message");
        }
    }
    
    /**
     * Clear log file
     */
    public static function clear() {
        self::init();
        if (file_exists(self::$logFile)) {
            @unlink(self::$logFile);
        }
    }
    
    /**
     * Get recent log entries
     */
    public static function getRecentLogs($lines = 50) {
        self::init();
        
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $content = @file(self::$logFile);
        if ($content === false) {
            return [];
        }
        
        return array_slice($content, -$lines);
    }
    
    /**
     * Enable/disable logging
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }
}