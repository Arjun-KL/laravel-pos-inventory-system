<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store Billing — New Order</title>
    @verbatim
    <style>
        :root {
            --ink: #1b2330;
            --muted: #5b6676;
            --line: #d7dde5;
            --field: #b9c3cf;
            --panel: #ffffff;
            --ground: #f3f5f8;
            --brand: #1f2a3a;
            --primary: #2f6fb3;
            --success: #1e8a4c;
            --danger: #b42318;
            --danger-bg: #fdf0ef;
            --warn-bg: #fff8e6;
            --warn-line: #e3a008;
            --warn-ink: #8a4b00;
            --radius: 8px;
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body { margin: 0; font: 15px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--ink); background: var(--ground); }
        .topbar { background: var(--brand); color: #fff; padding: 14px 24px; }
        .topbar h1 { margin: 0; font-size: 18px; }
        .topbar h1 span { font-weight: 400; opacity: .85; }
        .layout { max-width: 1180px; margin: 24px auto; padding: 0 16px; display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; align-items: start; }
        .card { background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); padding: 18px 20px; margin-bottom: 20px; }
        .card h2 { margin: 0 0 12px; font-size: 15px; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .field { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: var(--muted); }
        .field input, select { font: inherit; color: var(--ink); padding: 8px 10px; border: 1px solid var(--field); border-radius: 6px; background: #fff; }
        input:focus, select:focus, button:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
        .hint { color: var(--muted); font-size: 12px; }
        .error, .row-error { color: var(--danger); font-size: 12px; display: block; }
        .table-wrap { overflow-x: auto; }
        table.lines { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.lines th { text-align: left; background: #e9edf2; font-weight: 600; font-size: 13px; }
        table.lines th, table.lines td { padding: 8px 10px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .num, table.lines th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        tr.has-error td { background: var(--danger-bg); }
        input.qty { width: 76px; text-align: right; font: inherit; padding: 6px 8px; border: 1px solid var(--field); border-radius: 6px; }
        .empty { color: var(--muted); font-size: 13px; margin: 10px 0 0; }
        .add-row { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
        .add-row select { flex: 1 1 260px; min-width: 0; }
        .btn { font: inherit; font-weight: 600; border: 1px solid var(--field); background: #fff; color: var(--ink); padding: 8px 14px; border-radius: 6px; cursor: pointer; }
        .btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .btn-success { background: var(--success); border-color: var(--success); color: #fff; padding: 11px 28px; }
        .btn[disabled] { opacity: .6; cursor: progress; }
        .btn-link { border: 0; background: none; color: var(--danger); cursor: pointer; font: inherit; font-size: 13px; padding: 4px; }
        .payment-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 24px; align-items: start; }
        dl.totals { margin: 0; }
        dl.totals div { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; }
        dl.totals dt { color: var(--muted); }
        dl.totals dd { margin: 0; font-weight: 600; font-variant-numeric: tabular-nums; text-align: right; }
        dl.totals .grand { border-top: 1px dashed var(--line); margin-top: 4px; padding-top: 8px; font-size: 16px; }
        dl.totals .grand dt { color: var(--ink); font-weight: 700; }
        .balance { margin-top: 14px; font-size: 14px; }
        .balance strong { font-variant-numeric: tabular-nums; }
        .balance strong.owed { color: var(--danger); }
        .balance small { display: block; color: var(--muted); margin-top: 2px; }
        .actions { display: flex; align-items: center; gap: 14px; margin-top: 18px; flex-wrap: wrap; }
        .banner { border-radius: var(--radius); padding: 10px 14px; margin-bottom: 16px; font-size: 14px; }
        .banner.error { background: var(--danger-bg); border: 1px solid #f1b5ae; color: var(--danger); }
        .alert-panel { background: var(--warn-bg); border-color: var(--warn-line); position: sticky; top: 16px; }
        .alert-panel h2 { color: var(--warn-ink); margin-bottom: 4px; }
        .alert-panel ul { margin: 10px 0 0; padding-left: 18px; color: var(--warn-ink); font-size: 14px; }
        .alert-panel li { margin: 4px 0; }
        .bill-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: flex-start; margin-bottom: 12px; }
        .bill-head h2 { font-size: 18px; margin-bottom: 2px; }
        .bill-actions { display: flex; gap: 8px; }
        .bill dl.totals { max-width: 420px; margin: 14px 0 0 auto; }
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .alert-panel { position: static; order: -1; }
        }
        @media (max-width: 560px) {
            .field-row, .payment-grid { grid-template-columns: 1fr; }
        }
        @media print {
            body { background: #fff; }
            .topbar, .alert-panel, .bill-actions, .banner, #order-form { display: none !important; }
            .layout { display: block; margin: 0; }
            .card { border: 0; }
        }
    </style>
    @endverbatim
</head>
<body>
    <header class="topbar">
        <h1>Store Billing <span>— New Order</span></h1>
    </header>

    <main class="layout">
        <div>
            <div id="banner" class="banner" role="alert" hidden></div>

            <div id="order-form">
                <section class="card" aria-labelledby="customer-heading">
                    <h2 id="customer-heading">Customer</h2>
                    <div class="field-row">
                        <label class="field">
                            <span>Email</span>
                            <input id="customer-email" type="email" placeholder="e.g. thomas@example.com" autocomplete="off">
                            <small class="hint" id="customer-status"></small>
                            <small class="error" data-error-for="customer.email"></small>
                        </label>
                        <label class="field">
                            <span>Name</span>
                            <input id="customer-name" type="text" placeholder="auto-filled if email exists">
                            <small class="error" data-error-for="customer.name"></small>
                        </label>
                    </div>
                </section>

                <section class="card" aria-labelledby="products-heading">
                    <h2 id="products-heading">Products</h2>
                    <div class="table-wrap">
                        <table class="lines">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="num">Qty</th>
                                    <th class="num">Price</th>
                                    <th class="num">Tax</th>
                                    <th class="num">Line Total</th>
                                    <th><span class="hint">&nbsp;</span></th>
                                </tr>
                            </thead>
                            <tbody id="line-rows"></tbody>
                        </table>
                    </div>
                    <p class="empty" id="empty-lines">No products added yet.</p>
                    <small class="error" data-error-for="lines"></small>
                    <div class="add-row">
                        <select id="product-select" aria-label="Product to add">
                            <option value="">Loading products…</option>
                        </select>
                        <button type="button" id="add-product" class="btn btn-primary">+ Add Product</button>
                    </div>
                </section>

                <section class="card" aria-labelledby="payment-heading">
                    <h2 id="payment-heading">Payment</h2>
                    <div class="payment-grid">
                        <dl class="totals">
                            <div><dt>Subtotal</dt><dd id="subtotal">₹0.00</dd></div>
                            <div><dt>Tax</dt><dd id="tax-total">₹0.00</dd></div>
                            <div class="grand"><dt>Grand Total</dt><dd id="grand-total">₹0.00</dd></div>
                        </dl>
                        <div>
                            <label class="field">
                                <span>Amount Given by Customer</span>
                                <input id="amount-paid" type="text" inputmode="decimal" placeholder="₹250" autocomplete="off">
                                <small class="error" data-error-for="amount_paid"></small>
                            </label>
                            <div class="balance">
                                <span>Balance to Return:</span>
                                <strong id="balance">—</strong>
                                <small id="breakdown"></small>
                            </div>
                        </div>
                    </div>
                    <div class="actions">
                        <button type="button" id="generate-bill" class="btn btn-success">Generate Bill</button>
                        <small class="hint">Shows the bill here and emails a confirmation to the customer.</small>
                    </div>
                </section>
            </div>

            <section class="card bill" id="bill" aria-live="polite" hidden>
                <div class="bill-head">
                    <div>
                        <h2>Bill <span id="bill-number"></span></h2>
                        <p class="hint" id="bill-meta"></p>
                    </div>
                    <div class="bill-actions">
                        <button type="button" class="btn" id="print-bill">Print</button>
                        <button type="button" class="btn btn-primary" id="new-order">New Order</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="lines">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="num">Qty</th>
                                <th class="num">Unit Price</th>
                                <th class="num">Tax</th>
                                <th class="num">Line Total</th>
                            </tr>
                        </thead>
                        <tbody id="bill-rows"></tbody>
                    </table>
                </div>
                <dl class="totals" id="bill-totals"></dl>
                <p class="hint">Order confirmation email queued for <span id="bill-email"></span>.</p>
            </section>
        </div>

        <aside class="card alert-panel" aria-labelledby="low-stock-heading">
            <h2 id="low-stock-heading">⚠ Low Stock Alert</h2>
            <small class="hint" id="threshold-note"></small>
            <ul id="low-stock-list"></ul>
        </aside>
    </main>

    @verbatim
    <script>
    (() => {
        'use strict';

        // Mirrors the server rule. The server remains the authority; this only
        // avoids sending a quote request that is certain to be rejected.
        const AMOUNT_PATTERN = /^\d{1,10}(\.\d{1,2})?$/;
        const $ = (id) => document.getElementById(id);
        const state = { catalog: new Map(), rows: [], quote: null, quoteSeq: 0, submitting: false };
        let quoteTimer = null;

        async function api(method, path, body) {
            const headers = { Accept: 'application/json' };
            if (body !== undefined) headers['Content-Type'] = 'application/json';
            const response = await fetch(path, {
                method,
                headers,
                body: body === undefined ? undefined : JSON.stringify(body),
            });
            let data = null;
            try { data = await response.json(); } catch (error) { data = null; }
            return { status: response.status, data };
        }

        // Money arrives from the server as exact strings ("227.20") and is only
        // displayed here, never added up. Totals, tax and change are computed by
        // App\Support\Money so the screen cannot disagree with the bill.
        function rupees(amount) {
            if (amount === null || amount === undefined) return '—';
            const text = String(amount);
            return text.startsWith('-') ? `−₹${text.slice(1)}` : `₹${text}`;
        }

        function toPaise(amount) {
            const negative = amount.startsWith('-');
            const [whole, fraction = ''] = (negative ? amount.slice(1) : amount).split('.');
            const paise = Number(whole) * 100 + Number((fraction + '00').slice(0, 2));
            return negative ? -paise : paise;
        }

        // Suggested notes and coins for the change, in integer paise.
        function changeBreakdown(changeDue) {
            let paise = toPaise(changeDue);
            const parts = [];
            for (const rupee of [500, 200, 100, 50, 20, 10, 5, 2, 1]) {
                const count = Math.floor(paise / (rupee * 100));
                if (count > 0) {
                    parts.push(`${count} × ₹${rupee}`);
                    paise -= count * rupee * 100;
                }
            }
            if (paise > 0) parts.push(`${paise} paise`);
            return parts.join(' + ');
        }

        // Every value from the server is written with textContent, never
        // innerHTML, so a product named "<script>" is displayed, not run.
        function element(tag, text, className) {
            const node = document.createElement(tag);
            if (text !== undefined && text !== null) node.textContent = text;
            if (className) node.className = className;
            return node;
        }

        function showBanner(message) {
            const banner = $('banner');
            banner.hidden = !message;
            banner.className = 'banner error';
            banner.textContent = message || '';
        }

        function fieldError(key) {
            return document.querySelector(`[data-error-for="${CSS.escape(key)}"]`);
        }

        function clearErrors() {
            document.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
            document.querySelectorAll('#line-rows tr.has-error').forEach((row) => row.classList.remove('has-error'));
            showBanner(null);
            updateQuotedCells();
        }

        // Errors come back keyed like validation errors ("lines.2.quantity"), so
        // each one lands on the exact row or field it belongs to — all at once.
        function showErrors(payload) {
            showBanner(payload?.message ?? 'The order could not be placed.');
            for (const [key, messages] of Object.entries(payload?.errors ?? {})) {
                const lineMatch = key.match(/^lines\.(\d+)\./);
                if (lineMatch) {
                    const row = $('line-rows').children[Number(lineMatch[1])];
                    if (row) {
                        row.classList.add('has-error');
                        row.querySelector('.row-error').textContent = messages.join(' ');
                    }
                    continue;
                }
                const target = fieldError(key) ?? fieldError(key.split('.')[0]);
                if (target) target.textContent = messages.join(' ');
            }
        }

        async function loadCatalog() {
            const select = $('product-select');
            const { status, data } = await api('GET', '/api/products?per_page=100');
            select.replaceChildren();
            if (status !== 200) {
                select.append(new Option('Could not load products', ''));
                return;
            }
            state.catalog.clear();
            select.append(new Option('Select a product to add…', ''));
            for (const product of data.data) {
                state.catalog.set(product.id, product);
                const option = new Option(
                    `${product.name} (${product.code}) — ${rupees(product.price)} · ${product.stock_on_hand} in stock`,
                    String(product.id),
                );
                option.disabled = product.stock_on_hand === 0;
                select.append(option);
            }
        }

        async function loadLowStock() {
            const list = $('low-stock-list');
            const { status, data } = await api('GET', '/api/products/low-stock');
            list.replaceChildren();
            if (status !== 200) {
                list.append(element('li', 'Could not load the low-stock report.'));
                return;
            }
            $('threshold-note').textContent = `Products with fewer than ${data.meta.threshold} units`;
            if (data.data.length === 0) {
                list.append(element('li', 'Everything is sufficiently stocked.'));
                return;
            }
            for (const product of data.data) {
                const units = product.stock_on_hand === 1 ? 'unit' : 'units';
                list.append(element('li', `${product.name} — ${product.stock_on_hand} ${units} left`));
            }
        }

        function refreshInventory() {
            loadCatalog();
            loadLowStock();
        }

        async function lookupCustomer() {
            const input = $('customer-email');
            const email = input.value.trim();
            const status = $('customer-status');
            if (email === '' || !input.checkValidity()) {
                status.textContent = '';
                return;
            }
            const { status: code, data } = await api('GET', `/api/customers/lookup?email=${encodeURIComponent(email)}`);
            if (code !== 200 || input.value.trim() !== email) return;
            if (data.data) {
                $('customer-name').value = data.data.name;
                status.textContent = 'Existing customer — name filled in.';
            } else {
                status.textContent = 'New customer — enter their name.';
            }
        }

        function addSelectedProduct() {
            const productId = Number($('product-select').value);
            if (!productId) return;
            const existing = state.rows.find((row) => row.productId === productId);
            if (existing) {
                existing.quantity += 1;
            } else {
                state.rows.push({ productId, quantity: 1 });
            }
            $('product-select').value = '';
            renderRows();
            scheduleQuote();
        }

        function renderRows() {
            const body = $('line-rows');
            body.replaceChildren();
            $('empty-lines').hidden = state.rows.length > 0;

            state.rows.forEach((row, index) => {
                const product = state.catalog.get(row.productId);
                const tr = document.createElement('tr');

                const nameCell = element('td');
                nameCell.append(
                    element('div', product ? product.name : `Product #${row.productId}`),
                    element('small', product ? product.code : '', 'hint'),
                    element('small', '', 'row-error'),
                );

                const quantity = document.createElement('input');
                quantity.type = 'number';
                quantity.min = '1';
                quantity.step = '1';
                quantity.value = String(row.quantity);
                quantity.className = 'qty';
                quantity.setAttribute('aria-label', `Quantity of ${product ? product.name : 'product'}`);
                quantity.addEventListener('input', () => {
                    const value = Number(quantity.value);
                    if (Number.isInteger(value) && value >= 1) {
                        row.quantity = value;
                        scheduleQuote();
                    }
                });
                const quantityCell = element('td', null, 'num');
                quantityCell.append(quantity);

                const remove = element('button', 'Remove', 'btn-link');
                remove.type = 'button';
                remove.addEventListener('click', () => {
                    state.rows.splice(index, 1);
                    renderRows();
                    scheduleQuote();
                });
                const removeCell = element('td', null, 'num');
                removeCell.append(remove);

                tr.append(
                    nameCell,
                    quantityCell,
                    element('td', rupees(product?.price), 'num price'),
                    element('td', '—', 'num tax'),
                    element('td', '—', 'num total'),
                    removeCell,
                );
                body.append(tr);
            });

            updateQuotedCells();
        }

        // Updates figures in place rather than re-rendering, so a quantity field
        // the cashier is typing in keeps its focus.
        function updateQuotedCells() {
            const lines = state.quote?.lines ?? [];
            [...$('line-rows').children].forEach((tr, index) => {
                const row = state.rows[index];
                const quoted = lines[index];
                const line = row && quoted && quoted.product_id === row.productId && quoted.quantity === row.quantity
                    ? quoted
                    : null;
                tr.querySelector('.tax').textContent = line ? `${rupees(line.line_tax)} (${line.tax_percentage}%)` : '—';
                tr.querySelector('.total').textContent = line ? rupees(line.line_total) : '—';
                if (line) {
                    tr.querySelector('.price').textContent = rupees(line.unit_price);
                    tr.querySelector('.row-error').textContent = line.is_available
                        ? ''
                        : (line.stock_on_hand === 0 ? 'Out of stock' : `Only ${line.stock_on_hand} in stock`);
                }
            });
        }

        function renderTotals() {
            const quote = state.quote;
            $('subtotal').textContent = rupees(quote?.subtotal ?? '0.00');
            $('tax-total').textContent = rupees(quote?.tax_total ?? '0.00');
            $('grand-total').textContent = rupees(quote?.total ?? '0.00');

            const balance = $('balance');
            const breakdown = $('breakdown');
            balance.classList.remove('owed');
            breakdown.textContent = '';

            if (!quote || quote.change_due === null) {
                balance.textContent = '—';
                return;
            }
            if (quote.change_due.startsWith('-')) {
                balance.textContent = `Customer still owes ${rupees(quote.change_due.slice(1))}`;
                balance.classList.add('owed');
                return;
            }
            balance.textContent = rupees(quote.change_due);
            const suggestion = changeBreakdown(quote.change_due);
            if (suggestion) breakdown.textContent = `→ ${suggestion}`;
        }

        const linesPayload = () => state.rows.map((row) => ({ product_id: row.productId, quantity: row.quantity }));

        function scheduleQuote() {
            window.clearTimeout(quoteTimer);
            quoteTimer = window.setTimeout(requestQuote, 250);
        }

        async function requestQuote() {
            const seq = ++state.quoteSeq;
            const amount = $('amount-paid').value.trim();
            const amountIsValid = amount === '' || AMOUNT_PATTERN.test(amount);
            fieldError('amount_paid').textContent = amountIsValid ? '' : 'Enter an amount such as 250 or 250.50.';

            if (state.rows.length === 0) {
                state.quote = null;
                renderTotals();
                updateQuotedCells();
                return;
            }

            const payload = { lines: linesPayload() };
            if (amount !== '' && amountIsValid) payload.amount_paid = amount;

            try {
                const { status, data } = await api('POST', '/api/orders/quote', payload);
                // A newer quote has been requested since; this answer is stale.
                if (seq !== state.quoteSeq) return;
                state.quote = status === 200 ? data.data : null;
                if (status === 422) showErrors(data);
            } catch (error) {
                if (seq !== state.quoteSeq) return;
                state.quote = null;
            }
            renderTotals();
            updateQuotedCells();
        }

        function setSubmitting(isSubmitting) {
            state.submitting = isSubmitting;
            const button = $('generate-bill');
            button.disabled = isSubmitting;
            button.textContent = isSubmitting ? 'Generating…' : 'Generate Bill';
        }

        async function generateBill() {
            // Disabled while in flight, so a double-click cannot place two orders.
            if (state.submitting) return;
            clearErrors();
            if (state.rows.length === 0) {
                showBanner('Add at least one product before generating the bill.');
                return;
            }

            const payload = {
                customer: {
                    email: $('customer-email').value.trim(),
                    name: $('customer-name').value.trim(),
                },
                lines: linesPayload(),
            };
            const amount = $('amount-paid').value.trim();
            if (amount !== '') payload.amount_paid = amount;

            setSubmitting(true);
            try {
                const { status, data } = await api('POST', '/api/orders', payload);
                if (status === 201) {
                    showBill(data.data);
                    refreshInventory();
                    return;
                }
                if (status === 422) {
                    showErrors(data);
                    // Stock may have moved under us; show the cashier current figures.
                    refreshInventory();
                    return;
                }
                showBanner(`The order could not be placed (HTTP ${status}). Please try again.`);
            } catch (error) {
                showBanner('Network error — the order was not placed. Please try again.');
            } finally {
                setSubmitting(false);
            }
        }

        function totalRow(label, value, className) {
            const row = element('div', null, className);
            row.append(element('dt', label), element('dd', value));
            return row;
        }

        function showBill(order) {
            $('order-form').hidden = true;
            $('bill').hidden = false;
            $('bill-number').textContent = order.order_number;
            $('bill-meta').textContent =
                `${new Date(order.placed_at).toLocaleString('en-IN')} · ${order.customer.name} (${order.customer.email})`;

            const body = $('bill-rows');
            body.replaceChildren();
            for (const item of order.items) {
                const tr = document.createElement('tr');
                tr.append(
                    element('td', item.product ? item.product.name : `Item #${item.id}`),
                    element('td', String(item.quantity), 'num'),
                    element('td', rupees(item.unit_price), 'num'),
                    element('td', `${rupees(item.line_tax)} (${item.tax_percentage}%)`, 'num'),
                    element('td', rupees(item.line_total), 'num'),
                );
                body.append(tr);
            }

            const totals = $('bill-totals');
            totals.replaceChildren(
                totalRow('Subtotal', rupees(order.subtotal)),
                totalRow('Tax', rupees(order.tax_total)),
                totalRow('Grand Total', rupees(order.total), 'grand'),
            );
            if (order.amount_paid !== null) {
                const suggestion = changeBreakdown(order.change_due);
                totals.append(
                    totalRow('Amount Paid', rupees(order.amount_paid)),
                    totalRow('Change Returned', rupees(order.change_due) + (suggestion ? ` (${suggestion})` : '')),
                );
            }

            $('bill-email').textContent = order.customer.email;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function newOrder() {
            state.rows = [];
            state.quote = null;
            state.quoteSeq += 1;
            for (const id of ['customer-email', 'customer-name', 'amount-paid']) $(id).value = '';
            $('customer-status').textContent = '';
            $('bill').hidden = true;
            $('order-form').hidden = false;
            clearErrors();
            renderRows();
            renderTotals();
            $('customer-email').focus();
        }

        $('add-product').addEventListener('click', addSelectedProduct);
        $('product-select').addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addSelectedProduct();
            }
        });
        $('customer-email').addEventListener('change', lookupCustomer);
        $('amount-paid').addEventListener('input', scheduleQuote);
        $('generate-bill').addEventListener('click', generateBill);
        $('print-bill').addEventListener('click', () => window.print());
        $('new-order').addEventListener('click', newOrder);

        renderRows();
        renderTotals();
        refreshInventory();
    })();
    </script>
    @endverbatim
</body>
</html>
