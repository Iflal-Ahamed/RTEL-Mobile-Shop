<?php

/**
 * Variant helpers aligned with product.php “Choose variant” (color / storage)
 * plus generic option tokens used on cart bundle rows.
 */

function rtel_pv_split_option_list($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/\s*[,|]\s*/', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return array_values(array_unique($out));
}

function rtel_pv_row_is_color($featureName)
{
    $n = strtolower(trim(preg_replace('/\s+/', ' ', (string)$featureName)));
    if ($n === '') {
        return false;
    }
    return (bool)preg_match('/\b(color|colour|colours)\b/i', $n);
}

function rtel_pv_row_is_storage($featureName)
{
    $n = strtolower(trim(preg_replace('/\s+/', ' ', (string)$featureName)));
    if ($n === '') {
        return false;
    }
    if (preg_match('/\b(color|colour|colours)\b/i', $n)) {
        return false;
    }
    return (bool)preg_match(
        '/\b(ram|rom|storage|memory|capacity|variant|disk|ssd|hdd)\b|ram\s*\/\s*rom/i',
        $n
    );
}

/**
 * Color + storage buckets exactly like product.php listing (tblproduct_feature scan).
 *
 * @return array{color: string[], storage: string[]}
 */
function rtel_pv_feature_variant_buckets($conn, $productId)
{
    static $cache = [];
    $productId = trim((string)$productId);
    if ($productId === '') {
        return ['color' => [], 'storage' => []];
    }
    if (isset($cache[$productId])) {
        return $cache[$productId];
    }
    $color = [];
    $storage = [];
    $stmt = $conn->prepare("SELECT feature_name, feature_value FROM tblproduct_feature WHERE product_id = ? ORDER BY feature_id ASC");
    if ($stmt) {
        $stmt->bind_param("s", $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && $row = $res->fetch_assoc()) {
            $n = trim((string)($row['feature_name'] ?? ''));
            $v = trim((string)($row['feature_value'] ?? ''));
            if ($n === '' || $v === '') {
                continue;
            }
            $opts = rtel_pv_split_option_list($v);
            if (count($opts) === 0) {
                $opts = [$v];
            }
            if (rtel_pv_row_is_color($n)) {
                foreach ($opts as $o) {
                    if ($o !== '' && !in_array($o, $color, true)) {
                        $color[] = $o;
                    }
                }
            } elseif (rtel_pv_row_is_storage($n)) {
                foreach ($opts as $o) {
                    if ($o !== '' && !in_array($o, $storage, true)) {
                        $storage[] = $o;
                    }
                }
            }
        }
        $stmt->close();
    }
    $cache[$productId] = ['color' => array_values($color), 'storage' => array_values($storage)];
    return $cache[$productId];
}

function rtel_pv_generic_variant_tokens($conn, $productId)
{
    static $cache = [];
    $productId = trim((string)$productId);
    if ($productId === '') {
        return [];
    }
    if (isset($cache[$productId])) {
        return $cache[$productId];
    }
    $options = [];
    $stmt = $conn->prepare("SELECT feature_name, feature_value FROM tblproduct_feature WHERE product_id = ? ORDER BY feature_name ASC");
    if ($stmt) {
        $stmt->bind_param("s", $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && $row = $res->fetch_assoc()) {
            $name = trim((string)($row['feature_name'] ?? ''));
            $value = trim((string)($row['feature_value'] ?? ''));
            if ($value === '' || !preg_match('/ram|storage|rom|color|size|variant|capacity/i', $name)) {
                continue;
            }
            foreach (preg_split('/\s*[,\/]\s*/', $value) as $token) {
                $token = trim((string)$token);
                if ($token !== '') {
                    $options[] = $token;
                }
            }
            $options[] = $value;
        }
        $stmt->close();
    }
    $options = array_values(array_unique($options));
    $cache[$productId] = array_slice($options, 0, 24);
    return $cache[$productId];
}

/**
 * Full grouping for cart/wishlist/checkout (color, storage, generic fallback).
 *
 * @return array{color: string[], storage: string[], generic: string[]}
 */
function rtel_pv_variant_groups_cart($conn, $productId)
{
    $b = rtel_pv_feature_variant_buckets($conn, $productId);
    $generic = [];
    if (count($b['color']) === 0 && count($b['storage']) === 0) {
        $generic = rtel_pv_generic_variant_tokens($conn, $productId);
    }
    return [
        'color' => $b['color'],
        'storage' => $b['storage'],
        'generic' => $generic,
    ];
}

function rtel_pv_product_needs_variant_choice($conn, $productId)
{
    $g = rtel_pv_variant_groups_cart($conn, $productId);
    return count($g['color']) > 0 || count($g['storage']) > 0 || count($g['generic']) > 0;
}

function rtel_pv_extract_variant_piece($selected, $label)
{
    $selected = trim((string)$selected);
    if ($selected === '') {
        return '';
    }
    $lx = preg_quote((string)$label, '/');
    if (preg_match('/(?:^|[|;])\s*' . $lx . '\s*:\s*([^|;]+)/i', $selected, $m)) {
        return trim((string)($m[1] ?? ''));
    }
    return '';
}

function rtel_pv_variant_selection_complete($conn, $productId, $selectedFeature)
{
    $g = rtel_pv_variant_groups_cart($conn, $productId);
    if (count($g['color']) === 0 && count($g['storage']) === 0 && count($g['generic']) === 0) {
        return true;
    }
    $sf = trim((string)$selectedFeature);
    if ($sf === '') {
        return false;
    }

    $colorPick = rtel_pv_extract_variant_piece($sf, 'color');
    $storagePick = rtel_pv_extract_variant_piece($sf, 'storage');

    if (count($g['color']) > 0 && $colorPick === '') {
        foreach ($g['color'] as $opt) {
            if (strcasecmp($sf, trim((string)$opt)) === 0) {
                $colorPick = $opt;
                break;
            }
        }
    }
    if (count($g['storage']) > 0 && $storagePick === '') {
        foreach ($g['storage'] as $opt) {
            if (strcasecmp($sf, trim((string)$opt)) === 0) {
                $storagePick = $opt;
                break;
            }
        }
    }

    if (count($g['color']) > 0 && $colorPick === '') {
        return false;
    }
    if (count($g['storage']) > 0 && $storagePick === '') {
        return false;
    }

    if (count($g['color']) === 0 && count($g['storage']) === 0 && count($g['generic']) > 0) {
        foreach ($g['generic'] as $opt) {
            if (strcasecmp(trim($sf), trim((string)$opt)) === 0) {
                return true;
            }
        }
        return false;
    }

    return true;
}

function rtel_pv_build_variant_string($color, $storage, $generic)
{
    $parts = [];
    $color = trim((string)$color);
    $storage = trim((string)$storage);
    $generic = trim((string)$generic);
    if ($color !== '') {
        $parts[] = 'Color: ' . $color;
    }
    if ($storage !== '') {
        $parts[] = 'Storage: ' . $storage;
    }
    if (count($parts) === 0 && $generic !== '') {
        return mb_substr($generic, 0, 255);
    }
    return mb_substr(implode(' | ', $parts), 0, 255);
}
