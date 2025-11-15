<?php

return [
    'enable' => env('MULTICHAIN_ENABLE', false),
    'host' => env('MULTICHAIN_HOST', '127.0.0.1'),
    'port' => env('MULTICHAIN_PORT', 9538),
    'user' => env('MULTICHAIN_USER', 'multichainrpc'),
    'pass' => env('MULTICHAIN_PASS', 'password'),
    'chain' => env('MULTICHAIN_CHAIN', 'yourchain'),
];
