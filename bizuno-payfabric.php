<?php
/**
 * Plugin Name:       Bizuno PayFabric Plugin
 * Description:       PayFabric and Purchase Order plugins for WooCommerce
 * Version:           2.0
 * Requires at least: 6.5
 * Tested up to:      6.9
 * Requires PHP:      8.0
 * Author:            PhreeSoft, Inc./Global Payments
 * Author URI:        https://www.phreesoft.com
 * Author Email:      support@phreesoft.com
 * Text Domain:       bizuno-payfabric
 * Domain Path:       /locale
 * License:           AGPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.txt
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class bizuno_payfabric
{
    public function __construct()
    {
        // WordPress Actions
        add_action ( 'plugins_loaded', [ $this, 'ps_plugins_loaded' ] );
    }
    public function ps_plugins_loaded()
    {
        if ( ! is_plugin_active ( 'woocommerce/woocommerce.php' ) ) { return; }
        // Load Woocommerce plugins only if WooCommerce is installed and active
        require_once ( dirname( __FILE__ ) . '/plugins/payment-payfabric/payment-payfabric.php' );
        require_once ( dirname( __FILE__ ) . '/plugins/payment-purchase-order.php' );
        WC()->frontend_includes();
        if ( class_exists ( 'WC_Payment_Gateway' ) ) { // get instance of WooCommerce for Payfabric
            require ( plugin_dir_path ( __FILE__ ) . 'plugins/payment-payfabric/classes/class-payfabric-gateway-woocommerce.php' );
            Payfabric_Gateway_Woocommerce::get_instance();
        }
    }
}
new bizuno_payfabric();
