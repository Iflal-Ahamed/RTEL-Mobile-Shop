<?php

function rtel_fk_exists(mysqli $conn, $tableName, $constraintName)
{
    $dbRes = $conn->query("SELECT DATABASE() AS db_name");
    $dbName = $dbRes ? (string)($dbRes->fetch_assoc()['db_name'] ?? '') : '';
    if ($dbName === '') {
        return false;
    }
    $sql = "SELECT COUNT(*) AS c
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sss', $dbName, $tableName, $constraintName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)($row['c'] ?? 0) > 0);
}

function rtel_index_exists(mysqli $conn, $tableName, $indexName)
{
    $dbRes = $conn->query("SELECT DATABASE() AS db_name");
    $dbName = $dbRes ? (string)($dbRes->fetch_assoc()['db_name'] ?? '') : '';
    if ($dbName === '') {
        return false;
    }
    $sql = "SELECT COUNT(*) AS c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sss', $dbName, $tableName, $indexName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)($row['c'] ?? 0) > 0);
}

function rtel_sync_product_relationships(mysqli $conn)
{
    // Normalize child product_id column types so FK can be created safely.
    @mysqli_query($conn, "ALTER TABLE tblimage MODIFY product_id INT(11) NOT NULL");
    @mysqli_query($conn, "ALTER TABLE tblproduct_feature MODIFY product_id INT(11) NOT NULL");

    // Remove orphan rows before adding FK constraints.
    @mysqli_query($conn, "DELETE c FROM tblimage c LEFT JOIN tblproduct p ON p.product_id = c.product_id WHERE p.product_id IS NULL");
    @mysqli_query($conn, "DELETE c FROM tblproduct_feature c LEFT JOIN tblproduct p ON p.product_id = c.product_id WHERE p.product_id IS NULL");

    // Add indexes for FK columns only when missing.
    if (!rtel_index_exists($conn, 'tblproduct_feature', 'idx_tblproduct_feature_product_id')) {
        @mysqli_query($conn, "ALTER TABLE tblproduct_feature ADD INDEX idx_tblproduct_feature_product_id (product_id)");
    }

    if (!rtel_fk_exists($conn, 'tblimage', 'fk_tblimage_product')) {
        @mysqli_query($conn, "ALTER TABLE tblimage ADD CONSTRAINT fk_tblimage_product FOREIGN KEY (product_id) REFERENCES tblproduct(product_id) ON DELETE CASCADE ON UPDATE CASCADE");
    }
    if (!rtel_fk_exists($conn, 'tblproduct_feature', 'fk_tblproduct_feature_product')) {
        @mysqli_query($conn, "ALTER TABLE tblproduct_feature ADD CONSTRAINT fk_tblproduct_feature_product FOREIGN KEY (product_id) REFERENCES tblproduct(product_id) ON DELETE CASCADE ON UPDATE CASCADE");
    }
}
