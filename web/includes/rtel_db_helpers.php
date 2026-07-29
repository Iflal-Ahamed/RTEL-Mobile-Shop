<?php

/**
 * Customer display name column differs across DB installs (name vs cus_name).
 * Returns a safe identifier for use in SQL: `c.name` style (caller adds table alias).
 */
function rtel_customer_display_name_column(mysqli $conn)
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = 'name';
    $res = $conn->query("SHOW COLUMNS FROM tblcustomer WHERE Field IN ('cus_name','name')");
    $hasCusName = false;
    $hasName = false;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $f = (string)($row['Field'] ?? '');
            if ($f === 'cus_name') {
                $hasCusName = true;
            }
            if ($f === 'name') {
                $hasName = true;
            }
        }
    }
    if ($hasCusName) {
        $cached = 'cus_name';
    } elseif ($hasName) {
        $cached = 'name';
    }
    return in_array($cached, ['name', 'cus_name'], true) ? $cached : 'name';
}
