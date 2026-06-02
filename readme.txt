=== PriceBlueprint — Configurable Product Pricing for WooCommerce ===
Contributors: wpedgar
Tags: woocommerce, pricing, product attributes, pricing rules, attribute pricing
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Price WooCommerce products by attribute value — no variations needed. One reusable blueprint manages pricing across all linked products at once.

== Description ==

Managing WooCommerce products with multiple attributes — sizes, materials, colors — means creating a variation for every combination. Five sizes × four colors × three materials equals sixty variations to create, price, and maintain. Change one price and you are editing dozens of records one by one.

**PriceBlueprint eliminates that.**

Instead of variations, you create a **Price Blueprint** — a reusable template of pricing rules. Each rule maps an attribute value to a price add-on: Size XL adds $10, Material Leather adds $25, Color Gold adds $5. Assign that blueprint to as many products as you like. Update a rule once — every linked product reflects the new price immediately, no editing products one by one.

**Key features:**

* **Reusable Price Blueprints** — one template controls pricing across all linked products
* **Attribute-based pricing** — set price add-ons per attribute value (Size, Color, Material, or any WooCommerce global attribute)
* **Centralized updates** — change a rule once, every linked product updates instantly
* **Live price calculator** — customers see the total update in real time as they select options, no page reload required
* **Full cart and checkout integration** — attribute selections and price breakdown displayed at every step
* **Complete order records** — selections visible in WC Admin, customer emails, Thank You page, and My Account
* **Zero variation bloat** — no per-combination records in the database
* **HPOS compatible** — works with WooCommerce High-Performance Order Storage
* Schema.org structured data for configurable products
* RTL support and translations: English, German, French, Spanish, Ukrainian, Polish

**Requirements:** WooCommerce 6.0 or higher.

= Who is PriceBlueprint for? =

* **Clothing and apparel stores** — handle size, color, and material pricing without managing hundreds of variations
* **Custom and made-to-order shops** — flexible pricing for products built to specification
* **Stores with shared pricing logic** — if multiple products use the same size pricing, one blueprint covers all of them
* Any WooCommerce merchant who wants simpler product setup and centralized price management

== Installation ==

1. Upload the `priceblueprint-for-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Make sure WooCommerce is installed and active
4. Go to **Products → Price Blueprints → Add New**
5. Add your pricing rules — for example: Size XL → +$10, Color Gold → +$5
6. Create a product, select **Configurable Product** as the type, assign your blueprint on the PriceBlueprint tab — done.

== Frequently Asked Questions ==

= How is this different from WooCommerce variations? =

WooCommerce variations require a separate record for every combination of attributes. Five sizes × four colors means twenty variations to create and maintain. PriceBlueprint uses rules instead — you define a price add-on per attribute value and the final price is calculated automatically. No combinations, no variation records, no exponential overhead.

= Can I assign the same blueprint to multiple products? =

Yes — that is the core feature. Create a blueprint once and assign it to as many products as you need. When you update a rule, every linked product reflects the change immediately without touching individual products.

= What happens if I edit a blueprint while customers are shopping? =

Updated rules apply immediately to new sessions. Items already in the cart are also recalculated on the next page load, so customers always see the current price.

= Does the live price calculator work with caching plugins? =

Yes. The price display updates via AJAX on the product page and works correctly regardless of full-page caching.

= Can an attribute value add zero to the price? =

Yes. Set the add-on to `0.00` and that option will not change the base price — useful for a default or standard option that carries no surcharge.

== Changelog ==

= 1.0.0 =
* Initial release