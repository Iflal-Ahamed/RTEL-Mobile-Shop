<?php
if (!function_exists('rtel_ensure_bundle_schema')) {
    function rtel_ensure_bundle_schema(mysqli $conn)
    {
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblbundle (
            bundle_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            bundle_name VARCHAR(150) NOT NULL,
            bundle_model VARCHAR(120) NOT NULL DEFAULT '',
            bundle_image VARCHAR(255) NOT NULL DEFAULT '',
            bundle_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            expiry_date DATE NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL
        )");
        mysqli_query($conn, "ALTER TABLE tblbundle ADD COLUMN IF NOT EXISTS bundle_model VARCHAR(120) NOT NULL DEFAULT '' AFTER bundle_name");
        mysqli_query($conn, "ALTER TABLE tblbundle ADD COLUMN IF NOT EXISTS bundle_image VARCHAR(255) NOT NULL DEFAULT '' AFTER bundle_model");
        mysqli_query($conn, "ALTER TABLE tblbundle ADD COLUMN IF NOT EXISTS expiry_date DATE NULL AFTER bundle_price");
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tblbundle_item (
            bundle_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            bundle_id INT UNSIGNED NOT NULL,
            product_id VARCHAR(20) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_bundle_item_bundle (bundle_id),
            INDEX idx_bundle_item_product (product_id)
        )");
    }
}
