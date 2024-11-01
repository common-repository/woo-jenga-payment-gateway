<?php
/**
 * Created by PhpStorm.
 * User: denis.nyaga@finserve.africa
 * Date: 08/27/21
 * Time: 3:27 PM
 */

class Jenga_Payment_Gateway extends  WC_Payment_Gateway {

    public $jpgw_username;
    public $jpgw_password;
    public $jpgw_api_key;
    public $jpgw_wallet;
    public $currency;
    public $trans_status;
    public $jpgw_cstnames;
    public $jpgw_totalamount;
    public $environment;
    public $configured_token_environment;
    public $configured_launch_environment;

    public $private_key;
    public $return_url;

    public $token;
    public $signature;

    
    /**
     * @var string
     */



    function __construct() {
        
		// Setup general properties.
        $this->setup_properties();

        //Initialize form settings
        $this->init_form_fields();

        // load time variable setting
        $this->init_settings();     

        $this->instructions = $this->get_option( 'instructions' );
        $this->jpgw_username = $this->get_option( 'username' );
        $this->jpgw_password = $this->get_option( 'password' );
        $this->jpgw_api_key = $this->get_option( 'api_key' );
        $this->jpgw_wallet = $this->get_option('wallet');
        $this->currency = $this->get_option('currency');
        $this->environment = $this->get_option('environment');
        $this->private_key = $this->get_option('private_key');    
        $this->token = '';
        $this->signature = '';     

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        // further check of SSL if you want
        add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );

        // Add receipt page
        add_action( 'woocommerce_receipt_jpgw', array( $this, 'receipt_page' ));

        // Add custom thankyou page content on woocommerce_thankyou action trigger
        add_action( 'woocommerce_thankyou', array( $this, 'check_ipn_response'));

        //payment call back url 		
        add_action( 'woocommerce_api_wc_gateway_jpgw', array( $this, 'check_ipn_response' ) );
      

        // Save settings
        if ( is_admin() ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        }

    } // Here is the  End __construct()

    	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {

        // global ID
        $this->id = "jpgw";

        // Show Title
        $this->method_title = __( "Jenga Payment Gateway", 'jpgw' );

        // Show Description
        $this->method_description = __( "Jenga Payment Gateway Plug-in for WooCommerce", 'jpgw' );

        $this->title = apply_filters( 'woocommerce_jpgw_title', 'JENGA PGW');

		$this->has_fields         = false;
	}


    // function to Add custom content on thank-you page
    function check_ipn_response()
    {

        global $woocommerce;

        $response_array = [];
           

        if(isset($_GET['transactionStatus'])){

            $order_String = explode('?', $_GET['order_id']);
            $actual_order_id = $order_String[0];

            $order = wc_get_order($actual_order_id);  

            $orderStatus = sanitize_text_field($_GET['transactionStatus']);
            $orderReference = sanitize_text_field($_GET['orderReference']);
            $transactionReference = sanitize_text_field($_GET['transactionReference']);
            $transactionAmount = sanitize_text_field($_GET['transactionAmount']);
            $transactionCurrency = sanitize_text_field($_GET['transactionCurrency']);
            $paymentChannel = sanitize_text_field($_GET['paymentChannel']);
            $transactionDate = sanitize_text_field($_GET['transactionDate']);         

            $_SESSION['transactionStatus'] = $orderStatus;
            $_SESSION['orderReference'] = $orderReference;
            $_SESSION['transactionReference'] = $transactionReference;
            $_SESSION['transactionAmount'] = $transactionAmount;
            $_SESSION['transactionCurrency'] = $transactionCurrency;
            $_SESSION['paymentChannel'] = $paymentChannel;
            $_SESSION['transactionDate'] = $transactionDate;

        
            $query_string = '?transactionStatus='.$orderStatus.'&orderReference='.$orderReference.
            '&transactionReference='.$transactionReference.'&transactionAmount='.$transactionAmount.
            '&transactionCurrency='.$transactionCurrency.
            '&paymentChannel='.$paymentChannel.'&transactionDate='.$transactionDate;

            $resArr[] = array('transactionStatus' => $orderStatus, 'orderReference' =>$orderReference,
            'transactionReference'=>$transactionReference,'transactionAmount' => $transactionAmount,
            'transactionCurrency' => $transactionCurrency,'paymentChannel' => $paymentChannel,
            'transactionDate' =>$transactionDate,
         );        
            

            if (hash_equals($_GET['transactionStatus'], 'SUCCESS')) {    

                $order->update_status('processing');

                // UPDATE TRANSACTION TABLE
                $this->woojpgw_insert_transaction(array());               

                //REDIRECT ORDER-RECEIVED PAGE
                wp_redirect(  $this->get_return_url().$query_string );	              


            }else{
               
                wc_add_notice( __( 'PAYMENT '. $_GET['transactionStatus']. '! '.$_GET['message'].'.Please try Again', 'jpgw' ), 'error' );

                // UPDATE TRANSACTION TABLE
                $this->woojpgw_insert_transaction($resArr[0]);

                //REDIRECT TO CHECKOUT PAGE
               wp_redirect( wc_get_checkout_url() );	
               

            }

            exit;
            
        }      

    }

 


    // Function to insert Jenga Payment Gateway Transactions
    function woojpgw_insert_transaction($arr=[])
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'jpgw_trx';

        if (isset($_SESSION['transactionStatus']) ) {

            $date = strtotime($_SESSION['transactionDate']);
            $newDate = date('Y-m-d H:i:s', $date);
            
            $wpdb->insert(
                $table_name,
                array(

                    'order_status' => $_SESSION['transactionStatus'],
                    'order_reference' => $_SESSION['orderReference'],
                    'transaction_reference' => $_SESSION['transactionReference'],
                    'transaction_amount' => $_SESSION['transactionAmount'],
                    'transaction_currency' => $_SESSION['transactionCurrency'],
                    'payment_channel' => $_SESSION['paymentChannel'],
                    'transaction_date' => $newDate,
                )
            );
        }

    }

    // Initialize custom jenga payment plugin settings page fields
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'		=> __( 'Enable / Disable', 'jpgw' ),
                'label'		=> __( 'Enable this payment gateway', 'jpgw' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),

            'instructions'       => array(
				'title'       => __( 'Instructions', 'jpgw' ),
				'type'        => 'text',
				'description' => __( 'Instructions that will be shown on checkout page.', 'jpgw' ),
				'default'     => __( 'Pay with Jenga Payment Gateway', 'jpgw' ),
				'desc_tip'    => true,
			),

             'environment' =>  array(
                'title'		=> __( 'Environment', 'jpgw' ),
                 'label' 	=> 'Environment Setup',
                 'type' 	=> 'select',
                 'default'	=> 'Sandbox',
                 'options'	=> array(
                    'Sandbox' 	=> 'Sandbox',
                    'Production' => 'Production',
                ),
            ),

            'username' => array(
                'title'		=> __( 'merchantcode', 'jpgw' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'This is the Merchant Code issued when you register in Jenga HQ Under view Keys Menu .', 'jpgw' ),
            ),
            'password' => array(
                'title'		=> __( 'Consumer Secret', 'jpgw' ),
                'type'		=> 'password',
                'desc_tip'	=> __( 'This is the Consumer Secret found when you register in Jenga HQ Under view Keys Menu .', 'jpgw'),
            ),
        
            'api_key' => array(
                'title'		=> __( 'API Key', 'jpgw' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'This is the api key found when you register in Jenga HQ Under view Keys Menu .', 'jpgw'),
            ),
        

            'private_key'        => array(
				'title'       => __( 'Private Key', 'jpgw' ),
				'type'        => 'textarea',
                'css'       => 'height: 350px',
				'description' => __( 'Merchant generated Private Key. Required only if secure mode is enabled for products in Jenga HQ', 'jpgw' ),
				'default'     => __( 'Merchant\'s generated Private Key', 'jpg' ),
				'desc_tip'    => true,
			),


        );
    }

    //  Function to process payments
    public function process_payment ($order_id) {

        global $woocommerce;

        // $order = new WC_Order( $order_id ); 
        $order = wc_get_order($order_id);       

        $this->jpgw_cstnames = $order->get_billing_first_name();

        $this->jpgw_totalamount = $order->get_total();

        $_SESSION["orderID"] = $order->get_id();

        // Redirect to checkout/pay page
        $checkout_url = $order->get_checkout_payment_url(true);

        $checkout_edited_url = $checkout_url."&transactionType=checkout";

        return array(

            'result' => 'success',

            'redirect' => add_query_arg('order', $order->get_id(),
                add_query_arg('key', $order->get_order_key(), $checkout_edited_url))

        );

    }


 // Generate token required to launch checkout
    public function tokenInitializer () {
        $accessToken = '';


        if ($this->environment == 'Sandbox'){

            $this->configured_token_environment = 'https://uat.finserve.africa/authentication/api/v3/authenticate/merchant';         
           
            $this->configured_launch_environment = 'https://v3-uat.jengapgw.io/processPayment';
          

        } else {
            
            $this->configured_token_environment = 'https://api.finserve.africa/authentication/api/v3/authenticate/merchant';
          
            $this->configured_launch_environment = 'https://v3.jengapgw.io/processPayment';
        }


        $endpoint = $this->configured_token_environment;

        $body = [
            'merchantCode'  => $this->jpgw_username,
            'consumerSecret' => $this->jpgw_password,
        ];

        $body = wp_json_encode( $body );
        $options = [
            'body'        => $body,
            'headers'     => [
                 'Api-Key' => $this->jpgw_api_key,
                'Content-Type' => 'application/json',
            ],
            'method     '    => 'POST',
            'timeout'     => 90,
            'sslverify' => false
        ];

        $response = wp_remote_post( $endpoint, $options);

        if (is_wp_error($response)) {

            $error_message = $response->get_error_message();
            error_log( "Something went wrong: $error_message");
            wc_add_notice($error_message, 'error');
           
        }else{

            
            $response_body = wp_remote_retrieve_body( $response );

            // Decode the JSON response
            $response_data = json_decode($response_body, true);

            // $statusCode = isset($response_data['statusCode']) ? $response_data['statusCode'] : 
            //     ( isset($response_data['code']) ? $response_data['code'] : 
            //     ( isset($response['response']['code']) ?  $response['response']['code'] : 'unknown') ) ;

            $statusCode = $response_data['statusCode'] 
                ?? $response_data['code'] 
                ?? $response['response']['code'] 
                ?? 'unknown';

            $message = isset($response_data['message']) ? $response_data['message'] : 
                ( isset($response['response']['message']) ? $response['response']['message'] : 'No message');

            if(isset($response_data['code']) && $statusCode == 401){

                $message = $statusCode.': Authentication Error.Kindly contact us for support!';
            }

            if( isset($response_data['statusCode'])  && $statusCode == 500 ||  $statusCode== 502 || $statusCode == 504 ){
                $message = $statusCode. ': Internal Server Error.Kindly contact us for support!!';
            }

            if($statusCode == 404){
                $message =  $statusCode.' Resource Not found Error.Kindly contact us for support!!';
            }


            // Check if the token is present
            if (isset($response_data['accessToken'])) {

                $accessToken = $response_data['accessToken'];
            
            } else {
                
                error_log($statusCode." ".$message);
                wc_add_notice( $message, 'error' );
            
            }

        }

        return $accessToken;

    }

    // // Logic to generate signature
    public function generateSignature ($order) {

        $callbackUrl = str_replace( 'https:', 'http:',
        add_query_arg(array('wc-api'=> 'wc_gateway_jpgw','order_id' => $order->get_id() ) , home_url( '/' ) ) );      
       
        $data = $this->jpgw_username.$order->get_order_number().$order->get_currency(). $this->jpgw_totalamount.$callbackUrl;     


        if(!empty($this->private_key ) && strlen($this->private_key) > 250 ){

            $privateKey = openssl_pkey_get_private( $this->private_key );
            if ($privateKey === false) {
                error_log("Failed to load private key: ");
                throw new Exception('Failed to load private key: ' . openssl_error_string());
            }

            // Sign the data
            $signature = '';
            if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                error_log("Failed to sign data: ");
                throw new Exception('Failed to sign data: ' . openssl_error_string());
            
            }

            openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);         
            return base64_encode($signature);

        }

        return '';
       
    }

    
    public function receipt_page( $order_id ) {

        echo $this->woompesa_generate_iframe( $order_id );

    }


    public function woompesa_generate_iframe( $order_id ) {

        global $woocommerce;

        // $order = new WC_Order( $order_id );
        $order = wc_get_order($order_id);  

        $this->jpgw_cstnames = $order->get_billing_first_name(). ' '.$order->get_billing_last_name();

        $this->jpgw_totalamount = (($order->get_total() * 100) / 100);

        $callbackUrl = str_replace( 'https:', 'http:',
		 add_query_arg(array('wc-api'=> 'wc_gateway_jpgw','order_id' => $order->get_id() ) , home_url( '/' ) ) );

         $cartItems = [];
         $items = WC()->cart->get_cart();
         foreach($items as $cart_item){

            $item_name = $cart_item['data']->get_title();
            $quantity = $cart_item['quantity'];
            $price = $cart_item['data']->get_price();  
            
            $cartItems[] = array('itemName' => $item_name, 'amount' => $price,'quantity' => $quantity);      
             
         }
   

        try {
            $this->token = $this->tokenInitializer();
            $this->signature = $this->generateSignature($order);
        } catch (Exception $e) {
            error_log($e);
        }

        /**

         * Make the payment here by clicking on pay button and confirm by clicking on complete order button

         */

        if (isset($_GET['transactionType'])=='checkout'){           
        

            echo "<h4>Payment Instructions:</h4>";

            echo "

		  1. Click on the <b>Proceed to Pay</b> button in order to initiate the Jenga Payment Gateway.<br/>

		  2. This will redirect you to the Gateway Page where you will be able to make payments.<br/>

    	  3. After succesful payments, you will be redirected back to this site<br/>     	

    	  4. You will then be able to see the payment details you made for this order<br/>";


            echo "<br/>";?>

            <form action="<?php echo esc_html($this->configured_launch_environment); ?>" method="post">
            <input type="hidden" name="token" value="<?php echo esc_html ($this->token); ?>">
            <input type="hidden" name="signature" value="<?php echo esc_html ($this->signature); ?>">
            <input type="hidden" name="merchantCode" value="<?php echo esc_html($this->jpgw_username); ?>">
            <input type="hidden" name="currency" value="<?php echo esc_html($order->get_currency()); ?>">
            <input type="hidden" name="countryCode" value="<?php echo esc_html($order->get_billing_country()); ?>">
            <input type="hidden" name="orderAmount" value="<?php echo esc_html($this->jpgw_totalamount); ?>">
            <input type="hidden" name="orderReference" value="<?php echo esc_html($order->get_order_number()); ?>">
            <input type="hidden" name="productType" value="<?php echo esc_html('Jenga product'); ?>">
            <input type="hidden" name="productDescription" value="<?php echo esc_html( 'Jenga Woocommerce') ?>">
            <input type="hidden" name="extraData" value="<?php echo esc_html('NA'); ?>">
            <input type="hidden" name="paymentTimeLimit" value="<?php echo esc_html('200'); ?>">
            <input type="hidden" name="customerFirstName" value="<?php echo esc_html($order->get_billing_first_name()); ?>">
            <input type="hidden" name="customerLastName" value="<?php echo esc_html($order->get_billing_last_name()); ?>">
            <input type="hidden" name="customerEmail" value="<?php echo esc_html($order->get_billing_email()); ?>">
			<input type="hidden" name="customerPhone" value="<?php echo esc_html(str_replace("+","",$order->get_billing_phone())); ?>">
            <input type="hidden" name="customerPostalCodeZip" value="<?php echo esc_html($order->get_billing_postcode()); ?>">
            <input type="hidden" name="customerAddress" value="<?php echo esc_html($order->get_billing_address_1()); ?>">
            <input type="hidden" name="callbackUrl" value="<?php echo esc_url($callbackUrl) ?>">
            <input type="hidden" name="orderItems" value="<?php echo esc_html( esc_html( json_encode($cartItems))) ?>">
            <button type="submit" id="submit-form"> Proceed to Pay</button>

            </form>

            <?php

            echo "<br/>";

        }

    }

  // Validate fields
    public function validate_fields() {
        return true;
    }

    // Check for ssl and if not enabled notify user
    public function do_ssl_check()
    {
        if ($this->enabled == "yes") {
            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }

    }

}
