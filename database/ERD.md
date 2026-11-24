# Bloomify ERD (Entity Relationship Diagram)

```
users 1 ──── 1 carts 1 ──── * cart_items 1 ──── * cart_item_details * ──── 1 flower_types
  │                              │
  │                              * products * ──── 1 categories
  │                                │  │
  │                                │  1 ──── 1 product_stocks
  │                                1 ──── 1 flower_stocks (for per-stem products)
  │
  ├──── * orders 1 ──── * order_items 1 ──── * order_item_details
  │         │                  │
  │         │                  * products (snapshot kept in order_items)
  │         ├──── * order_status_history
  │         └──── * notifications
  │
  └──── * notifications

admin_accounts 1 ──── * order_status_history
admin_accounts 1 ──── * stock_movements

store_settings (key/value, standalone)
```

## Relationships

| Parent | Child | Type | On delete |
|---|---|---|---|
| users | carts | 1 : 1 | CASCADE |
| carts | cart_items | 1 : * | CASCADE |
| cart_items | cart_item_details | 1 : * | CASCADE |
| categories | products | 1 : * | RESTRICT |
| flower_types | products (hand bouquet) | 1 : * | SET NULL |
| flower_types | flower_stocks | 1 : 1 | CASCADE |
| products | product_stocks (fixed-price products) | 1 : 1 | CASCADE |
| users | orders | 1 : * | RESTRICT |
| orders | order_items | 1 : * | CASCADE |
| order_items | order_item_details | 1 : * | CASCADE |
| orders | order_status_history | 1 : * | CASCADE |
| orders | notifications | 1 : * | CASCADE |
| users | notifications | 1 : * | CASCADE |
| admin_accounts | order_status_history | 1 : * | SET NULL |
| admin_accounts | stock_movements | 1 : * | SET NULL |

## Pricing modes

- `per_stem` (Hand Bouquet): price comes from `flower_types.price_per_stem`.
- `mixed` (Mix Bouquet): sum of selected `flower_types.price_per_stem * stem_quantity`.
- `fixed` (Flower Box): `products.fixed_price`.

Wrapping fee applies only to `per_stem` and `mixed`:

```
wrapping_fee = floor((total_stems - 1) / 5) * 5000   -- only when total_stems > 0
```

## Stock ownership

- Hand Bouquet / Mix Bouquet: `flower_stocks.quantity` (stems).
- Flower Box: `product_stocks.quantity` (units).

Stock is deducted only when an admin confirms a `Pending` order
(`Pending -> Proses`), inside a transaction with `SELECT ... FOR UPDATE`.
