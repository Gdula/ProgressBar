<?php
/**
 * Main plugin loader for Progress Promo Bar.
 *
 * This class orchestrates the loading of all other components in the plugin.
 * It registers hooks for both the admin and public areas of WordPress and
 * ensures that WooCommerce is available before proceeding.  Inspired by
 * the structure of the original `wc-dynamic-pricing-and-discounts`
 * extension, the goal of this loader is to provide a clear entry point
 * for future development and easier maintenance.
 *
 * @package ProgressPromoBar\Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Progress_Promo_Bar_Loader
 */
class Progress_Promo_Bar_Loader {

    /**
     * Single instance of the class.
     *
     * @var Progress_Promo_Bar_Loader
     */
    private static $instance;

    /**
     * Plugin version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Plugin base name (e.g. progress-promo-bar/progress-promo-bar.php).
     *
     * @var string
     */
    public $plugin_basename;

    /**
     * Initialize the plugin loader.
     *
     * The constructor is marked private so that the class must be accessed
     * through the `instance()` method.  This ensures a singleton pattern.
     */
    private function __construct() {
        $this->plugin_basename = plugin_basename( PROGRESS_PROMO_BAR_PLUGIN_FILE );
        // Load translated strings if available.
        add_action( 'init', [ $this, 'load_textdomain' ] );
        // Verify WooCommerce is active.
        add_action( 'plugins_loaded', [ $this, 'check_woocommerce_dependency' ] );
        // Initialize admin and public hooks when appropriate.
        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    /**
     * Retrieve or create the singleton instance of the loader.
     *
     * @return Progress_Promo_Bar_Loader
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load plugin textdomain for localisation.
     */
    public function load_textdomain() {
        // Load translations.  Because this project has been renamed to
        // Progress Bar, use the new text domain `progress-bar` instead
        // of the old `progress-promo-bar`.  The language files should be
        // placed in the `languages` directory of the plugin.
        load_plugin_textdomain( 'progress-bar', false, dirname( $this->plugin_basename ) . '/languages' );
    }

    /**
     * Verify that WooCommerce is active.  If it is not, register an admin
     * notice and bail early to avoid fatal errors.  Since most of our
     * functionality depends on WooCommerce hooks, there is no reason to
     * continue loading the rest of the plugin if WooCommerce is missing.
     */
    public function check_woocommerce_dependency() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Progress Bar requires WooCommerce to be installed and active.', 'progress-bar' ) . '</p></div>';
            } );
        }
    }

    /**
     * Initialise plugin components.
     *
     * This method is called on the `plugins_loaded` action.  It will
     * instantiate admin and public classes only after verifying
     * WooCommerce is available.  The separation between admin and public
     * classes makes it easier to develop each side independently.
     */
    public function init_plugin() {
        // Bail if WooCommerce isn't active.
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Always load the core discount engine.  This class handles the
        // storage and evaluation of discount rules and can be used in both
        // admin and public contexts.
        require_once dirname( PROGRESS_PROMO_BAR_PLUGIN_FILE ) . '/includes/class-progress-promo-bar-discounts.php';

        // Instantiate the discount engine.
        Progress_Promo_Bar_Discounts::instance();

        // Load admin area hooks only when in the dashboard.
        if ( is_admin() ) {
            require_once dirname( PROGRESS_PROMO_BAR_PLUGIN_FILE ) . '/includes/class-progress-promo-bar-admin.php';
            Progress_Promo_Bar_Admin::instance();
        }

        // Load public area hooks on the front end.
        require_once dirname( PROGRESS_PROMO_BAR_PLUGIN_FILE ) . '/includes/class-progress-promo-bar-public.php';
        Progress_Promo_Bar_Public::instance();
    }
}
