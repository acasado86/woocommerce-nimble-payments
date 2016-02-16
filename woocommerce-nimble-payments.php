<?php

/*
Plugin Name: WooCommerce Nimble Payments
Plugin URI: https://www.nimblepayments.com
Description: Add Nimble payment services to your WooCommmerce.
Version: 1.0.0
Author: acasado
Author URI: 
License: GPLv2
Text Domain: woocommerce-nimble-payments
Domain Path: /lang/
*/

/* 
Copyright (C) 2016 acasado

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

class WoocommerceNimblePayments {
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
            
        }
    }
    
    function nimble_menu(){
        if ( !defined('WP_CONTENT_URL') )
            define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content'); // full url - WP_CONTENT_DIR is defined further up
        if ( !defined($icon_url) ){
            $icon_url=plugins_url( 'assets/images/nimble-img.png', __FILE__ );
        } 
            
        add_object_page( 'Nimble', 'Nimble', 'manage_options', 'wc-settings&tab=checkout&section=wc_gateway_nimble', array( $this, 'nimble_options' ), $icon_url);
    }
    
    function nimble_options() {
        //to do
    }
    
    function activar_plugin() {
        if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            return;
        }
        wp_die('WooCommerce plugin must be installed.');
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
         require_once 'lib-extensions/Nimble/wordpress/WP_NimbleAPI.php';
    } // End init_form_fields()
    
    function add_your_gateway_class( $methods ) {
	$methods[] = 'WC_Gateway_Nimble'; 
	return $methods;
    }
    
    function load_text_domain(){
        load_plugin_textdomain('woocommerce-nimble-payments', null, basename(dirname(__FILE__)) . "/lang");
    }
    
    function add_custom_statuses($order_statuses){
        $new_statuses = array(
		'wc-nimble-pending'    => _x( 'Pending Payment (Nimble)', 'Order status', 'woocommerce-nimble-payments' ),
		'wc-nimble-failed'     => _x( 'Failed (Nimble)', 'Order status', 'woocommerce-nimble-payments' ),
	);
        return array_merge($order_statuses, $new_statuses);
    }
    
    function register_post_status() {
        register_post_status('wc-nimble-pending', array(
            'label' => _x( 'Pending Payment (Nimble)', 'Order status', 'woocommerce-nimble-payments' ),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Pending Payment (Nimble) <span class="count">(%s)</span>', 'Pending Payment (Nimble) <span class="count">(%s)</span>', 'woocommerce')
        ));
        register_post_status('wc-nimble-failed', array(
            'label' => _x( 'Failed (Nimble)', 'Order status', 'woocommerce-nimble-payments' ),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Failed (Nimble) <span class="count">(%s)</span>', 'Failed (Nimble) <span class="count">(%s)</span>', 'woocommerce')
        ));
    }
    
    function valid_order_statuses_for_payment($order_statuses){
        $order_statuses[]='nimble-pending';
        return $order_statuses;
    }

}


$oWoocommerceNimblePayments = WoocommerceNimblePayments::getInstance();
register_activation_hook(__FILE__, array($oWoocommerceNimblePayments, 'activar_plugin'));
register_deactivation_hook(__FILE__, array( $oWoocommerceNimblePayments, 'desactivar_plugin'));
