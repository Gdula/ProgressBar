<?php
/**
 * Core discount engine for Progress Promo Bar.
 *
 * This class encapsulates the storage, retrieval and evaluation of
 * pricing and discount rules.  It is responsible for applying discounts
 * to product prices, cart totals and handling Buy‑X‑Get‑Y (BOGO) logic.
 * While the implementation here is deliberately lightweight, it is
 * designed to be extended.  Each rule consists of a type, method,
 * conditions and discount parameters.  Admin settings are loaded via
 * the option keys defined in the main plugin file.
 *
 * @package ProgressPromoBar\Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Progress_Promo_Bar_Discounts
 */
class Progress_Promo_Bar_Discounts {

    /**
     * Singleton instance.
     *
     * @var Progress_Promo_Bar_Discounts
     */
    private static $instance;

    /**
     * Fetch or create the singleton instance.
     *
     * @return Progress_Promo_Bar_Discounts
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register the required hooks.
     */
    private function __construct() {
        // Modify product prices.
        add_filter( 'woocommerce_product_get_price', [ $this, 'filter_product_price' ], 10, 2 );
        add_filter( 'woocommerce_product_variation_get_price', [ $this, 'filter_product_price' ], 10, 2 );
        // Apply cart level discounts and fees.
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_cart_discounts' ], 10, 1 );
        // Handle Buy X Get Y logic before totals calculation.
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_bogo_rules' ], 10, 1 );
        // Display a progress bar on the cart/mini cart.
        add_action( 'woocommerce_before_cart', [ $this, 'print_progress_bar' ] );
        add_action( 'woocommerce_widget_shopping_cart_before_buttons', [ $this, 'print_progress_bar' ] );
    }

    /**
     * Retrieve all stored rules from the database.
     *
     * @return array
     */
    public function get_rules() {
        $rules = get_option( Progress_Promo_Bar::RULES_OPTION, [] );
        if ( ! is_array( $rules ) ) {
            $rules = [];
        }
        return $rules;
    }

    /**
     * Retrieve the rule selection method.
     *
     * @return string
     */
    public function get_rule_selection_method() {
        $method = get_option( Progress_Promo_Bar::RULE_SELECTION_METHOD_OPTION, 'apply_all' );
        return $method ?: 'apply_all';
    }

    /**
     * Filter a product's price based on applicable rules.
     *
     * This method runs through all stored rules and applies pricing
     * adjustments on the fly.  The logic here is simplified: it applies
     * percentage or fixed discounts for product, category or tag types.
     * BOGO rules are handled separately by `apply_bogo_rules()`.
     *
     * @param float     $price  The original price.
     * @param WC_Product $product The WooCommerce product.
     * @return float    The modified price.
     */
    public function filter_product_price( $price, $product ) {
        // Only adjust price on the front end (not in admin or when editing products).
        if ( is_admin() ) {
            return $price;
        }
        $rules = $this->get_rules();
        if ( empty( $rules ) ) {
            return $price;
        }
        $selection = $this->get_rule_selection_method();
        $applied_prices = [];
        foreach ( $rules as $rule ) {
            if ( ! $this->is_rule_applicable_to_product( $rule, $product ) ) {
                continue;
            }
            // Skip disabled rules.
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }
            // Calculate discounted price.
            $discounted = $this->calculate_discounted_price( $price, $rule );
            if ( null !== $discounted ) {
                $applied_prices[] = $discounted;
                // If rule selection is to apply only the first matching rule, break.
                if ( 'first_applicable' === $selection ) {
                    break;
                }
            }
        }
        if ( empty( $applied_prices ) ) {
            return $price;
        }
        // Determine which result to return based on selection method.
        switch ( $selection ) {
            case 'smaller_price':
                return min( $applied_prices );
            case 'bigger_price':
                return max( $applied_prices );
            case 'apply_all':
            default:
                // When applying all rules, apply the largest discount (i.e. lowest price).
                return min( $applied_prices );
        }
    }

    /**
     * Determine whether a rule applies to the given product.
     *
     * Currently this method supports simple matching based on product ID and
     * category IDs.  Further conditions (customer role, date/time, etc.)
     * should be added here to mirror the full functionality of the
     * reference plugin.
     *
     * @param array     $rule    The rule definition.
     * @param WC_Product $product The product being priced.
     * @return bool
     */
    public function is_rule_applicable_to_product( $rule, $product ) {
        $type    = $rule['type'] ?? 'product';
        $objects = $rule['objects'] ?? [];
        // If no objects specified, the rule applies to all products.
        if ( empty( $objects ) ) {
            return true;
        }
        if ( 'product' === $type && in_array( $product->get_id(), $objects, true ) ) {
            return true;
        }
        if ( 'category' === $type ) {
            $product_terms = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'ids' ] );
            return (bool) array_intersect( $objects, $product_terms );
        }
        if ( 'tag' === $type ) {
            $product_terms = wp_get_post_terms( $product->get_id(), 'product_tag', [ 'fields' => 'ids' ] );
            return (bool) array_intersect( $objects, $product_terms );
        }
        // Unknown type; treat as non-applicable.
        return false;
    }

    /**
     * Calculate the price after applying a discount rule.
     *
     * Supports percentage and fixed price discounts.  For percentage
     * discounts, the value is treated as a percent to subtract from the
     * original price.  For fixed adjustments, the discount value is
     * subtracted directly.  A maximum discount amount can be enforced.
     *
     * @param float $price Original product price.
     * @param array $rule  Rule definition array.
     * @return float|null Modified price or null if no discount.
     */
    protected function calculate_discounted_price( $price, $rule ) {
        $discount = $rule['discount'] ?? [];
        $type     = $discount['type'] ?? 'percent';
        $value    = isset( $discount['value'] ) ? floatval( $discount['value'] ) : 0;
        $max      = isset( $discount['max'] ) ? floatval( $discount['max'] ) : 0;
        if ( $value <= 0 ) {
            return null;
        }
        if ( 'fixed' === $type ) {
            $discount_amount = $value;
        } else {
            // Percent by default.
            $discount_amount = ( $value / 100 ) * $price;
        }
        // Apply maximum discount limit if set and positive.
        if ( $max > 0 ) {
            $discount_amount = min( $discount_amount, $max );
        }
        $new_price = max( 0, $price - $discount_amount );
        return $new_price;
    }

    /**
     * Apply cart level discounts for rules of type `cart`.
     *
     * This method inspects the cart subtotal and applies a fee (negative
     * number) as a discount when the conditions of a rule are met.  At
     * present, only simple subtotal thresholds are implemented.  More
     * complex conditions like customer roles or purchase history can be
     * added analogously.
     *
     * @param WC_Cart $cart WooCommerce cart instance.
     */
    public function apply_cart_discounts( $cart ) {
        $rules = $this->get_rules();
        if ( empty( $rules ) ) {
            return;
        }
        foreach ( $rules as $rule ) {
            if ( 'cart' !== ( $rule['type'] ?? '' ) ) {
                continue;
            }
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }
            $discount = $rule['discount'] ?? [];
            $threshold = isset( $rule['condition']['min_price'] ) ? floatval( $rule['condition']['min_price'] ) : 0;
            if ( $cart->subtotal >= $threshold ) {
                $discount_amount = 0;
                if ( 'percent' === ( $discount['type'] ?? 'percent' ) ) {
                    $discount_amount = ( floatval( $discount['value'] ) / 100 ) * $cart->subtotal;
                } else {
                    $discount_amount = floatval( $discount['value'] );
                }
                // Apply maximum limit if set.
                if ( isset( $discount['max'] ) && $discount['max'] > 0 ) {
                    $discount_amount = min( $discount_amount, floatval( $discount['max'] ) );
                }
                $label = ! empty( $rule['name'] ) ? $rule['name'] : __( 'Cart Discount', 'progress-bar' );
                $cart->add_fee( $label, -1 * $discount_amount );
            }
        }
    }

    /**
     * Apply Buy‑X‑Get‑Y (BOGO) rules by adding free products or adjusting
     * quantities on the cart before totals are calculated.
     *
     * For each BOGO rule that matches a product in the cart, we calculate
     * how many free items to add.  This implementation only supports a
     * single free product per rule and ignores complex exclusivity logic.
     *
     * @param WC_Cart $cart WooCommerce cart instance.
     */
    public function apply_bogo_rules( $cart ) {
        $rules = $this->get_rules();
        if ( empty( $rules ) ) {
            return;
        }
        foreach ( $rules as $rule ) {
            $discount = $rule['discount'] ?? [];
            // Only process BOGO rules.
            if ( ! isset( $discount['bogo_free_product'] ) || $discount['bogo_free_product'] <= 0 ) {
                continue;
            }
            // Ensure rule is enabled and applicable to products in cart.
            if ( empty( $rule['enabled'] ) || 'product' !== ( $rule['type'] ?? 'product' ) ) {
                continue;
            }
            $buy_qty  = max( 1, intval( $discount['bogo_buy_qty'] ?? 1 ) );
            $get_qty  = max( 1, intval( $discount['bogo_get_qty'] ?? 1 ) );
            $free_pid = absint( $discount['bogo_free_product'] );
            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                /** @var WC_Product $prod */
                $prod = $cart_item['data'];
                if ( ! $this->is_rule_applicable_to_product( $rule, $prod ) ) {
                    continue;
                }
                $quantity = $cart_item['quantity'];
                $eligible_groups = intdiv( $quantity, $buy_qty );
                if ( $eligible_groups <= 0 ) {
                    continue;
                }
                $free_items_to_add = $eligible_groups * $get_qty;
                // Add free product to cart.  Use negative price to simulate free item.
                $cart->add_to_cart( $free_pid, $free_items_to_add, 0, [], [ 'progress_promo_bar_bogo' => true ] );
            }
        }
    }

    /**
     * Render a progress bar informing the customer how much more they need
     * to spend to reach the next discount threshold.
     *
     * This is a simple implementation that looks at cart rules with a
     * `min_price` condition and calculates the minimum remaining amount.
     * In future versions, multiple thresholds could be shown and styled
     * according to the example in the CodeCanyon plugin.
     */
    public function print_progress_bar() {
        if ( is_admin() ) {
            return;
        }
        $rules = $this->get_rules();
        if ( empty( $rules ) ) {
            return;
        }
        $cart = WC()->cart;
        if ( ! $cart ) {
            return;
        }
        $subtotal = $cart->subtotal;
        $thresholds = [];
        foreach ( $rules as $rule ) {
            if ( 'cart' === ( $rule['type'] ?? '' ) ) {
                $min = isset( $rule['condition']['min_price'] ) ? floatval( $rule['condition']['min_price'] ) : 0;
                if ( $min > 0 ) {
                    $thresholds[] = $min;
                }
            }
        }
        if ( empty( $thresholds ) ) {
            return;
        }
        sort( $thresholds );
        $next = null;
        foreach ( $thresholds as $threshold ) {
            if ( $subtotal < $threshold ) {
                $next = $threshold;
                break;
            }
        }
        if ( null === $next ) {
            return;
        }
        $remaining = max( 0, $next - $subtotal );
        echo '<div class="progress-promo-bar">
            <p>' . sprintf( esc_html__( 'Spend %s more to unlock your next discount!', 'progress-bar' ), wc_price( $remaining ) ) . '</p>
        </div>';
    }
}
