<?php

/**
 * Script to find truly unprepared SQL queries in the plugin.
 * Run from plugin root: php scripts/find-unprepared-sql.php
 */

$dir = __DIR__ . '/../includes';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$issues = [];

foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $lines = file($f->getPathname());
    $total = count($lines);

    for ($i = 0; $i < $total; $i++) {
        $ln = $lines[$i];

        // Check for wpdb method calls (get_results, get_row, get_var, ->query)
        if (preg_match('/(get_results|get_row|get_var|->query)\s*\(/', $ln) && !preg_match('/prepare/', $ln)) {
            // Look ahead 10 lines for the full query context
            $blk = '';
            for ($j = $i; $j < min($i + 10, $total); $j++) {
                $blk .= $lines[$j];
            }

            // Check if block contains $wpdb->prefix but NO prepare()
            if (
                strpos($blk, 'wpdb->prefix') !== false &&
                strpos($blk, 'prepare') === false &&
                preg_match('/SELECT|DELETE|UPDATE|INSERT|DROP|TRUNCATE/', $blk)
            ) {

                $fn = str_replace(realpath($dir) . DIRECTORY_SEPARATOR, '', realpath($f->getPathname()));
                $fn = str_replace('\\', '/', $fn);
                $issues[] = $fn . ':' . ($i + 1) . ' | ' . substr(trim($ln), 0, 120);
            }
        }
    }
}

echo "TOTAL UNPREPARED QUERIES: " . count($issues) . "\n";
echo str_repeat('-', 80) . "\n";
foreach ($issues as $x) {
    echo $x . "\n";
}
