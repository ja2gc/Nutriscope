<?php

return [
    'max_bytes' => [
        'profile' => 300_000,
        'purchase_order' => 8 * 1024 * 1024,
        'clinical' => 10 * 1024 * 1024,
        'branding' => 2 * 1024 * 1024,
    ],
    'max_dimension' => [
        'profile' => 1024,
        'purchase_order' => 2560,
        'clinical' => 4096,
        'branding' => 1024,
    ],
    'max_pixels' => 40_000_000,
];
