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
        }
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