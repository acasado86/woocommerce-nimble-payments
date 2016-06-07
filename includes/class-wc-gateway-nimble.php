<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Description of WC_Gateway_Nimble
 *
 * @author acasado
 */
class WC_Gateway_Nimble extends WC_Payment_Gateway {

    var $status_field_name = 'status_nimble';
    var $payment_nonce_field = 'payment_nonce';
    var $storedcard_field = 'storedcard';
    var $mode;

    //put your code here
    function __construct() {
        $this->id = 'nimble_payments_gateway';
        $this->icon = plugins_url('assets/images/BBVA.png', plugin_dir_path(__FILE__));
        $this->has_fields = false;
        $this->title = __('Nimble Payments by BBVA', 'woocommerce-nimble-payments'); //LANG: GATEWAY TITLE
        $this->method_title = __('Nimble Payments', 'woocommerce-nimble-payments'); //LANG: GATEWAY METHOD TITLE
        $this->description = __('Pay safely with your credit card through the BBVA.', 'woocommerce-nimble-payments'); //LANG: GATEWAY DESCRIPTION
        $this->supports = array(
            'products',
            'refunds',
            'default_credit_card_form'
        );
        $this->mode = NimbleAPIConfig::MODE;

        // Load the form fields
        $this->init_form_fields();

        // Load the settings
        if (!$this->get_option($this->status_field_name)) {
            $this->enabled = false;
            // $this->init_settings();
        }
        
        add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'check_credentials'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
        add_filter('woocommerce_get_checkout_order_received_url', array($this, 'checkout_order_received_url'), 10, 2);
        
        add_action('before_woocommerce_pay', array($this, 'payment_error'));
        
        add_action('before_woocommerce_pay', array($this, 'payment_redirect'));
        
        add_filter('woocommerce_thankyou_order_key', array($this, 'success_url_nonce'));
        
        add_filter('woocommerce_get_order_item_totals', array($this, 'order_total_payment_method_replace'), 10, 2);
        
        add_action('wp_ajax_woocommerce_refund_line_items_nimble_payments', array(&$this, 'woocommerce_refund_line_items_nimble_payments') );
  
    }
    
    function check_credentials($array) {

        $params = array(
            'clientId' => trim(html_entity_decode($array['seller_id'])),
            'clientSecret' => trim(html_entity_decode($array['secret_key'])),
            'mode' => $this->mode
        );

        try {
            $nimbleApi = new WP_NimbleAPI($params);
            //Validamos el par credenciales y modo.
            $response = NimbleAPICredentials::check($nimbleApi);
            if ( isset($response) && isset($response['result']) && isset($response['result']['code']) && 200 == $response['result']['code'] ){
                $array[$this->status_field_name] = true;
            } else{
                $array[$this->status_field_name] = false;
            }
        } catch (Exception $e) {
            $array[$this->status_field_name] = false;
        }

        return $array;
    }

    function process_payment($order_id) {
        $order = new WC_Order($order_id);
        if (is_user_logged_in()){
            $user = wp_get_current_user();
            
            if(get_user_meta($user->ID, 'np_shipping_hash')){
                $bd_hash = get_user_meta($user->ID, 'np_shipping_hash');
            }
            $hash = $this->get_location_hash_Customer();
            
            if(isset($bd_hash)  && isset($bd_hash[0]) && isset($hash) && $bd_hash[0] !=  $hash){
                update_user_meta($user->ID, 'np_shipping_hash', $hash);
                    //all cards delete 
                try{
                    $nimbleApi = $this->inicialize_nimble_api();
                    $response = NimbleAPIStoredCards::deleteAllCards($nimbleApi, $user->ID);  
                } catch (Exception $ex) {
                    //to do
                }
            }
        }  
	//Stored Cards Payments
        $storedcard = filter_input(INPUT_POST, $this->id . '_storedcard');
        if (!empty($storedcard)){
            return $this->process_stored_card_payment($order);
        }

        //Basic Payments
        //Intermediate reload URL Checkout to prevent invalid nonce generation
        $payment_url = $order->get_checkout_payment_url();
        $checkout_redirect_url = add_query_arg( 'payment_redirect', $this->id, $payment_url );
        
        return array(
            'result' => 'success',
            'redirect' => $checkout_redirect_url
        );
    }
    
    function process_stored_card_payment($order){
	// Mark as nimble-pending (we're awaiting the payment)
        $order->update_status('nimble-pending', __('Awaiting payment via Nimble Payments.', 'woocommerce-nimble-payments')); //LANG: ORDER NOTE PENDING
        try{
            $nimbleApi = $this->inicialize_nimble_api();
            //ADD HEADER SOURCE CALLER
            $oWoocommerceNimblePayments = Woocommerce_Nimble_Payments::getInstance();
            $version = $oWoocommerceNimblePayments->get_plugin_version();
            $nimbleApi->authorization->addHeader('source-caller', 'WOOCOMMERCE_'.$version);
            
            $storedCardPaymentInfo = $this->set_stored_card_payment_info($order);
            $response = NimbleAPIStoredCards::payment($nimbleApi, $storedCardPaymentInfo);
            //Save transaction_id to this order
            if ( isset($response["data"]) && isset($response["data"]["id"])){
                update_post_meta( $order->id, '_transaction_id', $response["data"]["id"] );
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg( $this->storedcard_field, 'true', $this->get_return_url($order) )
                );
            } else{
                //error_log(print_r($response, true));
            }
        }
        catch (Exception $e) {
            //Error
            //error_log(print_r($e, true));
        }
        
        $error_url = $order->get_checkout_payment_url();
        $error_url = add_query_arg( 'payment_status', 'error', $error_url );
        return array(
            'result' => 'success',
            'redirect' => $error_url
        );
        
    }
    
    function get_params(){
        $params = array(
            'clientId' => trim(html_entity_decode($this->get_option('seller_id'))),
            'clientSecret' => trim(html_entity_decode($this->get_option('secret_key'))),
            'mode' => $this->mode
        );
        return $params;
    }
    
    function inicialize_nimble_api() {
        /* High Level call */
        $nimbleApi = new WP_NimbleAPI($this->get_params());
        //Obtain language from wordpress
        $language = substr(get_bloginfo('language'), 0, 2);
        //Change Nimble Payments Default Language
        $nimbleApi->changeDefaultLanguage($language);
        return $nimbleApi;
    }
    
    function set_payment_info($order) {
        $error_url = $order->get_checkout_payment_url();
        $error_url = add_query_arg( 'payment_status', 'error', $error_url );
        
        $payment = array(
            'amount' => $order->get_total() * 100,
            'currency' => $order->get_order_currency(),
            'merchantOrderId' => $order->get_order_number(),
            'paymentSuccessUrl' => $this->get_return_url( $order ),
            'paymentErrorUrl' => $error_url
        );
        
        if (is_user_logged_in()){
            $user = wp_get_current_user();
            $payment['cardHolderId'] = $user->ID;
        }
        
        return $payment;
    }
    
    function set_stored_card_payment_info($order) {
        $payment = array(
            'amount' => $order->get_total() * 100,
            'currency' => $order->get_order_currency(),
            'merchantOrderId' => $order->get_order_number(),
        );
        
        if (is_user_logged_in()){
            $user = wp_get_current_user();
            $payment['cardHolderId'] = $user->ID;
        }
        
        return $payment;
    }

    /**
     * Init payment gateway form fields
     */
    function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-nimble-payments'),//LANG: FIELD ENABLED TITLE
                'label' => __('Enable Nimble Payments', 'woocommerce-nimble-payments'),//LANG: FIELD ENABLED LABEL
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
            'seller_id' => array(
                'title' => __('API Client ID', 'woocommerce-nimble-payments'),//LANG: FIELD SELLER_ID TITLE
                'type' => 'text',
                'description' => __('Obtained from https://www.nimblepayments.com', 'woocommerce-nimble-payments'), //LANG: FIELD SELLER_ID DESCRIPTION
                'default' => '',
                'desc_tip' => true
            ),
            'secret_key' => array(
                'title' => __('Client Secret', 'woocommerce-nimble-payments'),//LANG: FIELD SELLER_KEY TITLE
                'type' => 'text',
                'description' => __('Obtained from https://www.nimblepayments.com', 'woocommerce-nimble-payments'),//LANG: FIELD SELLER_KEY DESCRIPTION
                'default' => '',
                'desc_tip' => true
            )
        );
    }
    
    function checkout_order_received_url($order_received_url, $order) {
        if ("wc-nimble-pending" == $order->post_status){
            $nonce = wp_create_nonce();
            $order_received_url = remove_query_arg( 'key', $order_received_url );
            $order_received_url = add_query_arg( $this->payment_nonce_field, $nonce, $order_received_url );
        }
        return $order_received_url;
    }
       
    function success_url_nonce($order_key){
        global $wp;
        
        if ( isset($wp->query_vars['order-received']) && isset($_GET[$this->payment_nonce_field]) && wp_verify_nonce($_GET[$this->payment_nonce_field])) {
            $order_id = $wp->query_vars['order-received'];
            $order = wc_get_order( $order_id );
            
            
            if ($order->needs_payment()){
                if ( isset($_GET[$this->storedcard_field]) ){
                    //STORED CARD PAYMENT
                    $this->change_order_status($order);
                    //END STORED CARD PAYMENT
                } else {
                    //BASIC PAYMENT
                    $order->payment_complete();

                    //Email sending
                    WC_Emails::instance();
                    $email_actions = apply_filters( 'woocommerce_email_actions', array(
                        'woocommerce_order_status_pending_to_processing',
                        ) );
                    if (in_array('woocommerce_order_status_pending_to_processing', $email_actions)){
                        do_action('woocommerce_order_status_pending_to_processing_notification', $order_id);
                    }
                    //END BASIC PAYMENT
                }
            }

            return $order->order_key;
        }
        
        return $order_key;
    }
    
    function payment_error(){
        if ( isset($_GET['payment_status']) ){
            switch ($_GET['payment_status']){
                case 'error':
                    $message = __( 'Card payment was rejected. Please try again.', 'woocommerce-nimble-payments' ); //LANG: CARD PAYMENT REJECTED
                    echo '<div class="woocommerce-error">' . $message . '</div>';
                    break;
            }
        }
    }
    
    function payment_redirect(){
        global $wp;
        
        $redirect_input = filter_input(INPUT_GET, 'payment_redirect');
        if ( $this->id == $redirect_input ){
            
            $order_id = $wp->query_vars['order-pay'];
            $order = wc_get_order( $order_id );
            // Mark as nimble-pending (we're awaiting the payment)
            $order->update_status('nimble-pending', __('Awaiting payment via Nimble Payments.', 'woocommerce-nimble-payments')); //LANG: ORDER NOTE PENDING
            
            try{
                $nimbleApi = $this->inicialize_nimble_api();
                //ADD HEADER SOURCE CALLER
                $oWoocommerceNimblePayments = Woocommerce_Nimble_Payments::getInstance();
                $version = $oWoocommerceNimblePayments->get_plugin_version();
                $nimbleApi->authorization->addHeader('source-caller', 'WOOCOMMERCE_'.$version);
                
                $payment = $this->set_payment_info($order);

                $response = NimbleAPIPayments::SendPaymentClient($nimbleApi, $payment);
                //Save transaction_id to this order
                if ( isset($response["data"]) && isset($response["data"]["id"])){
                    update_post_meta( $order_id, '_transaction_id', $response["data"]["id"] );
                }

                if (!isset($response["data"]) || !isset($response["data"]["paymentUrl"])){
                    $order->update_status('nimble-failed', __('Could not connect to the bank. Code ERR_CONEX.', 'woocommerce-nimble-payments')); //LANG: ORDER NOTE 404
                    $message = __('Unable to process payment. An error has occurred. ERR_CONEX code. Please try later.', 'woocommerce-nimble-payments'); //LANG: SDK RETURN 404
                } else{
                    wp_redirect( $response["data"]["paymentUrl"] );
                    exit();
                }

            }
            catch (Exception $e) {
                $order->update_status('nimble-failed', __('An error has occurred. Code ERR_PAG.', 'woocommerce-nimble-payments')); //LANG: ORDER NOTE ERROR
                $message = __('Unable to process payment. An error has occurred. ERR_PAG code. Please try later.', 'woocommerce-nimble-payments'); //LANG: SDK ERROR MESSAGE
            }
            echo '<div class="woocommerce-error">' . $message . '</div>';
        }
    }
    
    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     */
    public function admin_options() {
        $oWoocommerceNimblePayments = Woocommerce_Nimble_Payments::getInstance();
            
        if ( ! $this->get_option('seller_id') ) {
            $this->gateway_register_notice();
        } elseif ( ("yes" == $this->get_option('enabled')) && !($this->get_option($this->status_field_name) ) ){
            $this->gateway_error_notice();
        }

        if ( ( "yes" == $this->get_option('enabled')) && $this->get_option($this->status_field_name) && ! $oWoocommerceNimblePayments->isOauth3Enabled() ){
            Woocommerce_Nimble_Payments::authorize_notice();
        }
        
        ?>
            <h3><?php echo $this->title; ?></h3>
            <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
        <?php
    }
    
    function gateway_register_notice(){
        ?>
                <div class="updated woocommerce-message"><div class="squeezer">
                        <h4><?php _e( 'Need an Nimble Payments account?', 'woocommerce-nimble-payments' );//LANG: MESSAGE REGISTRATION TEXT ?></h4>
                        <p class="submit">
                                <a class="button button-primary" href="https://www.nimblepayments.com/private/registration?utm_source=Woocommerce_Settings&utm_medium=Referral%20Partners&utm_campaign=Creacion-Cuenta&partner=woocommerce" target="_blank"><?php _e( 'Signup now', 'woocommerce-nimble-payments' ); //LANG: MESSAGE REGISTRATION BUTTOM ?></a>
                                &nbsp;&nbsp;
                                <a id="np-gateway" class="button" href="#" target="_blank" ><?php _e( 'Already registered', 'woocommerce-nimble-payments' ); //LANG: GATEWAY_LINK_BUTTON ?></a>
                        </p>
                </div></div>
        <?php
    }
    
    function gateway_error_notice(){
        ?>
                <div class="error message"><div class="squeezer">
                        <h4><?php _e("Data invalid gateway to accept payments.", "woocommerce-nimble-payments"); //LANG: MESSAGE ERROR TEXT ?></h4>
                </div></div>
        <?php
    }
    
    function order_total_payment_method_replace($total_rows, $order){
        $payment_method_id = get_post_meta( $order->id, '_payment_method', true);
        if ($payment_method_id == $this->id && isset($total_rows['payment_method']) && isset($total_rows['payment_method']['value']) ){
            $total_rows['payment_method']['value'] = __('Card payment', 'woocommerce-nimble-payments'); //LANG: FRONT ORDER PAYMENT METHOD
        }
        return $total_rows;
    }
    
    public function can_refund_order( $order ) {
        //return false;
        $oWoocommerceNimblePayments = Woocommerce_Nimble_Payments::getInstance();
        return $order && $oWoocommerceNimblePayments->get_transaction_id($order) && $oWoocommerceNimblePayments->isOauth3Enabled();
    }

    public function woocommerce_refund_line_items_nimble_payments(){
        $user_id = get_current_user_id();
        //Refunds parameters
        $order_id               = absint( $_POST['order_id'] );
        $refund_amount          = wc_format_decimal( sanitize_text_field( $_POST['refund_amount'] ), wc_get_price_decimals() );
        $refund_reason          = sanitize_text_field( $_POST['refund_reason'] );
        $line_item_qtys         = $_POST['line_item_qtys'];
        $line_item_totals       = $_POST['line_item_totals'];
        $line_item_tax_totals   = $_POST['line_item_tax_totals'];
        $api_refund             = true;
        $restock_refunded_items = $_POST['restock_refunded_items'] === 'true' ? true : false;
        $security               = $_POST['security'];
        $response_data          = array();
        
        //REFUND STEP 1
        // Validate that the refund can occur
        $order = wc_get_order( $order_id );
        $max_refund  = wc_format_decimal( $order->get_total() - $order->get_total_refunded(), wc_get_price_decimals() );
        
        if ( ! $refund_amount || $max_refund < $refund_amount || 0 >= $refund_amount ) {
            wp_send_json_error( array( 'error' => __( 'Invalid refund amount', 'woocommerce' ) ) );
        }

        if ( ! $this->can_refund_order( $order ) ) {
            wp_send_json_error( array( 'error' => __( 'Refund Failed: You must authorize the advanced options Nimble Payments.', 'woocommerce-nimble-payments' ) ) ); //LANG: TODO
        }
        
        $transaction_id = $order->get_transaction_id();
        try {
            $options = get_option('nimble_payments_options');
            unset($options['refreshToken']);
            $params = wp_parse_args($options, $this->get_params());
            $nimble_api = new WP_NimbleAPI($params);
            $total_refund = ($refund_amount) ? $refund_amount : $order->get_total();
            
            $refund = array(
                'amount' => $total_refund * 100,
                'concept' => $refund_reason,
                'reason' => 'REQUEST_BY_CUSTOMER'
            );
            
            $response = NimbleAPIPayments::sendPaymentRefund($nimble_api, $transaction_id, $refund);
        } catch (Exception $e) {
            wp_send_json_error( array( 'error' => $e->getMessage() ) );
        }
        
        //OPEN OPT
        if (isset($response['result']) && isset($response['result']['code']) && 428 == $response['result']['code']
                && isset($response['data']) && isset($response['data']['ticket']) && isset($response['data']['token']) ){
            $ticket = $response['data']['ticket'];
            $otp_info = array(
                'action'    =>  'refund',
                'ticket'    =>  $ticket,
                'token'     =>  $response['data']['token'],
                'order_id'  =>  $order_id,
                'refund_amount'    =>  $refund_amount,
                'refund_reason' => $refund_reason,
                'line_item_qtys' => $line_item_qtys,
                'line_item_totals' => $line_item_totals,
                'line_item_tax_totals' => $line_item_tax_totals,
                'api_refund' => $api_refund,
                'restock_refunded_items' => $restock_refunded_items,
                'security' => $security
            );
            //update_post_meta($order_id, $ticket, 
            update_user_meta($user_id, 'nimblepayments_ticket', $otp_info);
            //TODO: SAVE TICKET + DATA
            
            $back_url = admin_url('admin.php?page=nimble-payments');
            $url_otp = WP_NimbleAPI::getOTPUrl($ticket, $back_url);
            $response_data['otp_url'] = $url_otp;
            wp_send_json_success( $response_data );
        }
        $e = new Exception();
        wp_send_json_error( array( 'error' => $e->getMessage() ) );
    }
    
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );

        if ( ! $this->can_refund_order( $order ) ) {
            return new WP_Error( 'error', __( 'Refund Failed: You must authorize the advanced options Nimble Payments.', 'woocommerce-nimble-payments' ) ); //LANG: TODO
        }
        
        $transaction_id = $order->get_transaction_id();
        try {
            $otp_token = filter_input(INPUT_POST, 'otp_token');
            $params = $this->get_params();
            $params['token'] = $otp_token;
            $nimble_api = new WP_NimbleAPI($params);
            $total_refund = ($amount) ? $amount : $order->get_total();
            
            $refund = array(
                'amount' => $total_refund * 100,
                'concept' => $reason,
                'reason' => 'REQUEST_BY_CUSTOMER'
            );
            
            $response = NimbleAPIPayments::sendPaymentRefund($nimble_api, $transaction_id, $refund);
        } catch (Exception $e) {
            return false;
        }
        
        if (!isset($response['data']) || !isset($response['data']['refundId'])){
            $message = __( 'Refund Failed: ', 'woocommerce-nimble-payments' ); //LANG: TODO --- Reembolso fallido
            if ( isset($response['result']) && isset($response['result']['info']) ){
                $message .= $response['result']['info'];
            }
            return new WP_Error( 'error', $message );
        }
        
        return true;
    }
    
    public function credit_card_form( $args = array(), $fields = array() ) {
        if (is_user_logged_in()){
            $user = wp_get_current_user();            
            
            if(get_user_meta($user->ID, 'np_shipping_hash')){
                $bd_hash = get_user_meta($user->ID, 'np_shipping_hash');
            }
            $hash = $this->get_location_hash_Customer();
            if(isset($bd_hash)  && isset($bd_hash[0]) && isset($hash) && $bd_hash[0] ==  $hash){
                $cards = array();
                try{
                    $nimbleApi = $this->inicialize_nimble_api();
                    $response = NimbleAPIStoredCards::getStoredCards($nimbleApi, $user->ID);
                    if ( isset($response['data']) && isset($response['data']['storedCards']) ){
                        $cards = $response['data']['storedCards'];
                    }
                }
                catch (Exception $e) {
                    //Empty cards
                }
                if (!empty($cards)){
                    include_once( plugin_dir_path(__FILE__). '../templates/nimble-stored-cards.php' );
                }
            }
        }
    }
    
    public function validate_fields() {
        $storedcard = filter_input(INPUT_POST, $this->id . '_storedcard');
        if (!empty($storedcard)){
            $card_selected = json_decode(base64_decode($storedcard));
            if ( ! $card_selected->default ){
                $user = wp_get_current_user();
                $cardInfo = array(
                    "cardBrand" => $card_selected->cardBrand,
                    "maskedPan" => $card_selected->maskedPan,
                    "cardHolderId" => $user->ID
                );
                try{
                    $nimbleApi = $this->inicialize_nimble_api();
                    $response = NimbleAPIStoredCards::selectDefault($nimbleApi, $cardInfo);
                    if ( ! isset($response['result']) || ! isset($response['result']['code']) || $response['result']['code'] != 200 ){
                        throw new Exception();
                    }
                }
                catch (Exception $e) {
                    $message = __( 'Could not pay with the selected card.', 'woocommerce-nimble-payments' ); //LANG: TODO
                    throw new Exception($message);
                }
            }
        }
        return true;
    }
    
    public function get_trasaction_id($order){
        $oWoocommerceNimblePayments = Woocommerce_Nimble_Payments::getInstance();
        return $oWoocommerceNimblePayments->get_transaction_id($order);
    }
    
    public function change_order_status($order_id){
        $order = wc_get_order( $order_id );
        $transaction_id = $this->get_trasaction_id($order);
        $state = 'PENDING';
        
        try{
            $nimbleApi = $this->inicialize_nimble_api();
            $response = NimbleAPIPayments::getPaymentStatus($nimbleApi, $transaction_id);
            if ( isset($response['data']) && isset($response['data']['details']) && count($response['data']['details']) ){
                $state = $response['data']['details'][0]['state'];
            } elseif ( isset($response['result']) && isset($response['result']['code']) && 404 == $response['result']['code'] ) {
                $state = 'NOT_FOUND';
            }
        }
        catch (Exception $e) {
            //Do nothing
        }
        switch ($state){
            case 'ON_HOLD':
                //PAYMENT COMPLETE
                $order->payment_complete();

                //Email sending
                WC_Emails::instance();
                $email_actions = apply_filters( 'woocommerce_email_actions', array(
                    'woocommerce_order_status_pending_to_processing',
                    ) );
                if (in_array('woocommerce_order_status_pending_to_processing', $email_actions)){
                    do_action('woocommerce_order_status_pending_to_processing_notification', $order_id);
                }
                break;
            case 'ABANDONED':
            case 'DENIED':
            case 'ERROR':
                //PAYMENT ERROR
                $order->update_status( 'cancelled' );
                break;
            case 'NOT_FOUND':
                //PAYMENT ERROR
                $order->update_status( 'failed' );
                break;
            default:
                break;
        }
    }

    /* 
     * Get hash location customer
     */
    public static function get_location_hash_Customer() {      
        $location             = array();
        $location['country']  = WC()->customer->get_country();
        $location['state']    = WC()->customer->get_state();
        $location['postcode'] = WC()->customer->get_postcode();
        $location['city']     = WC()->customer->get_city();
        $location['address']  = WC()->customer->get_address();
        $location['address2'] = WC()->customer->get_address_2();
        
        return substr( md5( implode( '', $location ) ), 0, 12 );
    }
}
