<?php
/**
 * Created by PhpStorm.
 * User: denis.nyaga@finserve.africa
 * Date: 08/27/21
 * Time: 2:38 PM
 */

/*
Plugin Name: Jenga Payment Gateway for WooComerce
Plugin URI: https://developer.jengahq.io/
Description: Accept Cards, Mobile Money, and Bank Account Payments in a simple and convenient way from your customers on your store with Jenga Payment Gateway for WooCommerce
Version: 3.0.13
Author: Finserve Africa
Author URI: https://www.finserve.africa/
License: GPLv2 or later
*/


// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ){
    exit;
}

// Check if  WP_List_Table class exist and if not require it
if ( !class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


/**
 * Installation hook callback creates plugin settings
 */

// Hook called when plugin is activated to create jenga payment transactions table in the database
register_activation_hook( __FILE__, 'jpgw_jpgwtrx_install' );

function jpgw_jpgwtrx_install()
{
// Create Table for Jenga PGW Transactions
    global $wpdb;

    global $trx_db_version;

    $trx_db_version = '1.0';

    $table_name = $wpdb->prefix .'jpgw_trx';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (

		id mediumint(9) NOT NULL AUTO_INCREMENT,

		order_status varchar(150) DEFAULT '' NULL,
		
		order_reference varchar(150) DEFAULT '' NULL,
		
		transaction_reference varchar(150) DEFAULT '' NULL,
		
        transaction_amount varchar(150) DEFAULT '' NULL,
        
        transaction_currency varchar(150) DEFAULT '' NULL,
        
        payment_channel varchar(150) DEFAULT '' NULL,
              
		transaction_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,

		PRIMARY KEY  (id)

	) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    dbDelta( $sql );

    add_option( 'trx_db_version', $trx_db_version );
}


#Custom order-received title
add_action(
	'woocommerce_endpoint_order-received_title',
	function( $title ) {
     	

        $order_id = isset( $_GET['orderReference'] ) ? absint ($_GET['orderReference']) : 0;
        $order     = wc_get_order( $order_id );

        if( isset($_GET['transactionStatus']) && hash_equals( $_GET['transactionStatus'], 'FAILED') ){
         
            return 'Payment Failed';

        }

		return $title;
	}
);

#Custom Thank you page (order-received)
add_filter( 'woocommerce_thankyou_order_received_text', 'jpgw_custom_thank_you_text', 20, 2 );
function jpgw_custom_thank_you_text( $text, $order ){    
    
    if(isset($_GET['transactionStatus'])){

        
        $orderStatus = sanitize_text_field($_GET['transactionStatus']);
        $orderReference = sanitize_text_field($_GET['orderReference']);
        $transactionReference = sanitize_text_field($_GET['transactionReference']);
        $transactionAmount = sanitize_text_field($_GET['transactionAmount']);
        $transactionCurrency = sanitize_text_field($_GET['transactionCurrency']);
        $paymentChannel = sanitize_text_field($_GET['paymentChannel']);
        $transactionDate = sanitize_text_field($_GET['transactionDate']);        
        
        
        $order = new WC_Order( $_GET['orderReference'] );

        $order->update_status('processing');

        $table = '<h2>Payment Details</h2>
        <table class="woocommerce-table shop_table gift_info">
            <tbody>
            <tr>
                <th>Order Status</th>'.
                '<td>'. $orderStatus.'</td>'.'
            </tr>

            <tr>
                <th>Order Reference</th>'.
                '<td>'.$orderReference.'</td>
            </tr>

            <tr>
                <th>Transaction Reference</th>
                <td>'. $transactionReference.'</td>
            </tr>

            <tr>
                <th>Transaction Amount</th>
                <td>'.number_format((float)$transactionAmount, 2, '.', '') .'</td>
                
            </tr>

            <tr>
                <th>Transaction Currency</th>
                <td>'. $transactionCurrency.'</td>
            </tr>
            <tr>
                <th>Payment Channel</th>
                <td>'.$paymentChannel.'</td>
            </tr>

            <tr>
                <th>Payment Date</th>
                <td>'. date('F j, Y', strtotime($transactionDate)) .'</td>
              
            </tr>
            </tbody>
        </table>';  
        
        $text .= $table;

        return $text;   
    }

    return true;   

}



add_action( 'plugins_loaded', 'jenga_payment_gateway_init', 0 );
function jenga_payment_gateway_init() {
    //if condition used to do nothing while WooCommerce is not installed
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    define( 'JPGW_DIR', plugin_dir_path( __FILE__ ) );
    define( 'JPGW_INC_DIR', JPGW_DIR.'includes/' );
    define( 'WC_JPGW_VERSION', '1.1.1' );

// Admin Menus
require_once( JPGW_INC_DIR.'menu.php' );

// Payments Menu
require_once( JPGW_INC_DIR.'jpgwpayments.php');

// Include file with custom Jenga Payment Gateway class
include_once( 'jenga-payment-gateway-woocommerce.php' );


// add custom class methods  to WooCommerce
add_filter( 'woocommerce_payment_gateways', 'jenga_add_payment_gateway' );
function jenga_add_payment_gateway( $methods ) {
    $methods[] = 'Jenga_Payment_Gateway';
    return $methods;
}
}


// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'jpgw_action_links' );
function jpgw_action_links( $links ) {
    return array_merge( $links, [ '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=jpgw' ).'">&nbsp;Preferences</a>' ] );

}
// Add action to trim zeros in woocommerce price
add_filter( 'woocommerce_price_trim_zeros', '__return_true' );


add_action('http_api_curl', 'custom_http_api_curl', 10, 1);
function custom_http_api_curl($handle) {
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 90);
    curl_setopt($handle, CURLOPT_TIMEOUT, 90);
}



