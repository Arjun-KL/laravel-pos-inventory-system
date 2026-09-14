<h1>Thanks for your order, {{ $order->customer->name }}</h1>

<p>Order <strong>{{ $order->order_number }}</strong>, placed {{ $order->placed_at->toDayDateTimeString() }}.</p>

<table>
    <thead>
        <tr>
            <th align="left">Item</th>
            <th align="right">Qty</th>
            <th align="right">Unit price</th>
            <th align="right">Tax</th>
            <th align="right">Line total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product->name }} ({{ $item->product->sku }})</td>
                <td align="right">{{ $item->quantity }}</td>
                <td align="right">{{ $item->unit_price }}</td>
                <td align="right">{{ $item->line_tax }}</td>
                <td align="right">{{ $item->line_total }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" align="right">Subtotal</td>
            <td align="right">{{ $order->subtotal }}</td>
        </tr>
        <tr>
            <td colspan="4" align="right">Tax</td>
            <td align="right">{{ $order->tax_total }}</td>
        </tr>
        <tr>
            <td colspan="4" align="right"><strong>Total</strong></td>
            <td align="right"><strong>{{ $order->total }}</strong></td>
        </tr>
    </tfoot>
</table>
