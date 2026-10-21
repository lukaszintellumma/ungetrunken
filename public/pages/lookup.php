<?php
declare(strict_types=1);
require_role('admin', 'user', 'theke');
header('Content-Type: application/json; charset=utf-8');

$p = find_product((string)($_GET['code'] ?? ''));
if (!$p) { echo json_encode(null); exit; }

echo json_encode([
    'product_id'   => (int)$p['id'],
    'artikelnr'    => $p['artikelnr'],
    'name'         => $p['name'],
    'base_unit'    => $p['base_unit'],
    'packaging_id' => $p['pack_id'] ? (int)$p['pack_id'] : null,
], JSON_UNESCAPED_UNICODE);
