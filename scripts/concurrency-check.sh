#!/usr/bin/env bash
#
# End-to-end overselling check.
#
# The test suite proves the lock is taken and that it blocks. This proves the
# thing that actually matters: that N units of stock, hit by more simultaneous
# buyers than there are units, sell exactly N times and never N+1.
#
# Requires a running API and psql pointed at the same database.
#
#   php artisan serve &
#   ./scripts/concurrency-check.sh                 # 10 units, 40 buyers
#   STOCK=1 BUYERS=2 ./scripts/concurrency-check.sh  # the brief's exact scenario
#
# Note: `php artisan serve` handles one request at a time on Windows, so run the
# API behind PHP-FPM, Octane or Docker for this to exercise real concurrency.
#
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
STOCK="${STOCK:-10}"
BUYERS="${BUYERS:-40}"

PGHOST="${DB_HOST:-127.0.0.1}"
PGPORT="${DB_PORT:-5432}"
PGDATABASE="${DB_DATABASE:-pos_inventory}"
PGUSER="${DB_USERNAME:-postgres}"
export PGHOST PGPORT PGDATABASE PGUSER
export PGPASSWORD="${DB_PASSWORD:-postgres}"

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

psql_value() {
  psql --no-align --tuples-only --quiet --command "$1"
}

echo "Seeding one product with ${STOCK} units..."
CODE="CONCURRENCY-CHECK-$(date +%s)-$$"
PRODUCT_ID="$(psql_value "
  INSERT INTO products (code, name, price, tax_percentage, stock_on_hand, created_at, updated_at)
  VALUES ('${CODE}', 'Concurrency check', 100.00, 18.00, ${STOCK}, now(), now())
  RETURNING id;
")"
PRODUCT_ID="$(echo "$PRODUCT_ID" | tr -d '[:space:]')"

echo "Firing ${BUYERS} simultaneous orders for 1 unit each at product ${PRODUCT_ID}..."
seq 1 "$BUYERS" | xargs -P "$BUYERS" -I {} \
  curl --silent --output /dev/null --write-out "%{http_code}\n" \
    --header 'Content-Type: application/json' \
    --header 'Accept: application/json' \
    --data "{
      \"customer\": {\"name\": \"Buyer {}\", \"email\": \"buyer-{}@example.test\"},
      \"lines\": [{\"product_id\": ${PRODUCT_ID}, \"quantity\": 1}]
    }" \
    "${BASE_URL}/api/orders" \
  > "${WORK_DIR}/status-codes.txt"

CREATED="$(grep -c '^201$' "${WORK_DIR}/status-codes.txt" || true)"
REJECTED="$(grep -c '^422$' "${WORK_DIR}/status-codes.txt" || true)"
OTHER="$(grep -cv -e '^201$' -e '^422$' "${WORK_DIR}/status-codes.txt" || true)"

SOLD="$(psql_value "
  SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE product_id = ${PRODUCT_ID};
" | tr -d '[:space:]')"
REMAINING="$(psql_value "
  SELECT stock_on_hand FROM products WHERE id = ${PRODUCT_ID};
" | tr -d '[:space:]')"

echo
echo "  201 Created       ${CREATED}"
echo "  422 Rejected      ${REJECTED}"
echo "  other responses   ${OTHER}"
echo "  units sold        ${SOLD}"
echo "  stock remaining   ${REMAINING}"
echo

FAILED=0
[ "$CREATED" -eq "$STOCK" ] || { echo "FAIL: ${CREATED} orders succeeded against ${STOCK} units of stock."; FAILED=1; }
[ "$SOLD" -eq "$STOCK" ]    || { echo "FAIL: ${SOLD} units were sold out of ${STOCK}."; FAILED=1; }
[ "$REMAINING" -eq 0 ]      || { echo "FAIL: stock finished at ${REMAINING}, expected 0."; FAILED=1; }
[ "$OTHER" -eq 0 ]          || { echo "FAIL: ${OTHER} requests failed with an unexpected status."; FAILED=1; }

if [ "$FAILED" -ne 0 ]; then
  echo "Overselling check FAILED."
  exit 1
fi

echo "No overselling: exactly ${STOCK} units sold to ${BUYERS} simultaneous buyers."
