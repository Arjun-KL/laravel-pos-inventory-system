<?php

declare(strict_types=1);

/*
 * Places one order for one unit, in its own PHP process with its own database
 * connection — the way a separate web request would. Used by
 * Tests\Feature\Orders\ConcurrentOrdersTest.
 *
 * Usage:  php tests/Support/place-order.php <product_id> <customer_email>
 * Exit:   0 = order placed, 10 = rejected for insufficient stock, other = error
 */

use App\Actions\Orders\CreateOrderAction;
use App\Data\CustomerData;
use App\Data\OrderLineData;
use App\Exceptions\InsufficientStockException;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $productId, $email] = $argv;

try {
    $order = $app->make(CreateOrderAction::class)->execute(
        new CustomerData(name: 'Concurrent Buyer', email: $email),
        [new OrderLineData(productId: (int) $productId, quantity: 1)],
    );

    fwrite(STDOUT, json_encode(['result' => 'placed', 'order_id' => $order->id]).PHP_EOL);
    exit(0);
} catch (InsufficientStockException) {
    fwrite(STDOUT, json_encode(['result' => 'insufficient_stock']).PHP_EOL);
    exit(10);
}
