<?php
/**
 * Provides settings inputs for admin area view for the plugin
 * This file is used to markup the admin-facing aspects of the plugin.
 * @since      1.0.0
 * @package    PayFabric_Gateway_Woocommerce
 * @subpackage PayFabric_Gateway_Woocommerce/admin
 */
if (!defined('ABSPATH')) {
    exit;
}
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

/*Define the control parameter value to determine whether the LOG functionality show or not */
$show_log_field = '0';
/*Define the control parameter value to determine whether the AUTH functionality show or not */
$show_auth_fields = '1';
/*Define whether integration mode should be shown or not, 1 means to show, 0 means not */
$integration_show = '1';


$payfabric_fields_array = array();
$payfabric_fields_array['enabled'] = array(
    'title' => __('Enable/Disable', 'bizuno-payfabric'),
    'type' => 'checkbox',
    'label' => __('Enable PayFabric gateway', 'bizuno-payfabric'),
    'description' => __('Enable or disable the gateway.', 'bizuno-payfabric'),
    'desc_tip' => true,
    'default' => 'no'
);
$payfabric_fields_array['title'] = array(
    'title' => __('Title', 'bizuno-payfabric'),
    'type' => 'text',
    'description' => __('The title which the user sees during checkout.', 'bizuno-payfabric'),
    'desc_tip' => true,
    'default' => __('PayFabric', 'bizuno-payfabric')
);

$payfabric_fields_array['description'] = array(
    'title' => __('Description', 'bizuno-payfabric'),
    'type' => 'textarea',
    'description' => __('The description which the user sees during checkout.', 'bizuno-payfabric'),
    'desc_tip' => true,
    'default' => __("Pay via PayFabric", 'bizuno-payfabric')
);

$payfabric_fields_array['testmode'] = array(
    'title' => __('PayFabric test mode', 'bizuno-payfabric'),
    'type' => 'checkbox',
    'label' => __('Enable test mode', 'bizuno-payfabric'),
    'description' => __('Enable or disable the test mode for the gateway to test the payment method.', 'bizuno-payfabric'),
    'desc_tip' => true,
    'default' => 'yes'
);
$payfabric_fields_array['advanced'] = array(
    'title' => __('Advanced options', 'bizuno-payfabric'),
    'type' => 'title',
    'description' => '',
);
$payfabric_fields_array['api_merchant'] = array(
    'title' => __('Merchant data', 'bizuno-payfabric'),
    'type' => 'title',
    'description' => __('In this section You can set up your merchant data for PayFabric system.', 'bizuno-payfabric')
);
$payfabric_fields_array['api_merchant_id'] = array(
    'title' => __('Device ID', 'bizuno-payfabric'),
    'type' => 'text',
    'description' => __('Device ID from PayFabric', 'bizuno-payfabric'),
    'desc_tip' => true,
    'default' => ''
);
$payfabric_fields_array['api_password'] = array(
    'title' => __('Password', 'bizuno-payfabric'),
    'type' => 'password',
    'description' => __('Device password from PayFabric', 'bizuno-payfabric'),
    'desc_tip' => true,
    'default' => ''
);

if ($integration_show) {
    $payfabric_fields_array['api_payment_modes'] = array(
        'title' => __('Payment mode', 'bizuno-payfabric'),
        'type' => 'select',
        'description' => sprintf('Payment Mode controls the presentation of the Hosted Payment Page (HPP):<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<b>• Direct:</b> HPP shown directly on the checkout page, payment made when placing order. (A theme is required, see %sGuide%s).<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<b>• Iframe:</b> HPP is inside the shopping site page.<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<b>• Redirect:</b> Shopping site redirects user to the HPP.', '<a href="https://github.com/PayFabric/WooCommerce-Plugin#readme" target="_blank">', '</a>' ),
        'desc_tip' => false,
        'default' => 2,
        'options' => array(
            2 => __('Direct', 'bizuno-payfabric'),
            0 => __('Iframe', 'bizuno-payfabric'),
            1 => __('Redirect', 'bizuno-payfabric')
        )
    );
}

if ($show_auth_fields) {
    //Purchase or Auth
    $payfabric_fields_array['api_payment_action'] = array(
        'title' => __('Payment action', 'bizuno-payfabric'),
        'type' => 'select',
        'description' => __('Specify transaction type.', 'bizuno-payfabric'),
        'desc_tip' => true,
        'default' => 0,
        'options' => array(
            __('Purchase', 'bizuno-payfabric'),
            __('Auth', 'bizuno-payfabric')
        )
    );
}
//choose the default paid order status
$payfabric_fields_array['api_success_status'] = array(
    'title' => __('Success status', 'bizuno-payfabric'),
    'type' => 'select',
    'description' => __('Status of order after successful payment.', 'bizuno-payfabric'),
    'desc_tip' => true,
    'default' => 0,
    'options' => array(
        __('Processing', 'bizuno-payfabric'),
        __('Completed', 'bizuno-payfabric')
    )
);

if ($show_log_field) {
    $payfabric_fields_array['log_mode'] = array(
        'title' => __('Logging', 'bizuno-payfabric'),
        'type' => 'checkbox',
        'label' => __('Enable log debug', 'bizuno-payfabric'),
        'description' => __('Log payment events, such as gateway transaction callback, if enabled, log file will be found inside: wp-content/uploads/wc-logs', 'bizuno-payfabric'),
        'desc_tip' => false,
        'default' => 'no'
    );
}


return $payfabric_fields_array;
