<?php

return [
    'enabled' => env('DEMO_MODE', false),
    // Synthetic identities for the role switcher. These headers are not authentication.
    'customers' => [
        ['id' => 1, 'role' => 'customer', 'name' => 'Демо-заказчик 1', 'organization' => 'Демо университет «Алем»'],
        ['id' => 2, 'role' => 'customer', 'name' => 'Демо-заказчик 2', 'organization' => 'Демо колледж «Самғау»'],
    ],
];
