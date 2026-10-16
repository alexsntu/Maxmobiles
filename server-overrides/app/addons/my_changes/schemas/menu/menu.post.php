<?php
/**
 * Maxmobiles: пункты меню админки «Покупатели → Подтверждение номера Maxmobiles» (mm_cabinet.phones) и
 * «Покупатели → Заблокированные номера Maxmobiles» (mm_cabinet.blocked) – копия пунктов GOODMi
 * (app/addons/gm_delivery_date/schemas/menu/menu.post.php) для отдельного кабинета Maxmobiles.
 */

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$schema['central']['customers']['items']['mm_phone_verify'] = [
    'title' => 'Подтверждение номера Maxmobiles',
    'href' => 'mm_cabinet.phones',
    'position' => 152,
];
$schema['central']['customers']['items']['mm_phone_blocked'] = [
    'title' => 'Заблокированные номера Maxmobiles',
    'href' => 'mm_cabinet.blocked',
    'position' => 153,
];

// Свой поиск Maxmobiles (2026-10-16): статистика, проверка запроса, индекс – app/controllers/backend/mm_search.php
$schema['central']['products']['items']['mm_search'] = [
    'title' => 'Поиск Maxmobiles',
    'href' => 'mm_search.manage',
    'position' => 1500,
];

return $schema;
