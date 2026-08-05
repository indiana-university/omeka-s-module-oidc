<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = ['config', 'src', 'tests'];
$files = [$root . '/Module.php'];

foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);
foreach ($files as $file) {
    $output = [];
    $exitCode = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file), $output, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        exit($exitCode);
    }
}

printf("Linted %d PHP files successfully.%s", count($files), PHP_EOL);
