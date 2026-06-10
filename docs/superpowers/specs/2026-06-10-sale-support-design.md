# Sale Support — Design Spec

**Date:** 2026-06-10
**Version target:** 1.4.0

## Goal

Add WooCommerce-native sale price support to `prbp_configurable_product`. Behavior must match:
- **Product page:** same as a simple product on sale — strikethrough regular total + sale total, updating live as the user picks attribute options.
- **Shop/archive listings:** same as a variable product on sale — `~~From $regular_min~~ From $sale_min`.

Sale applies to the **base product price only**. Attribute rule additions are never discounted.

---

## What is currently broken

| Location | Problem |
|---|---|
| `admin-product.js` | Explicitly hides `_sale_price_field` and `sale_price_dates_fields` for our product type |
| `ProductPage::filterPriceHtml` | Always returns plain `"From $X"` — ignores `is_on_sale()` |
| `CalculatePrice::handle` | Returns only `{ formatted }` — no regular total for strikethrough |
| `update-price.js` | Renders only the single formatted price — no strikethrough support |
| `configurator-selects.php` | Initial price and `data-min-price-html` are plain prices — no sale markup |

WooCommerce's `is_on_sale()`, `_price` / `_regular_price` / `_sale_price` meta handling, date-range scheduling, and sale badge all work correctly already via `WC_Product` inheritance.

---

## Changes

### 1. Admin — `assets/js/admin-product.js`

In `applyVisibility()`, remove the two lines that hide the sale fields:

```js
// Remove these:
$( '#general_product_data ._sale_price_field' ).hide();
$( '.sale_price_dates_fields' ).hide();
```

Both fields will now show alongside `_regular_price_field`, matching simple product behavior. WooCommerce handles save, `_price` update, and date scheduling natively.

---

### 2. Listings — `src/Frontend/ProductPage.php`

**New helper:**

```php
public static function getMinRegularPrice( int $product_id ): float {
    $base        = (float) get_post_meta( $product_id, '_regular_price', true );
    $template_id = (int)   get_post_meta( $product_id, 'prbp_template_id', true );
    // ... same min_per_attr loop as getMinPrice(), using _regular_price as base
}
```

**Updated `filterPriceHtml()`:**

```php
if ( $product->is_on_sale() ) {
    $sale_min    = self::getMinPrice( $product_id );          // _price = sale price
    $regular_min = self::getMinRegularPrice( $product_id );   // _regular_price
    return sprintf(
        __( 'From %s', 'priceblueprint-for-woocommerce' ),
        wc_format_sale_price( wc_price( $regular_min ), wc_price( $sale_min ) )
    );
}
// existing "From $min" path unchanged when not on sale
```

`wc_format_sale_price()` produces `<del>…</del> <ins>…</ins>` — the same markup themes already style.

---

### 3. AJAX — `src/Ajax/CalculatePrice.php`

When the product is on sale, compute and return both totals:

```php
$base_sale    = (float) get_post_meta( $product_id, '_price',          true );
$base_regular = (float) get_post_meta( $product_id, '_regular_price',  true );
$on_sale      = (bool)  wc_get_product( $product_id )->is_on_sale();

// ... existing additions loop runs once, applies to both bases ...

$sale_total    = $base_sale    + $additions;
$regular_total = $base_regular + $additions;

$response = [
    'formatted'         => wp_strip_all_tags( wc_price( $sale_total * $quantity ) ),
    'on_sale'           => $on_sale,
];

if ( $on_sale ) {
    $response['formatted_regular'] = wp_strip_all_tags( wc_price( $regular_total * $quantity ) );
}

wp_send_json_success( $response );
```

When `on_sale` is false, response is identical to current — no JS changes needed for the non-sale path.

---

### 4. Live configurator JS — `assets/js/single-product/update-price.js`

After receiving the AJAX response, replace the current single-price render with:

```js
if ( data.on_sale ) {
    priceElement.innerHTML =
        '<del>' + data.formatted_regular + '</del> <ins>' + data.formatted + '</ins>';
} else {
    priceElement.innerHTML = data.formatted;
}
```

No new CSS required — themes already style `del` and `ins` inside `.price`.

---

### 5. Initial render — `templates/configurator-selects.php` + `renderSelects()`

**`renderSelects()`** passes two new variables to the template:

```php
$is_on_sale        = $product->is_on_sale();
$regular_min_total = ProductPage::getMinRegularPrice( $product_id )
                     + /* same attribute min additions as $prbp_min_total */;
```

**Template** — when on sale, use `wc_format_sale_price()` for both the initial displayed price and the `data-min-price-html` fallback:

```php
$prbp_min_price_html = $is_on_sale
    ? wc_format_sale_price( wc_price( $regular_min_total ), wc_price( $prbp_min_total ) )
    : wc_price( $prbp_min_total );

$prbp_initial_html = $is_on_sale
    ? wc_format_sale_price( wc_price( $regular_min_total ), wc_price( $prbp_initial_price ) )
    : wc_price( $prbp_initial_price );
```

The `data-min-price-html` attribute on `.prbp-total-price` uses `$prbp_min_price_html`. The element's inner content uses `$prbp_initial_html`.

---

## Version

Bump to **1.4.0** in:
- `priceblueprint-for-woocommerce.php` header + `PRBP_VERSION` constant
- `readme.txt` stable tag + changelog entry

---

## What does NOT change

- Attribute rule additions — never discounted
- Cart/checkout price recalculation (`PriceRecalculator`) — already uses `$product->get_price()` which returns the sale price when active; no change needed
- Cart item capture (`CartItemMeta`) — already captures `$product->get_price()` as base; correct
- WooCommerce sale badge — works via `is_on_sale()` on `WC_Product`; no change needed
- Tax handling — unchanged; `wc_price()` respects tax settings as before
