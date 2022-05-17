<?php
/*
Plugin Name: China Cross-Border Logistics/Shipment Price Query
Description: 物流价格查询
Author: hs
Version: 1
Author URI:
*/
function load_customize_domain() {
   load_plugin_textdomain( 'logsitic', false, dirname( plugin_basename( __FILE__ ) ) . '/language/' );
}

add_action( 'plugins_loaded', 'load_customize_domain' );

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

   add_action( 'woocommerce_shipping_init', 'your_shipping_method_init' );

   function your_shipping_method_init(){
      if (!class_exists('WC_Shunfeng1_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Shunfeng1_Shipping_Method extends WC_Shipping_Method {

            public function __construct($instance_id = 0) {
               $this->id                 = 'shunfeng1_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'SF express international parcel register', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('SF express international parcel register','logsitic'));
            }

            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'disabled',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "顺丰国际小包挂号",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Shunfeng2_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Shunfeng2_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'shunfeng2_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'SF express international e-commerce courier CD', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('SF express international e-commerce courier CD','logsitic'));
            }

            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "顺丰国际电商专递CD",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Eexpress_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Eexpress_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'eexpress_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'E-MINHOUYOUZHENG express', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('E-MINHOUYOUZHENG express','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "E特快-MINHOUYOUZHENG",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Emailtreasure_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Emailtreasure_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'emailtreasure_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'E mail treasure-MINHOUYOUZHENG', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('E mail treasure-MINHOUYOUZHENG','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "E邮宝-MINHOUYOUZHENG",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_DHL_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_DHL_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'dhl_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = 'DHL';
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', 'DHL');
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "DHL",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Cloudway1_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Cloudway1_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'cloudway1_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yuntu global private line registration (live)', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yuntu global private line registration (live)','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "云途全球专线挂号（带电）",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Cloudway2_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Cloudway2_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'cloudway2_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yuntu global private line registration (special HP goods)', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yuntu global private line registration (special HP goods)','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "云途全球专线挂号(特惠普货)",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Themailtreasure_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Themailtreasure_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'themailtreasure_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Us postal treasure (Canada special line)', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Us postal treasure (Canada special line)','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "美邮宝（加拿大专线）",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_USPS_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_USPS_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'usps_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Special offer for sino us special line (USPS)', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Special offer for sino us special line (USPS)','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "天梯中美专线特惠（USPS）",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_KeepserviceinShanghai_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_KeepserviceinShanghai_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'keepserviceinShanghai_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Shanghai guard (American small bag general cargo-express JX)', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Shanghai guard (American small bag general cargo-express JX)','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "上海守务（美国小包普货-特快JX）",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Putian1_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Putian1_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'putian1_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Putian 4PX-special line for general goods', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Putian 4PX-special line for general goods','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "莆田4PX-普货专线",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Putian2_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Putian2_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'putian2_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Putian 4PX-special line for masks', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Putian 4PX-special line for masks','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "莆田4PX-口罩专线",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Yanwen1_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Yanwen1_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'yanwen1_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yan heritage flow (tracking general goods) Europe', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yan heritage flow (tracking general goods) Europe','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "燕文物流(追踪普货)欧洲",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Yanwen2_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Yanwen2_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'yanwen2_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yan heritage flow (tracking general goods) North America', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yan heritage flow (tracking general goods) North America','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "燕文物流(追踪普货)北美",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Yanwen3_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Yanwen3_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'yanwen3_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yan-wen special line tracking general goods (shoes and clothing special line)', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yan-wen special line tracking general goods (shoes and clothing special line)','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "燕文专线追踪普货(鞋服专线)",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Yida1_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Yida1_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'yida1_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yida logistics global express-general goods', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yida logistics global express-general goods','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "义达物流全球快捷-普货",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Yida2_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Yida2_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'yida2_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yida logistics (Canada)', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yida logistics (Canada)','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "义达物流(加拿大）",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Yida3_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Yida3_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'yida3_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yida logistics global express-general cargo-AZGLON', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yida logistics global express-general cargo-AZGLON','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "义达物流全球快捷-普货-AZGLON",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Yida4_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Yida4_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'yida4_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yida logistics (Japan)', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yida logistics (Japan)','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "义达物流(日本)",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
      if (!class_exists('WC_Yida5_Shipping_Method')){
         /**
          * @class   WC_Shipping_Method
          * @version 2.6.0
          */
         class WC_Yida5_Shipping_Method extends WC_Shipping_Method {
            /**
             * @param int $instance_id int 实例 ID
             */
            public function __construct($instance_id = 0) {
               $this->id                 = 'yida5_shipping_method';
               $this->instance_id        = absint( $instance_id );
               $this->method_title       = __( 'Yida logistics (Japan-electric)', 'logsitic' );
               $this->method_description = '';
               $this->supports = [
                  'shipping-zones',
                  'instance-settings',
                  'instance-settings-modal',
               ];
               $this->init();
               $this->enabled = $this->get_option('enabled','yes');
               $this->title   = $this->get_option('title', __('Yida logistics (Japan-electric)','logsitic'));
            }
 
            /**
             * 初始化物流方法
             */
            function init() {
               // Load the settings.
               $this->init_form_fields();
               $this->init_settings();
               // Define user set variables
               $this->title 		  = $this->get_option( 'title' );
               $this->name 		  = $this->get_option( 'name' );
               $this->type 		  = $this->get_option( 'type' );
               $this->cost_per_order = $this->get_option( 'cost_per_order' );
               $this->key 			  = $this->get_option( 'key' );
               $this->custom_discount = $this->get_option( 'custom_discount' );
            }

            /**
             * Initialise Gateway Settings Form Fields
             * @access public
             * @return void
             */
            function init_form_fields() {
               $this->instance_form_fields = array(
                  'name' => array(
                     'title' 		=> __( 'express company name', 'logsitic' ),
                     'type' 			=> 'text',
                     'css' 			=> 'pointer-events: none;',
                     'description' 	=> '',
                     'default'		=> "义达物流(日本-带电)",
                     'desc_tip'		=> true
                  ),
                  'cost_per_order' => array(
                     'title' 		=> __( 'fixed charge per order', 'logsitic' ),
                     'type' 			=> 'price',
                     'placeholder'	=> wc_format_localized_price( 0 ),
                     'description'	=> __( 'You can set a fixed fee that will be charged for each order.  This fee will be charged regardless of the Settings below.  Such as 5.00.  The default is 0 yuan.  ', 'logsitic' ),
                     'default'		=> wc_format_localized_price( 0 ),
                     'desc_tip'		=> true
                  ),
                  'custom_discount' => array(
                     'title' 		=> __( 'custom discount', 'logsitic' ),
                     'type' 			=> 'text',
                     'placeholder'	=> 1,
                     'description'	=> '',
                     'default'		=> 1,
                     'desc_tip'		=> true
                  ),
                  'key' => array(
                     'title' 		=> 'Key',
                     'type' 			=> 'text',
                     'description' 	=> '',
                     'default'		=> "",
                     'desc_tip'		=> true
                  ),
                  'type' => array(
                     'title' 		=> "",
                     'type' 			=> 'hidden',
                     'default' 		=> 'order',
                  ),
               );
            }

            function auto_check_logistics_price($cost,$package){
               $weight = 0;
               foreach($package["contents"] as $item){
                  $product_id = $item["variation_id"]?$item["variation_id"]:$item["product_id"];
                  $p = get_product($product_id);
                  $weight += $item["quantity"]*$p->get_weight();
               }
               $country = $package["destination"]["country"];
               if(empty($country)){
                  return array(false,"");
               }
               //debug
               $KEY = $this->key;
               $content = bit_call_url("http://wuliuapi.qifeiye.com/api_logistics.php?countyCode=".$country."&key=".$KEY);
               // $content = bit_call_url("http://47.56.114.246/api_lg.php?countyCode=".$country);
               $rlt =  json_decode($content,true);
               if($rlt && !empty($rlt["items"])){
                  $countryExpressInfoNewGroup = $rlt["items"][0]["countryExpressInfoNewGroup"];
                  foreach($countryExpressInfoNewGroup as $item){
                     if($item["logistics"]["name"] == $cost){
                        //需要根据商品体积，重量计算价格
                        if($item["limitPriority"]<$weight){
                           continue;
                        }
                        if(empty($weight)){
                           continue;
                        }
                        $new_cost = _get_logistice_price($item,$weight);
                        return array($item["handlePrice"]+$new_cost,"(".$item["prescriptionStart"]."-".$item["prescriptionEnd"].__( 'workday', 'logsitic' ).")");
                     }
                  }
               }
               return array(false,"");
            }
            
            function calculate_shipping( $package = array() ) {
               list($r_cost,$r_msg) = $this->auto_check_logistics_price($this->name,$package);
               if(!$r_cost){
                  return false;
               }
               $this->rates 		= array();
               $cost_per_order 	= ( isset( $this->cost_per_order ) && ! empty( $this->cost_per_order ) ) ? $this->cost_per_order : 0;
               if ( $this->type == 'order' ) {
                  $shipping_total = $r_cost * $this->custom_discount;
                  if ( ! is_null( $shipping_total ) || $cost_per_order > 0 ) {
                     $msg = isset($r_msg)?$r_msg:"";
                     $rate = array(
                        'id' 	=> $this->id.':' . md5( $this->title),
                        'label' => $this->title.$msg,
                        'cost'  => $shipping_total + $cost_per_order,
                     );
                     $this->add_rate( $rate );
                  }
               }
            }
         }
      }
   }
}

add_filter('woocommerce_shipping_methods',function($methods){
    $methods['shunfeng1_shipping_method'] = 'WC_Shunfeng1_Shipping_Method';
    return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
    $methods['shunfeng2_shipping_method'] = 'WC_Shunfeng2_Shipping_Method';
    return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['eexpress_shipping_method'] = 'WC_Eexpress_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['emailtreasure_shipping_method'] = 'WC_Emailtreasure_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['dhl_shipping_method'] = 'WC_DHL_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['cloudway1_shipping_method'] = 'WC_Cloudway1_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['cloudway2_shipping_method'] = 'WC_Cloudway2_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['themailtreasure_shipping_method'] = 'WC_Themailtreasure_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['usps_shipping_method'] = 'WC_USPS_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['keepserviceinShanghai_shipping_method'] = 'WC_KeepserviceinShanghai_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['putian1_shipping_method'] = 'WC_Putian1_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['putian2_shipping_method'] = 'WC_Putian2_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['yanwen1_shipping_method'] = 'WC_Yanwen1_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['yanwen2_shipping_method'] = 'WC_Yanwen2_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['yanwen3_shipping_method'] = 'WC_Yanwen3_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['yida1_shipping_method'] = 'WC_Yida1_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['yida2_shipping_method'] = 'WC_Yida2_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['yida3_shipping_method'] = 'WC_Yida3_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['yida4_shipping_method'] = 'WC_Yida4_Shipping_Method';
   return $methods;
});
add_filter('woocommerce_shipping_methods',function($methods){
   $methods['yida5_shipping_method'] = 'WC_Yida5_Shipping_Method';
   return $methods;
});

function _get_logistice_price($item,$weight){
   $prices = json_decode($item["price"]);
   if($item["firstPriority"]>$weight){
       $weight = $item["firstPriority"];
   }
   $new_cost = $item["firstPriorityCost"];
   foreach($prices as $price){
       $useweight = $weight;
       if($item["ruleWeight"]==0){
           $useweight = $price->weight2;
       }
       if($weight>=$price->weight && ($weight<$price->weight2 || !isset($price->weight2) ) ){
           $new_cost = ($price->price * ($useweight?$useweight:$item["limitPriority"]) +$price->handle_price)*$price->rate;
       }
   }
   return number_format($new_cost,2);
}

function bit_call_url($url){
   $ch = curl_init();
   curl_setopt($ch, CURLOPT_URL, $url);
   curl_setopt($ch, CURLOPT_HEADER, 0);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
   curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
   curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36');
   if(!empty($para) && is_array($para)){
      curl_setopt($ch, $post, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($para));
   }
   if( !empty($proxy)  && $step==1){
      curl_setopt($ch, CURLOPT_PROXY, $proxy);
         curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
   }
   curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
   curl_setopt($ch, CURLOPT_TIMEOUT, 30);
   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
   $result = curl_exec($ch);
   curl_close($ch);
   return $result;
}