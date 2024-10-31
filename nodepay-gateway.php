<?php
/*
 * Plugin Name: NodePAY Payment Gateway
 * Plugin URI: https://nodepay.uk/simple-api
 * Description: Handle altcoin payments via NodePAY with your WooCommerce.
 * Author: Scott Laurie
 * Author URI: https://nodepay.uk/
 * Version: 1.0.2
 */
 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'wcnp_add_gateway_class' );
function wcnp_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_wcnp_Gateway'; // your class name is here
	return $gateways;
}
 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'wcnp_init_gateway_class' );
register_activation_hook( __FILE__, 'npg_plugin_activate' );
function npg_plugin_activate(){

    // Require parent plugin
    if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) and current_user_can( 'activate_plugins' ) ) {
        // Stop activation redirect and show error
        wp_die('Sorry, but this plugin requires the WooCommerce Plugin to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
    }
}

function wcnp_init_gateway_class() {
 
	class WC_wcnp_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
public function __construct() {
	
	//WRAP IN A FUNCTION TO CHECK IF WOO IS INSTALLED>>
 
	$this->id = 'wcnp'; // payment gateway plugin ID
	$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
	$this->has_fields = true; // in case you need a custom credit card form
	$this->method_title = 'NodePAY Gateway';
	$this->method_description = 'NodePAY payment gateway for WC'; // will be displayed on the options page
 
	// gateways can support subscriptions, refunds, saved payment methods,
	// but in this tutorial we begin with simple payments
	$this->supports = array(
		'products'
	);
 
	// Method with all the options fields
	$this->init_form_fields();
 
	// Load the settings.
	$this->init_settings();
	$this->title = $this->get_option( 'title' );
	$this->description = $this->get_option( 'description' );
	$this->enabled = $this->get_option( 'enabled' );
	$this->private_key = $this->get_option( 'private_key' );
	$this->api_key = $this->get_option( 'api_key' );
 
	// This action hook saves the settings
	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
 
	// We need custom JavaScript to obtain a token
	add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
 
	// You can also register a webhook here
	 add_action( 'woocommerce_api_webhook', array( $this, 'webhook' ) );
 }
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
public function init_form_fields(){
 
	$this->form_fields = array(
		'enabled' => array(
			'title'       => 'Enable/Disable',
			'label'       => 'Enable NP Gateway',
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no'
		),
		'title' => array(
			'title'       => 'Title',
			'type'        => 'text',
			'description' => 'NodePay',
			'default'     => 'NodePay',
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => 'Description',
			'type'        => 'textarea',
			'description' => 'Pay using NodePay',
			'default'     => 'Pay Using NodePay',
		),

		'api_key' => array(
			'title'       => 'API Key',
			'type'        => 'text'
		),
		'private_key' => array(
			'title'       => 'Private Key',
			'type'        => 'text'
		)
	);
}
 
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
 
		global $woocommerce;
	// we need it to get any order detailes
		$order = wc_get_order( $order_id );
		//$order->add_order_note( 'Sending to NodePAY for payment.', true );
	//wc_add_notice(  'Order Data: '.$order, 'error' );
			//return;
			
			$goto = "https://nodepay.uk/nodepay/?cmd=_cart";
			$goto = $goto ."&currency=".$order->currency;
			//$goto = $goto ."&currency=MB8";
			$goto = $goto ."&api_key=".$this->api_key;
			$goto = $goto ."&invoice_id=".$order_id;
			$goto = $goto ."&amount=".$order->total;
			$goto = $goto ."&description=".$order->order_key;
			$goto = $goto ."&callback_url=".get_site_url()."/wc-api/webhook/";
			$goto = $goto ."&return_url=".$this->get_return_url( $order );
			$goto = $goto ."&wc=1";
			
			
return array(
    'result' => 'success',
    'redirect' => $goto
);
//wc_add_notice(  'Under Construction...', 'success' );
//echo "under construvtion...";
	 	}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
			
			$order = wc_get_order( urldecode($_REQUEST['x_invoice_id']) );
			$payment_amount = urldecode($_REQUEST['x_amount']);
			$txid = urldecode($_REQUEST['x_trans_id']);
			
			
			$hash = urldecode($_REQUEST['x_hash']);
			
			if($_REQUEST['x_base_amount']){
				$base_amount = urldecode($_REQUEST['x_base_amount']);
			$validhash = md5(urldecode($_REQUEST['x_invoice_id']) . $txid. $payment_amount . $this->private_key);	
			} else {
			$validhash = md5(urldecode($_REQUEST['x_invoice_id']) . $txid. $order->total . $this->private_key);
			}
			
			if (($order->total == $payment_amount || $order->total == $base_amount) && $hash == $validhash){
			$order->add_order_note( 'Nodepay TX Ref: '.$txid, true );	
			$order->payment_complete();
			//$order->reduce_order_stock();
			}
 
			//update_option('webhook_debug', $_REQUEST);
			//echo "Updated order";
 
	 	}
 	}
}
?>