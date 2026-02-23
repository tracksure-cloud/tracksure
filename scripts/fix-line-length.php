#!/usr/bin/env php
<?php
// Exit if accessed directly.
if (! defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Fix line length issues - SIMPLE VERSION
 * 
 * Splits only the safest long lines to avoid complexity.
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
    $lines = file($file_path, FILE_IGNORE_NEW_LINES);
    $modified = false;
    $new_lines = [];

    foreach ($lines as $line) {
        // Only split simple string concatenations over 150 chars
        if (strlen($line) > 150 && preg_match('/^(\s+)(.+?)\s+\.\s+(.+)$/', $line, $matches)) {
            $indent = $matches[1];
            $part1 = $matches[2];
            $part2 = $matches[3];

            // Only split if first part is under 120 chars
            if (strlen($part1) < 120) {
                $new_lines[] = $indent . $part1 . ' .';
                $new_lines[] = $indent . "\t" . $part2;
                $modified = true;
                $total_fixes++;
                continue;
            }
        }

        $new_lines[] = $line;
    }

    if ($modified) {
        file_put_contents($file_path, implode("\n", $new_lines) . "\n");
        echo "✓ Fixed " . basename($file_path) . "\n";
    }
}

echo "\n✅ Total: Simplified {$total_fixes} long lines\n";
