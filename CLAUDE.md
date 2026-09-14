# CLAUDE.md — Store Order & Inventory System

Project rules for anyone (human or AI) writing code in this repository. Read this
before your first change. When a rule here conflicts with a habit from another
Laravel project, this file wins.

**Stack:** PHP 8.3+ · Laravel 12/13 · PostgreSQL 15+ · PHPUnit

---

## 1. The one rule that matters most

**Stock is only ever changed inside `App\Actions\Orders\CreateOrderAction`, under
a `lockForUpdate()` row lock, inside a transaction.**

This system's whole reason for existing is that it must not sell the same unit
twice. Every other rule in this file is ordinary good practice; this one is the
product. If you are about to write `->decrement('stock_on_hand')` or
`UPDATE products SET stock_on_hand` anywhere else, stop and reconsider.

---

## 2. Architecture

### Layer responsibilities

| Layer | Directory | Does | Never does |
|---|---|---|---|
| Route | `routes/api.php` | Maps URL → controller, applies middleware | Contains logic |
| Form Request | `app/Http/Requests` | Validates shape, types, existence | Touches stock or prices |
| Controller | `app/Http/Controllers` | Translates input → action → response | Business rules, queries |
| Action | `app/Actions` | One business operation, start to finish | Reads `request()` |
| Model | `app/Models` | Relationships, casts, scopes | Multi-step workflows |
| Resource | `app/Http/Resources` | Shapes the JSON response | Computes business values |
| Job | `app/Jobs` | Async side effects | Anything the response depends on |

### Request flow

```
POST /api/orders
  → StoreOrderRequest        validate shape; reject malformed input
  → OrderController::store   translate to DTOs, call the action
  → CreateOrderAction        transaction: lock → verify → price → persist → deduct
  → SendOrderConfirmation    queued, dispatched afterCommit
  → OrderResource            shape the 201 response
```

### Actions

One public method, named `execute()`. One business operation per class. Named as
a verb phrase: `CreateOrderAction`, `ResolveCustomerAction`.

An action must be callable from a controller, an Artisan command, a test, or a
queued job without modification. That is the test of whether it is written
correctly: **if it reads `request()`, `auth()`, or `session()`, it is not an
action, it is a controller with extra steps.** Pass everything in as arguments.

Dependencies go in the constructor for the container to resolve. Data goes in the
method signature.

```php
// Good
public function __construct(private readonly ResolveCustomerAction $resolveCustomer) {}
public function execute(CustomerData $customer, array $lines): Order

// Bad — untestable, hidden dependency on HTTP
public function execute(): Order
{
    $email = request()->input('customer.email');
}
```

### Controllers are thin

A controller method should be under about 15 lines. It has exactly four jobs:
take validated input, build domain objects, call an action, shape the response.

**No `try`/`catch` in controllers for domain exceptions.** `InsufficientStockException`
defines its own `render()` method and maps itself to a 422. Catching it in the
controller duplicates that mapping in a second place where it will drift.

### DTOs over arrays

Anything crossing a layer boundary is a `readonly` class in `app/Data`, not an
associative array. `$line->quantity` fails loudly when it is wrong;
`$line['qty']` returns null and corrupts a total silently.

---

## 3. Database

### PostgreSQL is required

Not "recommended". The application depends on `SELECT ... FOR UPDATE` row locking
and on `CHECK` constraints. SQLite locks the entire database file rather than a
row, so a SQLite test suite would pass while proving nothing about the behaviour
this system is built around. Do not add a SQLite fallback "to make tests faster".

### Money

- **Columns are `NUMERIC(12,2)`.** Never `float`, never `double`.
- **Models cast money to `decimal:2`,** which returns strings. This is deliberate —
  strings preserve the exact stored value.
- **All arithmetic goes through `App\Support\Money`,** which converts to integer
  paise, computes, and converts back. Integers are exact; floats are not.
- **Never add or multiply a `decimal:2` attribute directly.** `$product->price * $qty`
  silently casts to float and reintroduces the bug `Money` exists to prevent.
- **Tax rounds per line, half-up** — not once on the order total. This matches GST
  invoicing and keeps `line_subtotal + line_tax === line_total` true on every row.

### Snapshot financial data

Order lines store `unit_price` and `tax_percentage` copied from the product at the
moment of sale. This duplication is intentional and is not a normalisation error.

Joining to `products` at read time would mean tomorrow's price change silently
rewrites every historical bill. A financial record must reflect what was actually
charged.

The rule generalises: **anything a customer was shown or charged gets snapshotted.
Anything descriptive (product name, category) is read live via the relationship.**

### Migrations

- One concern per migration. Never edit a migration that has run anywhere but your
  own machine — write a new one.
- Every foreign key declares its delete behaviour explicitly. Pick deliberately:
  - `cascadeOnDelete()` — child is meaningless alone (`order_items` → `orders`)
  - `restrictOnDelete()` — child is a financial record (`orders` → `customers`)
  - `nullOnDelete()` — link is optional context (`stock_movements` → `orders`)
- **Postgres has no unsigned integers.** Laravel's `unsignedInteger()` silently
  emits a plain `integer` with no constraint. Where non-negativity matters, write
  the `CHECK` constraint by hand via `DB::statement()`.
- Index what you filter and sort on. `orders` carries `(customer_id, created_at)`
  because that is exactly how order history is queried.

### Constraints are part of the design

Application validation is the friendly error message; the database constraint is
the guarantee. Both, always, for anything that would be a correctness bug:

```php
DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_on_hand_non_negative CHECK (stock_on_hand >= 0)');
```

If a bug ever bypasses `CreateOrderAction`, this is what stops it becoming
negative inventory nobody notices for a month.

### Emails

Lower-cased on write via a model mutator, then a plain `unique` index. This gives
case-insensitive uniqueness without a hard dependency on the `CITEXT` extension.
Always lower-case the input before querying by email.

---

## 4. Concurrency

### The pattern

```php
DB::transaction(function () use ($lines) {
    $productIds = OrderLineData::productIds($lines);
    sort($productIds);                  // 1. consistent order

    $products = Product::whereIn('id', $productIds)
        ->orderBy('id')
        ->lockForUpdate()               // 2. inside the transaction
        ->get()
        ->keyBy('id');

    // 3. verify against the locked values, then write
}, attempts: 3);
```

Three things, all mandatory:

1. **Sort the IDs.** Two orders containing the same products in opposite order will
   deadlock if locks are taken in request order. Ascending ID, always.
2. **Lock inside the transaction.** Row locks release at `COMMIT`. A
   `lockForUpdate()` outside a transaction commits immediately and protects
   nothing — while still looking correct in review.
3. **Re-read after locking.** Data fetched before the lock is stale by definition.
   Validate against what the locked query returned, not against a model you loaded
   earlier in the request.

### Never validate stock in a Form Request

A validation rule runs outside the lock and outside the transaction. Its answer is
stale before it returns. Worse, it makes the code *look* safe — reviewers see stock
being checked and stop looking. Stock is checked once, under lock, in the action.

### Hold locks briefly

Do no work inside a transaction that does not need to be there. Resolve the
customer, call external services, and render responses outside it. Every
millisecond a product row stays locked is a millisecond a concurrent sale of that
product is blocked.

---

## 5. Queued jobs

- **Always `->afterCommit()`.** A worker is a separate process with its own
  connection. A job dispatched mid-transaction can start before `COMMIT` and query
  a row that is not visible to it yet — an intermittent failure that is miserable
  to reproduce.
- **Pass IDs, not models.** Forces a fresh read of committed data.
- **Jobs are for side effects only.** If the HTTP response depends on the result,
  it does not belong in a queue.
- **Set `$tries` and `$backoff` explicitly,** and implement `failed()` so a
  permanently failed job leaves a usable log line rather than vanishing.
- **`QUEUE_CONNECTION=sync` is not acceptable** outside quick local debugging. It
  runs jobs inline, which satisfies the async requirement in name only and hides
  serialisation bugs until production.

---

## 6. Testing

### What must be tested

Every action gets a feature test covering the happy path **and** the failure path.
For anything touching money or stock, the failure path matters more — a rejected
order is an inconvenience, a partially-applied one is a corrupted ledger.

Required for any stock-touching change:

- Success: totals correct, stock deducted, ledger row written, job dispatched
- Failure: **nothing** persisted — no order, no lines, no ledger, no job, no
  partial deduction of the lines that would have fitted
- Boundary: ordering exactly the remaining stock succeeds (`<` vs `<=` bugs live here)
- Concurrency: the lock is present and it blocks

### Assert absence, not just presence

The most valuable assertion in this suite is that a failed order leaves no trace.
Tests that only check the happy path pass just as well against code that
half-applies an order.

### Test names describe behaviour

`test_a_failed_order_leaves_no_trace_at_all`, not `test_order_fails`. The name
should tell you what broke without opening the file.

### Factories never bypass business rules

`OrderFactory` builds headers for read-back tests only; it does not touch stock.
**Any test about stock or totals must go through the API or the action.** A factory
that writes an order directly is testing the factory.

---

## 7. Code style

- `declare(strict_types=1);` at the top of every PHP file.
- Type every parameter, property and return. `mixed` needs a comment justifying it.
- `final readonly` for DTOs.
- Constructor property promotion; `private readonly` for injected dependencies.
- Constants over magic strings: `StockMovement::REASON_ORDER_PLACED`, never
  `'order_placed'` inline.
- Guard clauses and early returns over nested conditionals.
- PSR-12, enforced by Pint: `./vendor/bin/pint` before every commit.

### Comments

Comment **why**, never **what**. `// increment the counter` above `$i++` is noise.

Comment generously where a reader would otherwise reasonably assume the code is
wrong or redundant — and this codebase has several such places:

- why order lines duplicate product prices (snapshotting, not a normalisation bug)
- why IDs are sorted before locking (deadlock avoidance, looks arbitrary)
- why stock is not validated in the Form Request (the check would be stale)
- why the job takes an ID instead of a model

Each of those looks like something to "clean up" until you know the reason. The
comment is what stops a future maintainer from helpfully reintroducing a bug.

### Naming

- Actions: `VerbNounAction`
- Form Requests: `VerbNounRequest`
- Boolean methods read as assertions: `isLowStock()`, not `checkStock()`
- Database: `snake_case`, plural tables, singular foreign keys (`customer_id`)

---

## 8. API conventions

- Responses always go through a Resource class. Never `return $model`.
- Status codes: `201` created, `422` validation or unprocessable state, `404`
  genuinely missing resource, `200` otherwise.
- Business failures return the same envelope as validation failures
  (`{message, errors}`) so the client needs one error path, not two.
- **Return every failure at once,** not just the first. A cashier told about one
  short item at a time makes three round trips for one problem.
- Collection endpoints paginate. Always, from day one — retrofitting pagination
  is a breaking change.
- Don't leak existence. An unknown email on order history returns an empty list,
  not a 404; a 404 would confirm whether an address shops here.

---

## 9. Before you commit

```bash
./vendor/bin/pint                     # format
php artisan test                      # full suite, against Postgres
./scripts/concurrency-check.sh        # end-to-end overselling check
```

Checklist for any change touching stock or money:

- [ ] Stock changes only inside `CreateOrderAction`
- [ ] Locking inside a transaction, IDs sorted
- [ ] Money arithmetic via `App\Support\Money`, never float
- [ ] Failure path test asserts nothing was persisted
- [ ] Job dispatched `afterCommit()`
- [ ] New non-negative or range invariants have a DB `CHECK` constraint
