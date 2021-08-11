<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
 
 /**
 * Add roubles in currencies
 * 
 * @since 0.3
 */
function paymentnepal_usd_currency_symbol( $currency_symbol, $currency ) {
    if($currency == "USD") {
        $currency_symbol = '$';
    }
    return $currency_symbol;
}

function paymentnepal_rub_currency( $currencies ) {
    $currencies["USD"] = 'US Dollar';
    return $currencies;
}

add_filter( 'woocommerce_currency_symbol', 'paymentnepal_usd_currency_symbol', 10, 2 );
add_filter( 'woocommerce_currencies', 'paymentnepal_usd_currency', 10, 1 );


/* Add a custom payment class to WC
  ------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_paymentnepal', 0);
function woocommerce_paymentnepal(){
	if (!class_exists('WC_Payment_Gateways'))
		return; // if the WC payment gateway class is not available, do nothing
	if(class_exists('WC_PAYMENTNEPAL'))
		return;
class WC_PAYMENTNEPAL extends WC_Payment_Gateways{
	public function __construct(){
		
		$plugin_dir = plugin_dir_url(__FILE__);

		global $woocommerce;

		$this->id = 'paymentnepal';
		$this->icon = apply_filters('woocommerce_paymentnepal_icon', ''.$plugin_dir.'paymentnepal.png');
		$this->has_fields = false;
    $this->liveurl = 'https://pay.paymentnepal.com/alba/input';

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title = $this->get_option('title');
		$this->paymentnepal_merchant = $this->get_option('paymentnepal_merchant');
		$this->paymentnepal_key = $this->get_option('paymentnepal_key');
		$this->paymentnepal_skey = $this->get_option('paymentnepal_skey');
		$this->description = $this->get_option('description');
		$this->instructions = $this->get_option('instructions');

		// Actions
		add_action('valid-paymentnepal-standard-ipn-reques', array($this, 'successful_request') );
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

		// Save options
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Payment listener/API hook
		add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

		if (!$this->is_valid_for_use()){
			$this->enabled = false;
		}
	}
	
	/**
	 * Check if this gateway is enabled and available in the user's country
	 */
	function is_valid_for_use(){
		if (!in_array(get_option('woocommerce_currency'), array('USD'))){
			return false;
		}
		return true;
	}
	
	/**
	* Admin Panel Options 
	* - Options for bits like 'title' and availability on a country-by-country basis
	*
	* @since 0.1
	**/
	public function admin_options() {
		?>
		<h3><?php _e('PAYMENTNEPAL', 'woocommerce'); ?></h3>
		<p><?php _e('Payment via Paymentnepal settings.', 'woocommerce'); ?></p>

	  <?php if ( $this->is_valid_for_use() ) : ?>

		<table class="form-table">

		<?php    	
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
    ?>
    </table><!--/.form-table-->
    		
    <?php else : ?>
		<div class="inline error"><p><strong><?php _e('Method inactive', 'woocommerce'); ?></strong>: <?php _e('Paymentnepal does not support your shop currency.', 'woocommerce' ); ?></p></div>
		<?php
			endif;

    } // End admin_options()

  /**
  * Initialise Gateway Settings Form Fields
  *
  * @access public
  * @return void
  */
	function init_form_fields(){
		$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable\Disable', 'woocommerce'),
					'type' => 'checkbox',
					'label' => __('Enabled', 'woocommerce'),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __('Name', 'woocommerce'),
					'type' => 'text', 
					'description' => __( 'This name is displayed to customer.', 'woocommerce' ),
					'default' => __('PAYMENTNEPAL', 'woocommerce')
				),
				'rficb_key' => array(
					'title' => __('Payment key', 'woocommerce'),
					'type' => 'text',
					'description' => __('Please enter your payment key.', 'woocommerce'),
					'default' => ''
				),
				'rficb_skey' => array(
					'title' => __('Secret key', 'woocommerce'),
					'type' => 'text',
					'description' => __('Please enter your secret key.', 'woocommerce'),
					'default' => ''
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce' ),
					'type' => 'textarea',
					'description' => __( 'Payment method description, displayed to customer.', 'woocommerce' ),
					'default' => 'Payment via paymentnepal.com.'
				),
				'instructions' => array(
					'title' => __( 'Instructions', 'woocommerce' ),
					'type' => 'textarea',
					'description' => __( 'Payment instructions.', 'woocommerce' ),
					'default' => 'Payment via paymentnepal.com.'
				)
			);
	}

	/**
	* There are no payment fields for sprypay, but we want to show the description if set.
	**/
	function payment_fields(){
		if ($this->description){
			echo wpautop(wptexturize($this->description));
		}
	}
	/**
	* Generate the dibs button link
	**/
	public function generate_form($order_id){
		global $woocommerce;

		$order = new WC_Order( $order_id );

		$action_adr = $this->liveurl;

		$out_summ = number_format($order->order_total, 2, '.', '');

		$args = array(
				// Merchant
				'key' => $this->paymentnepal_key,
				'cost' => $out_summ,
				'order_id' => $order_id,
				'name' => 'Shop payment',
			);

		$paypal_args = apply_filters('woocommerce_paymentnepal_args', $args);

		$args_array = array();

		foreach ($args as $key => $value){
			$args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
		}

		return
			'<form action="'.esc_url($action_adr).'" method="POST" id="paymentnepal_payment_form">'."\n".
			implode("\n", $args_array).
			'<input type="submit" class="button alt" id="submit_paymentnepal_payment_form" value="'.__('Proceed', 'woocommerce').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel payment and return to cart', 'woocommerce').'</a>'."\n".
			'</form>';
	}
	
	/**
	 * Process the payment and return the result
	 **/
	function process_payment($order_id){
		$order = new WC_Order($order_id);

		return array(
			'result' => 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
		);
	}
	
	/**
	* receipt_page
	**/
	function receipt_page($order){
		echo '<p>'.__('Thanks for your order, please click the button below to proceed with payment.', 'woocommerce').'</p>';
		echo $this->generate_form($order);
	}
	
	/**
	 * Check Paymentnepal IPN validity
	 **/
	function check_ipn_request_is_valid($posted){

  $data = array(
    'tid' => $posted['tid'],			// Transactio ID
    'name' => urldecode($posted['name']), 		// Payment\order name
    'comment' => $posted['comment'],		// Payment comment
    'partner_id' => $posted['partner_id'],	// Your merchant ID
    'service_id' => $posted['service_id'],	// Your service ID
    'order_id' => $posted['order_id'],	// Unique order id
    'type' => $posted['type'],		// payment method (spg)
    'partner_income' => $posted['partner_income'], // Cost in USD
    'system_income' => $posted['system_income'] ,   // Total amount for customer
    'test' => $posted['test']    // Is payment test
  );

    $check = md5(join('', array_values($data)) . $this->paymentnepal_skey);
    
		if ($posted['check'] == $check)
		{
			echo 'OK'.$posted['order_id'];
			return true;
		}

		return false;
	}
	
	/**
	* Check Response
	**/
	function check_ipn_response(){
		global $woocommerce;

		if (isset($_GET['paymentnepal']) AND $_GET['paymentnepal'] == 'result'){
			@ob_clean();

			$_POST = stripslashes_deep($_POST);

			if ($this->check_ipn_request_is_valid($_POST)){
        do_action('valid-paymentnepal-standard-ipn-reques', $_POST);
			}
			else{
				wp_die('IPN Request Failure');
			}
		}
		else if (isset($_GET['paymentnepal']) AND $_GET['paymentnepal'] == 'success'){
			$order_id = $_POST['order_id'];
			$order = new WC_Order($order_id);
      if ($order->order_total <= $_POST['system_income']) {
			$order->update_status('processing', __('Payment successful', 'woocommerce'));
			WC()->cart->empty_cart();

			wp_redirect( $this->get_return_url( $order ) );  }
			else {
				wp_die('Cost Request Failure');
			}
		}
		else if (isset($_GET['paymentnepal']) AND $_GET['paymentnepal'] == 'fail'){
			$order_id = $_POST['order_id'];
			$order = new WC_Order($order_id);
			$order->update_status('failed', __('Payment failed', 'woocommerce'));

			wp_redirect($order->get_cancel_order_url());
			exit;
		}

	}

	/**
	* Successful Payment!
	**/
	function successful_request($posted){
		global $woocommerce;

		$out_summ = $posted['system_income'];
		$order_id = $posted['order_id'];

		$order = new WC_Order($order_id);

		// Check order not already completed
		if ($order->status == 'completed'){
			exit;
		}

		// Payment completed
		$order->add_order_note(__('Payment finished successfully.', 'woocommerce'));
		$order->payment_complete();
		exit;
	}
}

/**
 * Add the gateway to WooCommerce
 **/
function add_paymentnepal_gateway($methods){
	$methods[] = 'WC_PAYMENTNEPAL';
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_paymentnepal_gateway');
}
?>
