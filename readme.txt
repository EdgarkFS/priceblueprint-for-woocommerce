=== PriceBlueprint - Configurable Product Pricing for WooCommerce ===
Contributors: wpedgar
Tags: woocommerce, pricing, product attributes, pricing rules, attribute pricing
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.1.3
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Price WooCommerce products by attribute value. No variations needed. One blueprint handles pricing across every product linked to it.

== Description ==

If you sell products with multiple attributes, you know the problem. Four sizes, three materials, two colors — that's 24 variations to create, price, and keep up to date. Raise the price on one material and you're editing records for the next hour.

**PriceBlueprint takes a different approach.**

You build a Price Blueprint, a set of pricing rules like "Size XL adds $10" or "Material Leather adds $25." Attach that blueprint to as many products as you want. When something changes, you update the rule once and every linked product is done.

**What's included:**

* **Reusable blueprints**: one blueprint can cover your entire catalog if the pricing logic is the same
* **Attribute-based rules**: works with any WooCommerce global attribute: size, color, material, finish, whatever you use
* **Live price updates**: the price recalculates on the product page as customers make their selections
* **Cart and checkout**: selections and the final price carry through correctly at every step
* **Order records**: attribute choices show up in WC Admin, order emails, the Thank You page, and My Account
* **No variation records**: nothing gets written to the database per combination, so your store stays clean
* **HPOS compatible**: works with WooCommerce High-Performance Order Storage
* **Schema.org** structured data for configurable products
* **RTL support** and translations: English, German, French, Spanish, Ukrainian, Polish

Requires WooCommerce 6.0 or higher.

= Who uses it? =

Mostly store owners who got tired of managing hundreds of variations. Clothing shops with size and color pricing, custom product builders, print-on-demand stores — anyone who has the same pricing logic repeated across a lot of products.

== Installation ==

1. Upload the `priceblueprint-for-woocommerce` folder to `/wp-content/plugins/`
2. Activate it through the Plugins menu
3. Go to **Products → Price Blueprints → Add New**
4. Add your rules (Size XL → +$10, Material Oak → +$25, etc.)
5. Create a product, set the type to **Configurable Product**, and assign your blueprint on the PriceBlueprint tab

== Frequently Asked Questions ==

= How is this different from WooCommerce variations? =

With variations, every combination of attributes needs its own record. Four sizes × three colors = 12 variations to create and maintain. PriceBlueprint skips all that. You write one rule per attribute value and the price is calculated from those rules at runtime. No combinations, no bloat.

= Can I use the same blueprint on multiple products? =

Yes, that is the whole point. One blueprint, as many products as you want. Update a rule and all of them update with it.

= What if I change a blueprint while someone is shopping? =

New sessions get the updated price right away. Anything already in the cart recalculates on the next page load.

= Does it work with caching plugins? =

Yes. Price updates happen via AJAX on the product page, so full-page caching does not interfere.

= Can I set a rule that adds nothing to the price? =

Yes, just set the add-on to `0.00`. Handy for your base option that should not change the price.

== Screenshots ==

1. Quick Setup — generate pricing rules from an existing product in one click.
2. Blueprint editor with configured attribute rules ready to use.
3. Assigning a blueprint to a product in the product settings.
4. Live price calculator on the product page as customers make selections.
5. Attribute selections and final price visible in the order details.

== Changelog ==

= 1.1.3 =
* Fix: Missing padding in select fields in Safari

= 1.1.2 =
* Fix: "Add to cart" button now inherits theme styles correctly, including block theme support via wp-element-button.
* Fix: Quantity field is now visible on the product page instead of being hidden.
* Fix: Displayed price now updates when the quantity is changed.

= 1.1.1 =
* Fix: WooCommerce HPOS Notification Compatibility Update

= 1.1.0 =
* New: Quick Setup wizard — get your first blueprint running in under a minute.
* New: Freemius integration for license management and updates.
* New: Attribute options can now be sorted alphabetically in the blueprint editor (default: original order).

= 1.0.1 =
* Fix: attribute configurator not rendering on product page after saving blueprint rules.
* Fix: configured price not applied correctly in cart and checkout in certain setups.
* Fix: order-received page displaying internal blueprint meta to customers.
* Fix: compatibility header corrected for WordPress 6.x.

= 1.0.0 =
* Initial release