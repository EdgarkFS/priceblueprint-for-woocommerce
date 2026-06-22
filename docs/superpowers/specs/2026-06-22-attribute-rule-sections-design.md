# Pricing rules grouped by attribute ("sections") — Design

Date: 2026-06-22

## Problem

Pricing rules in a Price Blueprint are currently a flat list of rows. Each row has its own attribute `<select>`, so two rows can independently target the same attribute (e.g. two separate "Color" rows), and nothing stops a user from accidentally changing a row's attribute after it already has values and a price set. There's also no visual grouping — rows for the same attribute aren't kept together in the table.

## Goal

Restructure the Pricing Rules editor so rules are organized into **sections by attribute**. A section is created by picking an attribute once; every row inside that section is locked to that attribute and can only edit value(s) and price. Each attribute can have at most one section.

## Decisions

These were confirmed during brainstorming:

1. **Row granularity:** a row inside a section may select multiple values sharing one price (kept as-is from today — not split into one-value-per-row).
2. **Section uniqueness:** exactly one section per attribute. The "add section" attribute picker excludes attributes that already have a section.
3. **Storage shape:** the stored JSON (`prbp_template_rules`) changes to a nested sections structure (see below), not just a UI-only grouping over the old flat array.
4. **Migration:** auto-convert on load (no bulk migration routine). Old flat-format data is detected and grouped into sections in memory the next time it's read; saving afterward writes the new format.
5. **Section delete:** a section has one delete/restore action covering the whole group (header + rows). Individual rows can also be deleted/restored independently. Deleting a section's last row does **not** auto-remove the section.

## Data model

### Stored shape (new)

`prbp_template_rules` post meta, JSON-encoded:

```json
[
  {
    "attribute": "pa_color",
    "attribute_label": "Color",
    "rows": [
      { "value_ids": ["12", "15"], "value_slugs": ["red", "blue"], "value_labels": ["Red", "Blue"], "price": "5.00" },
      { "value_ids": ["20"], "value_slugs": ["green"], "value_labels": ["Green"], "price": "10.00" }
    ]
  },
  {
    "attribute": "pa_size",
    "attribute_label": "Size",
    "rows": [
      { "value_ids": ["30"], "value_slugs": ["xl"], "value_labels": ["XL"], "price": "2.00" }
    ]
  }
]
```

- `operator` is dropped from the stored row shape going forward in admin authoring, but `RulesCache`'s flattened output still emits `"operator": "+"` for the benefit of unchanged runtime consumers (see below). The plugin only ever supports `+` today, so this is a constant in the flattened view, not a new authoring field.
- No `status` field is ever persisted, for sections or rows — exactly like today. Soft-deleted sections/rows exist only in client-side editor state and are dropped from the submitted payload before it's saved.

### Old stored shape (still must be readable)

```json
[
  { "attribute": "pa_color", "attribute_label": "Color", "value_ids": ["12"], "value_slugs": ["red"], "value_labels": ["Red"], "price": "5.00", "operator": "+" }
]
```

Detected by the absence of a `rows` key on entries (and presence of a top-level `attribute` + `value_slugs` instead).

### Migration / normalization

A small helper normalizes a decoded `prbp_template_rules` value into the sections shape regardless of which format is stored:

- If entries already have `rows`, return as-is.
- Otherwise, treat entries as old-format flat rules: group by `attribute`, preserving `attribute_label` from the first occurrence, and turn each old rule into one row (`value_ids`/`value_slugs`/`value_labels`/`price`) inside its attribute's section, preserving original order of first appearance.

This normalization is used in two places:

- `RulesRepeater::render()` (admin editor read) — normalizes before handing data to the Alpine component, so the editor always works with sections.
- `RulesCache::get()` (runtime read) — normalizes, then **flattens** sections back into the legacy flat per-row shape (one array entry per row, carrying `attribute`/`attribute_label`/`value_ids`/`value_slugs`/`value_labels`/`price`/`operator: '+'`) before returning. This keeps `RulesCache`'s public contract identical to today.

The exact placement of this shared normalization logic (e.g., a method on `RulesCache` vs. a new tiny utility class) is an implementation detail to be settled in the implementation plan.

## Admin UI / UX

### Section header

- Displays the attribute label as a fixed heading — no `<select>` once the section exists (the attribute can never be changed on an existing section, only the section itself deleted and a new one created).
- Has a delete action that soft-deletes the entire section (header + all its rows) in client state, with a restore action to undo before save — mirroring today's per-row restore pattern.

### Rows inside a section

- Each row keeps today's per-row Tom Select (multi-value) and price input, plus its own delete/restore action — but has no attribute field; the attribute is inherited from the section.
- A "+ Add row" control per section appends another empty value+price row for that section's attribute.
- Deleting a section's last row leaves the (now empty) section visible with no rows; the section is not auto-removed. The user can add a new row or explicitly delete the section.

### Adding a new section

- The existing "+ Add Rule" button becomes "+ Add attribute section."
- Clicking it opens an attribute picker that lists only WC attributes that do not already have a section in this blueprint (active or soft-deleted-but-not-yet-saved — i.e., excluded as soon as a section object exists in client state, regardless of its delete status, since restoring it later would conflict).
- Once every available WC attribute already has a section, the control is disabled (or hidden, matching whichever is less jarring in the existing template's style).
- Creating a section starts it with exactly one empty row.

### Quick Setup (import from product)

- Behavior is unchanged in spirit: for each attribute on the imported product, create one section with one row pre-filled with all of that attribute's current values and price `0`.
- This already matches the new shape almost exactly (`QuickSetup` AJAX response is already one entry per attribute carrying all its values) — only the client-side object construction changes from a flat rule to a section+row.

### Filter bar

- A section is visible if its attribute label matches the filter query, OR if at least one of its rows' value labels matches.
- When a section is shown only because of a row-level match, non-matching rows within that section stay hidden (not deleted) — same suppress-on-filter behavior as today, applied at two levels instead of one.

### Sorting

- The existing attribute-column sort toggle (asc/desc/none) now reorders sections alphabetically by attribute label, instead of reordering flat rows.

## Validation (`RuleValidator`)

`RuleValidator::validate()` walks the sections array:

- Each section must have a valid `attribute` matching `/^pa_[a-z0-9_]+$/` and a non-empty `rows` array.
- **Duplicate-attribute defense:** reject a payload containing two sections with the same `attribute`, even though the UI prevents constructing one — defends against tampered or buggy client state.
- Within each section, every row must have non-empty `value_slugs`, with `value_labels` of matching length, and a numeric, non-negative `price`.
- **Duplicate-value check** is scoped to rows within the same section (previously it was global across all rules): a value slug appearing in two rows of the same section is rejected, since attribute is now fixed per section and such a row pair would create pricing ambiguity for that value.
- Error messages remain row/section-addressable (e.g., referencing the attribute label and row position) so the existing error-banner UI continues to work without changes to its rendering.

`SaveHandler::sanitizeRules()` sanitizes the same nested shape: existing field-level sanitizers (`sanitize_key`, `sanitize_text_field`, `absint`, numeric cast/clamp for price) apply per-row, one level deeper than today.

## Runtime / frontend impact

No changes required to:

- `src/Cart/PriceRecalculator.php`
- `src/Ajax/CalculatePrice.php`
- `src/Admin/AttributeSync.php`
- `src/Frontend/ProductPage.php`
- `src/Ajax/QuickSetup.php` (response shape unchanged; only the admin JS consuming it changes)

All of these consume `RulesCache::get()`'s flattened, per-row output and already group/match by `attribute` themselves (verified by reading each file) — that contract does not change.

## Affected files (implementation scope)

- `templates/admin-repeater.php` — restructure markup into section/row nesting; attribute `<select>` only appears in the "add section" picker, never on an existing row.
- `assets/js/admin/dom-controller.js` — replace the flat `rules` array and `makeRule()` factory with `sections` + `makeSection()`/`makeRow()`; add `addSection()`, `deleteSection()`, `restoreSection()`, `addRow()`; rework `onSubmit()` to serialize sections/rows and run the section-scoped duplicate check; rework `importFromProduct()` to build sections.
- `src/Utils/RuleValidator.php` — validate sections/rows as described above.
- `src/Admin/SaveHandler.php` — sanitize nested sections/rows.
- `src/Utils/RulesCache.php` — add format normalization (old flat → sections) and flatten sections back to the legacy per-row shape for all runtime consumers.
- `src/Utils/RulesFormat.php` (new) — `normalize()` (old flat → sections, passthrough if already sections) and `flatten()` (sections → legacy per-row shape, preserving a row's `status` field when present). Shared by `RulesRepeater::render()` and `RulesCache::get()`. Pure logic, no WordPress function calls, so it's directly unit-testable.
- `tests/RulesFormatTest.php` (new), `tests/RuleValidatorTest.php`, `tests/RuleValidatorAdditionalTest.php`, `tests/SaveHandlerSanitizeTest.php`, `tests/SaveHandlerExtendedTest.php` (rewritten) — see Testing approach below.

## Testing approach

Correction from an earlier version of this doc: there **is** an automated PHPUnit suite, at the repo root one level above the plugin folder (`composer.json` autoloads `PRBP\` to `priceblueprint-for-woocommerce/src/`; `tests/bootstrap.php` stubs the WordPress functions needed to unit-test plugin classes without a full WP install). It already covers exactly the classes this change touches: `RuleValidatorTest.php`, `RuleValidatorAdditionalTest.php`, `RulesCacheTest.php`, `SaveHandlerSanitizeTest.php`, `SaveHandlerExtendedTest.php`, plus `PriceRecalculatorTest.php`, `ProductPageGetMinPriceTest.php`, `ProductPageFilterPriceHtmlTest.php`, `CartItemMetaTest.php`, and `QuickSetupTest.php`. Run via `vendor/bin/phpunit` from the repo root (one level above `priceblueprint-for-woocommerce/`).

Baseline (before this change): 116 tests, 169 assertions, 11 pre-existing errors all in `BasePriceSidebarTest.php` (`Class "PRBP\Admin\BasePriceSidebar" not found` — that class doesn't exist in `src/`). This is unrelated to pricing rules and out of scope for this change; the implementation plan must not touch it, and the same 11 errors are expected to remain after this change.

This automated suite changes the testing approach: this is implemented test-first against real PHPUnit tests, not manual verification, with one exception (see below). Specifically:

- `RulesCacheTest.php`, `PriceRecalculatorTest.php`, `ProductPageGetMinPriceTest.php`, `ProductPageFilterPriceHtmlTest.php`, `CartItemMetaTest.php`, `QuickSetupTest.php` all build their fixtures directly in the **old flat-rule shape** and feed them through `RulesCache::get()` (directly or via `PriceRecalculator`/`ProductPage`/`CartItemMeta`). Since `RulesCache::get()` is the one place that normalizes old-format data and flattens sections back to the legacy per-row shape, none of these test files need to change — they're the regression net proving the runtime contract didn't break. This includes one subtlety the earlier version of this doc missed: `RulesCacheTest` proves `RulesCache::get()` already filters by a per-row `status` field when one is present in stored data (`test_returns_active_rules_by_default`, `test_active_only_false_returns_all_rules`), even though `SaveHandler` itself never writes that field. The new `RulesFormat` normalize/flatten helpers must pass an existing `status` value on a row through unchanged (and leave it absent when the source row has none) so this still works.
- `RuleValidatorTest.php`, `RuleValidatorAdditionalTest.php`, `SaveHandlerSanitizeTest.php`, `SaveHandlerExtendedTest.php` test the *old* flat-rule input contract of `RuleValidator::validate()` and `SaveHandler`'s private `sanitizeRules()` directly. Both methods are changing to accept the nested sections shape, so these four files are rewritten (fixtures restructured into sections/rows, error-message assertions updated to the new section/row wording, two operator-specific `SaveHandlerExtendedTest` cases removed since `operator` is no longer part of the sanitized row shape going forward). Coverage intent is preserved; only the shape of the fixtures and the literal wording asserted changes.
- A new `RulesFormatTest.php` is added for the new pure-logic `normalize()`/`flatten()` helpers, including the status-passthrough case above.
- `templates/admin-repeater.php` and `assets/js/admin/dom-controller.js` have no automated coverage (no JS test runner exists, and there's no browser-level PHP test for admin templates). These two are verified manually in a running WordPress install: create a blueprint with multiple sections and rows, save and reload; seed a blueprint with old-format flat JSON directly in post meta, open the editor, confirm it renders as sections, save, confirm it's rewritten in the new format; confirm Quick Setup still produces correct sections; confirm the duplicate-value and missing-value error banner still appears for sections.
