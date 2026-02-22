<?php
/**
 * Plugin Name:       Bizuno PayFabric Plugin
 * Description:       PayFabric and Bizuno authorized Purchase Order payment plugins for WooCommerce
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


/*Define live and test gateway host */
!defined('LIVEGATEWAY') && define('LIVEGATEWAY', 'https://www.payfabric.com');
!defined('TESTGATEWAY') && define('TESTGATEWAY', 'https://sandbox.payfabric.com');

/*
* Define log dir, severity level of logging mode and whether enable on-screen debug ouput.
* PLEASE DO NOT USE "DEBUG" LOGGING MODE IN PRODUCTION
*/
!defined('PayFabric_LOG_SEVERITY') && define('PayFabric_LOG_SEVERITY', 'INFO');
!defined('PayFabric_LOG_DIR')      && define('PayFabric_LOG_DIR', dirname(__FILE__) . '/logs');
!defined('PayFabric_DEBUG')        && define('PayFabric_DEBUG', false);

class bizuno_payfabric
{
    public function __construct()
    {
        // Actions
        add_action ( 'plugins_loaded',                                    [ $this, 'bizuno_payfabric_plugins_loaded' ] );
        add_action ( 'woocommerce_checkout_process',                      [ $this, 'bizuno_payfabric_payment' ] );
        add_action ( 'woocommerce_checkout_update_order_meta',            [ $this, 'bizuno_payfabric_update_order_meta' ] );
        add_action ( 'woocommerce_admin_order_data_after_billing_address',[ $this, 'bizuno_payfabric_order_meta' ], 10, 1 );
        // Filters
        add_filter ( 'woocommerce_payment_gateways',                      [ $this, 'bizuno_payfabric_add_to_gateways' ] );
        add_filter ( 'woocommerce_available_payment_gateways',            [ $this, 'bizuno_api_disable_purchorder' ], 99, 1);
    }

    public function bizuno_payfabric_plugins_loaded()
    {
        if ( ! is_plugin_active ( 'woocommerce/woocommerce.php' ) ) { return; }
        require ( plugin_dir_path ( __FILE__ ) . 'purchase_order.php' );
//require ( plugin_dir_path ( __FILE__ ) . 'payment-payfabric/admin/class-payfabric-gateway.php' );
        WC()->frontend_includes();
        if ( class_exists ( 'WC_Payment_Gateway' ) ) { // get instance of WooCommerce for Payfabric
            require ( plugin_dir_path ( __FILE__ ) . 'plugins/payment-payfabric/classes/class-payfabric-gateway-woocommerce.php' );
            Payfabric_Gateway_Woocommerce::get_instance();
        }
    }

    public function bizuno_payfabric_add_to_gateways( $gateways )
    {
        $gateways[] = 'WC_Gateway_PayFabric';
        $gateways[] = 'WC_Gateway_PurchOrder';
        return $gateways;
    }
    
    public function bizuno_payfabric_payment()
    {
        if($_POST['payment_method'] != 'custom') { return; }
        if( !isset($_POST['mobile']) || empty($_POST['mobile']) )           { wc_add_notice( __( 'Please add your mobile number', 'bizuno-payfabric' ), 'error' ); }
        if( !isset($_POST['transaction']) || empty($_POST['transaction']) ) { wc_add_notice( __( 'Please add your transaction ID', 'bizuno-payfabric' ), 'error' ); }
    }

    public function bizuno_payfabric_update_order_meta( $order_id ) // Update the order meta with field value
    { 
        if($_POST['payment_method'] != 'custom') { return; }
        update_post_meta( $order_id, 'mobile', $_POST['mobile'] );
        update_post_meta( $order_id, 'transaction', $_POST['transaction'] );
    }

    public function bizuno_payfabric_order_meta( $order ) {
        // Safety check: Ensure $order is a valid WC_Order object
        if ( ! is_a( $order, 'WC_Order' ) ) { return; }
        $method = $order->get_payment_method();  // Modern getter (replaces get_post_meta for _payment_method)
        if ( $method !== 'custom' ) { return; }
        // Use get_meta() for custom fields (stored via $order->update_meta_data() elsewhere)
        $mobile     = $order->get_meta( 'mobile', true );
        $transaction = $order->get_meta( 'transaction', true );
        // Output (unchanged except esc_html wrapping)
        echo '<p><strong>' . esc_html( __( 'Mobile Number', 'bizuno-payfabric' ) ) . ':</strong> ' . esc_html( $mobile ) . '</p>';
        echo '<p><strong>' . esc_html( __( 'Transaction ID', 'bizuno-payfabric' ) ) . ':</strong> ' . esc_html( $transaction ) . '</p>';
    }

    public function bizuno_api_disable_purchorder( $available_gateways ) { // Disable PO Method if the user is not logged in or doesn't have a contact ID link to Bizuno
        $disable = false;
        $user = wp_get_current_user(); // Check to see if user has permission to use this method
        if (empty($user)) { $disable = true; } // not logged in, we're done
        else {
            $cID = (int)get_user_meta( $user->ID, 'bizuno_payment_allow_po', true); // bizuno_wallet_id
            if (empty($cID)) { $disable = true; } // not linked to Bizuno contact, we're done
        }
        if ( $disable ) { unset($available_gateways['purchorder']); }
        return $available_gateways;
    }
}
new bizuno_payfabric();
