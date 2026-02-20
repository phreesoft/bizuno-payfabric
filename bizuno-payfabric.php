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

class bizuno_payfabric
{
    public function __construct()
    {
        // WordPress Actions
        add_action ( 'init',                                              [ $this, 'bizuno_payfabric_load_textdomain' ] );
        add_action ( 'plugins_loaded',                                    [ $this, 'bizuno_payfabric_plugins_loaded' ] );
        add_action ( 'woocommerce_checkout_process',                      [ $this, 'bizuno_payfabric_payment' ] );
        add_action ( 'woocommerce_checkout_update_order_meta',            [ $this, 'bizuno_payfabric_update_order_meta' ] );
        add_action ( 'woocommerce_admin_order_data_after_billing_address',[ $this, 'bizuno_payfabric_order_meta' ], 10, 1 );
        // WordPress Filters
        add_filter ( 'woocommerce_payment_gateways',                      [ $this, 'bizuno_payfabric_add_to_gateways' ] );
        add_filter ( 'woocommerce_available_payment_gateways',            [ $this, 'bizuno_api_disable_purchorder' ], 99, 1);
    }

    function bizuno_payfabric_load_textdomain() {
//        load_plugin_textdomain( 'bizuno-payfabric', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    public function bizuno_payfabric_plugins_loaded()
    {
        if ( ! is_plugin_active ( 'woocommerce/woocommerce.php' ) ) { return; }
        // Load Woocommerce plugins only if WooCommerce is installed and active
//        bizuno_payfabric_gateway_class();
        bizuno_payment_po_method_init();
        WC()->frontend_includes();
        if ( class_exists ( 'WC_Payment_Gateway' ) ) { // get instance of WooCommerce for Payfabric
            require ( plugin_dir_path ( __FILE__ ) . 'payment-payfabric/classes/class-payfabric-gateway-woocommerce.php' );
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

/***************************************************************************************************/
//  Payment Method - PayFabric
/***************************************************************************************************/
function bizuno_payfabric_gateway_class()
{
    class WC_Gateway_PayFabric extends WC_Payment_Gateway
    {
        public $domain = 'bizuno-payfabric';
        public $instructions;
        public $order_status;

        public function __construct() {
            $this->id                 = 'custom';
            $this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Custom', 'bizuno-payfabric' );
            $this->method_description = __( 'Allows payments with custom gateway.', 'bizuno-payfabric' );
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->order_status = $this->get_option( 'order_status', 'completed' );
            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
            add_action( 'woocommerce_thankyou_' . $this->id,                        [ $this, 'thankyou_page' ] );
            add_action( 'woocommerce_email_before_order_table',                     [ $this, 'email_instructions' ], 10, 3 ); // Customer Emails
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled'      => [ 'title' => __( 'Enable/Disable', 'bizuno-payfabric' ), 'type' => 'checkbox', 'default' => 'yes',
                    'label'       => __( 'Enable Custom Payment', 'bizuno-payfabric' )],
                'title'        => [ 'title' => __( 'Title', 'bizuno-payfabric' ), 'type' => 'text', 'default' => __( 'Custom Payment', 'bizuno-payfabric' ), 'desc_tip' => true,
                    'description' => __( 'This controls the title which the user sees during checkout.', 'bizuno-payfabric' )],
                'order_status' => [ 'title' => __( 'Order Status', 'bizuno-payfabric' ), 'type' => 'select', 'default' => 'wc-completed', 'class' => 'wc-enhanced-select',
                    'description' => __( 'Choose whether status you wish after checkout.', 'bizuno-payfabric' ), 'desc_tip' => true, 'options' => wc_get_order_statuses() ],
                'description'  => [ 'title' => __( 'Description', 'bizuno-payfabric' ), 'type' => 'textarea', 'default' => __('Payment Information', 'bizuno-payfabric'),
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'bizuno-payfabric' ), 'desc_tip' => true ],
                'instructions' => [ 'title' => __( 'Instructions', 'bizuno-payfabric' ), 'type' => 'textarea', 'default' => '',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', 'bizuno-payfabric' ), 'desc_tip' => true ] ];
        }

        public function thankyou_page()
        {
            if ( $this->instructions ) { echo wp_kses_post ( wpautop( wptexturize( $this->instructions ) ) ); }
        }

        public function email_instructions( $order, $sent_to_admin )// removed last param: , $plain_text = false
        {
            if ( $this->instructions && ! $sent_to_admin && 'custom' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wp_kses_post ( wpautop ( wptexturize( $this->instructions ) ) ) . PHP_EOL;
            }
        }

        public function payment_fields(){
            if ( $description = $this->get_description() ) { echo wp_kses_post ( wpautop ( wptexturize( $description ) ) );  }
?>
<div id="custom_input">
    <p class="form-row form-row-wide">
        <label for="mobile" class=""><?php esc_html_e('Mobile Number', 'bizuno-payfabric'); ?></label>
        <input type="text" class="" name="mobile" id="mobile" placeholder="" value="">
    </p>
    <p class="form-row form-row-wide">
        <label for="transaction" class=""><?php esc_html_e('Transaction ID', 'bizuno-payfabric'); ?></label>
        <input type="text" class="" name="transaction" id="transaction" placeholder="" value="">
    </p>
</div>
<?php
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;
            // Set order status
            $order->update_status( $status, __( 'Checkout with custom payment. ', 'bizuno-payfabric' ) );
            // or call the Payment complete
            // $order->payment_complete();
            // Reduce stock levels
            $order->reduce_order_stock();
            // Remove cart
            WC()->cart->empty_cart();
            // Return thankyou redirect
            return ['result'=>'success', 'redirect'=>$this->get_return_url( $order )];
        }
    }
}

/***************************************************************************************************/
//  Payment Method - Purchase Order
/***************************************************************************************************/
function bizuno_payment_po_method_init() {
    class WC_Gateway_PurchOrder extends WC_Payment_Gateway
    {
        public $instructions;

        public function __construct()
        {
            $this->id                 = 'purchorder';
            //$this->icon             = apply_filters( 'bizuno_api_purchorder_icon', '' );
            $this->has_fields         = false;
            $this->method_title       = _x( 'Purchase Order payments', 'Purchase Order payment method', 'bizuno-payfabric' );
            $this->method_description = __( 'Accept payment via business Purchase Order. This offline gateway can also be useful to test purchases.', 'bizuno-payfabric' );
            $this->init_form_fields();
            $this->init_settings();
            $this->title              = $this->get_option( 'title' );
            $this->description        = $this->get_option( 'description' );
            $this->instructions       = $this->get_option( 'instructions' );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options'] );
            add_action( 'woocommerce_thankyou_purchorder',                          [$this, 'thankyou_page'] );
            add_action( 'woocommerce_email_before_order_table',                     [$this, 'email_instructions'], 10, 3 );
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled'     => ['title'=>__( 'Enable/Disable', 'bizuno-payfabric' ),'type'=>'checkbox','default'=>'no',
                    'label'      => __( 'Enable PO Checkout', 'bizuno-payfabric' )],
                'title'       => ['title'=>__( 'Title', 'bizuno-payfabric' ),         'type'=>'text',    'desc_tip'=>true,
                    'description'=> __( 'This controls the title which the user sees during checkout.', 'bizuno-payfabric' ),
                    'default'    => _x( 'Purchase Order', 'Purchase Order payment method', 'bizuno-payfabric' )],
                'description' => ['title'=>__( 'Description', 'bizuno-payfabric' ),   'type'=>'textarea','desc_tip'=>true,
                    'description'=> __( 'Payment method description that the customer will see on your checkout.', 'bizuno-payfabric' ),
                    'default'    => __( 'You will receive an invoice with tracking once your order ships.', 'bizuno-payfabric' )],
                'instructions'=> ['title'=>__( 'Instructions', 'bizuno-payfabric' ),  'type'=>'textarea','desc_tip'=>true, 'default'=>'',
                    'description'=> __( 'Instructions that will be added to the thank you page and emails.', 'bizuno-payfabric' )]];
        }

        public function thankyou_page()
        {
            if ( $this->instructions ) { echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) ); }
        }

        public function email_instructions( $order, $sent_to_admin ) // removed last param: , $plain_text = false
        {
            if ( $this->instructions && ! $sent_to_admin && 'purchorder' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
                echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
            }
        }

        public function process_payment( $order_id )
        {
            $order = wc_get_order( $order_id );
            if ( $order->get_total() > 0 ) { // Mark as on-hold (we're awaiting the purchorder).
                $order->update_status( apply_filters( 'bizuno_api_purchorder_process_payment_order_status', 'on-hold', $order ), _x( 'Awaiting check payment', 'Check payment method', 'bizuno-payfabric' ) );
            } else { $order->payment_complete(); }
            WC()->cart->empty_cart();
            return ['result'=>'success', 'redirect'=>$this->get_return_url( $order )];
        }
    }
}
