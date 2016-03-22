<?php

/*
Plugin Name: NimblePayments
Plugin URI: https://www.nimblepayments.com
Description: Nimble Payments is an online payment gateway supported by BBVA that enables you to accept online payments flexibly and safely.
Version: 1.0.5
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
    var $slug = 'nimble-payments';
    var $domain = 'woocommerce-nimble-payments';
    var $options_name = 'nimble_payments_options';
    protected static $gateway = null;
    protected static $params = null;

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
            
            add_action( 'woocommerce_init', array( $this, 'gateway_loaded'), 0);
            
            add_action( 'admin_menu', array( $this, 'nimble_menu'));
            
            add_filter( 'wc_order_statuses', array( $this, 'add_custom_statuses' ) );
            
            add_action( 'init', array( $this, 'register_post_status' ), 9 );
            
            add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'valid_order_statuses_for_payment' ) );
            
            add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'valid_order_statuses_for_payment' ) );
            
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') );
            
            add_action('wp_login', array($this, 'login_actions'), 10, 2);
            
            add_action( 'admin_notices', array( $this, 'admin_notices' ), 0 );
            
            add_action( 'wp_ajax_nimble_payments_oauth3', array( $this, 'ajax_oauth3' ) );
            
            //Custom template checkout/payment-method.php
            add_filter( 'wc_get_template', array( $this, 'filter_templates_checkout' ), 10, 3);
            
            $this->load_settings();
        }
    }
    
    function load_settings(){
        $options = get_option($this->options_name);
        $this->oauth3_enabled = ( $options && isset($options['token']) ) ? true : false;
    }
    
    function gateway_loaded(){
        //Obtain wc_gateway_nimble
        $available_gateways = WC()->payment_gateways()->payment_gateways();
        $gateway_active = isset($available_gateways['nimble_payments_gateway']) ? $available_gateways['nimble_payments_gateway']->is_available() : false;
        if ( $gateway_active ){
            self::$gateway = $available_gateways['nimble_payments_gateway'];
            self::$params = self::$gateway->get_params();
            
            //Refresh Token if neccesary
            $this->refreshToken();
        }
    }
            
    function admin_enqueue_scripts($hook) {
        wp_enqueue_script('nimble-payments-js', plugins_url("js/nimble-payments.js", __FILE__), array('jquery'), '1.0.0');
        
        wp_register_style('wp_nimble_backend_css', plugins_url('css/wp-nimble-backend.css', __FILE__), false, '20160310');
        wp_enqueue_style('wp_nimble_backend_css');
        
        if( "woocommerce_page_wc-settings" == $hook || ( 'edit.php' == $hook && isset( $_GET['post_type'] ) && 'shop_order' == $_GET['post_type'] ) ){
            wp_register_style('nimble_setting_css', plugins_url('css/nimble_setting.css', __FILE__), false, '20160217');
            wp_enqueue_style('nimble_setting_css');
        }
    }
    
    function nimble_menu(){
        if ( !defined('WP_CONTENT_URL') )
            define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content'); // full url - WP_CONTENT_DIR is defined further up
        
        //add_object_page( 'Nimble Payments', 'Nimble Payments', 'manage_options', 'wc-settings&tab=checkout&section=wc_gateway_nimble', array( $this, 'nimble_options' ));
        add_object_page( 'Nimble Payments', 'Nimble Payments', 'manage_options', $this->slug, array( $this, 'nimble_options' ));
        //add_submenu_page($this->slug, __('Settings', $this->domain), __('Settings', $this->domain), 'manage_options', $this->slug.'-settings', array( $this, 'menu_settings'));
        
    }
    
    function nimble_options() {
        //Obtain token & reflesh token with code
        if ( ! $this->oauth3_enabled && isset($_REQUEST['code']) ){
            $code = filter_input(INPUT_GET, 'code');
            $this->validateOauthCode($code);
        }
        
        //Show resumen
        if ( $this->oauth3_enabled ){
            //var_dump(get_option($this->options_name));
            $this->getResumen();
            //delete_option($this->options_name);
        }
        
        //Show Authentication URL to AOUTH3
        if (! $this->oauth3_enabled ){
            $this->oauth3_url = $this->getOauth3Url();
            include_once( 'templates/nimble-oauth-form.php' );
        }
    }
    
    function admin_notices() {
        //Show Authentication URL to AOUTH3
        if ( ! $this->oauth3_enabled && ! isset($_REQUEST['code']) ){
        ?>
            <div id="np-authorize-message" class="updated message"><div class="squeezer">
                    <h4 class="info"><?php _e("No se ha podido realizar la operación.", "woocommerce-nimble-payments"); //LANG: TODO ?></h4>
                    <h4><?php _e("Todavía no has autorizado a WooCommerce para realizar operaciones en Nimble Payments.", "woocommerce-nimble-payments"); //LANG: TODO ?></h4>
                    <p class="submit">
                        <a id="np-oauth3" class="button button-primary" href="#" target="_blank"><?php _e( 'Authorize', 'woocommerce-nimble-payments' ); //LANG: TODO ?></a>
                    </p>
            </div></div>
        <?php
        }
    }
    
    function ajax_oauth3(){
        $data = array();
        $data['url_oauth3'] = $this->getOauth3Url();
        echo json_encode($data);
        die();
    }
    
    function menu_settings() {
        //to do
    }
    
    function activar_plugin() {
    }
    
    function desactivar_plugin() {
        delete_option( 'woocommerce_nimble_payments_gateway_settings' );
        delete_option( $this->options_name );
    }
    
    /**
     * Initialise Gateway Settings Form Fields
     */
     function init_your_gateway_class() {
        include_once( 'includes/class-wc-gateway-nimble.php' );
        require_once 'lib/Nimble/base/NimbleAPI.php';
        require_once 'lib/Nimble/extensions/wordpress/WP_NimbleAPI.php';
        require_once 'lib/Nimble/api/NimbleAPIPayments.php';
        require_once 'lib/Nimble/api/NimbleAPIReport.php';
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
    
    /**
     * 
     * @return $url to OAUTH 3 step or false
     */
    function getOauth3Url(){
        if ( self::$gateway ){
            try {
                $nimble_api = new WP_NimbleAPI(self::$params);
                $url=$nimble_api->getOauth3Url();
            } catch (Exception $e) {
                return false;
            }
        }
        return self::$gateway ? $url : false;
    }
    
    /**
     * Validate Oauth Code and update options
     */
    function validateOauthCode($code){
        if ( self::$gateway ){
            try {
                $params = array(
                    'authType' => '3legged',
                    'oauth_code' => $code
                );
                $params = wp_parse_args($params, self::$params);
                $nimble_api = new WP_NimbleAPI($params);
                $options = array(
                    'token' => $nimble_api->authorization->getAccessToken(),
                    'refreshToken' => $nimble_api->authorization->getRefreshToken()
                );
                update_option($this->options_name, $options);
                $this->oauth3_enabled = true;
            } catch (Exception $e) {
                $this->oauth3_enabled = false;
            }
        }
    }
    
    /**
     * Refresh token
     */
    function refreshToken(){
        if ( self::$gateway ){
            try {
                $options = get_option($this->options_name);
                if (is_array($options) && isset($options['login']) && $options['login']){
                    $params = wp_parse_args($options, self::$params);
                    $nimble_api = new WP_NimbleAPI($params);
                    $options = array(
                        'token' => $nimble_api->authorization->getAccessToken(),
                        'refreshToken' => $nimble_api->authorization->getRefreshToken()
                    );
                    update_option($this->options_name, $options);
                    $this->oauth3_enabled = true;
                }
            } catch (Exception $e) {
                $this->oauth3_enabled = false;
            }
        }
    }
    
    /*
     * Set var login = True
     */
    function login_actions($user_login, $user){
        if ( user_can($user, 'manage_options') ){
            $options = get_option($this->options_name);
            if (is_array($options)){
                $options['login'] = true;
                update_option($this->options_name, $options);
            }
        }
    }
    
    /*
     * Get Resumen
     */
    function getResumen(){
        if ( self::$gateway ){
            try {
                $options = get_option($this->options_name);
                unset($options['refreshToken']);
                $params = wp_parse_args($options, self::$params);
                $nimble_api = new WP_NimbleAPI($params);
                $commerces = NimbleAPIReport::getCommerces($nimble_api, 'enabled');
                if (!isset($commerces['error'])){
                    foreach ($commerces as $IdCommerce => $data){
                        $title = $data['name'];
                        $summary = NimbleAPIReport::getSummary($nimble_api, $IdCommerce);
                        include_once( 'templates/nimble-summary.php' );
                    }
                } else {
                    $this->oauth3_enabled = false;
                }
                
            } catch (Exception $e) {
                $this->oauth3_enabled = false;
            }
        }
    }
    
    function isOauth3Enabled(){
        return $this->oauth3_enabled;
    }
    
    /**
     * Get assigned transaction_id on Nimble Payments if neccesary
     * @param type $order
     * @return type
     */
    function get_transaction_id($order){
        $transaction_id = $order->get_transaction_id();
        if ( !$transaction_id && self::$gateway ){
            try {
                $order_total = $order->get_total() * 100;
                $options = get_option($this->options_name);
                unset($options['refreshToken']);
                $params = wp_parse_args($options, self::$params);
                $nimble_api = new WP_NimbleAPI($params);
                $commerces = NimbleAPIReport::getCommerces($nimble_api, 'enabled');
                foreach ($commerces as $IdCommerce => $data){
                    $payments = NimbleAPIPayments::getPaymentList($nimble_api, $IdCommerce, array('referenceId' => $order->id));
                    foreach ($payments as $payment){
                        if ($payment['customerData'] == $order->id
                                && $payment['amount']['amount'] == $order_total
                                && $payment['amount']['currency'] == $order->get_order_currency()
                                ){
                            $transaction_id = $payment['idTransaction'];
                            update_post_meta( $order->id, '_transaction_id', $transaction_id );
                            break;
                        }
                    }
                }
                
            } catch (Exception $e) {
            }
        }
        return $transaction_id;
    }

}


if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    $oWoocommerceNimblePayments = Woocommerce_Nimble_Payments::getInstance();
}
register_activation_hook(__FILE__, array($oWoocommerceNimblePayments, 'activar_plugin'));
register_deactivation_hook(__FILE__, array( $oWoocommerceNimblePayments, 'desactivar_plugin'));
