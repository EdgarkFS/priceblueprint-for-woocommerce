# PriceBlueprint — Configurable Product Pricing for WooCommerce

Reusable pricing blueprints for WooCommerce. Assign one blueprint to multiple products — define attribute-based pricing rules once, update everywhere instantly.

**WP.org:** [wordpress.org/plugins/priceblueprint-for-woocommerce](https://wordpress.org/plugins/priceblueprint-for-woocommerce/)  
**Website:** [getpriceblueprint.com](https://getpriceblueprint.com)

---

## How It Works

WooCommerce variations grow exponentially: 4 sizes × 3 materials × 3 colors = 36 variations to create and maintain. PriceBlueprint replaces that with rules.

1. **Create a Blueprint** — add attributes and set a price modifier per value (Size XL → +$10, Material Leather → +$25)
2. **Assign to products** — one blueprint works on any number of products
3. **Update once** — change a rule and every linked product reflects the new price immediately

No variations are created or stored. Rules grow linearly, not exponentially.

---

## Features

- **Reusable blueprints** — one template controls pricing across all linked products
- **Attribute-based pricing** — add, subtract, multiply, divide, or fixed price per attribute value
- **Live price calculator** — price updates in real time as customers select options
- **Full cart & checkout integration** — selections visible at every step
- **Complete order records** — attribute selections appear in WC Admin, emails, Thank You page, My Account
- **Zero variation bloat** — no per-combination records in the database
- **HPOS compatible** — works with WooCommerce High-Performance Order Storage
- **Schema.org structured data** for configurable products
- **RTL support** and translations: English, German, French, Spanish, Ukrainian, Polish

---

## Requirements

- PHP 7.4+
- WordPress 6.0+
- WooCommerce 6.0+

---

## Installation

1. Upload the `priceblueprint-for-woocommerce` folder to `/wp-content/plugins/`
2. Activate through **Plugins → Installed Plugins**
3. Go to **Products → Price Blueprints → Add New**
4. Add pricing rules, create a **Configurable Product**, assign the blueprint — done

---

## Coding Standards

- **Prefix:** `prbp_` for functions, hooks, meta keys, options
- **Classes:** `PRBP_` prefix
- **Text domain:** `priceblueprint-for-woocommerce`
- PHP 7.4 compatible — no named arguments, no enums, no fibers
- Vanilla JS — no React/Vue in admin UI

---

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
