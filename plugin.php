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

    function change_text_add_to_cart_button( $button, $product  ) {
        $product_fields = wcff()->dao->load_fields_for_product($product->get_id(), 'wccpf');

        if(!empty($product_fields)){
            $button_text = __("Select options", "woocommerce");
            $button = '<a class="button" href="' . $product->get_permalink() . '">' . $button_text . '</a>';
        }
        return $button;
    }
    
    function check_order_limit($posted, $errors_obj){
        $errors = array();
        //get order items
        if ( ! WC()->cart->is_empty() ) {
            $product_ids = array();
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $product = wc_get_product($cart_item['product_id']);
                //variation_id -> red clothes, product_id -> clothes
                if ($product->get_meta( '_product_rules' ) == 'yes') {
                    if (isset($cart_item['variation_id']) && $product->get_meta('_variations') == 'no') {
                        $product_ids[] = $cart_item['variation_id'];
                        $check_id = $cart_item['variation_id'];
                    } else {
                        $product_ids[] = $cart_item['product_id'];
                        $check_id = $cart_item['product_id'];
                    }

                    //$purchase_data[$cart_item['product_id']]['qty'] = $cart_item['quantity'];
                    $purchase_data[$check_id]['allow'] = $product->get_meta('_product_quantity');
                    $purchase_data[$check_id]['month'] = $product->get_meta('_month');
                    $purchase_data[$check_id]['variations'] = $product->get_meta('_variations');
                    $purchase_data[$check_id]['qty'] = $cart_item['quantity'];
                    $purchase_data[$check_id]['remain'] = (int)$purchase_data[$check_id]['allow'] - $purchase_data[$check_id]['qty'];
                    // echo $purchase_data[$check_id]['remain'];
                    if ($purchase_data[$check_id]['remain']<0){
                        $errors_obj->add( 'validation', sprintf( __( 'Pemmission denied. Per our rules you can buy %s %s per %s month(s)!', 'woocommerce' ), '<strong>' .$purchase_data[$check_id]['allow'] . '</strong>','<strong>' . $product->get_name(). '</strong>','<strong>' .$purchase_data[$check_id]['month']. '</strong>' ) );
                    }

                }
            }
        }


        //get orders of that $name
        global $wpdb;
        $name = $posted['billing_first_name'];
        $orders = $wpdb->get_col('SELECT post_id FROM '.$wpdb->postmeta.' WHERE meta_key="_billing_first_name" AND meta_value="'.$name.'" ORDER BY meta_id DESC');

        //loop to get all products from orders
        if (!empty($orders))
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);

                //get products of order
                foreach ( $order->get_items() as $item ) {
                    if ( ! $item->is_type( 'line_item' ) ) {
                        continue;
                    }

                    $product            = $item->get_product();
                    $product_id         = $product->get_type() == 'variation' ?$product->get_parent_id():$product->get_id();
                    $variation_id       = $item->get_variation_id();
                    if (in_array($product_id, $product_ids)) {
                        $check_id = $product_id;
                    }elseif (in_array($variation_id, $product_ids)) {
                        $check_id = $variation_id;
                    } else continue;



                    $order_date         = $order->get_date_created()->getTimestamp();
                    $months             = $purchase_data[$check_id]['month'];
                    $check_time         = strtotime("-$months months");
                    //echo $order->get_id(), " - ", $order->get_date_created()->format('m-d-Y'), " - ", $item->get_name(), " - ", $item->get_quantity(), " - ", date('Y-m-d', $check_time), "\n";

                    //if variation
                    if ($order_date>=$check_time) {
                        //$product = $product
                        $purchase_data[$check_id]['remain'] = $purchase_data[$check_id]['remain']-$item->get_quantity();
                        // echo "\n", $order->get_id(), "," ,$item->get_quantity(), " , ", $purchase_data[$check_id]['remain'];

                        if ($purchase_data[$check_id]['remain'] < 0 ) {

                            $errors[$check_id] = $item->get_name();
                            foreach ($product_ids as $key=>$id) {
                                if ($id == $check_id) unset($product_ids[$key]);
                            }
                            $last_order_date[$check_id] = $order->get_date_created();
                            break;
                        }
                    }

                }

            }




        if (!empty($errors)) {
            foreach ($errors as $id=>$error) {
                $errors_obj->add( 'validation', sprintf( __( 'Pemmission denied. Your last item %s was ordered on %s.<br>Per our rules <a href="%s" target="_blank" class="permission_denied">review them here</a> you are eligible for your next %s on %s', 'woocommerce' ), '<strong>' .$error . '</strong>', $last_order_date[$id]->date('m-d-Y'), site_url("/allotments/"), '<strong>' .$error . '</strong>', $last_order_date[$id]->modify("+{$purchase_data[$id]['month']} months")->date('m-d-Y')) );
            }
        }


    }

    /**
     * Add tab 'order_limits to product data.
     */
    function custom_product_tabs( $tabs) {
        $tabs['orderlimits'] = array(
            'label'		=> __( 'Order Limits', 'woocommerce' ),
            'target'	=> 'order_limits',
            'class'		=> array( 'show_if_simple', 'show_if_variable'  ),

        );
        return $tabs;
    }

    /**
     * Contents of the order limit product tab.
     */
    function order_limits_product_tab_content() {
        global $post;

        ?>
        <div id="order_limits" class="panel woocommerce_options_panel">
            <?php

            // Note the 'id' attribute needs to match the 'target' parameter set above

            woocommerce_wp_checkbox( array(
                'id' 		=> '_product_rules',
                'label' 	=> __( 'Product rules', 'woocommerce' ),
                'description' => ' Allow',
            ) );

            woocommerce_wp_text_input( array(
                'id' 		=> '_product_quantity',
                //'label' 	=> __( 'product quantity', 'woocommerce' ),
                'description' => $post->post_title . ' per user per',
                'type'              => 'number',
                'required' => 'true',
                'custom_attributes' => array(
                    'step' 	=> 'any',
                    'min'	=> '1'
                ),
                'style' => 'width:40px;'
            ) );

            woocommerce_wp_select( array(
                'id' 		=> '_month',
                // 'label' 	=> __( 'Product rules', 'woocommerce' ),
                'description' => 'months',
                'options' => array_combine(range(1,12), range(1,12)),
                'style' => 'width:50px;'
            ) );

            // show this check box if the product has variations
            echo '<div class="show_if_variable">';
            woocommerce_wp_checkbox( array(
                'id' 		=> '_variations',
                'description' 	=> __( 'Apply to product variations as well', 'woocommerce' )
            ) );
            echo '</div>';
            ?>
        </div>
        <?php
    }

    /**
     * Save the custom fields.
     */
    function save_order_limits_fields( $post_id ) {


        $product = wc_get_product( $post_id );

        $product_rules = isset( $_POST['_product_rules'] ) ? 'yes' : 'no';
        $product->update_meta_data( '_product_rules', $product_rules );

        $product_quantity = isset( $_POST[ '_product_quantity' ] ) ? ( $_POST[ '_product_quantity' ]):'1';
        $product->update_meta_data('_product_quantity',   $product_quantity);

        $month = isset( $_POST[ '_month' ] ) ? ( $_POST[ '_month' ]):'1';
        $product->update_meta_data('_month',    $month );

        $product_variations = isset( $_POST['_variations'] ) ? 'yes' : 'no';
        $product->update_meta_data('_variations',   $product_variations);

        $product->save();
    }

    /**
     * Renaming order status: processing-> new.
     */
    function wc_renaming_order_status( $order_statuses ) {
        if (is_admin()) {
            foreach ( $order_statuses as $key => $status ) {
                if ( 'wc-processing' === $key )
                    $order_statuses['wc-processing'] = _x( 'New', 'Order status', 'woocommerce' );
            }
        }

        return $order_statuses;
    }

    function woocommerce_order_item_get_formatted_meta_data( $formatted_meta){
        if (!empty($_GET['post_type'])  && $_GET['post_type']=='shop_order' ) {
            foreach ($formatted_meta as $key=> $meta) {
                if ($meta->key!='Backordered') unset($formatted_meta[$key]);
            }
        }
        return $formatted_meta;

    }

    function wp_authenticate(&$username, &$password){

        if (empty($username) &&  !empty($password)  && !empty($_REQUEST['redirect_to']) && strpos( $_REQUEST['redirect_to'], 'preview_order')) {
            global $wpdb;

            $find_usernames = $wpdb->get_results('SELECT ID, user_login,user_pass FROM '.$wpdb->users.' WHERE user_login LIKE "delivery%" OR user_login="sylevan"');
            foreach ($find_usernames as $user) {
                if (wp_check_password($password, $user->user_pass, $user->ID)){
                    $username = $user->user_login;
                }
            }
        }


        return;
    }
    function styling_admin_order_list() {
        global $pagenow, $post;
        echo '<style>.orderlimits_tab a::before{content: "\f508"!important;} </style>';
        if( $pagenow != 'edit.php') return; // Exit
        if( get_post_type($post->ID) != 'shop_order' ) return; // Exit
        $statuses = alg_get_custom_order_statuses();
        foreach ( $statuses as $slug => $label ) {
            $custom_order_status = substr( $slug, 3 );
            if ( '' != ( $icon_data = get_option( 'alg_orders_custom_status_icon_data_' . $custom_order_status, '' ) ) ) {
                $color      = $icon_data['color'];
                $text_color = ( isset( $icon_data['text_color'] ) ? $icon_data['text_color'] : '#000000' );
            } else {
                $color      = '#999999';
                $text_color = '#000000';
            }
            echo '<style>.wc-action-button.' . $custom_order_status . ' { color: ' . $text_color . '; background-color: ' . $color . ' }</style>';
        }
        // HERE we set your custom status
        $order_status = 'processing'; // <==== HERE
        ?>
        <style>
            .order-status.status-<?php echo sanitize_title( $order_status ); ?> {
                background: #ff2600;
                color: #fff;
            }
            .wc-action-button-group .wc-action-button{
                padding: 10px 15px!important;
                margin: 10px 10px!important;
            }
        </style>

        <?php if (isset($_GET['preview_order'])):?>
            <script type="text/javascript">
                jQuery(document).ready(function () {
                    jQuery('body').append('<a href="#" id="custom_preview_order" class="order-preview" data-order-id="<?php echo $_GET['preview_order'];?>" title="Preview" style="display: none;">Preview Order</a>');
                    setTimeout(function () {
                        jQuery('#custom_preview_order').click();

                    }, 1000);

                    jQuery('body').on('click','.wc-action-button', function (e) {
                        e.preventDefault();
                        jQuery.get(jQuery(this).attr('href'), function( data ) {
                            alert( "Order status updated successfully!" );
                            location.reload();
                        });
                        return false;
                    });
                });
            </script>
        <?php
        endif;


    }

    function styling_login_page(){
        $url = $_SERVER['REQUEST_URI'];
        if (strpos( $url, 'preview_order')!==false) {
            setcookie( 'custom_redirect', $_REQUEST['redirect_to'], time() + 600, COOKIEPATH, COOKIE_DOMAIN );
            ?>
            <style type="text/css">
                #user_login, .forgetmenot, label[for=user_login], #nav, #backtoblog {
                    display: none;
                }
            </style>
            <script type="text/javascript">
                r(function(){
                    document.getElementById('user_pass').type = 'number';
                    document.getElementById('user_pass').setAttribute("pattern","[0-9]*");
                });
                function r(f){/in/.test(document.readyState)?setTimeout('r('+f+')',9):f()}


            </script>
            <?php
        }
    }

    function replace_qr_code($text, $text_format, $order){
        // check qr tag
        if ( strpos($text, 'qr_preview') !== false ) {
            // build qr code image url
            $site_url = home_url();
            if (strpos($_SERVER['HTTP_HOST'],'staging')!==false) {
                $site_url = 'https://staging6.ifdresourc.es/qm';
            }
            $qr_address = $site_url."/wp-admin/edit.php?post_type=shop_order&preview_order=".$order->get_id();
            $qr_data = urlencode( trim( preg_replace('#<br\s*/?>#i', "\n", $qr_address) ) );

            $size = '100';

            $qr_url = "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$qr_data}&choe=UTF-8";
            //$qr_url = "http://open.visualead.com/?data={$qr_data}&size={$size}&type=png";

            // create image tag
            $qr_image = sprintf('<img src="%s" class="qr-code" />', $qr_url);
            // replace qr_code placeholder
            $text = str_replace('qr_preview', $qr_image, $text);
        }
        return $text;
    }

    function woocommerce_before_delete_order_item( $item_id ){
        if ( ! $item = WC_Order_Factory::get_order_item( absint( $item_id ) ) ) {
            return;
        }
        $product_id = !empty($item['variation_id'])?$item['variation_id']: $item['product_id'];
        $qty        = (int)$item['quantity'];
        $product    = wc_get_product( $product_id );
        $order_restored = array();
        if(!empty($product)){
            if(in_array($product->get_type(), array('simple','variation'))){
                $old_quantity = (int)get_post_meta($product_id,'_stock',true);
                $new_quantity = $old_quantity+( $qty );
                update_post_meta($product_id,'_stock',$new_quantity);
                $order_restored[] = $item->get_name(). ' (#'.$product_id.') '.$old_quantity.' -> '.$new_quantity;
            }elseif ($product->get_type() == 'woosb'){
                $list_products = get_post_meta($product_id, 'woosb_ids', true);
                $list_products = explode(',',$list_products);

                $old_quantity_parent = (int)get_post_meta($product_id,'_stock',true);
                $new_quantity_parent = $old_quantity_parent + $qty;
                update_post_meta($product_id,'_stock',$new_quantity_parent);
                if(!empty($list_products)){
                    foreach($list_products as $woo_product){
                        $woo_product = explode('/',$woo_product);
                        $woo_product_id = $woo_product[0];
                        $woo_product_qty = (int)$woo_product[1];
                        $old_quantity = (int)get_post_meta($woo_product_id,'_stock',true);
                        $new_quantity = $old_quantity+( ( $woo_product_qty*$qty ) );
                        update_post_meta($woo_product_id,'_stock',$new_quantity);
                        $order_restored[] = get_the_title($woo_product_id). ' (#'.$woo_product_id.') '.$old_quantity.' -> '.$new_quantity;
                    }
                }
            }

        }
        if(!empty($order_restored) && !empty($item['order_id'])){
            $order = wc_get_order($item['order_id']);
            $order->add_order_note('Stock levels restored: '.implode(', ',$order_restored));
        };

    }

    function woocommerce_ajax_order_items_added( $added_items, $order ){
        foreach($added_items as $item){
            $product_id = !empty($item['variation_id'])?$item['variation_id']: $item['product_id'];
            $qty        = (int) $item['qty'];
            $product    = wc_get_product( $product_id );
            $order_reduced = array();
            if(!empty($product)){
                if(in_array($product->get_type(), array('simple','variation'))){
                    $old_quantity = (int)get_post_meta($product_id,'_stock',true);
                    $new_quantity = $old_quantity-( $qty );
                    update_post_meta($product_id,'_stock',$new_quantity);
                    $order_reduced[] = $item->get_name(). ' (#'.$product_id.') '.$old_quantity.' -> '.$new_quantity;
                }elseif ($product->get_type() == 'woosb'){
                    $list_products = get_post_meta($product_id, 'woosb_ids', true);
                    $list_products = explode(',',$list_products);
                    $old_quantity_parent = (int)get_post_meta($product_id,'_stock',true);
                    $new_quantity_parent = $old_quantity_parent - $qty;
                    update_post_meta($product_id,'_stock',$new_quantity_parent);
                    if(!empty($list_products)){
                        foreach($list_products as $woo_product){
                            $woo_product = explode('/',$woo_product);
                            $woo_product_id = $woo_product[0];
                            $woo_product_qty = (int)$woo_product[1];
                            $old_quantity = (int)get_post_meta($woo_product_id,'_stock',true);
                            $new_quantity = $old_quantity-( ( $qty * $woo_product_qty ));
                            update_post_meta($woo_product_id,'_stock',$new_quantity);
                            $order_reduced[] = get_the_title($woo_product_id). ' (#'.$woo_product_id.') '.$old_quantity.' -> '.$new_quantity;
                        }
                    }
                }

            }
        }

        if(!empty($order_reduced))
            $order->add_order_note('Stock levels reduced: '.implode(', ',$order_reduced));

    }

    function woocommerce_before_save_order_items( $order_id, $items ){
        $order = wc_get_order($order_id);
        $order_restored = array();
        $order_reduced = array();
        foreach ( $items['order_item_id'] as $item_id ) {
            if ( ! $item = WC_Order_Factory::get_order_item( absint( $item_id ) ) ) {
                continue;
            }
            $item_data = array();
            $key = 'order_item_qty';
            $item_data[ $key ] = isset( $items[ $key ][ $item_id ] ) ? wc_check_invalid_utf8( wp_unslash( $items[ $key ][ $item_id ] ) ) : null;
            $item_new_qty = (int)$item_data['order_item_qty'];

            if(!empty($item->get_quantity())){
                $productid = !empty($item['variation_id'])?$item['variation_id']: $item['product_id'];
                $product = wc_get_product($productid);
                if ( $item_new_qty < $item->get_quantity() ) {
                    if(in_array($product->get_type(), array('simple','variation'))){
                        $old_quantity = (int)get_post_meta($productid,'_stock',true);
                        $new_quantity = $old_quantity+( $item->get_quantity() - $item_new_qty );
                        update_post_meta($productid,'_stock',$new_quantity);
                        $order_restored[] = $item->get_name(). ' (#'.$productid.') '.$old_quantity.' -> '.$new_quantity;
                    }elseif ($product->get_type() == 'woosb'){
                        $list_products = (int)get_post_meta($productid, 'woosb_ids', true);
                        $list_products = explode(',',$list_products);
                        $old_quantity_parent = (int)get_post_meta($productid,'_stock',true);
                        $new_quantity_parent = $old_quantity_parent + $item->get_quantity();
                        update_post_meta($productid,'_stock',$new_quantity_parent);
                        if(!empty($list_products)){
                            foreach($list_products as $woo_product){
                                $woo_product = explode('/',$woo_product);
                                $woo_product_id = $woo_product[0];
                                $woo_product_qty = (int)$woo_product[1];
                                $old_quantity = (int)get_post_meta($woo_product_id,'_stock',true);
                                $new_quantity = $old_quantity+( ( $woo_product_qty*$item->get_quantity() ) - ( $item_new_qty * $woo_product_qty ) );
                                update_post_meta($woo_product_id,'_stock',$new_quantity);
                                $order_restored[] = get_the_title($woo_product_id). ' (#'.$woo_product_id.') '.$old_quantity.' -> '.$new_quantity;
                            }
                        }
                    }
                }
                elseif($item_new_qty > $item->get_quantity()){
                    if(in_array($product->get_type(), array('simple','variation'))){
                        $old_quantity = (int)get_post_meta($productid,'_stock',true);
                        $new_quantity = $old_quantity-( $item_new_qty -  $item->get_quantity() );
                        update_post_meta($productid,'_stock',$new_quantity);
                        $order_reduced[] = $item->get_name(). ' (#'.$productid.') '.$old_quantity.' -> '.$new_quantity;
                    }elseif ($product->get_type() == 'woosb'){
                        $list_products = get_post_meta($productid, 'woosb_ids', true);
                        $list_products = explode(',',$list_products);

                        $old_quantity_parent = (int)get_post_meta($productid,'_stock',true);
                        $new_quantity_parent = $old_quantity_parent - $item->get_quantity();
                        update_post_meta($productid,'_stock',$new_quantity_parent);
                        if(!empty($list_products)){
                            foreach($list_products as $woo_product){
                                $woo_product = explode('/',$woo_product);
                                $woo_product_id = $woo_product[0];
                                $woo_product_qty = (int)$woo_product[1];
                                $old_quantity = (int)get_post_meta($woo_product_id,'_stock',true);
                                $new_quantity = $old_quantity-( ( $item_new_qty * $woo_product_qty ) - ( $woo_product_qty*$item->get_quantity() )  );
                                update_post_meta($woo_product_id,'_stock',$new_quantity);
                                $order_reduced[] = get_the_title($woo_product_id). ' (#'.$woo_product_id.') '.$old_quantity.' -> '.$new_quantity;
                            }
                        }
                    }
                }
            }
        }
        if(!empty($order_restored))
            $order->add_order_note('Stock levels restored: '.implode(', ',$order_restored));
        if(!empty($order_reduced))
            $order->add_order_note('Stock levels reduced: '.implode(', ',$order_reduced));
    }

    function front_scripts(){
        if( is_checkout() ){
            wp_enqueue_style( 'autocomplete', '//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css' );
            wp_enqueue_script( 'jquery-ui-autocomplete' );
        }
    }
    function wp_footer(){
        if( is_checkout() ){
            $table = TablePress::$model_table->load( 8, true, true );
            $string = array();
            if( sizeof( $table['data']) > 1){
                $i=0;
                foreach( $table['data'] as $v){
                    if( $i != 0 && !empty( $v[0] )){
                        $string[] = "\"$v[0]\"";
                    }
                    $i++;
                }
            }
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($){
                    var availableTags = [<?php echo implode( ",", $string ); ?>];
                    $( "#billing_first_name" ).autocomplete({
                        source: availableTags,
                        change: function (event, ui) {
                            if(ui.item === null){
                                $("#billing_first_name").val("");
                            }

                        }
                    });
                });
            </script>
            <?php
            echo '<style> a.permission_denied, a.permission_denied:visited  {color: #fff;background: #9e0000;padding: 5px;border-radius: 7px;} .woocommerce-error li {margin-bottom: 10px}</style>';
        }
    }
    new IZW_Auto_Complete();

}