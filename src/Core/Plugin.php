<?php
/**
 * Core Plugin bootstrap singleton.
 *
 * @package PRBP\Core
 */

namespace PRBP\Core;

use PRBP\CPT\Blueprint;
use PRBP\ProductType\ConfigurableProduct;
use PRBP\Admin\AttributeSync;
use PRBP\Admin\HelpTab;
use PRBP\Admin\ProductMetaBox;
use PRBP\Admin\RulesRepeater;
use PRBP\Admin\SaveHandler;
use PRBP\Admin\WelcomeScreen;
use PRBP\Frontend\ProductPage;
use PRBP\Frontend\StructuredData;
use PRBP\Ajax\GetTerms;
use PRBP\Ajax\CalculatePrice;
use PRBP\Ajax\QuickSetup;
use PRBP\Ajax\ImportDemo;
use PRBP\Cart\CartItemMeta;
use PRBP\Cart\PriceRecalculator;
use PRBP\Cart\OrderMetaDisplay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	private static ?Plugin $instance = null;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		I18n::register();
		Blueprint::register();
		ConfigurableProduct::register();
		AttributeSync::register();
		HelpTab::register();
		WelcomeScreen::register();
		ProductMetaBox::register();
		RulesRepeater::register();
		SaveHandler::register();
		ProductPage::register();
		StructuredData::register();
		GetTerms::register();
		CalculatePrice::register();
		QuickSetup::register();
		ImportDemo::register();
		CartItemMeta::register();
		PriceRecalculator::register();
		OrderMetaDisplay::register();
	}
}
