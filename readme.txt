=== PriceBlueprint — Configurable Product Pricing for WooCommerce ===
Contributors: wpedgar
Tags: woocommerce, pricing, product attributes, pricing rules, attribute pricing
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.7.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Set a price per attribute value — Size XL +$3, Material Cotton +$5. Customers select options, price updates live. No variations needed.

== Description ==

PriceBlueprint lets you set a price per attribute value: Color = Red adds $2, Material = Cotton adds $5. As customers make their selections, the price updates live. No variations needed.

Attach one blueprint to multiple products. Update one rule — every linked product reflects the change instantly.

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

1. Welcome screen with setup steps and one-click demo import.
2. Quick Setup — generate pricing rules from an existing product in one click.
3. Blueprint editor with configured attribute rules ready to use.
4. Assigning a blueprint to a product in the product settings.
5. Live price calculator on the product page as customers make selections.
6. Attribute selections and final price visible in the order details.

== Changelog ==

= 1.7.0 =
* New: Native WooCommerce gallery integration — attribute rule images now navigate the flexslider directly instead of swapping the DOM.
* New: Default gallery image priority — featured image first, then WC gallery images, then rule images.
* New: Image picker in admin settings — thumbnails enlarged, change/delete actions moved inside the image as hover icon overlays.
* New: Modernized admin settings page UI — card layout, DM Sans font, teal accents, column headers.
* Fix: TypeError on product pages when no featured image is set (PHP 8 strict type mismatch in `suppressGalleryPlaceholder()`).
* Accessibility: Admin UI now passes WCAG 2.1 AA color contrast across all foreground/background pairs.

= 1.6.0 =
* New: Informational blueprint type — check "Informational blueprint" in the Blueprint Settings sidebar box to sync all WooCommerce global attributes to every linked product for filtering and display. No configurator UI or pricing rules are applied; the product uses its own WooCommerce price as-is.
* New: Blueprint Settings sidebar meta box on the blueprint editor — switch a blueprint between pricing mode (default) and informational mode with a single checkbox.

= 1.5.0 =
* New: Pricing Rules editor now groups rules into collapsible attribute sections, with filtering and a live term count.
* New: Attribute sections in the Pricing Rules editor can now be reordered via drag & drop — the order you set also controls the order attribute selects appear in on the product page.
* New: Product edit screen now shows a link to the selected Price Blueprint's edit screen, right under the Price Blueprint dropdown.
* Fix: Pricing Rules section summary now counts the actual number of selected terms instead of the number of rows.

= 1.4.0 =
* New: Sale price support for configurable products — set a sale price and schedule dates the same way as a WooCommerce simple product. Shop listings show the strikethrough pair ("From ~~regular~~ sale"), the product page configurator updates live with sale-aware totals, and the Block cart displays the correct strikethrough on both the base and attribute additions.

= 1.3.1 =
* Fix: Removed URL query parameter sync on attribute selection change — selections no longer pollute the browser URL.

= 1.3.0 =
* New: Automatically update URL query parameters when attribute selections change.

= 1.2.3 =
* New: Import Demo Data button on the Welcome screen — one click imports a sample blueprint and a linked configurable product so you can see the plugin in action right away.
* Fix: Dutch (nl_NL) translation used "Regelmatige prijs" for "Regular price"; replaced with the correct WooCommerce NL term "Normale prijs".
* Fix: Spanish (es_ES) translation had an incorrect capital letter in "¿Qué es una Regla?"; corrected to "¿Qué es una regla?".
* Fix: Dutch (nl_NL) translation was inconsistent — formal "u" used throughout but two strings used informal "je"; standardised to formal "u".
* Fix: Missing blank-line separator between two PO entries in all 10 translation files; this caused some gettext tools to misparse the file.

= 1.2.2 =
* Fix: Blueprint editor now shows a clear notice with a link when no WooCommerce global attributes exist, instead of silently displaying an empty attribute dropdown.
* Fix: Selecting an attribute with no terms now shows an inline message with a direct link to add terms, instead of leaving an empty value field with no explanation.

= 1.2.1 =
* Fix: Welcome screen CSS and HTML extracted into separate files; all welcome screen strings added to translation files.

= 1.2.0 =
* New: Welcome screen shown after plugin activation — walks new users through creating their first blueprint.

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
