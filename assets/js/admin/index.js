/**
 * PriceBlueprint Admin — entry point.
 *
 * Imports the two controllers and wires them together inside the alpine:init
 * event, guaranteeing the component factory is registered before Alpine
 * processes the DOM.
 *
 * Load order (all deferred / module-deferred, in dependency order):
 *   1. tom-select.min.js   — TomSelect global
 *   2. index.js (module)   — imports controllers, registers alpine:init listener
 *   3. alpine.min.js       — fires alpine:init, then processes the DOM
 *
 * @module index
 * @package PriceBlueprint
 */

import { RequestsController } from './requests-controller.js';
import { DomController }      from './dom-controller.js';

document.addEventListener( 'alpine:init', () => {
	const requests = new RequestsController();
	const dom      = new DomController( requests );
	dom.register();
} );
