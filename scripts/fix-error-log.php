#!/usr/bin/env php
<?php
// Exit if accessed directly.
if (! defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Replace error_log() with WordPress debug logging
 * 
 * Replaces error_log() calls with proper WordPress debug logging.
 * Addresses ~20 WordPress.PHP.DevelopmentFunctions.error_log violations.
 */

$plugin_dir = dirname(__DIR__);
$files_to_process = [];

// Find all PHP files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir . '/includes')
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files_to_process[] = $file->getPathname();
    }
}

$files_to_process[] = $plugin_dir . '/tracksure.php';

$total_fixes = 0;

foreach ($files_to_process as $file_path) {
    $content = file_get_contents($file_path);
    $original = $content;
    $fixes = 0;

    // Replace: error_log($message)
    // With: if (defined('WP_DEBUG') && WP_DEBUG) { error_log($message); }
    $content = preg_replace_callback(
        '/(\s+)error_log\s*\(\s*(.+?)\s*\)\s*;/s',
        function ($matches) use (&$fixes) {
            $indent = $matches[1];
            $message = $matches[2];

            $fixes++;
            return $indent . "if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {\n" .
                $indent . "\terror_log( " . $message . " );\n" .
                $indent . "}";
        },
        $content
    );

    if ($content !== $original) {
        file_put_contents($file_path, $content);
        echo "✓ Fixed {$fixes} error_log() calls in " . basename($file_path) . "\n";
        $total_fixes += $fixes;
    }
}

echo "\n✅ Total: Wrapped {$total_fixes} error_log() calls with WP_DEBUG check\n";
