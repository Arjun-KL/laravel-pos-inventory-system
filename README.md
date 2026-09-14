# Store Order & Inventory Mini-System

A Laravel 12 + PostgreSQL application for a retail billing counter. It records
customer orders against a product catalogue, keeps stock in sync, and — the part
that matters most — **cannot sell the same unit twice**, even when two orders for
the last unit arrive at the same moment.

Built for the Mallow Technologies *Laravel Developer* take-home assignment.

- [Brief coverage](#brief-coverage)
- [Setup](#setup)
- [Using the application](#using-the-application)
- [API reference](#api-reference)
- [Design decisions](#design-decisions)
- [Assumptions](#assumptions)
- [Testing](#testing)
- [Project structure](#project-structure)
- [AI-assisted development](#ai-assisted-development)

---

## Brief coverage

| Brief item | Implementation |
|---|---|
| Products, customers, orders with subtotal, tax and grand total | `products`, `customers`, `orders`, `order_items` tables and Eloquent models |
| **1.** Normalised schema, plus supporting tables | Five migrations, including `order_items` and a `stock_movements` ledger, with `CHECK` constraints |
| Seed sample products and customers | `ProductSeeder` (12 retail items, several low on stock) and `CustomerSeeder` |
| **2.** Create-order API | `POST /api/orders` → `CreateOrderAction` |
| **3.** Order history by email | `GET /api/orders?email=` |
| **4.** Low-stock products, configurable threshold | `GET /api/products/low-stock` + `LOW_STOCK_THRESHOLD` (overridable per request) |
| **5.** Queued confirmation email | `SendOrderConfirmation` job, dispatched `afterCommit()`, delivered to the log mailer |
| **6.** Tests including edge cases | 55 PHPUnit tests, 221 assertions — see [Testing](#testing) |
| **7.** Safe under concurrent requests | Row lock (`SELECT … FOR UPDATE`) inside a transaction, proven by a test that races two real PHP processes for the last unit |
| Wireframe | Billing screen at `/` |

---

## Setup

Full step-by-step instructions, including Windows notes and troubleshooting, are
in **[SETUP.md](SETUP.md)**.

The short version needs PHP 8.2+ with the `pdo_pgsql` extension, Composer, and a
PostgreSQL 15+ **server** (PostgreSQL is required — see
[why](#postgresql-not-sqlite)):

```bash
composer install
cp .env.example .env              # then set DB_PASSWORD
php artisan key:generate

# create two databases: pos_inventory and pos_inventory_testing

php artisan migrate --seed
php artisan serve                 # http://127.0.0.1:8000
php artisan queue:work            # in a second terminal, for confirmation emails
```

Confirmation emails are written to `storage/logs/laravel.log` (`MAIL_MAILER=log`).

---

## Using the application

Open **http://127.0.0.1:8000**. The billing screen follows the brief's wireframe.

1. **Customer.** Type an email. A known customer's name fills in automatically
   (try `thomas@example.com`); otherwise, enter a name for a new customer.
2. **Products.** Pick from the dropdown and press *Add Product*. Adding the same
   product again increases its quantity. Price, tax and line total come from the
   server as you edit, and any line that exceeds current stock is flagged.
3. **Payment.** Subtotal, tax and grand total update live. Enter the amount the
   customer handed over to see the balance to return, with a suggested
   note-and-coin breakdown.
4. **Generate Bill.** This places the order.
   - On success, the bill is shown with *Print* and *New Order*, stock and the
     low-stock panel refresh, and a confirmation email is queued.
   - On failure, every problem is shown at once, against the exact row or field it
     concerns.

The **Low Stock Alert** panel lists products below the configured threshold.

---

## API reference

All endpoints return JSON. Every failure uses the same envelope, `{ "message",
"errors" }` with status `422`, whether the cause is validation, stock or payment,
so a client needs only one error path. Collection endpoints are paginated.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/products?per_page=` | Catalogue with price, tax and stock (`per_page` ≤ 100) |
| `GET` | `/api/products/low-stock?threshold=` | Products with stock strictly below the threshold, lowest first |
| `GET` | `/api/customers/lookup?email=` | The customer for an email, or `data: null` |
| `POST` | `/api/orders/quote` | Price a cart without placing it — nothing is written or reserved |
| `POST` | `/api/orders` | Place an order |
| `GET` | `/api/orders?email=` | A customer's order history, newest first |

### `POST /api/orders`

```json
{
  "customer": { "email": "thomas@example.com", "name": "Thomas Mathew" },
  "lines": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 2, "quantity": 5 }
  ],
  "amount_paid": "250.00"
}
```

`amount_paid` is optional. A successful order returns `201`:

```json
{
  "data": {
    "order_number": "ORD-01M2EXH6RJA1JSPVS3BS4ENSCS",
    "status": "placed",
    "subtotal": "150.00",
    "tax_total": "27.00",
    "total": "177.00",
    "amount_paid": "250.00",
    "change_due": "73.00",
    "customer": { "id": 1, "name": "Thomas Mathew", "email": "thomas@example.com" },
    "items": [
      {
        "quantity": 2, "unit_price": "50.00", "tax_percentage": "18.00",
        "line_subtotal": "100.00", "line_tax": "18.00", "line_total": "118.00",
        "product": { "id": 1, "code": "COL-TP-100", "name": "Colgate Toothpaste 100g" }
      }
    ]
  }
}
```

Insufficient stock returns `422`. Every short line is reported, keyed by its
position in the request:

```json
{
  "message": "One or more items are no longer available in the quantity requested.",
  "errors": {
    "lines.0.quantity": ["Farm Eggs (12) (EGGS-12): only 2 in stock, 5 requested."]
  }
}
```

Money is always a string with two decimal places, never a JSON float.

---

## Design decisions

### Overselling is prevented by a row lock, not by validation

`CreateOrderAction` is the only code that changes stock. Inside one transaction, it:

1. sorts the product ids in ascending order,
2. locks those rows with `SELECT … FOR UPDATE`,
3. checks stock against the values *returned by the locked query*,
4. prices the order, then saves it and deducts stock.

A second order for the same product waits at step 2 until the first one commits.
It then reads the updated stock and fails cleanly. Because the ids are sorted, two
orders touching the same products in opposite order cannot deadlock. Orders for
*different* products never wait for each other.

Stock is deliberately **not** checked in the Form Request. A validation rule runs
outside the lock, so its answer is already stale by the time it returns, even
though the code looks safe in review.

As a backstop, the database enforces `CHECK (stock_on_hand >= 0)`. If a future bug
ever bypasses the action, the write fails loudly instead of creating negative
inventory.

### Money is never a float

Money columns are `NUMERIC(12,2)`, and models cast them to strings. All arithmetic
goes through `App\Support\Money`, which works in integer paise. Tax is rounded
half-up **per line**, so `line_subtotal + line_tax = line_total` holds on every
row. A `CHECK` constraint enforces that too.

### Order lines snapshot what was charged

`order_items` copies `unit_price` and `tax_percentage` from the product at the
moment of sale. This duplication is intentional, not a normalisation mistake:
joining to `products` at read time would let a future price change rewrite past
bills. Descriptive data, such as the product name, is read live.

### The screen never does money arithmetic

The billing screen gets prices, totals and change from `POST /api/orders/quote`
instead of computing them in JavaScript, so it cannot disagree with the bill. The
pricing logic lives in one place, `App\Support\OrderPricing`, shared by the quote
and the real order. A quote takes no lock and reserves nothing; the final price and
stock check always happen under the lock.

### Asynchronous work happens after commit

`SendOrderConfirmation` is dispatched with `afterCommit()`. A queue worker has its
own database connection, so a job dispatched mid-transaction could start before the
order is visible to it. The job receives an order **id** rather than a model, so it
reads committed data. It sets `$tries` and `$backoff`, and logs permanent failures
in `failed()`.

### Laravel structure

- **Thin controllers** translate validated input into DTOs (`app/Data`) and call a
  single-purpose action (`app/Actions`).
- **Actions never read the request**, so they can be called unchanged from a
  controller, a command, a job or a test.
- **Domain exceptions render their own `422` responses**, so controllers contain no
  `try`/`catch`.
- **API Resources** shape every response.

### PostgreSQL, not SQLite

The guarantee above depends on row-level locking and `CHECK` constraints. SQLite
locks the whole database file rather than a single row, so a SQLite test suite
would pass while proving nothing about concurrency. The tests run against
PostgreSQL too.

---

## Assumptions

Where the brief was open to interpretation, these are the calls I made:

1. **Product "unique code"** is a free-form, unique `code` string (e.g.
   `COL-TP-100`).
2. **Tax is exclusive.** It is a per-product percentage added on top of the unit
   price, rounded half-up to the paisa on each line. The currency is INR.
3. **Email identifies a customer**, case-insensitively. An existing customer keeps
   the name on file; a name typed at checkout is only used for a new customer, so a
   typo cannot rename anyone. A name is still required on every order.
4. **Low stock means strictly below the threshold.** With a threshold of 10, a
   product with exactly 10 units is not listed. There is one global threshold,
   set by `LOW_STOCK_THRESHOLD` (default 10) and overridable with `?threshold=`.
   There are no per-product thresholds.
5. **Payment is optional cash**, following the wireframe. If `amount_paid` is sent,
   it must cover the total, and the change is stored with the order. Card, split
   and partial payments are out of scope.
6. **Stock problems are reported before payment problems.** Whether a payment
   covers an order only matters once the order can be fulfilled, so an order that
   is both short on stock and underpaid reports the stock error.
7. **The same product may appear on several lines** (e.g. scanned twice). The lines
   stay separate on the bill, but their quantities are added together for the
   stock check.
8. **"Emails PDF to customer"** in the wireframe is simulated, as requirement 5
   allows: a queued job sends an HTML confirmation to the log mailer. No PDF is
   generated.
9. **Customer lookup reveals whether an email belongs to a customer.** The
   wireframe's name auto-fill requires this. It is acceptable for a staff-only
   counter screen; once authentication exists, the endpoint would require a staff
   role.
10. **There is no authentication, product management, cancellation or refund
    handling**, as none of these are in the brief. The stock ledger's `reason`
    column is ready for cancellations and adjustments.
11. **Seeders create products and customers, but no orders.** A seeded order would
    bypass the stock and pricing rules. Opening stock is set directly and is not
    recorded in the ledger, because it is not a sale.
12. **Order numbers are ULID-based** (`ORD-01M2…`). They are unique and
    time-ordered without a shared counter, but less friendly than sequential
    numbers.
13. **Limits:** at most 100 lines per order and 10,000 units per line. The screen's
    product dropdown loads the first 100 products, which suits a small counter
    catalogue.

---

## Testing

```bash
php artisan test
```

55 tests, all run against PostgreSQL:

| Area | What is covered |
|---|---|
| **Genuine concurrency (requirement 7)** | Two separate PHP processes race for the last unit: exactly one order is placed, the other is rejected, stock ends at 0, and only one email is queued |
| Placing orders | Totals and per-line tax rounding, stock deduction, ledger rows, job dispatch |
| **A failure leaves no trace** | A rejected order writes no order, lines, ledger row or job, and does not partly deduct the lines that would have fitted |
| Boundaries | Ordering exactly the remaining stock succeeds; one more fails |
| Every failure at once | Several short lines are reported together, each keyed by its line index |
| Duplicate lines | Added together for the stock check; deducted once |
| Payment | Change recorded, exact payment accepted, underpayment rejected with no trace, more than 2 decimal places rejected |
| Snapshotting | A later price change does not alter a past order line |
| Customers | Case-insensitive matching; an existing name is not overwritten |
| Quote | Prices without writing anything, flags lines over stock, shows change or the amount still owed |
| Low stock | Strictly below the threshold, per-request override, an empty override falls back to the configured value, lowest stock first |
| Catalogue, lookup, history | Pagination, page-size caps, an empty list rather than 404 for an unknown email |
| Row locking | The lock query runs once, in ascending id order |
| The lock blocks | With two database connections, the second cannot lock a product row the first has locked (PostgreSQL `55P03`), but can lock a different product |
| Seeding and screen | Seeders produce low-stock items and no orders; the billing screen renders |
| Money | Exact arithmetic, half-up rounding, negative change, comparison by value |

### How the concurrency test forces a real race

Starting two processes doesn't guarantee they overlap, since one could finish before
the other begins. So `ConcurrentOrdersTest`:

1. takes the product's row lock itself,
2. starts two PHP processes (`tests/Support/place-order.php`), each ordering the
   last unit,
3. polls `pg_locks` until PostgreSQL reports **both** orders waiting on that lock,
4. releases the lock, so the two orders compete for the row at the same instant.

It then asserts that exactly one process placed its order and the other was
rejected for insufficient stock.

### End-to-end HTTP check

```bash
STOCK=1 BUYERS=2 ./scripts/concurrency-check.sh    # the brief's exact scenario, over HTTP
./scripts/concurrency-check.sh                      # 10 units, 40 simultaneous buyers
```

This script sends real parallel HTTP requests and asserts that exactly `STOCK`
orders succeed. On Windows, `php artisan serve` handles one request at a time, so
the script needs PHP-FPM, Octane or Docker to exercise real concurrency.
`ConcurrentOrdersTest` does not have this limitation.

---

## Project structure

```
app/
  Actions/Orders/CreateOrderAction.php         the only place stock changes
  Actions/Orders/QuoteOrderAction.php          price a cart, no lock, no writes
  Actions/Customers/ResolveCustomerAction.php
  Data/                                        readonly DTOs passed between layers
  Exceptions/                                  self-rendering 422 domain errors
  Http/Controllers/Api/                        thin controllers
  Http/Requests/                               shape validation (never stock)
  Http/Resources/                              response shaping
  Jobs/SendOrderConfirmation.php               queued, afterCommit
  Support/Money.php                            integer-paise arithmetic
  Support/OrderPricing.php                     the single pricing definition
config/inventory.php                           low-stock threshold
database/migrations/                           schema + CHECK constraints
database/seeders/                              sample catalogue and customers
resources/views/billing.blade.php              the billing screen
scripts/concurrency-check.sh                   end-to-end HTTP overselling check
tests/                                         55 PHPUnit tests
tests/Support/place-order.php                  child process used by ConcurrentOrdersTest
CLAUDE.md                                      engineering rules used during development
SETUP.md                                       detailed setup guide
```

---

## AI-assisted development

This project was built with **Claude Code**. Before any application code was
written, the engineering rules were written down in [`CLAUDE.md`](CLAUDE.md). For
example:

- stock changes only inside one action, under a row lock;
- money is computed only with integer arithmetic;
- failure-path tests must assert that nothing was saved.

The assistant worked within those rules, and they served as the review checklist.

Screenshots of the prompts used are in [`/prompts`](prompts).
