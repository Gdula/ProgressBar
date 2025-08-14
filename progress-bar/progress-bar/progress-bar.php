<?php
/*
 * Plugin Name:       Progress Bar – Dynamic Pricing & Discounts
 * Plugin URI:        https://example.com/progress-bar
 * Description:       Adds a dynamic progress bar to WooCommerce and provides flexible pricing & discount rules similar to popular pricing extensions.  Create product‑level, category, tag and cart‑based discount rules; support BOGO offers, role‑based pricing, date ranges and maximum discount limits.  Displays a visual progress indicator showing customers how much more they need to spend to unlock special promotions.
 * Version:           1.0.0
 * Author:            ProgressPromoBar Team
 * Author URI:        https://example.com
 * Text Domain:       progress-bar
 * Domain Path:       /languages
 * License:           GPL‑2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * The core plugin file is responsible for bootstrapping all other
 * components.  It defines global constants, loads the main loader class
 * and exposes option names for storing rule definitions.  To keep the
 * namespace clean, most functionality resides in the `includes/` folder.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define the absolute path to this plugin file for use in includes.
if ( ! defined( 'PROGRESS_BAR_PLUGIN_FILE' ) ) {
    define( 'PROGRESS_BAR_PLUGIN_FILE', __FILE__ );
}
// Also define the old constant for backward compatibility.
if ( ! defined( 'PROGRESS_PROMO_BAR_PLUGIN_FILE' ) ) {
    define( 'PROGRESS_PROMO_BAR_PLUGIN_FILE', PROGRESS_BAR_PLUGIN_FILE );
}

// Define option keys used to persist rules and settings for this project.
if ( ! class_exists( 'Progress_Bar' ) ) {
    /**
     * Placeholder class used to store global constants for option names.
     *
     * Other parts of the plugin continue to reference Progress_Promo_Bar
     * constants.  To maintain backwards compatibility while using the new
     * project name, we define those constants by extending this class
     * later in the file.
     */
    final class Progress_Bar {
        /**
         * Name of the option storing all discount rules.
         *
         * @var string
         */
        const RULES_OPTION = 'progress_bar_rules';
        /**
         * Name of the option storing the rule selection method.
         *
         * @var string
         */
        const RULE_SELECTION_METHOD_OPTION = 'progress_bar_rule_selection_method';
    }
}

// Define old constant names and classes as aliases of new ones.
if ( ! class_exists( 'Progress_Promo_Bar' ) ) {
    /**
     * Backwards compatibility wrapper.  Extends the new Progress_Bar class
     * so existing references to Progress_Promo_Bar::RULES_OPTION still
     * resolve to the updated option names.  All other functionality is
     * delegated to the loader and discount classes.
     */
    class Progress_Promo_Bar extends Progress_Bar {}
}

// Load the main loader class.  Note that the class name remains
// Progress_Promo_Bar_Loader to reduce the scope of this refactor.  The
// loader uses the defined constants for option names.
require_once dirname( __FILE__ ) . '/includes/class-progress-promo-bar.php';

// Kick things off.  The loader will register necessary hooks.
Progress_Promo_Bar_Loader::instance();
