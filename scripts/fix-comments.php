#!/usr/bin/env php
<?php
// Exit if accessed directly.
if (! defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Fix inline comment formatting
 * 
 * Adds proper punctuation to inline comments that lack it.
 * Addresses ~800 Squiz.Commenting.InlineComment.InvalidEndChar violations.
 */

$plugin_dir = dirname(__DIR__);
$files_to_process = [];

// Find all PHP files in includes/
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir . '/includes')
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files_to_process[] = $file->getPathname();
    }
}

// Add main plugin file
$files_to_process[] = $plugin_dir . '/tracksure.php';

$total_fixes = 0;

foreach ($files_to_process as $file_path) {
    $content = file_get_contents($file_path);
    $original = $content;
    $fixes = 0;

    // Fix inline comments that don't end with proper punctuation
    // Pattern: // Comment without punctuation at end
    $content = preg_replace_callback(
        '/^(\s*)\/\/\s+(.+?)(\s*)$/m',
        function ($matches) use (&$fixes) {
            $indent = $matches[1];
            $comment = trim($matches[2]);

            // Skip if already has proper punctuation
            if (preg_match('/[.!?:;]$/', $comment)) {
                return $matches[0];
            }

            // Skip if it's a short comment (likely a label)
            if (strlen($comment) < 10) {
                return $matches[0];
            }

            // Skip if it's a TODO, FIXME, etc.
            if (preg_match('/^(TODO|FIXME|NOTE|XXX|HACK):/i', $comment)) {
                return $matches[0];
            }

            // Add period
            $fixes++;
            return $indent . '// ' . $comment . '.';
        },
        $content
    );

    if ($content !== $original) {
        file_put_contents($file_path, $content);
        echo "✓ Fixed {$fixes} comments in " . basename($file_path) . "\n";
        $total_fixes += $fixes;
    }
}

echo "\n✅ Total: Fixed {$total_fixes} inline comments\n";
