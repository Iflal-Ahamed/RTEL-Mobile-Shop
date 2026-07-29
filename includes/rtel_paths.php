<?php

/**
 * Web URL prefix for static files under the project root (e.g. /rtel when DocumentRoot is htdocs).
 * Used so logos/images work even when relative ../ paths break (subfolders, proxies).
 */
function rtel_app_web_base()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = '';
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    // This file lives in /rtel/includes/ — project root is /rtel.
    $rtelRoot = realpath(dirname(__DIR__));
    if (!$docRoot || !$rtelRoot) {
        return $cached;
    }
    $docRoot = str_replace('\\', '/', $docRoot);
    $rtelRoot = str_replace('\\', '/', $rtelRoot);
    $docRoot = rtrim($docRoot, '/');
    if (stripos($rtelRoot, $docRoot) !== 0) {
        return $cached;
    }
    $rel = trim(substr($rtelRoot, strlen($docRoot)), '/');
    if ($rel === '') {
        $cached = '.';
        return $cached;
    }
    $cached = '/' . $rel;
    return $cached;
}

/**
 * Public URL for a file in /rtel/web/images/{filename} or /rtel/images/{filename}.
 */
function rtel_image_url($filename)
{
    $file = basename((string)$filename);
    if ($file === '') {
        return '';
    }
    $root = realpath(dirname(__DIR__));
    $base = rtel_app_web_base();
    $candidates = ['web/images/' . $file, 'images/' . $file];
    foreach ($candidates as $relPath) {
        $exists = $root ? is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath)) : false;
        if (!$exists) {
            continue;
        }
        $urlRel = str_replace('\\', '/', $relPath);
        $absPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        $version = is_file($absPath) ? ('?v=' . (string)@filemtime($absPath)) : '';
        if ($base === '.') {
            return '/' . $urlRel . $version;
        }
        if ($base !== '') {
            return $base . '/' . $urlRel . $version;
        }
        return '../' . $urlRel . $version;
    }
    // Fallback for legacy references when file existence is unknown.
    return $base === '.' ? '/web/images/' . rawurlencode($file) : ($base !== '' ? $base . '/web/images/' . rawurlencode($file) : '../web/images/' . rawurlencode($file));
}
