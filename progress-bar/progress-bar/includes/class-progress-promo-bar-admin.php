<?php
/**
 * Admin functionality for Progress Promo Bar.
 *
 * Handles creation of the WooCommerce submenu page, rendering of the
 * configuration interface and sanitisation of input values.  This
 * implementation focuses on the structure of the page and the overall
 * workflow rather than perfect parity with the reference plugin.  The
 * layout uses tabs for different rule contexts (product pricing, cart
 * discounts and combinations) and supports adding multiple rules
 * dynamically via JavaScript.  Each rule captures its own conditions
 * and discount parameters.
 *
 * @package ProgressPromoBar\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Progress_Promo_Bar_Admin
 */
class Progress_Promo_Bar_Admin {
    /**
     * Singleton instance.
     *
     * @var Progress_Promo_Bar_Admin
     */
    private static $instance;

    /**
     * Option group used for settings API.
     *
     * @var string
     */
    private $option_group = 'progress_promo_bar_options';

    /**
     * Retrieve or create the singleton instance.
     *
     * @return Progress_Promo_Bar_Admin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.  Registers admin hooks.
     */
    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Register the WooCommerce submenu for our plugin.
     */
    public function register_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Progress Bar', 'progress-bar' ),
            __( 'Progress Bar', 'progress-bar' ),
            'manage_woocommerce',
            'progress-bar',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Register plugin settings using WordPress Settings API.
     */
    public function register_settings() {
        register_setting( $this->option_group, Progress_Bar::RULES_OPTION );
        register_setting( $this->option_group, Progress_Bar::RULE_SELECTION_METHOD_OPTION );
    }

    /**
     * Enqueue admin‑side styles and scripts.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only enqueue on our own page.
        if ( 'woocommerce_page_progress-bar' !== $hook ) {
            return;
        }
        // Basic styling.
        wp_enqueue_style( 'progress-bar-admin', plugins_url( 'assets/css/progress-promo-bar.css', PROGRESS_PROMO_BAR_PLUGIN_FILE ), [], Progress_Promo_Bar_Loader::VERSION );
        // jQuery for dynamic rule duplication.
        wp_enqueue_script( 'progress-bar-admin', plugins_url( 'assets/js/progress-promo-bar-admin.js', PROGRESS_PROMO_BAR_PLUGIN_FILE ), [ 'jquery' ], Progress_Promo_Bar_Loader::VERSION, true );
    }

    /**
     * Render the plugin configuration page.
     */
    public function render_admin_page() {
        // Fetch existing rules and selection method.
        $rules     = get_option( Progress_Promo_Bar::RULES_OPTION, [] );
        $selection = get_option( Progress_Promo_Bar::RULE_SELECTION_METHOD_OPTION, 'apply_all' );
        if ( ! is_array( $rules ) ) {
            $rules = [];
        }
        // Determine active tab.
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'product_pricing';
        $tabs = [
            'product_pricing' => __( 'Product Pricing', 'progress-bar' ),
            'cart_discounts'  => __( 'Cart Discounts', 'progress-bar' ),
            'combination'     => __( 'Combination', 'progress-bar' ),
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Progress Bar – Discount Rules', 'progress-bar' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=progress-bar&tab=' . $tab_key ) ); ?>" class="nav-tab <?php echo ( $current_tab === $tab_key ) ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_label ); ?></a>
                <?php endforeach; ?>
            </h2>
            <form method="post" action="">
                <?php settings_fields( $this->option_group ); ?>
                <?php // Global rule selection method. ?>
                <h2><?php esc_html_e( 'Rule Selection Method', 'progress-bar' ); ?></h2>
                <select name="rule_selection_method">
                    <option value="apply_all" <?php selected( $selection, 'apply_all' ); ?>><?php esc_html_e( 'Apply all applicable rules', 'progress-bar' ); ?></option>
                    <option value="first_applicable" <?php selected( $selection, 'first_applicable' ); ?>><?php esc_html_e( 'Apply first applicable rule', 'progress-bar' ); ?></option>
                    <option value="smaller_price" <?php selected( $selection, 'smaller_price' ); ?>><?php esc_html_e( 'Apply rule resulting in smaller price', 'progress-bar' ); ?></option>
                    <option value="bigger_price" <?php selected( $selection, 'bigger_price' ); ?>><?php esc_html_e( 'Apply rule resulting in bigger price', 'progress-bar' ); ?></option>
                </select>
                <p class="description"><?php esc_html_e( 'Select how pricing rules should be resolved when multiple rules match.', 'progress-bar' ); ?></p>

                <?php // Table of rules. ?>
                <h2><?php esc_html_e( 'Rules', 'progress-bar' ); ?></h2>
                <table class="widefat fixed" id="progress-promo-bar-rules">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Method', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Objects (IDs)', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Discount Type', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Value', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Max', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'BOGO (Free ID, Buy X, Get Y)', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Priority', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Enabled', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Exclusivity', 'progress-bar' ); ?></th>
                            <th><?php esc_html_e( 'Remove', 'progress-bar' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $rules ) ) : ?>
                            <?php foreach ( $rules as $index => $rule ) : ?>
                                <?php $this->render_rule_row( $index, $rule ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="12"><button type="button" class="button" id="progress-promo-bar-add-rule"><?php esc_html_e( 'Add Rule', 'progress-bar' ); ?></button></td>
                        </tr>
                    </tfoot>
                </table>
                <?php submit_button( __( 'Save Rules', 'progress-bar' ), 'primary', 'progress_promo_bar_save' ); ?>
            </form>
        </div>
        <?php
        // Render a hidden template row for JS cloning.
        $this->render_rule_row( '__INDEX__', [] );
    }

    /**
     * Output a single rule table row.
     *
     * @param int|string $index Row index.  When `__INDEX__`, a hidden
     *                           template row is generated for JS cloning.
     * @param array      $rule  Rule data.
     */
    private function render_rule_row( $index, $rule ) {
        $is_template = '__INDEX__' === $index;
        $style       = $is_template ? 'display:none;' : '';
        $name        = $rule['name'] ?? '';
        $type        = $rule['type'] ?? 'product';
        $method      = $rule['method'] ?? 'simple_adjustment';
        $objects     = isset( $rule['objects'] ) ? implode( ',', (array) $rule['objects'] ) : '';
        $discount    = $rule['discount'] ?? [];
        $discount_type  = $discount['type'] ?? 'percent';
        $discount_val   = $discount['value'] ?? '';
        $discount_max   = $discount['max'] ?? '';
        $bogo_free      = $discount['bogo_free_product'] ?? '';
        $bogo_buy       = $discount['bogo_buy_qty'] ?? '';
        $bogo_get       = $discount['bogo_get_qty'] ?? '';
        $priority    = $rule['priority'] ?? 10;
        $enabled     = ! empty( $rule['enabled'] );
        $exclusivity = $rule['exclusivity'] ?? 'non_exclusive';
        ?>
        <tr class="progress-promo-bar-rule" style="<?php echo esc_attr( $style ); ?>">
            <td>
                <input type="text" name="rules[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" />
            </td>
            <td>
                <select name="rules[<?php echo esc_attr( $index ); ?>][type]">
                    <option value="product" <?php selected( $type, 'product' ); ?>><?php esc_html_e( 'Product', 'progress-bar' ); ?></option>
                    <option value="category" <?php selected( $type, 'category' ); ?>><?php esc_html_e( 'Category', 'progress-bar' ); ?></option>
                    <option value="tag" <?php selected( $type, 'tag' ); ?>><?php esc_html_e( 'Tag', 'progress-bar' ); ?></option>
                    <option value="cart" <?php selected( $type, 'cart' ); ?>><?php esc_html_e( 'Cart', 'progress-bar' ); ?></option>
                    <option value="combination" <?php selected( $type, 'combination' ); ?>><?php esc_html_e( 'Combination', 'progress-bar' ); ?></option>
                </select>
            </td>
            <td>
                <select name="rules[<?php echo esc_attr( $index ); ?>][method]" class="ppb-method-select">
                    <optgroup label="Simple">
                        <option value="simple_adjustment" <?php selected( $method, 'simple_adjustment' ); ?>><?php esc_html_e( 'Simple adjustment', 'progress-bar' ); ?></option>
                    </optgroup>
                    <optgroup label="Volume">
                        <option value="bulk_pricing" <?php selected( $method, 'bulk_pricing' ); ?>><?php esc_html_e( 'Bulk pricing', 'progress-bar' ); ?></option>
                        <option value="tiered_pricing" <?php selected( $method, 'tiered_pricing' ); ?>><?php esc_html_e( 'Tiered pricing', 'progress-bar' ); ?></option>
                    </optgroup>
                    <optgroup label="Group">
                        <option value="group_of_products" <?php selected( $method, 'group_of_products' ); ?>><?php esc_html_e( 'Group of products', 'progress-bar' ); ?></option>
                        <option value="group_of_products_repeating" <?php selected( $method, 'group_of_products_repeating' ); ?>><?php esc_html_e( 'Group of products – Repeating', 'progress-bar' ); ?></option>
                    </optgroup>
                    <optgroup label="BOGO">
                        <option value="buy_x_get_y" <?php selected( $method, 'buy_x_get_y' ); ?>><?php esc_html_e( 'Buy x get y', 'progress-bar' ); ?></option>
                        <option value="buy_x_get_y_repeating" <?php selected( $method, 'buy_x_get_y_repeating' ); ?>><?php esc_html_e( 'Buy x get y – Repeating', 'progress-bar' ); ?></option>
                    </optgroup>
                    <optgroup label="Other">
                        <option value="exclude_matched" <?php selected( $method, 'exclude_matched' ); ?>><?php esc_html_e( 'Exclude matched items from other rules', 'progress-bar' ); ?></option>
                    </optgroup>
                </select>
            </td>
            <td>
                <input type="text" name="rules[<?php echo esc_attr( $index ); ?>][objects]" value="<?php echo esc_attr( $objects ); ?>" placeholder="IDs separated by comma" />
            </td>
            <td>
                <select name="rules[<?php echo esc_attr( $index ); ?>][discount_type]">
                    <option value="percent" <?php selected( $discount_type, 'percent' ); ?>><?php esc_html_e( 'Percent', 'progress-bar' ); ?></option>
                    <option value="fixed" <?php selected( $discount_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed', 'progress-bar' ); ?></option>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" name="rules[<?php echo esc_attr( $index ); ?>][discount_value]" value="<?php echo esc_attr( $discount_val ); ?>" />
            </td>
            <td>
                <input type="number" step="0.01" name="rules[<?php echo esc_attr( $index ); ?>][discount_max]" value="<?php echo esc_attr( $discount_max ); ?>" />
            </td>
            <td>
                <input type="text" size="10" name="rules[<?php echo esc_attr( $index ); ?>][bogo_free_product]" value="<?php echo esc_attr( $bogo_free ); ?>" placeholder="Free product ID" />
                <input type="number" min="1" name="rules[<?php echo esc_attr( $index ); ?>][bogo_buy_qty]" value="<?php echo esc_attr( $bogo_buy ); ?>" placeholder="Buy" style="width:45px;" />
                <input type="number" min="1" name="rules[<?php echo esc_attr( $index ); ?>][bogo_get_qty]" value="<?php echo esc_attr( $bogo_get ); ?>" placeholder="Get" style="width:45px;" />
            </td>
            <td>
                <input type="number" name="rules[<?php echo esc_attr( $index ); ?>][priority]" value="<?php echo esc_attr( $priority ); ?>" />
            </td>
            <td>
                <label><input type="checkbox" name="rules[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> /> <?php esc_html_e( 'Yes', 'progress-bar' ); ?></label>
            </td>
            <td>
                <select name="rules[<?php echo esc_attr( $index ); ?>][exclusivity]">
                    <option value="non_exclusive" <?php selected( $exclusivity, 'non_exclusive' ); ?>><?php esc_html_e( 'Non‑exclusive', 'progress-bar' ); ?></option>
                    <option value="exclusive" <?php selected( $exclusivity, 'exclusive' ); ?>><?php esc_html_e( 'Exclusive: apply this rule only', 'progress-bar' ); ?></option>
                    <option value="exclusive_if_others_not_applicable" <?php selected( $exclusivity, 'exclusive_if_others_not_applicable' ); ?>><?php esc_html_e( 'Exclusive if others not applicable', 'progress-bar' ); ?></option>
                </select>
            </td>
            <td>
                <button type="button" class="button-link delete-rule"><?php esc_html_e( 'Delete', 'progress-bar' ); ?></button>
            </td>
        </tr>
        <?php
    }
}
