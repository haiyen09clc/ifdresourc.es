<?php
/*
Plugin Name: IZW Auto Complete Checkout
Plugin URI: https://izweb.biz
Description: Populates full name field in woocommerce checkout with personnel database in TablePress
Version: 1.0.0
Author: Sy Le
Author URI:
Author email: levansy@gmail.com
Text Domain: greentech
Domain Path: /i18n
License: GPL 2
*/
/**
 * IZW Auto Complete Class
 */
if (!class_exists('IZW_Auto_Complete')) {
    class IZW_Auto_Complete
    {
        function __construct()
        {
            add_action( 'wp_enqueue_scripts', array( $this, 'front_scripts' ) );
            add_action( 'wp_footer', array( $this, 'wp_footer') );
            add_action( 'woocommerce_before_save_order_items', array( $this, 'woocommerce_before_save_order_items') ,10 ,2);
            add_action( 'woocommerce_ajax_order_items_added', array( $this, 'woocommerce_ajax_order_items_added') ,10 ,2);
            add_action( 'woocommerce_before_delete_order_item', array( $this, 'woocommerce_before_delete_order_item') ,10 ,1);
            add_filter( 'wc_order_statuses', array( $this,  'wc_renaming_order_status') );
            add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this,  'woocommerce_order_item_get_formatted_meta_data') );
            add_filter( 'wclabels_formatted_address', array( $this,  'replace_qr_code'), 10, 3 );
            add_action( 'admin_head', array( $this, 'styling_admin_order_list' ));
            add_action( 'login_head', array( $this, 'styling_login_page' ));
            add_action( 'wp_authenticate', array( $this, 'wp_authenticate' ),10,2);
//            add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'woocommerce_after_checkout_billing_form' ));
            //add_filter( 'login_redirect', array( $this,'my_login_redirect'), 10, 3 );
            add_action( 'wp_ajax_check_order_limit', array( $this,'check_order_limit') );
            add_action( 'wp_ajax_nopriv_check_order_limit', array( $this,'check_order_limit') );
            add_filter( 'woocommerce_product_data_tabs',array( $this,'custom_product_tabs'));
            add_filter( 'woocommerce_product_data_panels',array( $this,'order_limits_product_tab_content'));
            add_action( 'woocommerce_process_product_meta', array( $this,'save_order_limits_fields'  ));
            add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_order_limit' ), 10, 2 );
            add_filter( 'woocommerce_loop_add_to_cart_link', array( $this,'change_text_add_to_cart_button'), 10, 2 );
        }
    }
    new IZW_Auto_Complete();
}