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

    //put your code here
    function __construct() {
        $this->id = 'nimble_payments_gateway';
        $this->icon = plugins_url('assets/images/bbva_logo.svg', plugin_dir_path(__FILE__));
        $this->has_fields = false;
        $this->method_title = __('Nimble payments', 'woocommerce-nimble-payments');
        //$this->method_description = __('', 'woocommerce-nimble-payments');
        $this->title = __('Card payment', 'woocommerce-nimble-payments');
        $this->supports = array(
            'products',
            'refunds'
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
        
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'payment_complete'));
        
        add_action('before_woocommerce_pay', array($this, 'payment_error'));
  
    }
    
    function check_credentials($array) {

        $params = array(
            'clientId' => trim(html_entity_decode($array['seller_id'])),
            'clientSecret' => trim(html_entity_decode($array['secret_key'])),
            'mode' => 'demo'
        );

        try {
            new NimbleAPI($params);
            $array[$this->status_field_name] = true;
        } catch (Exception $e) {
            $array[$this->status_field_name] = false;
        }

        return $array;
    }

    function process_payment($order_id) {
        global $woocommerce;
        $order = new WC_Order($order_id);
        
        // Mark as nimble-pending (we're awaiting the payment)
        $order->update_status('nimble-pending', __('Awaiting payment via Nimble', 'woocommerce-nimble-payment'));
        
        try{
            $nimbleApi = $this->inicialize_nimble_api();

            $payment = $this->set_payment_info($order);
            
            $p = new Payments();
            $response = $p->SendPaymentClient($nimbleApi, $payment);
        }
        catch (Exception $e) {
            $order->update_status('nimble-failed', __('Could not connect to the bank right now. Try again later.', 'woocommerce-nimble-payment'));
            throw new Exception(__('Could not connect to the bank right now. Try again later.', 'woocommerce-nimble-payments'));
        }

        // Reduce stock levels
        //$order->reduce_order_stock();

        // Remove cart
        //$woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $response["data"]["paymentUrl"]
        );
    }

    function inicialize_nimble_api() {
        $params = array(
            'clientId' => trim(html_entity_decode($this->get_option('seller_id'))),
            'clientSecret' => trim(html_entity_decode($this->get_option('secret_key'))),
            'mode' => 'demo'
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
             'nimble_help' => array(
                'type' => 'nimble_help',
                'description' => '',
                'default' => '',
                'desc_tip' => false
            ),
            $this->status_field_name => array(
                'title' => __('Check Values', 'woocommerce-nimble-payments'),
                'type' => 'status_nimble',
                'description' => __('Obtained from https://www.nimblepayments.com', 'woocommerce-nimble-payments'),
                'default' => '',
                'desc_tip' => false
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-nimble-payments'),
                'label' => __('Enable Nimble Payments', 'woocommerce-nimble-payments'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
            'seller_id' => array(
                'title' => __('Seller ID', 'woocommerce-nimble-payments'),
                'type' => 'text',
                'description' => __('Obtained from https://www.nimblepayments.com', 'woocommerce-nimble-payments'),
                'default' => '',
                'desc_tip' => true
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'woocommerce-nimble-payments'),
                'type' => 'text',
                'description' => __('Obtained from https://www.nimblepayments.com', 'woocommerce-nimble-payments'),
                'default' => '',
                'desc_tip' => true
            )
        );
    }

    public function generate_nimble_help_html($key, $data) {

        $field = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'class' => ''
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
            <div class="textNimble" id="u28">
                <p>
                    <span id="n_title"><?php _e("NIMBLE PAYMENTS: ","woocommerce-nimble-payments")?></span>
                    <span id="n_title2"><?php _e("WELCOME CHANGE","woocommerce-nimble-payments")?></span>
                </p>
            </div>
            <div class="textNimble" id="u29">
                <p>
                    <span id="n_subtitle1"><?php _e("Getting started with Nimble Payments in two steps.","woocommerce-nimble-payments")?></span>
                    <span id="n_subtitle2"><?php _e("Watch video tutorial","woocommerce-nimble-payments")?></span>
                </p>
            </div>
            <div class="textNimble" id="u30">
                <p>
                    <span id="n_step1"><?php _e("Step 1 ","woocommerce-nimble-payments")?></span>
                    <span id="n_step11"><?php _e("- signup Nimble Payments.</span>","woocommerce-nimble-payments")?>
                </p>
            </div>
            <div class="textNimble" id="u31">
                <p>
                    <span id="n_registro_text"><?php _e("If you are not registered yet in Payments Nimble , you can register completely free and online .

You just need an email and a password to start testing.","woocommerce-nimble-payments")?></span>
                </p>
            </div>
            <div class="textNimble" id="u32">
                <p>
                    <span id="n_registro"><?php _e("CLICK HERE","woocommerce-nimble-payments")?></span>
                </p>
            </div>
            <div class="textNimble" id="u33">
                <p>
                    <span id="n_step2"><?php _e("Step 2 ","woocommerce-nimble-payments")?></span>
                    <span id="n_step22"><?php _e("- configure your module.</span>","woocommerce-nimble-payments")?>
                </p>
            </div>
            <div class="textNimble" id="u34">
                <p>
                    <span id="identification"><?php _e("To accept payments only you have to give the IDs you get in Nimble Payments

If you do not have to hand Check them out here.","woocommerce-nimble-payments")?></span>
                </p>
            </div>
            <div class="textNimble" id="u35">
                <p>
                    <span id="enter_nimble"><?php _e("ENTER NIMBLE PAYMENTS","woocommerce-nimble-payments")?></span>
                </p>
            </div>
        <?php
        return ob_get_clean();
    }

    public function generate_status_nimble_html($key, $data) {

        $field = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'class' => ''
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();

        if ($this->get_option('enabled') == "yes") {
            ?>
            <tr valign="top">
                <th scope="row" class="titleCheck">
            <?php if ($this->get_option($key) == true) { ?>
                        <label class="checkValidate" for="<?php echo esc_attr($field); ?>"><?php _e("Valid data gateway to accept payments.", "woocommerce-nimble-payments"); ?></label> 
                    <?php } else { ?>
                        <label class="checkError "for="<?php echo esc_attr($field); ?>"><?php _e("Data invalid gateway to accept payments.", "woocommerce-nimble-payments"); ?></label> 
                    <?php } ?>
                </th>
            </tr>	
            <?php
        }

        return ob_get_clean();
    }
    
    function checkout_order_received_url($order_received_url, $order) {
        if ($order->post_status == "wc-nimble-pending"){
            $nonce = wp_create_nonce();
            $order_received_url = add_query_arg( 'payment', $nonce, $order_received_url );
        }
        return $order_received_url;
    }
    
    function payment_complete($order_id){
        global $wp;
        
        if (isset($wp->query_vars['order-received']) && isset($_GET['payment']) && wp_verify_nonce($_GET['payment'])) {
            $order = new WC_Order($order_id);
            $order->payment_complete();
        }
    }
    
    function payment_error(){
        
        if ( isset($_GET['payment_status']) ){
            switch ($_GET['payment_status']){
                case 'error':
                    echo '<div class="woocommerce-error">' . __( 'Card payment was rejected. Please try again.', 'woocommerce' ) . '</div>';
                    break;
            }
        }
    }

}
