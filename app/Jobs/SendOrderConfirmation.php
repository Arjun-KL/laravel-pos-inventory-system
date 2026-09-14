<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * A side effect only: the 201 response does not depend on this having run.
 */
final class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /**
     * Takes an id, not an Order. The worker is a separate process with its own
     * connection, so it must read the committed row itself rather than trust a
     * model that was serialised while the transaction was still open.
     */
    public function __construct(private readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::query()
            ->with(['customer', 'items.product'])
            ->findOrFail($this->orderId);

        Mail::to($order->customer->email)->send(new OrderConfirmationMail($order));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Order confirmation e-mail permanently failed.', [
            'order_id' => $this->orderId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
