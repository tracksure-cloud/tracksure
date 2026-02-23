#!/usr/bin/env php
<?php
// Exit if accessed directly.
if (! defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Fix MissingUnslash warnings
 * 
 * Adds wp_unslash() before sanitize_*() calls on superglobals.
 * Addresses ~400 WordPress.Security.ValidatedSanitizedInput.MissingUnslash warnings.
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

    // Pattern: sanitize_*($_GET['key']) or sanitize_*($_POST['key'])
    // Fix: sanitize_*(wp_unslash($_GET['key']))

    // Match: sanitize_text_field($_GET['something'])
    $content = preg_replace_callback(
        '/(sanitize_\w+)\s*\(\s*(\$_(GET|POST|REQUEST|COOKIE)\[[^\]]+\])\s*\)/i',
        function ($matches) use (&$fixes) {
            $sanitize_func = $matches[1];
            $superglobal = $matches[2];

            // Skip if already has wp_unslash
            if (strpos($superglobal, 'wp_unslash') !== false) {
                return $matches[0];
            }

            $fixes++;
            return $sanitize_func . '( wp_unslash( ' . $superglobal . ' ) )';
        },
        $content
    );

    if ($content !== $original) {
        file_put_contents($file_path, $content);
        echo "✓ Fixed {$fixes} unslash issues in " . basename($file_path) . "\n";
        $total_fixes += $fixes;
    }
}

echo "\n✅ Total: Added wp_unslash() to {$total_fixes} locations\n";
