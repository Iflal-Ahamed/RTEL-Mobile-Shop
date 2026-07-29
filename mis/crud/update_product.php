<?php
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/product_schema.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';
header('Content-Type: application/json');
rtel_require_admin_auth();
rtel_require_admin_page_access('allproducts.php');
rtel_sync_product_relationships($conn);

function out_json($status, $message = '')
{
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$category = (int)($_POST['category'] ?? 0);
$brand = (int)($_POST['brand'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$modal = trim((string)($_POST['modal'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$price = (float)($_POST['price'] ?? 0);
$cpriceRaw = trim((string)($_POST['cprice'] ?? ''));
$cprice = ($cpriceRaw === '') ? 0.0 : (float)$cpriceRaw;
$quantity = (int)($_POST['quantity'] ?? 0);
$colors = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['colors'] ?? [])))));
$ramOptions = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['ram_options'] ?? [])))));
$romOptions = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['rom_options'] ?? [])))));
$featureNames = (array)($_POST['feature_name'] ?? []);
$featureValues = (array)($_POST['feature_value'] ?? []);

if ($id <= 0 || $category <= 0 || $name === '' || $modal === '' || $price < 0 || $cprice < 0 || $quantity < 0) {
    rtel_admin_log_event($conn, 'product_edit', 'failed', 'Invalid input for product edit #' . $id);
    out_json('error', 'Invalid input');
}
if ($price <= 0) {
    rtel_admin_log_event($conn, 'product_edit', 'failed', 'Invalid price for product #' . $id);
    out_json('error', 'Price must be greater than 0');
}

$conn->begin_transaction();

$stmt = $conn->prepare("UPDATE tblproduct SET cat_id = ?, brand_id = NULLIF(?,0), name = ?, modal = ?, description = ?, price = ?, cprice = ?, quantity = ? WHERE product_id = ?");
$stmt->bind_param('iisssddii', $category, $brand, $name, $modal, $description, $price, $cprice, $quantity, $id);
$ok = $stmt->execute();
$stmt->close();
if (!$ok) {
    $conn->rollback();
    out_json('error', 'Update failed');
}

$tblCheck = $conn->query("SHOW TABLES LIKE 'tblproduct_feature'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $delF = $conn->prepare("DELETE FROM tblproduct_feature WHERE product_id = ?");
    $delF->bind_param('i', $id);
    $delF->execute();
    $delF->close();
    $insF = $conn->prepare("INSERT INTO tblproduct_feature (product_id, feature_name, feature_value) VALUES (?, ?, ?)");
    if ($insF) {
        foreach ($colors as $c) {
            $c = trim((string)$c);
            if ($c === '') {
                continue;
            }
            $fname = 'Color';
            $fvalue = $c;
            $insF->bind_param('iss', $id, $fname, $fvalue);
            $insF->execute();
        }
        foreach ($featureNames as $idx => $fn) {
            $fn = trim((string)$fn);
            $fv = trim((string)($featureValues[$idx] ?? ''));
            if ($fn === '' || $fv === '') {
                continue;
            }
            $insF->bind_param('iss', $id, $fn, $fv);
            $insF->execute();
        }
        foreach ($ramOptions as $ram) {
            $ram = trim((string)$ram);
            if ($ram === '') {
                continue;
            }
            $fname = 'RAM Option';
            $fvalue = $ram;
            $insF->bind_param('iss', $id, $fname, $fvalue);
            $insF->execute();
        }
        foreach ($romOptions as $rom) {
            $rom = trim((string)$rom);
            if ($rom === '') {
                continue;
            }
            $fname = 'ROM Option';
            $fvalue = $rom;
            $insF->bind_param('iss', $id, $fname, $fvalue);
            $insF->execute();
        }
        $insF->close();
    }
}

$oldStmt = $conn->prepare("SELECT image_1, image_2, image_3, image_4, image_5 FROM tblimage WHERE product_id = ? LIMIT 1");
$oldStmt->bind_param('i', $id);
$oldStmt->execute();
$old = $oldStmt->get_result()->fetch_assoc();
$oldStmt->close();
$oldUnique = [];
if ($old) {
    foreach (['image_1', 'image_2', 'image_3', 'image_4', 'image_5'] as $key) {
        $nm = trim((string)($old[$key] ?? ''));
        if ($nm !== '' && !in_array($nm, $oldUnique, true)) {
            $oldUnique[] = $nm;
        }
    }
}

$keepExistingRaw = trim((string)($_POST['keep_existing_images'] ?? ''));
$keepExisting = [];
if ($keepExistingRaw !== '') {
    $decoded = json_decode($keepExistingRaw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $name) {
            $name = basename(trim((string)$name));
            if ($name !== '' && in_array($name, $oldUnique, true) && !in_array($name, $keepExisting, true)) {
                $keepExisting[] = $name;
            }
        }
    }
}

$allowed = ['jpg', 'jpeg', 'png', 'webp'];
$uploadNames = [];
if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
    $filesCount = count($_FILES['images']['name']);
    if ($filesCount > 5) {
        $conn->rollback();
        out_json('error', 'Maximum 5 images allowed');
    }
    for ($i = 0; $i < $filesCount; $i++) {
        $original = (string)($_FILES['images']['name'][$i] ?? '');
        $tmp = (string)($_FILES['images']['tmp_name'][$i] ?? '');
        if ($original === '' || $tmp === '') {
            continue;
        }
        $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $conn->rollback();
            out_json('error', 'Invalid image type');
        }
        $newName = date('YmdHis') . '_' . random_int(1000, 9999) . "_{$i}." . $ext;
        $newPath = __DIR__ . '/../../images/' . $newName;
        if (!move_uploaded_file($tmp, $newPath)) {
            foreach ($uploadNames as $n) {
                @unlink(__DIR__ . '/../../images/' . $n);
            }
            $conn->rollback();
            out_json('error', 'Image upload failed');
        }
        $uploadNames[] = $newName;
    }
}

$shouldApplyImageUpdate = ($keepExistingRaw !== '' || count($uploadNames) > 0);
if ($shouldApplyImageUpdate) {
    $finalImages = array_values(array_slice(array_merge($keepExisting, $uploadNames), 0, 5));
    if (count($finalImages) < 1) {
        foreach ($uploadNames as $n) {
            @unlink(__DIR__ . '/../../images/' . $n);
        }
        $conn->rollback();
        out_json('error', 'At least one product image is required');
    }
    while (count($finalImages) < 5) {
        $finalImages[] = $finalImages[0];
    }

    $imgUpdate = $conn->prepare("UPDATE tblimage SET image_1 = ?, image_2 = ?, image_3 = ?, image_4 = ?, image_5 = ? WHERE product_id = ?");
    $imgUpdate->bind_param('sssssi', $finalImages[0], $finalImages[1], $finalImages[2], $finalImages[3], $finalImages[4], $id);
    $imgOk = $imgUpdate->execute();
    $imgUpdate->close();
    if (!$imgOk) {
        foreach ($uploadNames as $n) {
            @unlink(__DIR__ . '/../../images/' . $n);
        }
        $conn->rollback();
        out_json('error', 'Image update failed');
    }

    $finalUnique = [];
    foreach ($finalImages as $nm) {
        if ($nm !== '' && !in_array($nm, $finalUnique, true)) {
            $finalUnique[] = $nm;
        }
    }
    foreach ($oldUnique as $oldName) {
        if (!in_array($oldName, $finalUnique, true)) {
            @unlink(__DIR__ . '/../../images/' . $oldName);
        }
    }
}

/* Legacy single-image fallback support (if old client sends image) */
if (!empty($_FILES['image']['name'])) {
    $ext = strtolower((string)pathinfo((string)$_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        $conn->rollback();
        out_json('error', 'Invalid image type');
    }
    $newName = date('YmdHis') . '_' . random_int(1000, 9999) . '.' . $ext;
    $newPath = __DIR__ . '/../../images/' . $newName;
    if (!move_uploaded_file((string)$_FILES['image']['tmp_name'], $newPath)) {
        $conn->rollback();
        out_json('error', 'Image upload failed');
    }

    $oldStmt = $conn->prepare("SELECT image_1 FROM tblimage WHERE product_id = ? LIMIT 1");
    $oldStmt->bind_param('i', $id);
    $oldStmt->execute();
    $old = $oldStmt->get_result()->fetch_assoc();
    $oldStmt->close();

    $imgUpdate = $conn->prepare("UPDATE tblimage SET image_1 = ?, image_2 = ?, image_3 = ?, image_4 = ?, image_5 = ? WHERE product_id = ?");
    $imgUpdate->bind_param('sssssi', $newName, $newName, $newName, $newName, $newName, $id);
    $imgOk = $imgUpdate->execute();
    $imgUpdate->close();

    if (!$imgOk) {
        @unlink($newPath);
        $conn->rollback();
        out_json('error', 'Image update failed');
    }
    if (!empty($old['image_1'])) {
        $oldPath = __DIR__ . '/../../images/' . (string)$old['image_1'];
        if (file_exists($oldPath)) @unlink($oldPath);
    }
}

$conn->commit();
rtel_admin_log_event($conn, 'product_edit', 'success', 'Updated product #' . $id . ': ' . $name);
out_json('success');
