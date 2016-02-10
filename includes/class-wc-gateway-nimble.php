<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description of WC_Gateway_Nimble
 *
 * @author acasado
 */
class WC_Gateway_Nimble extends WC_Payment_Gateway {
    //put your code here
    function __construct() {
        $this->id   = 'nimble_payments_gateway';
        $this->icon = plugins_url( 'assets/images/bbva_logo.svg', plugin_dir_path( __FILE__ ) );
        $this->has_fields = false;
        $this->method_title = __( 'Nimble payments', 'woocommerce-nimble-payments' );
        $this->method_description = __( 'Description...', 'woocommerce-nimble-payments' );
        $this->title    =   __( 'Card payment', 'woocommerce-nimble-payments' );
        $this->supports           = array(
			'products',
			'refunds'
		);
        
        // Load the form fields
        $this->init_form_fields();

        // Load the settings
        $this->init_settings();

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
        
     }
     
    function process_payment( $order_id ) {
            global $woocommerce;
            $order = new WC_Order( $order_id );
            
            $nimbleApi = $this->inicialize_nimble_api();
            
            $payment = $this->set_payment_info($order);
            
            $p = new Payments();
            $response = $p->SendPaymentClient($nimbleApi, $payment);
            
            
            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Return thankyou redirect
            return array(
                    'result' => 'success',
                    //'redirect' => $this->get_return_url( $order )
                    //'redirect' => 'success/?hola=hola'
                    'redirect' => $response["data"]["paymentUrl"]
            );
    }
    
    function inicialize_nimble_api(){
        $params = array(
                'clientId' => $this->get_option('seller_id'),
                'clientSecret' => $this->get_option('secret_key'),
                'mode' => 'demo'
        );

        /* High Level call */
        return new NimbleAPI($params);
    }
    
    function set_payment_info($order){
        $payment = array(
            'amount' => $order->get_total() * 100,
            'currency' => $order->get_order_currency( ),
            'customerData' => $order->get_order_number(),
            'paymentSuccessUrl' => home_url( $path = 'success' ),
            'paymentErrorUrl' => home_url( $path = 'error' )
        );
        
        return $payment;
    }
    
    /**
     * Init payment gateway form fields
     */
    function init_form_fields() {

            $this->form_fields = array(
                    'enabled' => array(
                            'title'       => __( 'Enable/Disable', 'woocommerce-nimble-payments' ),
                            'label'       => __( 'Enable Nimble Payments', 'woocommerce-nimble-payments' ),
                            'type'        => 'checkbox',
                            'description' => '',
                            'default'     => 'no'
                    ),
                    'seller_id' => array(
                            'title'       => __( 'Seller ID', 'woocommerce-nimble-payments' ),
                            'type'        => 'text',
                            'description' => __( 'Obtained from https://www.nimblepayments.com', 'woocommerce-nimble-payments' ),
                            'default'     => '',
                            'desc_tip'    => true
                    ),
                    'secret_key' => array(
                            'title'       => __( 'Secret Key', 'woocommerce-nimble-payments' ),
                            'type'        => 'text',
                            'description' => __( 'Obtained from https://www.nimblepayments.com', 'woocommerce-nimble-payments' ),
                            'default'     => '',
                            'desc_tip'    => true
                    ),
                    'sandbox' => array(
                            'title'       => __( 'Use Sandbox', 'woocommerce-nimble-payments' ),
                            'label'       => __( 'Enable sandbox', 'woocommerce-nimble-payments' ),
                            'type'        => 'checkbox',
                            'description' => '',
                            'default'     => 'no'
                    )
       );
    }
}
