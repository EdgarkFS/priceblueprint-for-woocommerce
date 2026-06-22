# Pricing Rules Grouped by Attribute Sections — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the Pricing Rules admin editor so rules are grouped into one section per attribute; once a section exists, its attribute is locked and rows inside it can only edit value(s) and price.

**Architecture:** Storage moves from a flat array of rules to a nested `[{attribute, attribute_label, rows:[...]}]` shape. A new pure-logic `RulesFormat` helper normalizes old flat data into this shape and flattens it back to the legacy per-row shape, so every runtime consumer (`PriceRecalculator`, `CalculatePrice`, `AttributeSync`, `ProductPage`, `CartItemMeta`) keeps working against `RulesCache::get()` unchanged. Only the admin-editing path (`RuleValidator`, `SaveHandler`, `RulesRepeater`, the admin template, and the Alpine JS controller) changes shape.

**Tech Stack:** PHP 7.4+ (WordPress/WooCommerce plugin, custom PSR-4 autoloader), vanilla ES modules + Alpine.js + Tom Select (no bundler), PHPUnit 13.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-06-22-attribute-rule-sections-design.md` (this plan implements it in full).
- Exactly one section per attribute; the UI's attribute picker excludes attributes already used by an existing section (active or soft-deleted-but-unsaved).
- No `status` field is ever written by `SaveHandler`/`sanitizeSections` — soft-deleted sections/rows are dropped client-side before submit, same as today. `RulesCache`/`RulesFormat` must still honor a `status` field if one is present in stored data (this is real, tested behavior — not dead code), defaulting absent status to `'active'`.
- The PHPUnit suite lives at `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/` (one level **above** this plugin's git repo root). Run it with `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit`. That parent directory is **not** under git version control — only `priceblueprint-for-woocommerce/` is a git repo. Every task's "Commit" step only stages/commits paths inside `priceblueprint-for-woocommerce/`; edits under the parent `tests/` directory are saved to disk but have no git history and are not committed.
- Baseline: `vendor/bin/phpunit` currently reports 116 tests, 169 assertions, 11 pre-existing errors, all `Class "PRBP\Admin\BasePriceSidebar" not found` in `tests/BasePriceSidebarTest.php`. That class does not exist in `src/` and is unrelated to this work — out of scope, do not fix. After every task in this plan, the only acceptable errors are those same 11.
- No JS test runner exists; `templates/admin-repeater.php`, `assets/js/admin/dom-controller.js`, and `assets/css/admin.css` have no automated coverage. Those tasks use `php -l` / `node --check` for syntax verification and defer behavioral verification to the final end-to-end manual task, which uses the already-installed `@wordpress/env` (Docker is running, `node_modules/.bin/wp-env` exists).
- Plugin root: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/`. Git commands in this plan run from that directory unless stated otherwise.

---

## Task 1: `RulesFormat` normalize/flatten helper

**Files:**
- Create: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/src/Utils/RulesFormat.php`
- Test: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/tests/RulesFormatTest.php`

**Interfaces:**
- Produces: `PRBP\Utils\RulesFormat::normalize(array $decoded): array` — old flat rules → sections shape `[{attribute, attribute_label, rows:[{value_ids,value_slugs,value_labels,price,status?}]}]`; passes through data already in sections shape unchanged.
- Produces: `PRBP\Utils\RulesFormat::flatten(array $sections): array` — sections → legacy flat per-row shape `[{attribute, attribute_label, value_ids, value_slugs, value_labels, price, operator:'+', status?}]`.

- [ ] **Step 1: Write the failing test file**

Create `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/tests/RulesFormatTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

class RulesFormatTest extends TestCase {

	private function oldRule( array $overrides = [] ): array {
		return array_merge( [
			'attribute'       => 'pa_color',
			'attribute_label' => 'Color',
			'value_ids'       => [ '12' ],
			'value_slugs'     => [ 'red' ],
			'value_labels'    => [ 'Red' ],
			'price'           => '5.00',
			'operator'        => '+',
		], $overrides );
	}

	public function test_normalize_groups_old_flat_rules_by_attribute(): void {
		$sections = PRBP\Utils\RulesFormat::normalize( [
			$this->oldRule( [ 'value_slugs' => [ 'red' ], 'value_labels' => [ 'Red' ], 'price' => '5.00' ] ),
			$this->oldRule( [ 'value_slugs' => [ 'blue' ], 'value_labels' => [ 'Blue' ], 'price' => '7.00' ] ),
			$this->oldRule( [ 'attribute' => 'pa_size', 'attribute_label' => 'Size', 'value_slugs' => [ 'xl' ], 'value_labels' => [ 'XL' ], 'price' => '2.00' ] ),
		] );

		$this->assertCount( 2, $sections );
		$this->assertSame( 'pa_color', $sections[0]['attribute'] );
		$this->assertCount( 2, $sections[0]['rows'] );
		$this->assertSame( 'pa_size', $sections[1]['attribute'] );
		$this->assertCount( 1, $sections[1]['rows'] );
	}

	public function test_normalize_preserves_first_attribute_label_seen(): void {
		$sections = PRBP\Utils\RulesFormat::normalize( [
			$this->oldRule( [ 'attribute_label' => 'Color' ] ),
			$this->oldRule( [ 'attribute_label' => 'Color (duplicate label)' ] ),
		] );

		$this->assertSame( 'Color', $sections[0]['attribute_label'] );
	}

	public function test_normalize_passes_through_already_sectioned_data(): void {
		$sections = [
			[ 'attribute' => 'pa_color', 'attribute_label' => 'Color', 'rows' => [
				[ 'value_ids' => [ '1' ], 'value_slugs' => [ 'red' ], 'value_labels' => [ 'Red' ], 'price' => '5.00' ],
			] ],
		];

		$result = PRBP\Utils\RulesFormat::normalize( $sections );

		$this->assertSame( $sections, $result );
	}

	public function test_normalize_empty_array_returns_empty_array(): void {
		$this->assertSame( [], PRBP\Utils\RulesFormat::normalize( [] ) );
	}

	public function test_normalize_carries_row_status_when_present(): void {
		$sections = PRBP\Utils\RulesFormat::normalize( [
			$this->oldRule( [ 'status' => 'inactive' ] ),
		] );

		$this->assertSame( 'inactive', $sections[0]['rows'][0]['status'] );
	}

	public function test_normalize_omits_row_status_when_absent(): void {
		$rule = $this->oldRule();
		unset( $rule['status'] );

		$sections = PRBP\Utils\RulesFormat::normalize( [ $rule ] );

		$this->assertArrayNotHasKey( 'status', $sections[0]['rows'][0] );
	}

	public function test_flatten_produces_one_entry_per_row_with_operator_plus(): void {
		$flat = PRBP\Utils\RulesFormat::flatten( [
			[ 'attribute' => 'pa_color', 'attribute_label' => 'Color', 'rows' => [
				[ 'value_ids' => [ '1' ], 'value_slugs' => [ 'red' ],  'value_labels' => [ 'Red' ],  'price' => '5.00' ],
				[ 'value_ids' => [ '2' ], 'value_slugs' => [ 'blue' ], 'value_labels' => [ 'Blue' ], 'price' => '7.00' ],
			] ],
		] );

		$this->assertCount( 2, $flat );
		$this->assertSame( 'pa_color', $flat[0]['attribute'] );
		$this->assertSame( 'Color',    $flat[0]['attribute_label'] );
		$this->assertSame( [ 'red' ],  $flat[0]['value_slugs'] );
		$this->assertSame( '5.00',     $flat[0]['price'] );
		$this->assertSame( '+',        $flat[0]['operator'] );
		$this->assertSame( [ 'blue' ], $flat[1]['value_slugs'] );
	}

	public function test_flatten_carries_row_status_when_present(): void {
		$flat = PRBP\Utils\RulesFormat::flatten( [
			[ 'attribute' => 'pa_color', 'attribute_label' => 'Color', 'rows' => [
				[ 'value_ids' => [ '1' ], 'value_slugs' => [ 'red' ], 'value_labels' => [ 'Red' ], 'price' => '5.00', 'status' => 'inactive' ],
			] ],
		] );

		$this->assertSame( 'inactive', $flat[0]['status'] );
	}

	public function test_flatten_omits_status_when_row_has_none(): void {
		$flat = PRBP\Utils\RulesFormat::flatten( [
			[ 'attribute' => 'pa_color', 'attribute_label' => 'Color', 'rows' => [
				[ 'value_ids' => [ '1' ], 'value_slugs' => [ 'red' ], 'value_labels' => [ 'Red' ], 'price' => '5.00' ],
			] ],
		] );

		$this->assertArrayNotHasKey( 'status', $flat[0] );
	}

	public function test_flatten_empty_sections_returns_empty_array(): void {
		$this->assertSame( [], PRBP\Utils\RulesFormat::flatten( [] ) );
	}

	public function test_round_trip_old_format_through_normalize_and_flatten_matches_original_shape(): void {
		$old  = [ $this->oldRule() ];
		$flat = PRBP\Utils\RulesFormat::flatten( PRBP\Utils\RulesFormat::normalize( $old ) );

		$this->assertSame( 'pa_color', $flat[0]['attribute'] );
		$this->assertSame( 'Color',    $flat[0]['attribute_label'] );
		$this->assertSame( [ '12' ],   $flat[0]['value_ids'] );
		$this->assertSame( [ 'red' ],  $flat[0]['value_slugs'] );
		$this->assertSame( [ 'Red' ],  $flat[0]['value_labels'] );
		$this->assertSame( '5.00',     $flat[0]['price'] );
		$this->assertSame( '+',        $flat[0]['operator'] );
	}
}
```

- [ ] **Step 2: Run the test file to verify it fails**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit tests/RulesFormatTest.php`
Expected: `Error: Class "PRBP\Utils\RulesFormat" not found` (the file doesn't exist yet).

- [ ] **Step 3: Create `src/Utils/RulesFormat.php`**

```php
<?php
/**
 * Converts between the legacy flat rule-array format and the current
 * attribute-sections format used by prbp_template_rules.
 *
 * @package PRBP\Utils
 */

namespace PRBP\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RulesFormat {

	/**
	 * Normalize a decoded prbp_template_rules value into the sections shape.
	 *
	 * Entries that already have a 'rows' array are assumed to be in the
	 * current format and are returned unchanged. Otherwise, entries are
	 * treated as old-format flat rules (each carrying its own 'attribute'
	 * and 'value_slugs') and grouped into one section per attribute,
	 * preserving the order attributes first appear in.
	 *
	 * @param  array<int, array<string, mixed>> $decoded
	 * @return array<int, array{attribute: string, attribute_label: string, rows: array<int, array<string, mixed>>}>
	 */
	public static function normalize( array $decoded ): array {
		$is_sections = true;
		foreach ( $decoded as $entry ) {
			if ( ! isset( $entry['rows'] ) || ! is_array( $entry['rows'] ) ) {
				$is_sections = false;
				break;
			}
		}

		if ( $is_sections ) {
			return $decoded;
		}

		$sections = [];

		foreach ( $decoded as $rule ) {
			$attribute = $rule['attribute'] ?? '';

			if ( ! isset( $sections[ $attribute ] ) ) {
				$sections[ $attribute ] = [
					'attribute'       => $attribute,
					'attribute_label' => $rule['attribute_label'] ?? '',
					'rows'            => [],
				];
			}

			$row = [
				'value_ids'    => $rule['value_ids']    ?? [],
				'value_slugs'  => $rule['value_slugs']  ?? [],
				'value_labels' => $rule['value_labels'] ?? [],
				'price'        => $rule['price']        ?? '0',
			];
			if ( isset( $rule['status'] ) ) {
				$row['status'] = $rule['status'];
			}

			$sections[ $attribute ]['rows'][] = $row;
		}

		return array_values( $sections );
	}

	/**
	 * Flatten the sections shape back into one entry per row, matching the
	 * legacy flat-rule shape that runtime consumers (PriceRecalculator,
	 * CalculatePrice, AttributeSync, ProductPage, CartItemMeta) expect.
	 *
	 * @param  array<int, array{attribute: string, attribute_label: string, rows: array<int, array<string, mixed>>}> $sections
	 * @return array<int, array<string, mixed>>
	 */
	public static function flatten( array $sections ): array {
		$flat = [];

		foreach ( $sections as $section ) {
			$attribute = $section['attribute']       ?? '';
			$label     = $section['attribute_label'] ?? '';

			foreach ( (array) ( $section['rows'] ?? [] ) as $row ) {
				$entry = [
					'attribute'       => $attribute,
					'attribute_label' => $label,
					'value_ids'       => $row['value_ids']    ?? [],
					'value_slugs'     => $row['value_slugs']  ?? [],
					'value_labels'    => $row['value_labels'] ?? [],
					'price'           => $row['price']        ?? '0',
					'operator'        => '+',
				];
				if ( isset( $row['status'] ) ) {
					$entry['status'] = $row['status'];
				}
				$flat[] = $entry;
			}
		}

		return $flat;
	}
}
```

- [ ] **Step 4: Run the test file to verify it passes**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit tests/RulesFormatTest.php`
Expected: `OK (12 tests, ...)`

- [ ] **Step 5: Commit**

```bash
cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce"
git add src/Utils/RulesFormat.php
git commit -m "Add RulesFormat helper to convert flat rules to attribute sections"
```

Note: `tests/RulesFormatTest.php` is outside this git repo (see Global Constraints) — it stays saved on disk but is not part of this commit.

---

## Task 2: Wire `RulesFormat` into `RulesCache`

**Files:**
- Modify: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/src/Utils/RulesCache.php`
- Test: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/tests/RulesCacheTest.php` (add 2 tests; existing tests must keep passing unmodified)

**Interfaces:**
- Consumes: `RulesFormat::normalize(array): array`, `RulesFormat::flatten(array): array` (Task 1).
- Produces: `RulesCache::get(int $template_id, bool $active_only = true): array` — same public signature, now transparently reads either old flat or new sections format from `prbp_template_rules`.

- [ ] **Step 1: Add two failing tests to `tests/RulesCacheTest.php`**

Add these two methods inside the existing `RulesCacheTest` class (before the final closing `}`):

```php
	public function test_reads_new_sections_format_directly(): void {
		$GLOBALS['prbp_test_meta'][60]['prbp_template_rules'] = json_encode( [
			[ 'attribute' => 'pa_color', 'attribute_label' => 'Color', 'rows' => [
				[ 'value_ids' => [ '1' ], 'value_slugs' => [ 'red' ],  'value_labels' => [ 'Red' ],  'price' => '5.00' ],
				[ 'value_ids' => [ '2' ], 'value_slugs' => [ 'blue' ], 'value_labels' => [ 'Blue' ], 'price' => '7.00' ],
			] ],
		] );

		$result = PRBP\Utils\RulesCache::get( 60 );

		$this->assertCount( 2, $result );
		$this->assertSame( 'pa_color', $result[0]['attribute'] );
		$this->assertSame( 'red',  $result[0]['value_slugs'][0] );
		$this->assertSame( 'blue', $result[1]['value_slugs'][0] );
		$this->assertSame( '+',    $result[0]['operator'] );
	}

	public function test_new_format_row_status_inactive_is_filtered_out(): void {
		$GLOBALS['prbp_test_meta'][61]['prbp_template_rules'] = json_encode( [
			[ 'attribute' => 'pa_color', 'attribute_label' => 'Color', 'rows' => [
				[ 'value_ids' => [ '1' ], 'value_slugs' => [ 'red' ],  'value_labels' => [ 'Red' ],  'price' => '5.00', 'status' => 'active' ],
				[ 'value_ids' => [ '2' ], 'value_slugs' => [ 'blue' ], 'value_labels' => [ 'Blue' ], 'price' => '7.00', 'status' => 'inactive' ],
			] ],
		] );

		$result = PRBP\Utils\RulesCache::get( 61 );

		$this->assertCount( 1, $result );
		$this->assertSame( 'red', $result[0]['value_slugs'][0] );
	}
```

- [ ] **Step 2: Run to verify the two new tests fail**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit tests/RulesCacheTest.php`
Expected: 11 tests pass (existing), 2 fail (`test_reads_new_sections_format_directly`, `test_new_format_row_status_inactive_is_filtered_out`) — the current `get()` body still expects flat rule shape, so `value_slugs` won't be found at the top level of a `rows`-nested entry.

- [ ] **Step 3: Modify `RulesCache::get()`**

In `src/Utils/RulesCache.php`, replace the body of `get()`:

```php
	public static function get( int $template_id, bool $active_only = true ): array {
		$key = $template_id . '_' . ( $active_only ? 'active' : 'all' );

		if ( ! isset( self::$cache[ $key ] ) ) {
			$raw     = get_post_meta( $template_id, 'prbp_template_rules', true );
			$decoded = $raw ? json_decode( $raw, true ) : [];

			if ( ! is_array( $decoded ) ) {
				$decoded = [];
			}

			$sections = RulesFormat::normalize( $decoded );
			$all      = RulesFormat::flatten( $sections );

			self::$cache[ $template_id . '_all' ]    = $all;
			self::$cache[ $template_id . '_active' ] = array_values(
				array_filter( $all, fn( array $r ): bool => ( $r['status'] ?? 'active' ) === 'active' )
			);
		}

		return self::$cache[ $key ];
	}
```

This replaces the old body that did `$all = $raw ? json_decode( $raw, true ) : [];` directly followed by the same `array_filter`/cache-assignment lines — keep those two cache-assignment lines and the `array_filter` call exactly as they were; only the decode step changes (decode → normalize → flatten instead of decode straight into `$all`).

- [ ] **Step 4: Run the full `RulesCacheTest` suite to verify everything passes**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit tests/RulesCacheTest.php`
Expected: `OK (13 tests, ...)`

- [ ] **Step 5: Run the full suite to confirm no regressions elsewhere**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit`
Expected: same 11 pre-existing `BasePriceSidebarTest` errors, zero other failures (this exercises `PriceRecalculatorTest`, `ProductPageGetMinPriceTest`, `ProductPageFilterPriceHtmlTest`, `CartItemMetaTest` against the new `RulesCache::get()` body via their old-flat-format fixtures).

- [ ] **Step 6: Commit**

```bash
cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce"
git add src/Utils/RulesCache.php
git commit -m "Read RulesCache via RulesFormat so old and new rule formats both work"
```

---

## Task 3: Rewrite `RuleValidator` for attribute sections

**Files:**
- Modify: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/src/Utils/RuleValidator.php`
- Modify (full rewrite): `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/tests/RuleValidatorTest.php`
- Modify (full rewrite): `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/tests/RuleValidatorAdditionalTest.php`

**Interfaces:**
- Produces: `PRBP\Utils\RuleValidator::validate(array $sections): array{valid: bool, errors: string[]}` — input is now `[{attribute, attribute_label, rows:[{value_ids,value_slugs,value_labels,price}]}]`, not flat rules.

This task replaces both existing test files' fixtures first (red), then rewrites the validator (green) — keep going task-internally rather than per-file, since both test files share the same target contract.

- [ ] **Step 1: Replace `tests/RuleValidatorTest.php` in full**

```php
<?php
use PHPUnit\Framework\TestCase;

class RuleValidatorTest extends TestCase {

	private function section( array $overrides = [] ): array {
		return array_merge( [
			'attribute'       => 'pa_color',
			'attribute_label' => 'Color',
			'rows'            => [ $this->row() ],
		], $overrides );
	}

	private function row( array $overrides = [] ): array {
		return array_merge( [
			'value_ids'    => [ '12', '17' ],
			'value_slugs'  => [ 'red', 'blue' ],
			'value_labels' => [ 'Red', 'Blue' ],
			'price'        => '5.00',
		], $overrides );
	}

	public function test_valid_section_with_multiple_values(): void {
		$result = PRBP\Utils\RuleValidator::validate( [ $this->section() ] );
		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_missing_value_slugs_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [ 'value_slugs' => [] ] ) ] ] ),
		] );
		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'value is required', $result['errors'][0] );
	}

	public function test_non_array_value_slugs_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [ 'value_slugs' => 'red' ] ) ] ] ),
		] );
		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'value is required', $result['errors'][0] );
	}

	public function test_missing_attribute_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [ $this->section( [ 'attribute' => '' ] ) ] );
		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'invalid or missing attribute', $result['errors'][0] );
	}

	public function test_negative_price_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [ 'price' => '-1' ] ) ] ] ),
		] );
		$this->assertFalse( $result['valid'] );
	}

	public function test_duplicate_value_within_section_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [
				$this->row( [ 'value_slugs' => [ 'red' ], 'value_labels' => [ 'Red' ] ] ),
				$this->row( [ 'value_slugs' => [ 'red' ], 'value_labels' => [ 'Red' ] ] ),
			] ] ),
		] );
		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'duplicate value', $result['errors'][0] );
	}

	public function test_duplicate_slug_within_same_row_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [ 'value_slugs' => [ 'red', 'red' ] ] ) ] ] ),
		] );
		$this->assertFalse( $result['valid'] );
	}

	public function test_same_slug_different_attributes_is_valid(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'attribute' => 'pa_color', 'rows' => [ $this->row( [ 'value_slugs' => [ 'red' ], 'value_labels' => [ 'Red' ] ] ) ] ] ),
			$this->section( [ 'attribute' => 'pa_size',  'rows' => [ $this->row( [ 'value_slugs' => [ 'red' ], 'value_labels' => [ 'Red' ] ] ) ] ] ),
		] );
		$this->assertTrue( $result['valid'] );
	}

	public function test_duplicate_section_for_same_attribute_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'attribute' => 'pa_color' ] ),
			$this->section( [ 'attribute' => 'pa_color' ] ),
		] );
		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'Duplicate section', $result['errors'][0] );
	}
}
```

- [ ] **Step 2: Replace `tests/RuleValidatorAdditionalTest.php` in full**

```php
<?php

use PHPUnit\Framework\TestCase;

class RuleValidatorAdditionalTest extends TestCase {

	private function section( array $overrides = [] ): array {
		return array_merge( [
			'attribute'       => 'pa_color',
			'attribute_label' => 'Color',
			'rows'            => [ $this->row() ],
		], $overrides );
	}

	private function row( array $overrides = [] ): array {
		return array_merge( [
			'value_ids'    => [ '12' ],
			'value_slugs'  => [ 'red' ],
			'value_labels' => [ 'Red' ],
			'price'        => '5.00',
		], $overrides );
	}

	public function test_empty_sections_array_is_valid(): void {
		$result = PRBP\Utils\RuleValidator::validate( [] );
		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_zero_price_is_valid(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [ 'price' => '0' ] ) ] ] ),
		] );
		$this->assertTrue( $result['valid'] );
	}

	public function test_float_zero_price_is_valid(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [ 'price' => '0.00' ] ) ] ] ),
		] );
		$this->assertTrue( $result['valid'] );
	}

	public function test_large_price_is_valid(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [ 'price' => '9999.99' ] ) ] ] ),
		] );
		$this->assertTrue( $result['valid'] );
	}

	public function test_attribute_without_pa_prefix_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [ $this->section( [ 'attribute' => 'color' ] ) ] );
		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'invalid or missing attribute', $result['errors'][0] );
	}

	public function test_attribute_with_uppercase_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [ $this->section( [ 'attribute' => 'pa_Color' ] ) ] );
		$this->assertFalse( $result['valid'] );
	}

	public function test_attribute_with_numbers_is_valid(): void {
		$result = PRBP\Utils\RuleValidator::validate( [ $this->section( [ 'attribute' => 'pa_size2' ] ) ] );
		$this->assertTrue( $result['valid'] );
	}

	public function test_attribute_with_underscore_is_valid(): void {
		$result = PRBP\Utils\RuleValidator::validate( [ $this->section( [ 'attribute' => 'pa_shoe_size' ] ) ] );
		$this->assertTrue( $result['valid'] );
	}

	public function test_value_labels_count_mismatch_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [
				'value_slugs'  => [ 'red', 'blue' ],
				'value_labels' => [ 'Red' ],
			] ) ] ] ),
		] );
		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'same number', $result['errors'][0] );
	}

	public function test_missing_value_labels_key_fails(): void {
		$row = $this->row();
		unset( $row['value_labels'] );
		$result = PRBP\Utils\RuleValidator::validate( [ $this->section( [ 'rows' => [ $row ] ] ) ] );
		$this->assertFalse( $result['valid'] );
	}

	public function test_non_numeric_price_fails(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [ 'price' => 'abc' ] ) ] ] ),
		] );
		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'price must be a non-negative number', $result['errors'][0] );
	}

	public function test_missing_price_key_fails(): void {
		$row = $this->row();
		unset( $row['price'] );
		$result = PRBP\Utils\RuleValidator::validate( [ $this->section( [ 'rows' => [ $row ] ] ) ] );
		$this->assertFalse( $result['valid'] );
	}

	public function test_multiple_invalid_sections_accumulate_errors(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'attribute' => 'bad' ] ),
			$this->section( [ 'attribute' => 'pa_color', 'rows' => [ $this->row( [ 'price' => '-5' ] ) ] ] ),
			$this->section( [ 'attribute' => 'also_bad', 'rows' => [ $this->row( [ 'price' => 'nope' ] ) ] ] ),
		] );
		$this->assertFalse( $result['valid'] );
		$this->assertGreaterThanOrEqual( 3, count( $result['errors'] ) );
	}

	public function test_errors_include_section_numbers(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'attribute' => 'bad' ] ),
			$this->section( [ 'attribute' => 'bad2' ] ),
		] );
		$this->assertStringContainsString( 'Section 1', $result['errors'][0] );
		$this->assertStringContainsString( 'Section 2', $result['errors'][1] );
	}

	public function test_single_slug_matching_single_label_is_valid(): void {
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'rows' => [ $this->row( [
				'value_slugs'  => [ 'xl' ],
				'value_labels' => [ 'XL' ],
			] ) ] ] ),
		] );
		$this->assertTrue( $result['valid'] );
	}

	public function test_many_valid_rows_in_one_section_all_pass(): void {
		$rows = [];
		for ( $i = 0; $i < 20; $i++ ) {
			$rows[] = $this->row( [ 'value_slugs' => [ "val_$i" ], 'value_labels' => [ "Val $i" ] ] );
		}
		$result = PRBP\Utils\RuleValidator::validate( [
			$this->section( [ 'attribute' => 'pa_size', 'rows' => $rows ] ),
		] );
		$this->assertTrue( $result['valid'] );
	}
}
```

- [ ] **Step 3: Run both test files to verify they fail against the current (flat-rule) validator**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit tests/RuleValidatorTest.php tests/RuleValidatorAdditionalTest.php`
Expected: most tests fail — the current `validate()` reads `$rule['attribute']`/`$rule['value_slugs']` directly off each top-level entry, which no longer exist now that entries are `{attribute, rows:[...]}`.

- [ ] **Step 4: Replace `src/Utils/RuleValidator.php` in full**

```php
<?php
/**
 * Validates a decoded array of attribute sections before saving to post meta.
 *
 * @package PRBP\Utils
 */

namespace PRBP\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RuleValidator {

	/**
	 * @param  array<int, array<string, mixed>> $sections Raw decoded JSON array of section objects.
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate( array $sections ): array {
		$errors          = [];
		$seen_attributes = [];

		foreach ( $sections as $i => $section ) {
			$section_num = $i + 1;
			$attribute   = $section['attribute'] ?? '';
			$label       = $section['attribute_label'] ?? $attribute;

			if ( empty( $attribute ) || ! preg_match( '/^pa_[a-z0-9_]+$/', $attribute ) ) {
				/* translators: %d: Section number in the pricing rules editor */
				$errors[] = sprintf( __( 'Section %d: invalid or missing attribute.', 'priceblueprint-for-woocommerce' ), $section_num );
				continue;
			}

			if ( isset( $seen_attributes[ $attribute ] ) ) {
				/* translators: %s: Attribute label */
				$errors[] = sprintf( __( 'Duplicate section for attribute: %s.', 'priceblueprint-for-woocommerce' ), $label );
			}
			$seen_attributes[ $attribute ] = true;

			$rows = $section['rows'] ?? null;
			if ( empty( $rows ) || ! is_array( $rows ) ) {
				/* translators: %s: Attribute label */
				$errors[] = sprintf( __( '%s: at least one value is required.', 'priceblueprint-for-woocommerce' ), $label );
				continue;
			}

			$seen_values = [];

			foreach ( $rows as $j => $row ) {
				$row_num = $j + 1;

				if ( empty( $row['value_slugs'] ) || ! is_array( $row['value_slugs'] ) ) {
					/* translators: 1: Attribute label, 2: Row number within the section */
					$errors[] = sprintf( __( '%1$s, row %2$d: value is required.', 'priceblueprint-for-woocommerce' ), $label, $row_num );
				} else {
					$labels = $row['value_labels'] ?? null;
					if ( ! is_array( $labels ) || count( $labels ) !== count( $row['value_slugs'] ) ) {
						/* translators: 1: Attribute label, 2: Row number within the section */
						$errors[] = sprintf( __( '%1$s, row %2$d: value slugs and labels must have the same number of entries.', 'priceblueprint-for-woocommerce' ), $label, $row_num );
					}

					foreach ( $row['value_slugs'] as $slug ) {
						if ( isset( $seen_values[ $slug ] ) ) {
							/* translators: 1: Attribute label, 2: Row number within the section, 3: Value slug */
							$errors[] = sprintf( __( '%1$s, row %2$d: duplicate value (%3$s).', 'priceblueprint-for-woocommerce' ), $label, $row_num, $slug );
						}
						$seen_values[ $slug ] = true;
					}
				}

				if ( ! isset( $row['price'] ) || ! is_numeric( $row['price'] ) || (float) $row['price'] < 0 ) {
					/* translators: 1: Attribute label, 2: Row number within the section */
					$errors[] = sprintf( __( '%1$s, row %2$d: price must be a non-negative number.', 'priceblueprint-for-woocommerce' ), $label, $row_num );
				}
			}
		}

		return [ 'valid' => empty( $errors ), 'errors' => $errors ];
	}
}
```

- [ ] **Step 5: Run both test files to verify they pass**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit tests/RuleValidatorTest.php tests/RuleValidatorAdditionalTest.php`
Expected: `OK (25 tests, ...)`

- [ ] **Step 6: Run the full suite**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit`
Expected: same 11 pre-existing `BasePriceSidebarTest` errors, zero other failures.

- [ ] **Step 7: Commit**

```bash
cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce"
git add src/Utils/RuleValidator.php
git commit -m "Validate pricing rules as attribute sections instead of flat rules"
```

---

## Task 4: Rewrite `SaveHandler::sanitizeRules` → `sanitizeSections`

**Files:**
- Modify: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/src/Admin/SaveHandler.php`
- Modify (full rewrite): `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/tests/SaveHandlerSanitizeTest.php`
- Modify (full rewrite): `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/tests/SaveHandlerExtendedTest.php`

**Interfaces:**
- Consumes: `RuleValidator::validate(array $sections): array` (Task 3, unchanged signature).
- Produces: private `SaveHandler::sanitizeSections(array $sections): array` — sanitizes the nested shape; row output no longer has an `operator` key (added back later only by `RulesFormat::flatten()` for runtime consumers).

- [ ] **Step 1: Replace `tests/SaveHandlerSanitizeTest.php` in full**

```php
<?php
use PHPUnit\Framework\TestCase;

class SaveHandlerSanitizeTest extends TestCase {

	private function callSanitize( array $sections ): array {
		$ref = new ReflectionMethod( PRBP\Admin\SaveHandler::class, 'sanitizeSections' );
		$ref->setAccessible( true );
		return $ref->invoke( null, $sections );
	}

	public function test_sanitizes_array_fields(): void {
		$result = $this->callSanitize( [ [
			'attribute'       => 'pa_color',
			'attribute_label' => 'Color',
			'rows'            => [ [
				'value_ids'    => [ '12', '17' ],
				'value_slugs'  => [ 'red', 'blue' ],
				'value_labels' => [ 'Red', 'Blue' ],
				'price'        => '5.00',
			] ],
		] ] );

		$this->assertSame( [ 12, 17 ],        $result[0]['rows'][0]['value_ids'] );
		$this->assertSame( [ 'red', 'blue' ], $result[0]['rows'][0]['value_slugs'] );
		$this->assertSame( [ 'Red', 'Blue' ], $result[0]['rows'][0]['value_labels'] );
	}

	public function test_no_status_or_v_in_output(): void {
		$result = $this->callSanitize( [ [
			'attribute'       => 'pa_color',
			'attribute_label' => 'Color',
			'rows'            => [ [
				'value_ids'    => [],
				'value_slugs'  => [],
				'value_labels' => [],
				'price'        => '0',
			] ],
		] ] );

		$this->assertArrayNotHasKey( 'status', $result[0] );
		$this->assertArrayNotHasKey( 'v',      $result[0] );
		$this->assertArrayNotHasKey( 'status', $result[0]['rows'][0] );
	}

	public function test_missing_arrays_default_to_empty(): void {
		$result = $this->callSanitize( [ [
			'attribute'       => 'pa_size',
			'attribute_label' => 'Size',
			'rows'            => [ [ 'price' => '2.50' ] ],
		] ] );

		$this->assertSame( [], $result[0]['rows'][0]['value_ids'] );
		$this->assertSame( [], $result[0]['rows'][0]['value_slugs'] );
		$this->assertSame( [], $result[0]['rows'][0]['value_labels'] );
	}

	public function test_price_floored_at_zero(): void {
		$result = $this->callSanitize( [ [
			'attribute'       => 'pa_color',
			'attribute_label' => 'Color',
			'rows'            => [ [
				'value_ids'    => [],
				'value_slugs'  => [],
				'value_labels' => [],
				'price'        => '-3',
			] ],
		] ] );

		$this->assertSame( '0', $result[0]['rows'][0]['price'] );
	}

	public function test_missing_rows_key_defaults_to_empty(): void {
		$result = $this->callSanitize( [ [
			'attribute'       => 'pa_color',
			'attribute_label' => 'Color',
		] ] );

		$this->assertSame( [], $result[0]['rows'] );
	}
}
```

- [ ] **Step 2: Replace `tests/SaveHandlerExtendedTest.php` in full**

```php
<?php

use PHPUnit\Framework\TestCase;

class SaveHandlerExtendedTest extends TestCase {

	private function callSanitize( array $sections ): array {
		$ref = new ReflectionMethod( PRBP\Admin\SaveHandler::class, 'sanitizeSections' );
		$ref->setAccessible( true );
		return $ref->invoke( null, $sections );
	}

	private function section( array $overrides = [] ): array {
		return array_merge( [
			'attribute'       => 'pa_color',
			'attribute_label' => 'Color',
			'rows'            => [ $this->row() ],
		], $overrides );
	}

	private function row( array $overrides = [] ): array {
		return array_merge( [
			'value_ids'    => [ '12' ],
			'value_slugs'  => [ 'red' ],
			'value_labels' => [ 'Red' ],
			'price'        => '5.00',
		], $overrides );
	}

	public function test_no_operator_key_in_sanitized_row(): void {
		$result = $this->callSanitize( [ $this->section() ] );
		$this->assertArrayNotHasKey( 'operator', $result[0]['rows'][0] );
	}

	public function test_html_in_attribute_label_is_stripped(): void {
		$result = $this->callSanitize( [ $this->section( [ 'attribute_label' => '<b>Bold Color</b>' ] ) ] );
		$this->assertSame( 'Bold Color', $result[0]['attribute_label'] );
	}

	public function test_html_tags_in_value_labels_are_stripped(): void {
		$result = $this->callSanitize( [
			$this->section( [ 'rows' => [ $this->row( [ 'value_labels' => [ '<b>Red</b>' ] ] ) ] ] ),
		] );
		$this->assertSame( 'Red', $result[0]['rows'][0]['value_labels'][0] );
	}

	public function test_attribute_is_lowercased_by_sanitize_key(): void {
		$result = $this->callSanitize( [ $this->section( [ 'attribute' => 'PA_COLOR' ] ) ] );
		$this->assertSame( 'pa_color', $result[0]['attribute'] );
	}

	public function test_special_chars_stripped_from_attribute(): void {
		$result = $this->callSanitize( [ $this->section( [ 'attribute' => 'pa_co lor!' ] ) ] );
		$this->assertSame( 'pa_color', $result[0]['attribute'] );
	}

	public function test_float_price_preserved_as_string(): void {
		$result = $this->callSanitize( [
			$this->section( [ 'rows' => [ $this->row( [ 'price' => '19.99' ] ) ] ] ),
		] );
		$this->assertIsString( $result[0]['rows'][0]['price'] );
		$this->assertSame( '19.99', $result[0]['rows'][0]['price'] );
	}

	public function test_large_float_price_preserved(): void {
		$result = $this->callSanitize( [
			$this->section( [ 'rows' => [ $this->row( [ 'price' => '9999.50' ] ) ] ] ),
		] );
		$this->assertSame( '9999.5', $result[0]['rows'][0]['price'] );
	}

	public function test_value_slugs_are_sanitized_as_keys(): void {
		// sanitize_key strips non-[a-z0-9_-] chars; spaces are removed, not converted to hyphens.
		$result = $this->callSanitize( [
			$this->section( [ 'rows' => [ $this->row( [ 'value_slugs' => [ 'Red Color!', 'BLUE' ] ] ) ] ] ),
		] );
		$this->assertSame( [ 'redcolor', 'blue' ], $result[0]['rows'][0]['value_slugs'] );
	}

	public function test_multiple_sections_all_sanitized(): void {
		$result = $this->callSanitize( [
			$this->section( [ 'attribute' => 'pa_size' ] ),
			$this->section( [ 'attribute' => 'pa_color' ] ),
		] );
		$this->assertCount( 2, $result );
		$this->assertSame( 'pa_size',  $result[0]['attribute'] );
		$this->assertSame( 'pa_color', $result[1]['attribute'] );
	}

	public function test_multiple_rows_in_one_section_all_sanitized(): void {
		$result = $this->callSanitize( [
			$this->section( [ 'rows' => [
				$this->row( [ 'price' => '3.00' ] ),
				$this->row( [ 'value_slugs' => [ 'blue' ], 'value_labels' => [ 'Blue' ], 'price' => '7.50' ] ),
			] ] ),
		] );
		$this->assertCount( 2, $result[0]['rows'] );
		$this->assertSame( '3.00', $result[0]['rows'][0]['price'] );
		$this->assertSame( '7.50', $result[0]['rows'][1]['price'] );
	}

	public function test_empty_sections_array_returns_empty(): void {
		$result = $this->callSanitize( [] );
		$this->assertSame( [], $result );
	}

	public function test_whitespace_trimmed_from_attribute_label(): void {
		$result = $this->callSanitize( [ $this->section( [ 'attribute_label' => '  Color  ' ] ) ] );
		$this->assertSame( 'Color', $result[0]['attribute_label'] );
	}
}
```

- [ ] **Step 3: Run both files to verify they fail**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit tests/SaveHandlerSanitizeTest.php tests/SaveHandlerExtendedTest.php`
Expected: `Error: Method PRBP\Admin\SaveHandler::sanitizeSections() does not exist` (the method is still named `sanitizeRules` and expects flat input).

- [ ] **Step 4: Modify `src/Admin/SaveHandler.php`**

Replace the `handle()` method body and the `sanitizeRules()` method (the rest of the file — `register()`, `showErrorNotice()` — is unchanged):

```php
	public static function handle( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['prbp_rules_nonce_field'] )
			|| ! check_admin_referer( 'prbp_rules_nonce', 'prbp_rules_nonce_field' )
		) {
			return;
		}

		// JSON must not be run through a string sanitizer — content is validated by RuleValidator after decode.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_json = isset( $_POST['prbp_rules_json'] ) ? wp_unslash( $_POST['prbp_rules_json'] ) : '';

		$sections = [];
		if ( ! empty( $raw_json ) ) {
			$decoded = json_decode( $raw_json, true );
			if ( is_array( $decoded ) ) {
				$sections = $decoded;
			}
		}

		$result = RuleValidator::validate( $sections );

		if ( ! $result['valid'] ) {
			$user_id = get_current_user_id();
			set_transient( 'prbp_save_error_' . $user_id, $result['errors'], 60 );

			$redirect = add_query_arg(
				[ 'prbp_error' => 1, 'post' => $post_id, 'action' => 'edit' ],
				admin_url( 'post.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		update_post_meta( $post_id, 'prbp_template_rules', wp_json_encode( self::sanitizeSections( $sections ) ) );
		RulesCache::flush( $post_id );
	}

	/**
	 * Whitelist and sanitize each section and its rows before storage.
	 *
	 * @param  array<int, array<string, mixed>> $sections
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitizeSections( array $sections ): array {
		$clean = [];

		foreach ( $sections as $section ) {
			$rows = [];
			foreach ( (array) ( $section['rows'] ?? [] ) as $row ) {
				$rows[] = [
					'value_ids'    => array_map( 'absint',              (array) ( $row['value_ids']    ?? [] ) ),
					'value_slugs'  => array_map( 'sanitize_key',        (array) ( $row['value_slugs']  ?? [] ) ),
					'value_labels' => array_map( 'sanitize_text_field', (array) ( $row['value_labels'] ?? [] ) ),
					'price'        => (string) max( 0.0, (float) ( $row['price'] ?? 0 ) ),
				];
			}

			$clean[] = [
				'attribute'       => sanitize_key( $section['attribute'] ?? '' ),
				'attribute_label' => sanitize_text_field( $section['attribute_label'] ?? '' ),
				'rows'            => $rows,
			];
		}

		return $clean;
	}
```

- [ ] **Step 5: Run both files to verify they pass**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit tests/SaveHandlerSanitizeTest.php tests/SaveHandlerExtendedTest.php`
Expected: `OK (17 tests, ...)`

- [ ] **Step 6: Run the full suite**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit`
Expected: same 11 pre-existing `BasePriceSidebarTest` errors, zero other failures.

- [ ] **Step 7: Commit**

```bash
cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce"
git add src/Admin/SaveHandler.php
git commit -m "Sanitize pricing rules as attribute sections instead of flat rules"
```

---

## Task 5: Admin template — sections markup + `RulesRepeater` normalization

**Files:**
- Modify: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/src/Admin/RulesRepeater.php`
- Modify: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/templates/admin-repeater.php`

**Interfaces:**
- Consumes: `RulesFormat::normalize(array): array` (Task 1).
- Produces: the `prbpRulesData` JS global is now sections-shaped (`[{attribute, attribute_label, rows:[...]}]`) instead of flat rules — consumed by Task 6's rewritten `init()`.

No automated test exists for either file (both require live WordPress/WooCommerce functions). Verification here is `php -l` syntax checks; behavior is verified end-to-end in Task 8.

- [ ] **Step 1: Modify `RulesRepeater::render()`**

Add `use PRBP\Utils\RulesFormat;` to the top of the file (after `namespace PRBP\Admin;`), and change the rules-decoding block inside `render()`:

```php
namespace PRBP\Admin;

use PRBP\Utils\RulesFormat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
```

```php
	public static function render( \WP_Post $post ): void {
		$raw   = get_post_meta( $post->ID, 'prbp_template_rules', true );
		$rules = [];

		if ( $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$rules = RulesFormat::normalize( $decoded );
			}
		}

		// Fetch all WC global attributes (pa_* taxonomies).
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		require PRBP_PLUGIN_DIR . 'templates/admin-repeater.php';
	}
```

(Only the `$decoded` → `$rules` line changes — wraps it in `RulesFormat::normalize()`. Everything else in the file, including `addBox()` and `enqueueAssets()`, is unchanged in this step.)

- [ ] **Step 2: Lint-check the PHP file**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce" && php -l src/Admin/RulesRepeater.php`
Expected: `No syntax errors detected in src/Admin/RulesRepeater.php`

- [ ] **Step 3: Update the duplicate-value error string's wording in `enqueueAssets()`**

In `src/Admin/RulesRepeater.php`, inside the `i18n` array passed to `wp_localize_script`, change:

```php
				/* translators: 1: Attribute label, 2: Value label */
				'duplicate_msg'       => __( 'Duplicate rule: %1$s → %2$s. Remove duplicates before saving.', 'priceblueprint-for-woocommerce' ),
```

to:

```php
				/* translators: 1: Attribute label, 2: Value label */
				'duplicate_msg'       => __( 'Duplicate value for %1$s: %2$s. Remove duplicates before saving.', 'priceblueprint-for-woocommerce' ),
```

- [ ] **Step 4: Replace `templates/admin-repeater.php` from the Quick Setup block onward**

Keep lines 1–61 of the file exactly as they are (the doc comment, the no-attributes early return, `wp_nonce_field`, and the `<script>` block that JSON-encodes `prbpRulesData`/`prbpAttrsData` — `$rules` is now sections-shaped because of Step 1, but the encoding code itself doesn't change). Starting at the `<!-- ── Quick Setup` comment through the end of the file, replace everything with:

```php
	<!-- ── Quick Setup (visible only when no active sections) ───────────── -->
	<div class="prbp-quick-setup"
	     x-show="activeSectionsCount === 0"
	     style="display:none;">

		<div class="prbp-qs-panels">

			<!-- Generate from a product -->
			<div class="prbp-qs-panel">
				<div class="prbp-qs-chip">&#9889; <?php esc_html_e( 'Quick Setup', 'priceblueprint-for-woocommerce' ); ?></div>
				<h3 class="prbp-qs-panel-title">
					<?php esc_html_e( 'Generate rules from a product', 'priceblueprint-for-woocommerce' ); ?>
				</h3>
				<p class="prbp-qs-panel-desc">
					<?php esc_html_e( 'Pick a product and its WooCommerce attributes will pre-fill this blueprint. Prices start at 0 — set them as needed. The product stays exactly as it is.', 'priceblueprint-for-woocommerce' ); ?>
				</p>

				<div class="prbp-qs-controls">
					<select class="prbp-qs-product-select"
					        x-init="initProductSelect($el)">
					</select>
					<button type="button"
					        class="prbp-qs-btn"
					        :disabled="!quickSetupProductId || quickSetupLoading"
					        @click="importFromProduct()">
						<span x-show="quickSetupLoading">&#8230;</span>
						<span x-show="!quickSetupLoading">&#9889; <?php esc_html_e( 'Generate', 'priceblueprint-for-woocommerce' ); ?></span>
					</button>
				</div>

				<!-- No-attributes notice -->
				<p class="prbp-qs-notice"
				   x-show="quickSetupError === 'no_attrs'"
				   style="display:none;">
					<?php esc_html_e( 'This product has no WooCommerce attributes.', 'priceblueprint-for-woocommerce' ); ?>
					<a :href="productEditUrl(quickSetupProductId)"
					   target="_blank" rel="noopener">
						<?php esc_html_e( 'Add them in WooCommerce →', 'priceblueprint-for-woocommerce' ); ?>
					</a>
				</p>

				<!-- Fetch-error notice -->
				<p class="prbp-qs-notice prbp-qs-notice--error"
				   x-show="quickSetupError === 'fetch_error'"
				   style="display:none;">
					<?php esc_html_e( 'Could not load attributes. Please try again.', 'priceblueprint-for-woocommerce' ); ?>
				</p>
			</div>

		</div><!-- /.prbp-qs-panels -->

	</div><!-- /.prbp-quick-setup -->

	<!-- ── Error banner ───────────────────────────────────────────────────── -->
	<div class="prbp-error-banner"
	     x-show="errorMsg"
	     x-text="errorMsg"
	     style="display:none;"></div>

	<!-- ── Filter bar ────────────────────────────────────────────────────── -->
	<div class="prbp-filter-bar"
	     x-show="activeSectionsCount > 0"
	     style="display:none;">
		<input type="text"
		       x-model.debounce.200ms="query"
		       placeholder="<?php esc_attr_e( 'Filter by attribute or value…', 'priceblueprint-for-woocommerce' ); ?>"
		       autocomplete="off">
		<button type="button" class="prbp-col-sortable prbp-sort-toggle" @click="toggleSort()">
			<?php esc_html_e( 'Sort by attribute', 'priceblueprint-for-woocommerce' ); ?>
			<span class="prbp-sort-icon"
			      :class="{ 'prbp-sort-icon--asc': sortDir === 'asc', 'prbp-sort-icon--desc': sortDir === 'desc' }"></span>
		</button>
		<span class="prbp-rules-count" x-text="countLabel"></span>
	</div>

	<!-- ── Sections ──────────────────────────────────────────────────────── -->
	<div class="prbp-sections" x-show="activeSectionsCount > 0" style="display:none;">

		<template x-for="entry in displaySections" :key="entry.section._uid">
			<div class="prbp-section" x-show="entry.sectionInDom">

				<div class="prbp-section-header">
					<h4 class="prbp-section-title" x-text="entry.section.attribute_label || entry.section.attribute"></h4>

					<button type="button"
					        class="prbp-delete-btn button button-small"
					        title="<?php esc_attr_e( 'Delete', 'priceblueprint-for-woocommerce' ); ?>"
					        x-show="entry.section.status !== 'deleted'"
					        @click="deleteSection(entry.section)">
						<span class="dashicons dashicons-trash"></span>
					</button>

					<button type="button"
					        class="prbp-restore-btn button button-small"
					        title="<?php esc_attr_e( 'Restore', 'priceblueprint-for-woocommerce' ); ?>"
					        x-show="entry.section.status === 'deleted'"
					        style="display:none;"
					        @click="restoreSection(entry.section)">
						<span class="dashicons dashicons-undo"></span>
					</button>
				</div>

				<table class="prbp-rules-table prbp-section-table widefat"
				       x-show="entry.section.status !== 'deleted'">
					<thead>
						<tr>
							<th class="prbp-col-index">#</th>
							<th class="prbp-col-value"><?php esc_html_e( 'Value', 'priceblueprint-for-woocommerce' ); ?></th>
							<th class="prbp-col-price"><?php esc_html_e( 'Price', 'priceblueprint-for-woocommerce' ); ?></th>
							<th class="prbp-col-actions"><?php esc_html_e( 'Actions', 'priceblueprint-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>

						<template x-for="rowEntry in entry.rows" :key="rowEntry.row._uid">
							<tr x-show="rowEntry.inDom"
							    :class="{'prbp-row--deleted': rowEntry.row.status === 'deleted'}">

								<td class="prbp-col-index">
									<span x-text="rowEntry.pos || ''"></span>
								</td>

								<td class="prbp-col-value">
									<select multiple
									        class="prbp-value-select"
									        x-init="initValueSelect($el, rowEntry.row)">
									</select>
								</td>

								<td class="prbp-col-price">
									<span class="prbp-price-wrap">
										<input type="number"
										       class="prbp-price-input"
										       x-model="rowEntry.row.price"
										       step="0.01"
										       min="0"
										       placeholder="0.00">
										<span class="prbp-price-currency" aria-hidden="true"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
									</span>
								</td>

								<td class="prbp-col-actions">
									<button type="button"
									        class="prbp-delete-btn button button-small"
									        title="<?php esc_attr_e( 'Delete', 'priceblueprint-for-woocommerce' ); ?>"
									        x-show="rowEntry.row.status !== 'deleted'"
									        @click="deleteRow(rowEntry.row)">
										<span class="dashicons dashicons-trash"></span>
									</button>

									<button type="button"
									        class="prbp-restore-btn button button-small"
									        title="<?php esc_attr_e( 'Restore', 'priceblueprint-for-woocommerce' ); ?>"
									        x-show="rowEntry.row.status === 'deleted'"
									        style="display:none;"
									        @click="restoreRow(rowEntry.row)">
										<span class="dashicons dashicons-undo"></span>
									</button>
								</td>

							</tr>
						</template>

					</tbody>
				</table>

				<p class="prbp-add-row" x-show="entry.section.status !== 'deleted'">
					<button type="button" class="button button-secondary button-small" @click="addRow(entry.section)">
						<?php esc_html_e( '+ Add value', 'priceblueprint-for-woocommerce' ); ?>
					</button>
				</p>

			</div>
		</template>

	</div><!-- /.prbp-sections -->

	<!-- ── Add section ───────────────────────────────────────────────────── -->
	<p class="prbp-add-section" x-show="availableAttributes.length > 0">
		<label for="prbp-add-section-select" class="screen-reader-text">
			<?php esc_html_e( 'Add attribute section', 'priceblueprint-for-woocommerce' ); ?>
		</label>
		<select id="prbp-add-section-select" @change="addSection($event)">
			<option value=""><?php esc_html_e( '+ Add attribute section', 'priceblueprint-for-woocommerce' ); ?></option>
			<template x-for="attr in availableAttributes" :key="attr.slug">
				<option :value="attr.slug" x-text="attr.label"></option>
			</template>
		</select>
	</p>

	<!-- JSON payload — written by onSubmit immediately before the form POSTs -->
	<input type="hidden" name="prbp_rules_json" id="prbp-rules-json" value="">

</div><!-- /.prbp-admin-wrap -->
```

- [ ] **Step 5: Lint-check the template**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce" && php -l templates/admin-repeater.php`
Expected: `No syntax errors detected in templates/admin-repeater.php`

- [ ] **Step 6: Run the full PHPUnit suite (regression check — this task touches no tested code path directly, but confirms nothing else broke)**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit`
Expected: same 11 pre-existing `BasePriceSidebarTest` errors, zero other failures.

- [ ] **Step 7: Commit**

```bash
cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce"
git add src/Admin/RulesRepeater.php templates/admin-repeater.php
git commit -m "Render pricing rules as attribute sections in the admin editor"
```

---

## Task 6: Rewrite `assets/js/admin/dom-controller.js` Alpine component

**Files:**
- Modify: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/assets/js/admin/dom-controller.js`

**Interfaces:**
- Consumes: `prbpRulesData`/`prbpAttrsData` globals from Task 5's template (sections-shaped).
- Produces: Alpine component `rulesRepeater` exposing `sections`, `availableAttributes`, `activeSectionsCount`, `displaySections`, `countLabel`, `addSection(event)`, `deleteSection(section)`, `restoreSection(section)`, `addRow(section)`, `deleteRow(row)`, `restoreRow(row)`, `onSubmit(event)`, `importFromProduct()` — consumed by Task 5's template (already written) and posted as JSON for `SaveHandler` (Task 4) to validate/sanitize.

No automated test runner exists for this file; verification is `node --check` for syntax, full behavior in Task 8.

- [ ] **Step 1: Replace the rule factory (`makeRule`) with section/row factories**

In `assets/js/admin/dom-controller.js`, replace the `// ── Rule factory ──...` block (originally the `makeRule()` method, lines ~53-81) with:

```js
	// ── Section / row factories ────────────────────────────────────────────────

	/**
	 * Create a plain section object. _uid is a stable internal key used by
	 * x-for (:key); it is never serialised to the JSON payload sent to PHP.
	 *
	 * @param  {Object} [data]
	 * @return {Object}
	 */
	makeSection( data = {} ) {
		return Object.assign(
			{
				_uid:            ++this._uid,
				attribute:       '',
				attribute_label: '',
				status:          'active',
				rows:            [],
			},
			data
		);
	}

	/**
	 * Create a plain row object belonging to a section. `attribute` is a
	 * denormalized copy of the parent section's attribute, kept only so the
	 * existing Tom Select lifecycle methods below (initValueSelect,
	 * loadTermsForSelect) can stay unchanged — they were written against
	 * "rule" objects that carried their own attribute.
	 *
	 * @param  {string} attribute
	 * @param  {Object} [data]
	 * @return {Object}
	 */
	makeRow( attribute, data = {} ) {
		return Object.assign(
			{
				_uid:         ++this._uid,
				attribute,
				value_ids:    [],
				value_slugs:  [],
				value_labels: [],
				price:        '0',
				status:       'active',
			},
			data
		);
	}
```

- [ ] **Step 2: Add a new `restoreRow` method to the `DomController` class**

The existing `restoreRule(rule)` method is currently defined *inside* the Alpine component object returned from `register()` (not on `DomController` itself) — it will be deleted as part of Step 4's full replacement of `register()`. Here, add a new method directly on the `DomController` class (place it right after `loadTermsForSelect`, before `_clearNoValuesMsg`), so the Alpine component's new `restoreRow` (Step 4) can proxy to it the same way `initValueSelect` already does:

```js
	/**
	 * Reset a row's values/price to blank and reload its Tom Select for the
	 * row's (unchanged) attribute. Unlike the old restoreRule, attribute is
	 * never cleared here — it's owned by the parent section, not the row.
	 *
	 * @param {Object} row
	 */
	restoreRow( row ) {
		row.value_ids    = [];
		row.value_slugs  = [];
		row.value_labels = [];
		row.price        = '0';
		row.status       = 'active';

		const ts = this.getTomSelect( row._uid );
		if ( ts ) {
			this.loadTermsForSelect( ts, row.attribute );
		}
	}
```

Keep this as a method on `DomController` (same class as `initValueSelect`/`loadTermsForSelect`), in the same position in the file where `restoreRule` used to be.

- [ ] **Step 3: No separate edit needed for `onAttributeChange`**

Like `restoreRule`, the existing `onAttributeChange(rule, event)` method lives inside the Alpine component object in `register()`, not on `DomController`. It no longer applies — there is no per-row attribute `<select>` anymore — and it is removed automatically by Step 4's full replacement of `register()`. Nothing to do here; this step is a no-op called out only so the removal isn't mistaken for an oversight when diffing the file.

- [ ] **Step 4: Replace the entire `register()` method**

Replace the full body of `register()` (the `Alpine.data( 'rulesRepeater', ... )` call) with:

```js
	register() {
		const ctrl = this;

		Alpine.data( 'rulesRepeater', ( rulesData, attrsData ) => ( {

			sections: [],
			query:    '',
			sortDir:  null,   // null | 'asc' | 'desc'
			errorMsg: '',
			attrs:    attrsData || [],

			quickSetupProductId: null,
			quickSetupLoading:   false,
			quickSetupError:     '',

			// ── Lifecycle ─────────────────────────────────────────────────────

			init() {
				( rulesData || [] ).forEach( s => {
					const section = ctrl.makeSection( {
						attribute:       s.attribute       || '',
						attribute_label: s.attribute_label || '',
					} );
					( s.rows || [] ).forEach( r => {
						section.rows.push( ctrl.makeRow( section.attribute, {
							value_ids:    Array.isArray( r.value_ids )    ? r.value_ids.map( String )    : [],
							value_slugs:  Array.isArray( r.value_slugs )  ? r.value_slugs.map( String )  : [],
							value_labels: Array.isArray( r.value_labels ) ? r.value_labels.map( String ) : [],
							price:        r.price || '0',
						} ) );
					} );
					this.sections.push( section );
				} );

				// Hook WP's post form — it wraps the entire page, so
				// @submit on our meta-box div alone is not enough.
				const form = this.$el.closest( 'form' );
				if ( form ) {
					this._submitHandler = e => this.onSubmit( e );
					form.addEventListener( 'submit', this._submitHandler );
				}
			},

			destroy() {
				this.$el?.closest( 'form' )
					?.removeEventListener( 'submit', this._submitHandler );
				ctrl.destroyAll();
			},

			// ── Computed ──────────────────────────────────────────────────────

			get availableAttributes() {
				const used = new Set( this.sections.map( s => s.attribute ) );
				return this.attrs.filter( a => ! used.has( a.slug ) );
			},

			get activeSectionsCount() {
				return this.sections.filter( s => s.status !== 'deleted' ).length;
			},

			get displaySections() {
				const q = this.query.toLowerCase();
				let sections = this.sections.slice();

				if ( this.sortDir ) {
					sections.sort( ( a, b ) => {
						const la = ( a.attribute_label || a.attribute ).toLowerCase();
						const lb = ( b.attribute_label || b.attribute ).toLowerCase();
						return this.sortDir === 'asc'
							? la.localeCompare( lb )
							: lb.localeCompare( la );
					} );
				}

				return sections.map( section => {
					const isSectionDeleted = section.status === 'deleted';
					const labelMatches = ! q || ( section.attribute_label || section.attribute ).toLowerCase().includes( q );

					let pos = 0;
					const rows = section.rows.map( row => {
						const isRowDeleted = row.status === 'deleted';
						const rowMatches    = labelMatches || this._rowMatchesQuery( row, q );
						const inDom         = ! isSectionDeleted && ! isRowDeleted && rowMatches;
						return { row, inDom, pos: inDom ? ++pos : null };
					} );

					const visibleRowCount = rows.filter( r => r.inDom ).length;
					const sectionInDom    = ! isSectionDeleted && ( labelMatches || visibleRowCount > 0 );

					return { section, sectionInDom, rows };
				} );
			},

			get countLabel() {
				const q = this.query.toLowerCase();
				let n = 0;
				for ( const section of this.sections ) {
					if ( section.status === 'deleted' ) { continue; }
					const labelMatches = ! q || ( section.attribute_label || section.attribute ).toLowerCase().includes( q );
					for ( const row of section.rows ) {
						if ( row.status === 'deleted' ) { continue; }
						if ( labelMatches || this._rowMatchesQuery( row, q ) ) { n++; }
					}
				}
				return sprintf( prbpAdmin.i18n.rules_count, n );
			},

			// ── Sorting ───────────────────────────────────────────────────────

			toggleSort() {
				if ( this.sortDir === null )  { this.sortDir = 'asc';  return; }
				if ( this.sortDir === 'asc' ) { this.sortDir = 'desc'; return; }
				this.sortDir = null;
			},

			// ── Section CRUD ──────────────────────────────────────────────────

			addSection( event ) {
				const slug = event.target.value;
				if ( ! slug ) { return; }

				const attr    = this.attrs.find( a => a.slug === slug );
				const section = ctrl.makeSection( {
					attribute:       slug,
					attribute_label: attr ? attr.label : '',
				} );
				section.rows.push( ctrl.makeRow( slug ) );
				this.sections.push( section );

				event.target.value = '';
			},

			deleteSection( section ) {
				section.status = 'deleted';
			},

			restoreSection( section ) {
				section.status = 'active';
			},

			// ── Row CRUD ──────────────────────────────────────────────────────

			addRow( section ) {
				section.rows.push( ctrl.makeRow( section.attribute ) );
			},

			deleteRow( row ) {
				row.status = 'deleted';
			},

			restoreRow( row ) {
				ctrl.restoreRow( row );
			},

			// ── Tom Select init  (called from x-init in the template) ─────────

			initValueSelect( el, row ) {
				ctrl.initValueSelect( el, row );
			},

			initProductSelect( el ) {
				ctrl.initProductSelect( el, this );
			},

			productEditUrl( id ) {
				return prbpAdmin.product_edit_url + id + '&action=edit#product_attributes';
			},

			async importFromProduct() {
				if ( ! this.quickSetupProductId || this.quickSetupLoading ) { return; }
				this.quickSetupLoading = true;
				this.quickSetupError   = '';

				const attrs = await ctrl.loadProductAttributes( this.quickSetupProductId );

				if ( attrs === null ) {
					this.quickSetupError   = 'fetch_error';
					this.quickSetupLoading = false;
					return;
				}
				if ( attrs.length === 0 ) {
					this.quickSetupError   = 'no_attrs';
					this.quickSetupLoading = false;
					return;
				}

				try {
					const used = new Set( this.sections.map( s => s.attribute ) );
					attrs.forEach( attr => {
						if ( used.has( attr.slug ) ) { return; }
						const section = ctrl.makeSection( {
							attribute:       attr.slug,
							attribute_label: attr.label,
						} );
						section.rows.push( ctrl.makeRow( attr.slug, {
							value_ids:    attr.value_ids    || [],
							value_slugs:  attr.value_slugs  || [],
							value_labels: attr.value_labels || [],
							price:        '0',
						} ) );
						this.sections.push( section );
					} );
				} finally {
					this.quickSetupLoading = false;
				}
				// activeSectionsCount > 0 now — Quick Setup block hides reactively.
				// Each new row's x-init fires initValueSelect which loads and pre-selects value_ids.
			},

			// ── Form serialisation ────────────────────────────────────────────

			onSubmit( event ) {
				const payloadSections = [];

				for ( const section of this.sections ) {
					if ( section.status === 'deleted' ) { continue; }

					const sectionRows = [];
					const seenSlugs   = Object.create( null );
					let   duplicate   = null;

					for ( const row of section.rows ) {
						if ( row.status === 'deleted' ) { continue; }

						const ts          = ctrl.getTomSelect( row._uid );
						let   selectedIds = ts ? ts.getValue() : row.value_ids;
						if ( ! Array.isArray( selectedIds ) ) {
							selectedIds = selectedIds ? [ selectedIds ] : [];
						}

						const value_slugs  = selectedIds.map( id => ts?.options[ id ]?.slug ?? '' );
						const value_labels = selectedIds.map( id => ts?.options[ id ]?.name ?? '' );

						for ( let i = 0; i < value_slugs.length; i++ ) {
							const slug = value_slugs[ i ];
							if ( ! slug ) { continue; }
							if ( seenSlugs[ slug ] ) {
								duplicate = { label: value_labels[ i ] || slug };
								break;
							}
							seenSlugs[ slug ] = true;
						}
						if ( duplicate ) { break; }

						sectionRows.push( {
							value_ids:    selectedIds,
							value_slugs,
							value_labels,
							price:        row.price,
						} );
					}

					if ( duplicate ) {
						event.preventDefault();
						this.errorMsg = sprintf(
							prbpAdmin.i18n.duplicate_msg,
							section.attribute_label || section.attribute,
							duplicate.label
						);
						setTimeout( () => {
							this.$el?.querySelector( '.prbp-error-banner' )
								?.scrollIntoView( { behavior: 'smooth', block: 'center' } );
						}, 0 );
						return;
					}

					if ( sectionRows.length === 0 ) { continue; }

					payloadSections.push( {
						attribute:       section.attribute,
						attribute_label: section.attribute_label,
						rows:            sectionRows,
					} );
				}

				this.errorMsg = '';
				const jsonField = document.getElementById( 'prbp-rules-json' );
				if ( jsonField ) {
					jsonField.value = JSON.stringify( payloadSections );
				}
			},

			// ── Filter ────────────────────────────────────────────────────────

			_rowMatchesQuery( row, q ) {
				return row.value_labels.join( ' ' ).toLowerCase().includes( q );
			},

		} ) );
	}
```

- [ ] **Step 5: Syntax-check the file**

Run: `node --check "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/assets/js/admin/dom-controller.js"`
Expected: no output, exit code 0. (`dom-controller.js` is loaded as a classic script via `wp_enqueue_script`, not a module, but its syntax — class body, methods, template literals — is plain ES2017+ and `node --check` validates it correctly regardless of module type.)

- [ ] **Step 6: Run the full PHPUnit suite (regression check)**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit`
Expected: same 11 pre-existing `BasePriceSidebarTest` errors, zero other failures (this file has no PHP-side coupling, so this just confirms nothing else regressed).

- [ ] **Step 7: Commit**

```bash
cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce"
git add assets/js/admin/dom-controller.js
git commit -m "Rework admin rules Alpine component around attribute sections"
```

---

## Task 7: CSS for sections

**Files:**
- Modify: `/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce/assets/css/admin.css`

- [ ] **Step 1: Append new rules after the existing "Add Row button" block (after the `.prbp-admin-wrap .button.button-secondary:hover/:focus` rule, before end of file)**

```css
/* --------------------------------------------------------------------------
   Attribute sections
   -------------------------------------------------------------------------- */
.prbp-sections {
	display: flex;
	flex-direction: column;
	gap: 18px;
	margin-top: 15px;
}

.prbp-section {
	border: 1px solid #e0e0e0;
	border-radius: 3px;
	overflow: hidden;
}

.prbp-section-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	padding: 8px 12px;
	background: #f6f7f7;
	border-bottom: 2px solid #0D9488;
}

.prbp-section-title {
	margin: 0;
	font-size: 13px;
	font-weight: 600;
	color: #1d2327;
}

.prbp-section-table {
	margin: 0;
}

.prbp-section-table .prbp-col-value {
	width: 60%;
}

.prbp-add-row {
	margin: 0;
	padding: 8px 12px;
	border-top: 1px solid #e0e0e0;
}

.prbp-add-section {
	margin-top: 15px;
}

.prbp-add-section select {
	max-width: 280px;
}

/* Reset default <button> chrome — relies on .prbp-col-sortable (existing
   rule, not scoped to <th>) for the cursor/hover-color behavior. */
.prbp-sort-toggle {
	background: none;
	border: none;
	padding: 0;
	font: inherit;
	color: inherit;
}
```

- [ ] **Step 2: Visual check is deferred to Task 8 (no CSS linter is configured in this project).**

- [ ] **Step 3: Commit**

```bash
cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin/priceblueprint-for-woocommerce"
git add assets/css/admin.css
git commit -m "Add styles for attribute-section pricing rules layout"
```

---

## Task 8: End-to-end manual verification + final regression run

**Files:** none (verification only).

- [ ] **Step 1: Start the local WordPress environment**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && npm start`
Expected: output ending with a line like `WordPress development site started at http://localhost:8888` (and a tests site on another port). This uses the `.wp-env.json` at that directory, which installs latest WooCommerce and mounts `./priceblueprint-for-woocommerce` as a plugin.

- [ ] **Step 2: Log in and confirm the plugin + WooCommerce are active**

Open `http://localhost:8888/wp-admin/plugins.php` in a browser. Default `wp-env` credentials: username `admin`, password `password`. Confirm both "WooCommerce" and "PriceBlueprint — Configurable Product Pricing for WooCommerce" are listed as Active (activate them if not — wp-env normally auto-activates plugins listed in `.wp-env.json`). If WooCommerce's setup wizard appears, skip/dismiss it.

- [ ] **Step 3: Create two global attributes with terms**

Go to `http://localhost:8888/wp-admin/edit.php?post_type=product&page=product_attributes`. Create attribute "Color" (slug `color`) and "Size" (slug `size`). For each, click "Configure terms" and add: Color → Red, Blue, Green; Size → Small, Large.

- [ ] **Step 4: Create a new Price Blueprint with multiple sections and rows**

Go to `http://localhost:8888/wp-admin/post-new.php?post_type=price_blueprint`, give it a title (e.g. "Test Blueprint"). In the Pricing Rules box:
- Use the "+ Add attribute section" picker to add a Color section. Confirm the section header shows "Color" and there's one empty row.
- Add a value (Red) and a price (5.00) to that row.
- Click "+ Add value" inside the Color section, select Blue, set price 8.00.
- Use "+ Add attribute section" again to add a Size section; select Small, price 2.00.
- Confirm the "+ Add attribute section" dropdown no longer lists Color or Size (both used).
- Click "Sort by attribute" in the filter bar twice — confirm the Size and Color section order swaps (ascending, then descending), proving `toggleSort()`/`sortDir` still work via the new button trigger.
- Click Publish.

Expected: page reloads with no error banner; both sections persist with their rows after reload.

- [ ] **Step 5: Confirm the duplicate-value error banner**

In the Color section, click "+ Add value", select Red again (same value as the first row), and click Update. Expected: an error banner appears (mentioning Color and Red) and the save is blocked (page does not navigate away). Remove the duplicate row before continuing.

- [ ] **Step 6: Confirm delete/restore**

Click the trash icon on the Size section's only row. Confirm the row is struck through with a restore icon. Click restore — confirm the row returns to its editable state with empty value/price (not the prior Small/2.00, per the spec's restore behavior). Re-select Small and set price 2.00, then click the trash icon on the whole Size section header — confirm the entire section (header + table) disappears, with the "+ Add attribute section" dropdown now listing Size again as available. This is expected per the design (a deleted-but-unsaved section frees up its attribute slot immediately). Do not save in this deleted state — instead use the section restore icon area: since the section is fully hidden when deleted, to undo, refresh the page without saving (discards the in-progress delete) and confirm Size reappears with Small/2.00 intact, proving the delete was only an unsaved client-side state. Click Update to persist the final state (Color: Red 5.00, Blue 8.00; Size: Small 2.00).

- [ ] **Step 7: Confirm old-format migration**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && npm run wp -- post list --post_type=price_blueprint --field=ID`
Note the blueprint's post ID from the output (call it `<ID>`). Run:
`npm run wp -- post meta update <ID> prbp_template_rules '[{"attribute":"pa_color","attribute_label":"Color","value_ids":["1"],"value_slugs":["red"],"value_labels":["Red"],"price":"9.00","operator":"+"}]'`
This writes old-flat-format data directly, bypassing the admin editor. Reload the blueprint's edit screen in the browser. Expected: the editor renders one Color section with one row (Red, 9.00) — proving `RulesFormat::normalize()` migrates old data on read. Click Update without changing anything, then run:
`npm run wp -- post meta get <ID> prbp_template_rules`
Expected: the output is now in the new nested `{"attribute":...,"rows":[...]}` shape, proving the save path rewrites old data into the new format.

- [ ] **Step 8: Confirm Quick Setup still works**

Create a simple WooCommerce product with the Color and Size attributes assigned (some terms selected) at `http://localhost:8888/wp-admin/post-new.php?post_type=product`. Create a second, fresh Price Blueprint. In its Pricing Rules box, use "Generate rules from a product", search for and select that product, click Generate. Expected: one section per attribute the product has, each pre-filled with that attribute's selected values at price 0.00, and the Quick Setup panel disappears once sections exist.

- [ ] **Step 9: Confirm the front-end configurator and cart still work**

Assign the first test blueprint (from Step 6) to a WooCommerce product via that product's "PriceBlueprint" tab. Visit the product's front-end page (`http://localhost:8888/?p=<product_id>` or via the shop). Confirm: the "From $X" price shown matches base + cheapest addition per attribute; selecting Color=Blue and Size=Small updates the displayed total; adding to cart and viewing the cart page shows the selected Color/Size as line item meta with the correct total price.

- [ ] **Step 10: Final full regression run**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && vendor/bin/phpunit`
Expected: same 11 pre-existing `BasePriceSidebarTest` errors as the Global Constraints baseline, zero other failures.

- [ ] **Step 11: Stop the environment**

Run: `cd "/Users/edgarkhachaturov/Documents/Obsidian Vault/PriceBlueprint/plugin" && npm stop`

No commit in this task — it's verification only, and any test-data posts created live only in the local wp-env database (destroyed/reset on `npm run destroy` if ever needed, not run here).
