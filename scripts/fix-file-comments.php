#!/usr/bin/env php
<?php
// Exit if accessed directly.
if (! defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Fix file comment spacing
 * 
 * Fixes spacing after opening file comment blocks.
 * Addresses Squiz.Commenting.FileComment.SpacingAfterOpen violations.
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

    // Fix file comment spacing - should have one blank line after /**
    // Pattern: /**\n * @package ... (no blank line after /**)
    // Fix: /**\n *\n * @package ...
    $content = preg_replace(
        '/^(<\?php\s*\n)(\/\*\*\n)(\s*\*\s*@)/',
        "$1$2 *\n$3",
        $content
    );

    // Also fix if there's a description right after /**
    $content = preg_replace(
        '/^(<\?php\s*\n)(\/\*\*\n)(\s*\*\s*[A-Z])/',
        "$1$2 *\n$3",
        $content
    );

    if ($content !== $original) {
        file_put_contents($file_path, $content);
        echo "✓ Fixed file comment spacing in " . basename($file_path) . "\n";
        $total_fixes++;
    }
}

echo "\n✅ Total: Fixed {$total_fixes} file comment spacing issues\n";
