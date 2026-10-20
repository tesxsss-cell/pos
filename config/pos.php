<?php

return [
    // Jumlah hari pengamatan penjualan dan cadangan pengaman untuk titik pemesanan.
    'reorder' => [
        'sales_days' => (int) env('POS_REORDER_SALES_DAYS', 14),
        'safety_days' => (int) env('POS_REORDER_SAFETY_DAYS', 2),
    ],
];
