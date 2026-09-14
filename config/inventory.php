<?php

declare(strict_types=1);

return [
    // Products with stock strictly below this are reported as low stock.
    // GET /api/products/low-stock?threshold=N overrides it for one request.
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 10),
];
