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
    $dataDir = __DIR__ . '/../data';
    $dataFile = $dataDir . '/' . $filename;
    if (file_exists($dataFile)) {
        return $dataFile;
    }
    $tmpFile = '/tmp/fittrack_data/' . $filename;
    if (file_exists($tmpFile)) {
        return $tmpFile;
    }
    return $dataFile;
}
