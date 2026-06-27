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

function getDataFile($filename) {
    return getDataDir() . '/' . $filename;
}
