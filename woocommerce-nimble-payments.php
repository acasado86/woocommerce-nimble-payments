<?php

/*
Plugin Name: WooCommerce Nimble Payments
Plugin URI: https://www.nimblepayments.com
Description: Nimble Payments is an online payment gateway supported by BBVA that enables you to accept online payments flexibly and safely.
Version: 1.0.4
Author: BBVA
Author URI: 
License: GPLv2
Text Domain: woocommerce-nimble-payments
Domain Path: /lang/
*/

/* 
Copyright (C) 2016 BBVA

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

class Woocommerce_Nimble_Payments {
    protected static $instance = null;

    static function & getInstance() {
        if ( is_null(self::$instance) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    
    function __construct() {
        // If instance is null, create it. Prevent creating multiple instances of this class
        if ( is_null( self::$instance ) ) {
            self::$instance = $this;
            
            add_action( 'init', array( $this, 'load_text_domain' ), 0 );
            
            add_action( 'plugins_loaded', array( $this, 'init_your_gateway_class' ) );
            
            add_filter( 'woocommerce_payment_gateways', array( $this, 'add_your_gateway_class' ) );
            
            add_action( 'admin_menu', array( $this, 'nimble_menu'));
            
            add_filter( 'wc_order_statuses', array( $this, 'add_custom_statuses' ) );
            
            add_action( 'init', array( $this, 'register_post_status' ), 9 );
            
            add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'valid_order_statuses_for_payment' ) );
            
            add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'valid_order_statuses_for_payment' ) );
            
            add_action('admin_enqueue_scripts', array($this, 'load_nimble_style'));
            
            //Custom template checkout/payment-method.php
            add_filter( 'wc_get_template', array( $this, 'filter_templates_checkout' ), 10, 3);
            
        }
    }
    
    function load_nimble_style($hook) {
        wp_register_style('wp_nimble_backend_css', plugins_url('css/wp-nimble-backend.css', __FILE__), false, '20160222');
        wp_enqueue_style('wp_nimble_backend_css');
        
        if( "woocommerce_page_wc-settings" == $hook || ( 'edit.php' == $hook && isset( $_GET['post_type'] ) && 'shop_order' == $_GET['post_type'] ) ){
            wp_register_style('nimble_setting_css', plugins_url('css/nimble_setting.css', __FILE__), false, '20160217');
            wp_enqueue_style('nimble_setting_css');
        }
    }
    
    function nimble_menu(){
        if ( !defined('WP_CONTENT_URL') )
            define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content'); // full url - WP_CONTENT_DIR is defined further up
        
        add_object_page( 'Nimble Payments', 'Nimble Payments', 'manage_options', 'wc-settings&tab=checkout&section=wc_gateway_nimble', array( $this, 'nimble_options' ));
    }
    
    function nimble_options() {
        //to do
    }
    
    function activar_plugin() {
    }
    
    function desactivar_plugin() {
        delete_option( 'woocommerce_nimble_payments_gateway_settings' );
    }
    
    /**
     * Initialise Gateway Settings Form Fields
     */
     function init_your_gateway_class() {
         include_once( 'includes/class-wc-gateway-nimble.php' );
         require_once 'lib/Nimble/base/NimbleAPI.php';
         require_once 'lib/Nimble/extensions/wordpress/WP_NimbleAPI.php';
    } // End init_form_fields()
    
    function add_your_gateway_class( $methods ) {
	$methods[] = 'WC_Gateway_Nimble'; 
	return $methods;
    }
    
    function load_text_domain(){
        load_plugin_textdomain('woocommerce-nimble-payments', null, plugin_basename(dirname(__FILE__)) . "/lang");
    }
    
    function add_custom_statuses($order_statuses){
        $new_statuses = array(
		'wc-nimble-pending'    => _x( 'Pending Payment (Nimble Payments)', 'Order status', 'woocommerce-nimble-payments' ), //LANG: PENDING STATUS
		'wc-nimble-failed'     => _x( 'Failed (Nimble Payments)', 'Order status', 'woocommerce-nimble-payments' ), //LANG: FAILED STATUS
	);
        return array_merge($order_statuses, $new_statuses);
    }
    
    function register_post_status() {
        register_post_status('wc-nimble-pending', array(
            'label' =>  _x( 'Pending Payment (Nimble Payments)', 'Order status', 'woocommerce-nimble-payments' ), //LANG: PENDING STATUS
            'public'    => false,
            'exclude_from_search'   =>  false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' =>  true,
            'label_count' => _n_noop('Pending Payment (Nimble Payments) <span class="count">(%s)</span>', 'Pending Payment (Nimble Payments) <span class="count">(%s)</span>', 'woocommerce') //LANG: PENDING STATUS LIST
        ));
        register_post_status('wc-nimble-failed', array(
            'label' =>  _x( 'Failed (Nimble Payments)', 'Order status', 'woocommerce-nimble-payments' ), //LANG: FAILED STATUS
            'public'    => false,
            'exclude_from_search'   =>  false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' =>  true,
            'label_count'   =>    _n_noop('Failed (Nimble Payments) <span class="count">(%s)</span>', 'Failed (Nimble Payments) <span class="count">(%s)</span>', 'woocommerce') //LANG: FAILED LIST
        ));
    }
    
    function valid_order_statuses_for_payment($order_statuses){
        $order_statuses[]='nimble-pending';
        return $order_statuses;
    }
    
    function filter_templates_checkout($located, $template_name, $args){
        if ( 'checkout/payment-method.php' == $template_name  && isset($args['gateway']) && 'nimble_payments_gateway' == $args['gateway']->id ){
            $located = plugin_dir_path(__FILE__) . "templates/nimble-checkout-payment-method.php";
        }
        elseif ( 'checkout/thankyou.php' == $template_name && isset($args['order']) ){
            $order = $args['order'];
            $payment_method_id = get_post_meta( $order->id, '_payment_method', true);
            if ('nimble_payments_gateway' == $payment_method_id){
                $located = plugin_dir_path(__FILE__) . "templates/nimble-checkout-thankyou.php";
            }
        }
        return $located;
    }

}


if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    $oWoocommerceNimblePayments = Woocommerce_Nimble_Payments::getInstance();
}
register_activation_hook(__FILE__, array($oWoocommerceNimblePayments, 'activar_plugin'));
register_deactivation_hook(__FILE__, array( $oWoocommerceNimblePayments, 'desactivar_plugin'));
