#!/usr/bin/env php
<?php
// Exit if accessed directly.
if (! defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Fix missing @throws documentation
 * 
 * Adds @throws tags to functions that throw exceptions.
 * Addresses Squiz.Commenting.FunctionCommentThrowTag.Missing violations.
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

    // Find functions that throw exceptions but don't document them
    // Pattern: function with docblock, then function body with throw
    $content = preg_replace_callback(
        '/(\/\*\*.*?\*\/)\s*(public|private|protected)?\s*function\s+(\w+)\s*\([^)]*\)\s*\{([^}]*throw new [^;]+;[^}]*)\}/s',
        function ($matches) use (&$total_fixes) {
            $docblock = $matches[1];
            $visibility = $matches[2];
            $func_name = $matches[3];
            $func_body = $matches[4];

            // Check if @throws already exists
            if (strpos($docblock, '@throws') !== false) {
                return $matches[0];
            }

            // Find what exception is thrown
            preg_match_all('/throw new ([A-Za-z_\\\\]+)/', $func_body, $exceptions);
            $exception_types = array_unique($exceptions[1]);

            if (empty($exception_types)) {
                return $matches[0];
            }

            // Add @throws tag before the closing */
            $throws_tags = '';
            foreach ($exception_types as $exception) {
                $throws_tags .= "\t * @throws " . $exception . "\n";
            }

            $new_docblock = str_replace('*/', $throws_tags . "\t */", $docblock);

            $total_fixes++;

            return $new_docblock . ' ' . ($visibility ? $visibility . ' ' : '') . 'function ' . $func_name . '(' .
                (preg_match('/function\s+\w+\s*\(([^)]*)\)/', $matches[0], $params) ? $params[1] : '') .
                ') {' . $func_body . '}';
        },
        $content
    );

    if ($content !== $original) {
        file_put_contents($file_path, $content);
        echo "✓ Added @throws tags in " . basename($file_path) . "\n";
    }
}

echo "\n✅ Total: Added @throws tags to {$total_fixes} functions\n";
