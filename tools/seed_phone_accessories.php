<?php
require __DIR__ . '/../web/connection.php';

$conn->set_charset('utf8mb4');

function ensure_category(mysqli $conn, string $name, string $image = 'smartphone.png'): int {
    $stmt = $conn->prepare("SELECT cat_id FROM tblcategory WHERE LOWER(name)=LOWER(?) LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return (int)$row['cat_id'];

    $ins = $conn->prepare("INSERT INTO tblcategory (name, image, status) VALUES (?, ?, 1)");
    $ins->bind_param('ss', $name, $image);
    $ins->execute();
    $id = (int)$ins->insert_id;
    $ins->close();
    return $id;
}

function ensure_brand(mysqli $conn, string $name): int {
    $stmt = $conn->prepare("SELECT brand_id FROM tblbrand WHERE LOWER(name)=LOWER(?) LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return (int)$row['brand_id'];

    $desc = $name . ' accessories and devices';
    $img = 'logo.jpg';
    $ins = $conn->prepare("INSERT INTO tblbrand (name, description, image, status) VALUES (?, ?, ?, 1)");
    $ins->bind_param('sss', $name, $desc, $img);
    $ins->execute();
    $id = (int)$ins->insert_id;
    $ins->close();
    return $id;
}

function next_product_id(mysqli $conn): int {
    $r = $conn->query("SELECT IFNULL(MAX(CAST(product_id AS UNSIGNED)),0)+1 AS next_id FROM tblproduct");
    $row = $r ? $r->fetch_assoc() : ['next_id' => 1];
    if ($r) $r->free();
    return (int)($row['next_id'] ?? 1);
}

function ensure_phone_compat_features(mysqli $conn): int {
    $updated = 0;
    $allowed = ['smart phones', 'phones', 'budget phones', 'flagship phones'];
    $sql = "SELECT p.product_id, p.name, p.modal, COALESCE(c.name,'') AS category_name
            FROM tblproduct p
            LEFT JOIN tblcategory c ON p.cat_id = c.cat_id
            WHERE p.status = 1";
    $res = $conn->query($sql);
    while ($res && ($row = $res->fetch_assoc())) {
        $cat = strtolower(trim((string)($row['category_name'] ?? '')));
        if (!in_array($cat, $allowed, true)) continue;
        $pid = (int)$row['product_id'];
        $nameModal = strtolower(trim((string)$row['name'] . ' ' . (string)$row['modal']));
        $port = (strpos($nameModal, 'iphone') !== false || strpos($nameModal, 'i15') !== false || strpos($nameModal, 'i14') !== false) ? 'Lightning' : 'USB-C';
        $eco = (strpos($nameModal, 'iphone') !== false || strpos($nameModal, 'apple') !== false) ? 'iOS' : 'Android';

        $chk = $conn->prepare("SELECT COUNT(*) c FROM tblproduct_feature WHERE product_id = ? AND LOWER(feature_name) = 'charging port'");
        $chk->bind_param('i', $pid);
        $chk->execute();
        $hasPort = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        $chk->close();
        if (!$hasPort) {
            $ins = $conn->prepare("INSERT INTO tblproduct_feature (product_id, feature_name, feature_value) VALUES (?, 'Charging Port', ?)");
            $ins->bind_param('is', $pid, $port);
            $ins->execute();
            $ins->close();
            $updated++;
        }

        $chk2 = $conn->prepare("SELECT COUNT(*) c FROM tblproduct_feature WHERE product_id = ? AND LOWER(feature_name) = 'ecosystem'");
        $chk2->bind_param('i', $pid);
        $chk2->execute();
        $hasEco = (int)($chk2->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        $chk2->close();
        if (!$hasEco) {
            $ins2 = $conn->prepare("INSERT INTO tblproduct_feature (product_id, feature_name, feature_value) VALUES (?, 'Ecosystem', ?)");
            $ins2->bind_param('is', $pid, $eco);
            $ins2->execute();
            $ins2->close();
            $updated++;
        }
    }
    if ($res) $res->free();
    return $updated;
}

$categories = [
    'Accessories' => 'smartphone.png',
    'Headphones' => 'product-1.jpg',
    'Smart Watches' => 'product-3.jpg',
];
$catIds = [];
foreach ($categories as $cn => $img) {
    $catIds[$cn] = ensure_category($conn, $cn, $img);
}

$catalog = [
    ['brand'=>'Anker','category'=>'Accessories','name'=>'USB-C 25W Fast Charger','modal'=>'ANK-CHG-25C','price'=>6999,'cprice'=>7999,'qty'=>60,'desc'=>'Fast wall charger for USB-C Android phones.','features'=>['Connector'=>'USB-C','Accessory Type'=>'Charger','Compatibility'=>'Android phones / USB-C phones']],
    ['brand'=>'Anker','category'=>'Accessories','name'=>'USB-C to USB-C 100W Cable','modal'=>'ANK-CBL-C2C','price'=>2999,'cprice'=>3499,'qty'=>75,'desc'=>'Durable fast charging cable for USB-C phones.','features'=>['Connector'=>'USB-C','Accessory Type'=>'Cable','Compatibility'=>'USB-C phones']],
    ['brand'=>'Baseus','category'=>'Accessories','name'=>'Lightning 20W iPhone Charger','modal'=>'BAS-LGT-20W','price'=>8499,'cprice'=>9499,'qty'=>42,'desc'=>'20W charger built for iPhone lightning cable charging.','features'=>['Connector'=>'Lightning','Accessory Type'=>'Charger','Compatibility'=>'iPhone / iOS']],
    ['brand'=>'Baseus','category'=>'Accessories','name'=>'USB-C to Lightning Cable','modal'=>'BAS-C2L-CBL','price'=>3499,'cprice'=>3999,'qty'=>55,'desc'=>'Fast charge cable for iPhone with USB-C adapter.','features'=>['Connector'=>'Lightning','Accessory Type'=>'Cable','Compatibility'=>'iPhone / iOS']],
    ['brand'=>'Spigen','category'=>'Accessories','name'=>'Samsung Galaxy S24 Ultra Rugged Cover','modal'=>'CVR-S24U-RGD','price'=>4999,'cprice'=>5999,'qty'=>40,'desc'=>'Shockproof back cover for Galaxy S24 Ultra.','features'=>['Accessory Type'=>'Back Cover','Compatibility'=>'Samsung Galaxy S24 Ultra']],
    ['brand'=>'Spigen','category'=>'Accessories','name'=>'Samsung Galaxy A55 Silicone Cover','modal'=>'CVR-A55-SLC','price'=>3999,'cprice'=>4599,'qty'=>45,'desc'=>'Soft silicone case for Samsung Galaxy A55 5G.','features'=>['Accessory Type'=>'Back Cover','Compatibility'=>'Samsung Galaxy A55 5G']],
    ['brand'=>'Ringke','category'=>'Accessories','name'=>'iPhone 15 Pro Max Clear Cover','modal'=>'CVR-I15PM-CLR','price'=>5499,'cprice'=>6299,'qty'=>38,'desc'=>'Slim clear back cover for iPhone 15 Pro Max.','features'=>['Accessory Type'=>'Back Cover','Compatibility'=>'Apple iPhone 15 Pro Max']],
    ['brand'=>'Nillkin','category'=>'Accessories','name'=>'Google Pixel 8 Pro Matte Cover','modal'=>'CVR-P8P-MAT','price'=>4299,'cprice'=>4899,'qty'=>34,'desc'=>'Matte protective cover for Pixel 8 Pro.','features'=>['Accessory Type'=>'Back Cover','Compatibility'=>'Google Pixel 8 Pro']],
    ['brand'=>'SmartDevil','category'=>'Accessories','name'=>'Galaxy S24 Ultra Tempered Glass','modal'=>'TG-S24U-2PK','price'=>3299,'cprice'=>3999,'qty'=>50,'desc'=>'9H tempered glass protector for S24 Ultra.','features'=>['Accessory Type'=>'Tempered Glass','Compatibility'=>'Samsung Galaxy S24 Ultra']],
    ['brand'=>'SmartDevil','category'=>'Accessories','name'=>'iPhone 15 Pro Max Tempered Glass','modal'=>'TG-I15PM-2PK','price'=>3499,'cprice'=>4199,'qty'=>46,'desc'=>'Tempered glass protector for iPhone 15 Pro Max.','features'=>['Accessory Type'=>'Tempered Glass','Compatibility'=>'Apple iPhone 15 Pro Max']],
    ['brand'=>'JBL','category'=>'Headphones','name'=>'JBL Tune 520BT Wireless Headphones','modal'=>'JBL-520BT','price'=>19999,'cprice'=>22999,'qty'=>24,'desc'=>'Bluetooth headphones for Android and iOS phones.','features'=>['Accessory Type'=>'Headphone','Connection'=>'Bluetooth','Compatibility'=>'Android / iOS']],
    ['brand'=>'Sony','category'=>'Headphones','name'=>'Sony MDR ZX110AP Wired Headphones','modal'=>'SNY-ZX110AP','price'=>8999,'cprice'=>9999,'qty'=>30,'desc'=>'Wired headphone with 3.5mm jack.','features'=>['Accessory Type'=>'Headphone','Connector'=>'3.5mm','Compatibility'=>'Phones with 3.5mm jack']],
    ['brand'=>'Anker','category'=>'Headphones','name'=>'Soundcore R50i Earbuds','modal'=>'ANK-R50I','price'=>12499,'cprice'=>13999,'qty'=>29,'desc'=>'True wireless earbuds for daily phone use.','features'=>['Accessory Type'=>'Earbuds','Connection'=>'Bluetooth','Compatibility'=>'Android / iOS']],
    ['brand'=>'Samsung','category'=>'Smart Watches','name'=>'Galaxy Watch 6 44mm','modal'=>'SM-R940','price'=>89999,'cprice'=>94999,'qty'=>15,'desc'=>'Samsung smartwatch optimized for Android phones.','features'=>['Accessory Type'=>'Smart Watch','Compatibility'=>'Android']],
    ['brand'=>'Apple','category'=>'Smart Watches','name'=>'Apple Watch Series 9 45mm','modal'=>'AP-WCH-S9-45','price'=>169999,'cprice'=>179999,'qty'=>11,'desc'=>'Apple smartwatch designed for iPhone users.','features'=>['Accessory Type'=>'Smart Watch','Compatibility'=>'iOS / iPhone']],
];

$inserted = 0;
$skipped = 0;
$featureInserted = 0;

foreach ($catalog as $item) {
    $brandId = ensure_brand($conn, $item['brand']);
    $catId = $catIds[$item['category']] ?? ensure_category($conn, $item['category']);

    $chk = $conn->prepare("SELECT product_id FROM tblproduct WHERE modal = ? LIMIT 1");
    $chk->bind_param('s', $item['modal']);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($existing) {
        $skipped++;
        continue;
    }

    $pid = next_product_id($conn);
    $name = $item['name'];
    $modal = $item['modal'];
    $desc = $item['desc'];
    $price = (float)$item['price'];
    $cprice = (float)$item['cprice'];
    $qty = (int)$item['qty'];

    $ins = $conn->prepare("INSERT INTO tblproduct (product_id, cat_id, brand_id, name, modal, description, price, cprice, quantity, added_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
    $ins->bind_param('iiisssddi', $pid, $catId, $brandId, $name, $modal, $desc, $price, $cprice, $qty);
    $ok = $ins->execute();
    $ins->close();
    if (!$ok) continue;

    $img1 = 'product-' . (($pid % 4) + 1) . '.jpg';
    $img2 = 'product-' . ((($pid + 1) % 4) + 1) . '.jpg';
    $img3 = 'img.avif';
    $img4 = 'banner.webp';
    $img5 = 'smartphone.png';
    $imgIns = $conn->prepare("INSERT INTO tblimage (product_id, image_1, image_2, image_3, image_4, image_5) VALUES (?, ?, ?, ?, ?, ?)");
    $imgIns->bind_param('isssss', $pid, $img1, $img2, $img3, $img4, $img5);
    $imgIns->execute();
    $imgIns->close();

    foreach (($item['features'] ?? []) as $fn => $fv) {
        $fn = trim((string)$fn);
        $fv = trim((string)$fv);
        if ($fn === '' || $fv === '') continue;
        $fIns = $conn->prepare("INSERT INTO tblproduct_feature (product_id, feature_name, feature_value) VALUES (?, ?, ?)");
        $fIns->bind_param('iss', $pid, $fn, $fv);
        $fIns->execute();
        $fIns->close();
        $featureInserted++;
    }

    $inserted++;
}

$phoneFeatureUpdated = ensure_phone_compat_features($conn);

echo "Accessories inserted: {$inserted}\n";
echo "Skipped existing: {$skipped}\n";
echo "Accessory features inserted: {$featureInserted}\n";
echo "Phone compatibility features updated: {$phoneFeatureUpdated}\n";

