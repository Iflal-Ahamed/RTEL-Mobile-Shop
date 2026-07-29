<?php

/**
 * Simple file cache for slow product-page fetches (GSMArena, Gemini).
 */
function rtel_web_cache_dir()
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $path = __DIR__ . '/../cache';
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $dir = is_writable($path) ? $path : '';
    return $dir;
}

function rtel_web_cache_get($key, $ttlSeconds)
{
    $base = rtel_web_cache_dir();
    if ($base === '') {
        return null;
    }
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$key);
    if ($safe === '') {
        return null;
    }
    $path = $base . '/' . $safe . '.cache';
    if (!is_readable($path)) {
        return null;
    }
    if ($ttlSeconds > 0 && (time() - (int)@filemtime($path)) > $ttlSeconds) {
        return null;
    }
    $raw = @file_get_contents($path);
    return $raw === false ? null : $raw;
}

function rtel_web_cache_set($key, $value)
{
    $base = rtel_web_cache_dir();
    if ($base === '') {
        return;
    }
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$key);
    if ($safe === '') {
        return;
    }
    @file_put_contents($base . '/' . $safe . '.cache', (string)$value);
}
