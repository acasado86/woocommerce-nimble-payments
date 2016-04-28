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
    var $mode = 'real';

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
            //'refunds'
        );

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
  
    }
    
    function check_credentials($array) {

        $params = array(
            'clientId' => trim(html_entity_decode($array['seller_id'])),
            'clientSecret' => trim(html_entity_decode($array['secret_key'])),
            'mode' => $this->mode
        );

        try {
            $nimbleApi = new WP_NimbleAPI($params);
            //TODO: Verificación credenciales mediante llamada a NimbleApi cuando se actualize el SDK
            $nimbleApi->uri .= 'check';
            $nimbleApi->method = 'GET';
            $response = $nimbleApi->rest_api_call();
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

        //Intermediate reload URL Checkout to prevent invalid nonce generation
        $payment_url = $order->get_checkout_payment_url();
        $checkout_redirect_url = add_query_arg( 'payment_redirect', $this->id, $payment_url );
        
        return array(
            'result' => 'success',
            'redirect' => $checkout_redirect_url
        );
    }

    function inicialize_nimble_api() {
        $params = array(
            'clientId' => trim(html_entity_decode($this->get_option('seller_id'))),
            'clientSecret' => trim(html_entity_decode($this->get_option('secret_key'))),
            'mode' => $this->mode
        );

        /* High Level call */
        return new WP_NimbleAPI($params);
    }

    function set_payment_info($order) {
        $error_url = $order->get_checkout_payment_url();
        $error_url = add_query_arg( 'payment_status', 'error', $error_url );
        
        $payment = array(
            'amount' => $order->get_total() * 100,
            'currency' => $order->get_order_currency(),
            'customerData' => $order->get_order_number(),
            'paymentSuccessUrl' => $this->get_return_url( $order ),
            'paymentErrorUrl' => $error_url
        );
        
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
            $order->payment_complete();
            
            //Email sending
            WC_Emails::instance();
            $email_actions = apply_filters( 'woocommerce_email_actions', array(
                'woocommerce_order_status_pending_to_processing',
                ) );
            if (in_array('woocommerce_order_status_pending_to_processing', $email_actions)){
                do_action('woocommerce_order_status_pending_to_processing_notification', $order_id);
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

                $payment = $this->set_payment_info($order);

                $response = Payments::SendPaymentClient($nimbleApi, $payment);

                if (!isset($response["data"]) || !isset($response["data"]["paymentUrl"])){
                    $order->update_status('nimble-failed', __('Could not connect to the bank. Code ERR_PAG.', 'woocommerce-nimble-payments')); //LANG: ORDER NOTE 404
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
            ?>
            <h3><?php echo $this->title; ?></h3>

            <?php if ( ! $this->get_option('seller_id') ) : ?>
                    <div class="updated woocommerce-message"><div class="squeezer">
                            <h4><?php _e( 'Need an Nimble Payments account?', 'woocommerce-nimble-payments' );//LANG: MESSAGE REGISTRATION TEXT ?></h4>
                            <p class="submit">
                                    <a class="button button-primary" href="https://www.nimblepayments.com/private/registration?utm_source=Woocommerce_Settings&utm_medium=Referral%20Partners&utm_campaign=Creacion-Cuenta&partner=woocommerce" target="_blank"><?php _e( 'Signup now', 'woocommerce-nimble-payments' ); //LANG: MESSAGE REGISTRATION BUTTOM ?></a>
                            </p>
                    </div></div>
            <?php
            elseif ( ("yes" == $this->get_option('enabled')) && !($this->get_option($this->status_field_name) ) ) :
            ?>
                <div class="error message"><div class="squeezer">
                        <h4><?php _e("Data invalid gateway to accept payments.", "woocommerce-nimble-payments"); //LANG: MESSAGE ERROR TEXT ?></h4>
                </div></div>
            <?php endif;
            ?>
            

            <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
            <?php
    }
    
    function order_total_payment_method_replace($total_rows, $order){
        $payment_method_id = get_post_meta( $order->id, '_payment_method', true);
        if ($payment_method_id == $this->id && isset($total_rows['payment_method']) && isset($total_rows['payment_method']['value']) ){
            $total_rows['payment_method']['value'] = __('Card payment', 'woocommerce-nimble-payments'); //LANG: FRONT ORDER PAYMENT METHOD
        }
        return $total_rows;
    }

}
