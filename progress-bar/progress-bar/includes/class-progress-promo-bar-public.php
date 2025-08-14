<?php
/**
 * Front‑end functionality for Progress Promo Bar.
 *
 * Responsible for enqueuing public CSS/JS assets and providing hooks
 * required on the front end.  Most of the display logic for the progress
 * bar resides in the discount engine, however the public class ensures
 * styling is available on the cart and mini cart.  Additional front
 * end features (such as AJAX price updates or mini bar animations) can
 * be added here in future iterations.
 *
 * @package ProgressPromoBar\Public
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Progress_Promo_Bar_Public
 */
class Progress_Promo_Bar_Public {
    /**
     * Singleton instance.
     *
     * @var Progress_Promo_Bar_Public
     */
    private static $instance;

    /**
     * Retrieve the singleton instance.
     *
     * @return Progress_Promo_Bar_Public
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.  Registers front‑end hooks.
     */
    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );
    }

    /**
     * Enqueue public facing styles and scripts.
     */
    public function enqueue_public_assets() {
        wp_enqueue_style( 'progress-bar-public', plugins_url( 'assets/css/progress-promo-bar.css', PROGRESS_PROMO_BAR_PLUGIN_FILE ), [], Progress_Promo_Bar_Loader::VERSION );
    }
}
