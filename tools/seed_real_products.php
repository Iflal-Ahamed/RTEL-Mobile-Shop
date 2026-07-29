<?php
require __DIR__ . '/../web/connection.php';

$conn->set_charset('utf8mb4');
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblmobile_specs (
    mspecs_id VARCHAR(20) NOT NULL PRIMARY KEY,
    product_id VARCHAR(20) NOT NULL,
    ram VARCHAR(100) NOT NULL,
    rom VARCHAR(100) NOT NULL,
    os VARCHAR(100) NOT NULL,
    processor VARCHAR(100) NOT NULL,
    display VARCHAR(100) NOT NULL,
    camera VARCHAR(400) NOT NULL,
    battery VARCHAR(400) NOT NULL,
    sim_type VARCHAR(400) NOT NULL,
    connectivity VARCHAR(400) NOT NULL,
    warranty VARCHAR(400) NOT NULL,
    material VARCHAR(400) NOT NULL
)");

function ensureCategory(mysqli $conn, string $name, string $image = 'smartphone.png'): int {
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

function ensureBrand(mysqli $conn, string $name): int {
    $stmt = $conn->prepare("SELECT brand_id FROM tblbrand WHERE LOWER(name)=LOWER(?) LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return (int)$row['brand_id'];

    $desc = $name . ' mobile devices';
    $img = 'logo.jpg';
    $ins = $conn->prepare("INSERT INTO tblbrand (name, description, image, status) VALUES (?, ?, ?, 1)");
    $ins->bind_param('sss', $name, $desc, $img);
    $ins->execute();
    $id = (int)$ins->insert_id;
    $ins->close();
    return $id;
}

$products = [
    ['Samsung','Flagship Phones','Samsung Galaxy S24 Ultra','S24-Ultra',459999,489999,18,'12 GB','256 GB','Android 14','Snapdragon 8 Gen 3','6.8-inch Dynamic AMOLED 2X','200MP + 12MP + 50MP + 10MP','5000 mAh'],
    ['Samsung','Smart Phones','Samsung Galaxy A55 5G','A55-5G',149999,164999,30,'8 GB','256 GB','Android 14','Exynos 1480','6.6-inch Super AMOLED','50MP + 12MP + 5MP','5000 mAh'],
    ['Apple','Flagship Phones','Apple iPhone 15 Pro Max','i15PM',499999,539999,14,'8 GB','256 GB','iOS 17','Apple A17 Pro','6.7-inch Super Retina XDR','48MP + 12MP + 12MP','4441 mAh'],
    ['Apple','Smart Phones','Apple iPhone 14','i14-128',329999,359999,22,'6 GB','128 GB','iOS 17','Apple A15 Bionic','6.1-inch Super Retina XDR','12MP + 12MP','3279 mAh'],
    ['Xiaomi','Flagship Phones','Xiaomi 14','Xiaomi-14',279999,304999,20,'12 GB','256 GB','Android 14','Snapdragon 8 Gen 3','6.36-inch AMOLED','50MP + 50MP + 50MP','4610 mAh'],
    ['Xiaomi','Budget Phones','Redmi Note 13 Pro','RN13-Pro',114999,129999,35,'8 GB','256 GB','Android 13','Snapdragon 7s Gen 2','6.67-inch AMOLED','200MP + 8MP + 2MP','5100 mAh'],
    ['OnePlus','Flagship Phones','OnePlus 12','OP12',289999,319999,16,'16 GB','512 GB','Android 14','Snapdragon 8 Gen 3','6.82-inch LTPO AMOLED','50MP + 64MP + 48MP','5400 mAh'],
    ['OnePlus','Smart Phones','OnePlus Nord CE 4','Nord-CE4',129999,144999,28,'8 GB','256 GB','Android 14','Snapdragon 7 Gen 3','6.7-inch AMOLED','50MP + 8MP','5500 mAh'],
    ['Google','Flagship Phones','Google Pixel 8 Pro','Pixel-8-Pro',319999,349999,12,'12 GB','256 GB','Android 14','Google Tensor G3','6.7-inch LTPO OLED','50MP + 48MP + 48MP','5050 mAh'],
    ['Google','Smart Phones','Google Pixel 7a','Pixel-7a',169999,184999,24,'8 GB','128 GB','Android 14','Google Tensor G2','6.1-inch OLED','64MP + 13MP','4385 mAh'],
];

$inserted = 0;
$skipped = 0;

foreach ($products as $p) {
    [$brandName,$catName,$name,$model,$price,$cprice,$qty,$ram,$rom,$os,$proc,$display,$camera,$battery] = $p;

    $chk = $conn->prepare("SELECT product_id FROM tblproduct WHERE modal = ? LIMIT 1");
    $chk->bind_param('s', $model);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($exists) {
        $skipped++;
        continue;
    }

    $catId = ensureCategory($conn, $catName, 'smartphone.png');
    $brandId = ensureBrand($conn, $brandName);

    $maxRes = $conn->query("SELECT IFNULL(MAX(CAST(product_id AS UNSIGNED)),0)+1 AS next_id FROM tblproduct");
    $nextId = (int)($maxRes->fetch_assoc()['next_id'] ?? 1);
    $maxRes->free();

    $desc = $name . ' - ' . $brandName . ' smartphone.';
    $ins = $conn->prepare("INSERT INTO tblproduct (product_id, cat_id, brand_id, name, modal, description, price, cprice, quantity, added_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
    $ins->bind_param('iiisssddi', $nextId, $catId, $brandId, $name, $model, $desc, $price, $cprice, $qty);
    $ok = $ins->execute();
    $ins->close();
    if (!$ok) continue;

    $img1 = 'product-' . (($nextId % 4) + 1) . '.jpg';
    $img2 = 'product-' . ((($nextId + 1) % 4) + 1) . '.jpg';
    $img3 = 'img.avif';
    $img4 = 'banner.webp';
    $img5 = 'smartphone.png';
    $imgIns = $conn->prepare("INSERT INTO tblimage (product_id, image_1, image_2, image_3, image_4, image_5) VALUES (?, ?, ?, ?, ?, ?)");
    $imgIns->bind_param('isssss', $nextId, $img1, $img2, $img3, $img4, $img5);
    $imgIns->execute();
    $imgIns->close();

    $mspecsId = 'MS' . str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);
    $sim = 'Dual SIM';
    $connx = '5G, WiFi, Bluetooth, NFC';
    $war = '12 Months';
    $mat = 'Glass/Metal';
    $specIns = $conn->prepare("INSERT INTO tblmobile_specs (mspecs_id, product_id, ram, rom, os, processor, display, camera, battery, sim_type, connectivity, warranty, material) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $specIns->bind_param('sisssssssssss', $mspecsId, $nextId, $ram, $rom, $os, $proc, $display, $camera, $battery, $sim, $connx, $war, $mat);
    $specIns->execute();
    $specIns->close();

    $inserted++;
}

echo "Inserted: {$inserted}\n";
echo "Skipped(existing): {$skipped}\n";

