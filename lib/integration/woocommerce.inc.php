<?php
	/*
	
	PAYMILL Payment Class
	
	*/
	
	// HOOKED FUNCTIONS FROM PAYMILL WEBHOOKS
	function paymill_webhooks(){
		global $wpdb;
		
		// is there a webhook from Paymill?
		if(class_exists('WC_Subscriptions_Manager') && isset($_GET['paymill_webhook']) && $_GET['paymill_webhook'] == 1){
			
			error_log(date(DATE_RFC822).' - Webhook activated'."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
		
			// grab data from webhook
			$body = @file_get_contents('php://input');
			$event_json = json_decode($body, true);
			
			ob_start();
			var_dump($event_json);
			$event_json_dump = ob_get_flush();
			
			/* output example:
				array(1) {
				  ["event"]=>
				  array(4) {
					["event_type"]=>
					string(20) "subscription.deleted"
					["event_resource"]=>
					array(13) {
					  ["id"]=>
					  string(24) "sub_b71adbf5....."
					  ["offer"]=>
					  array(10) {
						["id"]=>
						string(26) "offer_8083a5b....."
						["name"]=>
						string(39) "woo_91_73da6....."
						["amount"]=>
						int(100)
						["currency"]=>
						string(3) "EUR"
						["interval"]=>
						string(5) "1 DAY"
						["trial_period_days"]=>
						int(0)
						["created_at"]=>
						int(1389547028)
						["updated_at"]=>
						int(1389547028)
						["subscription_count"]=>
						array(2) {
						  ["active"]=>
						  string(1) "1"
						  ["inactive"]=>
						  string(1) "1"
						}
						["app_id"]=>
						NULL
					  }
					  ["livemode"]=>
					  bool(false)
					  ["cancel_at_period_end"]=>
					  bool(false)
					  ["trial_start"]=>
					  NULL
					  ["trial_end"]=>
					  NULL
					  ["next_capture_at"]=>
					  int(1389836717)
					  ["created_at"]=>
					  int(1389663382)
					  ["updated_at"]=>
					  int(1389750317)
					  ["canceled_at"]=>
					  NULL
					  ["app_id"]=>
					  NULL
					  ["payment"]=>
					  array(12) {
						["id"]=>
						string(28) "pay_4e3759f....."
						["type"]=>
						string(10) "creditcard"
						["client"]=>
						string(27) "client_dbe164....."
						["card_type"]=>
						string(4) "visa"
						["country"]=>
						NULL
						["expire_month"]=>
						string(2) "12"
						["expire_year"]=>
						string(4) "2020"
						["card_holder"]=>
						string(13) "dfgdfgdfgdfgd"
						["last4"]=>
						string(4) "1111"
						["created_at"]=>
						int(1389663369)
						["updated_at"]=>
						int(1389663380)
						["app_id"]=>
						NULL
					  }
					  ["client"]=>
					  array(8) {
						["id"]=>
						string(27) "client_dbe164....."
						["email"]=>
						string(22) "matthias@pc-intern.com"
						["description"]=>
						string(15) "Matthias Reuter"
						["created_at"]=>
						int(1389547027)
						["updated_at"]=>
						int(1389547027)
						["app_id"]=>
						NULL
						["payment"]=>
						array(2) {
						  [0]=>
						  array(12) {
							["id"]=>
							string(28) "pay_1a5ff8....."
							["type"]=>
							string(10) "creditcard"
							["client"]=>
							string(27) "client_dbe16....."
							["card_type"]=>
							string(4) "visa"
							["country"]=>
							NULL
							["expire_month"]=>
							string(2) "12"
							["expire_year"]=>
							string(4) "2020"
							["card_holder"]=>
							string(10) "dfgdfgdfgd"
							["last4"]=>
							string(4) "1111"
							["created_at"]=>
							int(1389547027)
							["updated_at"]=>
							int(1389547028)
							["app_id"]=>
							NULL
						  }
						  [1]=>
						  array(12) {
							["id"]=>
							string(28) "pay_4e375....."
							["type"]=>
							string(10) "creditcard"
							["client"]=>
							string(27) "client_dbe164....."
							["card_type"]=>
							string(4) "visa"
							["country"]=>
							NULL
							["expire_month"]=>
							string(2) "12"
							["expire_year"]=>
							string(4) "2020"
							["card_holder"]=>
							string(13) "dfgdfgdfgdfgd"
							["last4"]=>
							string(4) "1111"
							["created_at"]=>
							int(1389663369)
							["updated_at"]=>
							int(1389663380)
							["app_id"]=>
							NULL
						  }
						}
						["subscription"]=>
						array(2) {
						  [0]=>
						  string(24) "sub_fcc4....."
						  [1]=>
						  string(24) "sub_b71a....."
						}
					  }
					}
					["created_at"]=>
					int(1389816435)
					["app_id"]=>
					NULL
				  }
				}
				
			*/
			error_log($event_json_dump."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
			
			// get subscription info, if available
			if(isset($event_json['event']['event_resource']['id']) && strlen($event_json['event']['event_resource']['id']) > 0){
				
				$sql = $wpdb->prepare('
				SELECT * FROM '.$wpdb->prefix.'paymill_subscriptions WHERE paymill_sub_id=%s',
				array(
					$event_json['event']['event_resource']['id']
				));
				
				$sub_cache			= $wpdb->get_results($sql,ARRAY_A);
				$sub_cache			= $sub_cache[0];
				
				/* output example:
				SELECT * FROM wp_paymill_subscriptions WHERE paymill_sub_id="sub_b71adbf5e097bbe5ba80"
				*/
				error_log("\n\n".$query."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
				
				/* output example:
				
				1
				
				30
				
				*/
				error_log($sub_cache['woo_user_id']."\n\n".$sub_cache['woo_offer_id']."\n\n", 3, PAYMILL_DIR.'lib/debug/PHP_errors.log');
				
				// update subscriptions when webhook is triggered
				if(isset($sub_cache['woo_offer_id']) && strlen($sub_cache['woo_offer_id']) > 0){
					// tell WooCommerce when payment for subscription is successfully processed
					if($event_json['event']['event_type'] == 'subscription.succeeded'){
						/* example data WC_Subscriptions_Manager::get_subscription:
							array(15) {
							  ["order_id"]=>
							  string(3) "201"
							  ["product_id"]=>
							  string(2) "91"
							  ["variation_id"]=>
							  string(0) ""
							  ["status"]=>
							  string(6) "active"
							  ["period"]=>
							  string(3) "day"
							  ["interval"]=>
							  string(1) "1"
							  ["length"]=>
							  string(2) "12"
							  ["start_date"]=>
							  string(19) "2014-01-12 17:17:10"
							  ["expiry_date"]=>
							  string(19) "2014-01-24 17:17:10"
							  ["end_date"]=>
							  string(1) "0"
							  ["trial_expiry_date"]=>
							  string(1) "0"
							  ["failed_payments"]=>
							  string(1) "0"
							  ["completed_payments"]=>
							  array(1) {
								[0]=>
								string(19) "2014-01-12 17:17:10"
							  }
							  ["suspension_count"]=>
							  string(1) "0"
							  ["last_payment_date"]=>
							  string(19) "2014-01-12 17:17:10"
							}
						*/
						$subscription           = WC_Subscriptions_Manager::get_subscription($sub_cache['woo_offer_id']);
						
						WC_Subscriptions_Manager::process_subscription_payments_on_order($subscription['order_id'], $subscription['product_id']);
					}
					// cancel subscription, as it was deleted through Paymill dashboard
					if($event_json['event']['event_type'] == 'subscription.deleted'){

					$sql = $wpdb->prepare('
					DELETE FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id=%s AND woo_offer_id=%s',
					array(
						$sub_cache['woo_user_id'],
						$sub_cache['woo_offer_id']
					));
						
						WC_Subscriptions_Manager::cancel_subscription($sub_cache['woo_user_id'], $sub_cache['woo_offer_id']);
					}
					// tell WC that payment failure occured
					if($event_json['event']['event_type'] == 'subscription.failed'){
						$subscription           = WC_Subscriptions_Manager::get_subscription($sub_cache['woo_offer_id']);
						
						WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($subscription['order_id'], $subscription['product_id']);
					}
				}
			}
		}
	}
	add_action( 'woocommerce_init', 'paymill_webhooks' );
	
	function add_paymill_gateway_class( $methods ) {
		$methods[] = 'WC_Gateway_Paymill_Gateway'; 
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_paymill_gateway_class' );
	
	add_action( 'cancelled_subscription_paymill','woo_cancelled_subscription_paymill', 10, 2 );
	// add_action( 'updated_users_subscriptions','woo_updated_subscription_paymill', 10, 2 );
	//add_action( 'subscription_put_on-hold_paymill','woo_subscription_put_on_hold_paymill', 10, 2 );
	//add_action( 'reactivated_subscription_paymill','woo_reactivated_subscription_paymill', 10, 2 );

	function woo_cancelled_subscription_paymill($user,$subscription_key){
		global $wpdb;
		
		$userInfo			= get_userdata(get_current_user_id());
		$subscriptions		= new paymill_subscriptions('woocommerce');

		$sql = $wpdb->prepare('SELECT paymill_sub_id FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id=%s AND woo_offer_id=%s',
		array(
			$userInfo->ID,
			$user->id.'_'.$subscription_key
		));
		$client_cache		= $wpdb->get_results($sql,ARRAY_A);
		
		$subscriptions->remove($client_cache[0]['paymill_sub_id']);
		
		$wpdb->prepare('DELETE FROM '.$wpdb->prefix.'paymill_subscriptions WHERE woo_user_id=%s AND woo_offer_id=%s',
		array(
			$userInfo->ID,
			$user->id.'_'.$subscription_key
		));
	}
	function woo_updated_subscription_paymill($user,$subscription_details){
		var_dump($user);
		var_dump($subscription_details);
		die();
	}
	
	function woo_subscription_put_on_hold_paymill(){
	
	}
	function woo_reactivated_subscription_paymill($user,$subscription_key){
	
	}
	
	function process_paymill_ipn_request( $transaction_details ) {
		//var_dump(WC_Subscriptions_Manager::get_all_users_subscriptions());
		//die('test');
	}
	
	function init_paymill_gateway_class() {
		global $wpdb;

		if(class_exists('WC_Payment_Gateway')){
			class WC_Gateway_Paymill_Gateway extends WC_Payment_Gateway{
			
				public function __construct(){
				
					$GLOBALS['paymill_source']['woocommerce_version'] = ((isset($GLOBALS['woocommerce']) && is_object($GLOBALS['woocommerce']) && isset($GLOBALS['woocommerce']->version)) ? $GLOBALS['woocommerce']->version : 0);
					
					$this->id					= 'paymill';
					$this->icon					= plugins_url('',__FILE__ ).'/../img/icon.png';
					$this->logo					= plugins_url('',__FILE__ ).'/../img/logo.png';
					$this->logo_small			= plugins_url('',__FILE__ ).'/../img/logo_small.png';

					$this->has_fields			= true;
					
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
				
					$this->init_form_fields();
					$this->init_settings();
					
					$this->title				= $this->settings['title'];
					$this->description			= $this->settings['description'];
					
					$this->supports = array(
						'products',
						'subscriptions',
						'subscription_cancellation',/*
						'subscription_suspension', 
						'subscription_reactivation',
						'subscription_amount_changes',
						'subscription_date_changes',
						'subscription_payment_method_change'*/
					);
				}
				
				function get_icon() {
					global $woocommerce;

					$icon = '<a href="https://www.paymill.com/" target="_blank"><img src="' . WC_HTTPS::force_https_url( $this->logo_small ) . '" alt="' . $this->title . '" /></a>';

					if(isset($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) && is_array($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) && count($GLOBALS['paymill_settings']->paymill_general_settings['payments_display']) > 0){
						foreach($GLOBALS['paymill_settings']->paymill_general_settings['payments_display'] as $name => $type){
							if($type==1){
								$icon .= '<img src="'.plugins_url('',__FILE__ ).'/../img/logos/'.$name.'.png" style="vertical-align:middle;" alt="'.$name.'" />';
							}
						}
					}
	
					return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
				}
				
				public function init_form_fields(){
					$this->form_fields = array(
						'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable PAYMILL Payment', 'woocommerce' ),
							'default' => 'yes'
						),
						'title' => array(
							'title' => __( 'Title', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'PAYMILL Payment', 'woocommerce' ),
							'desc_tip'      => true,
						),
						'description' => array(
							'title' => __( 'Customer Message', 'woocommerce' ),
							'type' => 'textarea',
							'default' => 'Payments made easy'
						)
					);
				}
				
				public function process_payment( $order_id ) {
					global $woocommerce,$wpdb;
					
					// get trial time
					$now = time();
					
					// first retrieve client data, either from cache or from API
					require_once(PAYMILL_DIR.'lib/integration/client.inc.php');
					$clientClass			= new paymill_client(
												$_POST['billing_email'],
												$_POST['billing_first_name'].' '.$_POST['billing_last_name']
											);
					
					$client					= $clientClass->getCurrentClient();

					// client retrieved, now we are ready to process the payment
					if($client['id'] !== false && strlen($client['id']) > 0){
						require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
						
						$paymentClass		= new paymill_payment($client['id']);
						
						$order = new WC_Order($order_id);

						/*ob_start();
						var_dump(WC_Subscriptions_Order::get_sign_up_fee($order));
						$var = ob_get_flush();
						$woocommerce->add_error($var);
						return;*/
						
						// make subscription
						if($client['id'] && $paymentClass->getPaymentID() && class_exists('WC_Subscriptions_Order') && WC_Subscriptions_Order::order_contains_subscription($order)){
							$cart = $woocommerce->cart->get_cart();
							
							$subscriptions = new paymill_subscriptions('woocommerce');
							
							foreach($cart as $product){

								$woo_sub_key	= WC_Subscriptions_Manager::get_subscription_key($order_id,$product['product_id']);

								if(!WC_Subscriptions_Manager::user_has_subscription(get_current_user_id(), $woo_sub_key)){
									// required vars
									$amount			= (floatval(WC_Subscriptions_Order::get_item_recurring_amount( $order,$product['product_id'] ))*100);
									$currency		= get_woocommerce_currency();
									
									$woocommerce->add_error($amount);
									$woocommerce->add_error($currency);
									return;
									$interval		= '1 '.strtoupper(WC_Subscriptions_Order::get_subscription_period( $order,$product['product_id'] ));

									$trial_end		= strtotime(WC_Subscriptions_Product::get_trial_expiration_date($product['product_id'], get_gmt_from_date($order->order_date)));
									if($trial_end === false){
										$trial_time		= 0;
									}else{
										$datediff		= $trial_end - $now;
										$trial_time		= ceil($datediff/(60*60*24));
									}
									
									// md5 name
									$woo_sub_md5	= md5($amount.$currency.$interval.$trial_time);
									
									// get offer
									$name			= 'woo_'.$product['product_id'].'_'.$woo_sub_md5;
									$offer			= $subscriptions->offerGetDetailByName($name);
									$offer			= $offer[0];
									
									// check wether offer exists in paymill
									if(count($offer) == 0){
										// offer does not exist in paymill yet, create it
										$params = array(
											'amount'			=> $amount,
											'currency'			=> $currency,
											'interval'			=> $interval,
											'name'				=> $name,
											'trial_period_days'	=> intval($trial_time)
										);
										$offer = $subscriptions->offerCreate($params);
										/*
							ob_start();
							var_dump($product['product_id']);
							var_dump(get_gmt_from_date($order->order_date));
							var_dump(WC_Subscriptions_Product::get_trial_expiration_date($product['product_id'], get_gmt_from_date($order->order_date)));
							var_dump($now);
							var_dump($trial_end);
							var_dump($trial_time);
							var_dump($offer);
							$var = ob_get_flush();
							$woocommerce->add_error($var);
							return;*/
										
										if(isset($offer['error']['messages'])){
											foreach($offer['error']['messages'] as $field => $msg){
												$woocommerce->add_error($field.': '.$msg);
											}
											return;
										}
									}

									// create user subscription
									$user_sub = $subscriptions->create($client['id'], $offer['id'], $paymentClass->getPaymentID());
									
									//ob_start(); var_dump($offer);var_dump($offer['id']); $var = ob_get_flush();
									//$woocommerce->add_error($var);
									
									if(isset($user_sub['error']) && strlen($user_sub['error']) > 0){
										$woocommerce->add_error(__($user_sub['error'], 'paymill'));
										return;
									}else{
										$wpdb->prepare('INSERT INTO '.$wpdb->prefix.'paymill_subscriptions (paymill_sub_id, woo_user_id, woo_offer_id) VALUES (%s, %s, %s)',
										array(
											$user_sub['id'],
											get_current_user_id(),
											$woo_sub_key
										));
									
										// subscription successful
											do_action('paymill_woocommerce_subscription_created', array(
												'product_id'	=> $id,
												'offer_id'		=> $offer['id'],
												'offer_data'	=> $offer
										));
									}
							
								}
							}
						}
						
						// calculate total based on product settings
						if(WC_Subscriptions_Order::order_contains_subscription($order)){
							$total = WC_Subscriptions_Order::get_sign_up_fee($order);
						}else{
							$total = (floatval($woocommerce->cart->total)*100);
						}
						
						// make transaction (single time)
						if($total > 0){
							$transactionsObject = new Services_Paymill_Transactions($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'], $GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);

							// make transaction
							$params = array(
								'amount'      	=> $total,  // e.g. "4200" for 42.00 EUR
								'currency'   	=> get_woocommerce_currency(),   // ISO 4217
								'payment'		=> $paymentClass->getPaymentID(),
								'client'     	=> $client['id'],
								'description'	=> 'Order #'.$order_id,
								'source'		=> serialize($GLOBALS['paymill_source'])
							);				
							$transaction        = $transactionsObject->create($params);

							$response = $transactionsObject->getResponse();
							if(isset($response['body']['data']['response_code']) && $response['body']['data']['response_code'] != '20000'){
								echo __($response['body']['data']['response_code'], 'paymill');
								die();
							}

							// save data to transaction table
							$wpdb->prepare('INSERT INTO '.$wpdb->prefix.'paymill_transactions
							(paymill_transaction_id, paymill_payment_id, paymill_client_id, woocommerce_order_id, paymill_transaction_time, paymill_transaction_data)
							VALUES (%s, %s, %s, %s, %d, %s)',
							array(
								$transaction['id'],
								$transaction['payment']['id'],
								$transaction['client']['id'],
								$order_id,
								$now,
								serialize($_POST)
								
							));
							
							do_action('paymill_woocommerce_products_paid', array(
								'total'			=> $total,
								'currency'		=> $GLOBALS['paymill_settings']->paymill_general_settings['currency'],
								'client'		=> $client['id']
							));
						}

					}

					//$woocommerce->add_error('ende');
					//return;
					
					// Mark as on-hold (we're awaiting the cheque)
					//$order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));
					
					if(method_exists($order, 'payment_complete')){
						$order->payment_complete();
					}

					// Reduce stock levels
					if(method_exists($order, 'reduce_order_stock')){
						$order->reduce_order_stock();
					}

					// Remove cart
					$woocommerce->cart->empty_cart();

					// Return thankyou redirect
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				}
				
				public function validate_fields(){
					global $woocommerce;
					// check Paymill payment
					if(empty($_POST['paymillToken'])){
						$woocommerce->add_error('Es konnte kein Token erstellt werden.');

						return false;
					}
					
					return true;
				}
				
				public function payment_fields(){
					global $woocommerce;

					if(!$GLOBALS['paymill_active']){
						paymill_load_frontend_scripts(); // load frontend scripts
					
						// settings
						$GLOBALS['paymill_active'] = true;
						$cart_total = $woocommerce->cart->total*100;
						$currency = get_woocommerce_currency();
						$cc_logo = plugins_url('',__FILE__ ).'/../img/cc_logos_v.png';
						$no_logos = true;
						
						// form ids
						echo '<script>
						paymill_form_checkout_id = ".checkout";
						paymill_form_checkout_submit_id = "#place_order";
						paymill_shop_name = "woocommerce";
						</script>';
			
						require_once(PAYMILL_DIR.'lib/tpl/checkout_form.php');
					}else{
						echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> Paymill can be loaded once only on the same page.</div>';
					}
					return true;
				}
			}
		}
	}
	add_action( 'plugins_loaded', 'init_paymill_gateway_class' );
?>