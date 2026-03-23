<?php
/**
 * check_server.php
 * Diagnostic script for EXIF Tool discovery and server permissions.
 */

header('Content-Type: text/plain; charset=UTF-8');

echo "--- SERVER DIAGNOSTICS ---\n";
echo "Operating System: " . PHP_OS . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";

echo "\n--- PERMISSIONS ---\n";
$disabled = explode(',', ini_get('disable_functions'));
$disabled = array_map('trim', $disabled);
$check_funcs = ['proc_open', 'shell_exec', 'exec', 'system', 'passthru'];

foreach ($check_funcs as $f) {
    $status = in_array($f, $disabled) ? "DISABLED" : "ENABLED";
    echo "Function $f: $status\n";
}

echo "\n--- EXIFTOOL DISCOVERY ---\n";
$candidates = ['exiftool', '/usr/bin/exiftool', '/usr/local/bin/exiftool'];
foreach ($candidates as $c) {
    echo "Checking '$c': ";
    $output = @shell_exec("$c -ver 2>&1");
    if ($output && preg_match('/^\d+\.\d+/', trim($output))) {
        echo "FOUND (Version: " . trim($output) . ")\n";
    } else {
        echo "NOT FOUND\n";
    }
}

echo "\n--- LOCAL FILES ---\n";
$local_files = ['exiftool.exe', 'exiftool'];
foreach ($local_files as $f) {
    if (file_exists(__DIR__ . '/' . $f)) {
        echo "File '$f' exists in current directory.\n";
        $perms = substr(sprintf('%o', fileperms(__DIR__ . '/' . $f)), -4);
        echo "   Permissions: $perms\n";
    } else {
        echo "File '$f' NOT found in current directory.\n";
    }
}

echo "\n--- RECOMMENDATION ---\n";
if (PHP_OS_FAMILY !== 'Windows' && file_exists(__DIR__ . '/exiftool.exe')) {
    echo "(!) WARNING: You are on Linux/Unix but uploaded 'exiftool.exe' (Windows). This will not work.\n";
    echo "    You need the Perl version of ExifTool.\n";
}
