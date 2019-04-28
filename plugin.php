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
        }
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