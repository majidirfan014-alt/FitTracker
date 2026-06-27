<?php

function getDataDir() {
    $dataDir = __DIR__ . '/../data';
    if (is_dir($dataDir) && is_writable($dataDir)) {
        return $dataDir;
    }
    $tmpDir = '/tmp/fittrack_data';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }
    return $tmpDir;
}

function getWriteFile($filename) {
    return getDataDir() . '/' . $filename;
}

function getReadFile($filename) {
    $dir = getDataDir();
    $file = $dir . '/' . $filename;
    if (file_exists($file)) {
        return $file;
    }
    $srcFile = __DIR__ . '/../data/' . $filename;
    if (file_exists($srcFile)) {
        copy($srcFile, $file);
    }
    return $file;
}
