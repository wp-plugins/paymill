<?php

	function paymill_pay_button_errorHandling($errors){
		$output = '';
		foreach($errors as $error){
			$output .= '<p class="paymill_error">'.$error.'</p>';
		}
		return $output;
	}

	function paymill_pay_button_process_payment(){
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_pay_button_errorHandling');
		global $wpdb;
		$GLOBALS['paymill_source']['pay_button_version'] = PAYMILL_VERSION;
		
		if(isset($_REQUEST['paymill_pay_button_order']) && $_REQUEST['paymill_pay_button_order'] == 1){
			// first retrieve client data, either from cache or from API
			require_once(PAYMILL_DIR.'lib/integration/client.inc.php');
			if(isset($_POST['forename']) && isset($_POST['surname'])){
				$desc		= $_POST['forename'].' '.$_POST['surname'];
			}elseif(isset($_POST['forename'])){
				$desc		= $_POST['forename'];
			}elseif(isset($_POST['surname'])){
				$desc		= $_POST['surname'];
			}else{
				$desc		= '';
			}
			$clientClass			= new paymill_client($_POST['email'],$desc);
			$client					= $clientClass->getCurrentClient();
			
			// client retrieved, now we are ready to process the payment
			if($client->getId() !== false && strlen($client->getId()) > 0){
				require_once(PAYMILL_DIR.'lib/integration/payment.inc.php');
				
				$subscriptions		= false;
				
				if($subscriptions === false){
					$subscriptions	= new paymill_subscriptions('pay_button');
				}
				
				// get the complete total for pre authorization
				$total_complete		= 0;
				$offers				= $subscriptions->offerGetList();
				foreach($_POST['paymill_quantity'] as $id => $quantity){
					if(isset($offers[$GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['offer']]['amount']) && floatval($offers[$GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['offer']]['amount']) > 0){
						$total_complete	= ($total_complete+floatval($offers[$GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['offer']]['amount'])*intval($quantity));
					}else{
						$total_complete	= ($total_complete+floatval($GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['price'])*intval($quantity))*100;
					}
				}
				$paymentClass		= new paymill_payment($client->getId(),$total_complete,$GLOBALS['paymill_settings']->paymill_general_settings['currency']); // create payment object, as it should be used for next processing instead of the token.
				if($GLOBALS['paymill_loader']->paymill_errors->status()){
					return false;
				}
				// calculate total based on product settings
				if(isset($_POST['paymill_quantity']) && count($_POST['paymill_quantity']) > 0){
					$total			= 0;
					$order_id		= time();
					
					foreach($_POST['paymill_quantity'] as $id => $quantity){
						// item is subscription, so don't add amount to total calculation
						if(isset($_POST['paymill_offer'][$id])){
							// create subscription
							if(isset($_POST['paymill_quantity'][$id]) && $_POST['paymill_quantity'][$id] == 1){
								$offer = $subscriptions->create($client->getId(), $_POST['paymill_offer'][$id], $paymentClass->getPaymentID());

								//var_dump($offer);
								// offer cannot be subscribed.
								if(isset($offer['error']) && strlen($offer['error']) > 0){
									echo __($offer['error'], 'paymill');
									die();
								}else{ // subscription successful
									do_action('paymill_paybutton_subscription_created', array(
										'product_id'	=> $id,
										'offer_id'		=> $_POST['paymill_offer'][$id],
										'offer_data'	=> $offer
									));
								}
							}
						}else{
							// retrieve product price and add to total
							$total	= ($total+floatval($GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$id]['price'])*intval($quantity));
						}
					}
					// now we have total amount of all non-subscription products. Time to make transaction.
					if($total > 0){
						// make transaction
						$order_desc = apply_filters( 'paymill_paybutton_order_desc', __('Order', 'paymill').' #'.$order_id."\n\n", array($order_id, $transaction, $_POST, $order_mail));
						
						// $paymentClass->getPaymentID()
						$GLOBALS['paymill_loader']->request_transaction->setAmount($total*100); // e.g. "4200" for 42.00 EUR
						$GLOBALS['paymill_loader']->request_transaction->setCurrency($GLOBALS['paymill_settings']->paymill_general_settings['currency']);
						$GLOBALS['paymill_loader']->request_transaction->setPreauthorization($paymentClass->getPreauthID());
						$GLOBALS['paymill_loader']->request_transaction->setClient($client->getId());
						$GLOBALS['paymill_loader']->request_transaction->setDescription($order_desc);
						// 'source'		=> serialize($GLOBALS['paymill_source'])
						
						$GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_transaction);

						$response = $GLOBALS['paymill_loader']->request->getLastResponse();
						
						if(isset($response['body']['data']['response_code']) && $response['body']['data']['response_code'] != '20000'){
							echo __($response['body']['data']['response_code'], 'paymill');
							die();
						}

						// save data to transaction table
						$wpdb->query($wpdb->prepare('
						INSERT INTO '.$wpdb->prefix.'paymill_transactions (paymill_transaction_id, paymill_payment_id, paymill_client_id, pay_button_order_id, paymill_transaction_time, paymill_transaction_data)
						VALUES (%s,%s,%s,%d,%d,%s)',
						array(
							$response['body']['data']['id'],
							$response['body']['data']['payment']['id'],
							$response['body']['data']['client']['id'],
							$order_id,
							$order_id,
							serialize($_POST)
						)));
						
						do_action('paymill_paybutton_products_paid', array(
							'total'			=> $total,
							'currency'		=> $GLOBALS['paymill_settings']->paymill_general_settings['currency'],
							'client'		=> $response['body']['data']['client']['id']
						));
					}

				}
			}else{
				echo __('There was an issue with adding you as client for the payment process.', 'paymill');
				die();
			}
			
			// order complete
			do_action( 'paymill_paybutton_order_complete', array($order_id, $transaction, $_POST) );
			
			// prepare order confirmation mail
			if(!isset($order_desc)){
				$order_desc = '';
			}
			
			// customer details
			$email_customer_desc = __('Company Name', 'paymill').': '.strip_tags($_POST['company_name'])."\n".
			__('Forename', 'paymill').': '.strip_tags($_POST['forename'])."\n".
			__('Surname', 'paymill').': '.strip_tags($_POST['surname'])."\n".
			__('Street', 'paymill').': '.strip_tags($_POST['street'])."\n".
			__('Number', 'paymill').': '.strip_tags($_POST['number'])."\n".
			__('ZIP', 'paymill').': '.strip_tags($_POST['zip'])."\n".
			__('City', 'paymill').': '.strip_tags($_POST['city'])."\n".
			__('Country', 'paymill').': '.strip_tags($_POST['paymill_shipping'])."\n".
			__('Email', 'paymill').': '.strip_tags($_POST['email'])."\n".
			__('Phone', 'paymill').': '.strip_tags($_POST['phone'])."\n\n";
			
			// products details
			foreach($_POST['paymill_quantity'] as $product => $quantity){
				if(intval($quantity) > 0){
					$order_products .= $quantity.'x '.$GLOBALS['paymill_settings']->paymill_pay_button_settings['products'][$product]['title']."\n";
				}
			}
			
			$order_mail = $order_desc.$email_customer_desc.$order_products;
			
			// allow filtering the order email
			$order_mail = apply_filters( 'paymill_paybutton_email_text', $order_mail, array($order_id, $transaction, $_POST, $order_mail));
			
			// send confirmation mail
			wp_mail(
				$_POST['email'],
				__('Confirmation of your Order', 'paymill'),
				$order_mail,
				'From: "'.get_option('blogname').'" <'.$GLOBALS['paymill_settings']->paymill_pay_button_settings['email_outgoing'].'>'
			);
			wp_mail(
				$GLOBALS['paymill_settings']->paymill_pay_button_settings['email_incoming'],
				__('New Order received', 'paymill'),
				$order_mail,
				'From: "'.get_option('blogname').'" <'.$GLOBALS['paymill_settings']->paymill_pay_button_settings['email_outgoing'].'>'
			);
		
			// order complete
			do_action( 'paymill_paybutton_email_sent', array($order_id, $transaction, $_POST, $order_mail) );
		
			// success, redirect if thankyou url is set
			if(isset($GLOBALS['paymill_settings']->paymill_pay_button_settings['thankyou_url']) && strlen($GLOBALS['paymill_settings']->paymill_pay_button_settings['thankyou_url']) > 0){
				header('Location: '.$GLOBALS['paymill_settings']->paymill_pay_button_settings['thankyou_url']);
				die();
			}
		}
	}
	
	add_action('plugins_loaded', 'paymill_pay_button_process_payment');

	class paymill_pay_button_widget extends WP_Widget{
		var $subscriptions = false;
		
		/** constructor */
		function __construct() {
			parent::WP_Widget('paymill_pay_button_widget', 'Paymill Pay Button', array( 'description' => __('Shows a Paymill Payment Button.', 'paymill')));
			
			load_paymill(); // this function-call can and should be used whenever working with Paymill API
			$GLOBALS['paymill_loader']->paymill_errors->setFunction('paymill_pay_button_errorHandling');
		}
		function widget($args, $instance){
			global $wpdb;
			if(
				!$GLOBALS['paymill_active'] &&
				isset($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) &&
				count($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) > 0
			){
				paymill_load_frontend_scripts(); // load frontend scripts
			
				$GLOBALS['paymill_active'] = true;
				
				echo $args['before_widget'];
				
				if(strlen($instance['title']) > 0){
					echo $args['before_title']; ?><?php echo $instance['title']; ?><?php echo $args['after_title'];
				}

				if(isset($_POST['paymill_pay_button_order']) && $_POST['paymill_pay_button_order'] == 1 && $GLOBALS['paymill_loader']->paymill_errors->status() === false){
					echo __('Thank you for your order.', 'paymill');
				}else{
					// settings
					$currency = $GLOBALS['paymill_settings']->paymill_general_settings['currency'];
					$cc_logo = plugins_url('',__FILE__ ).'/../img/cc_logos_v.png';
					$title = apply_filters( 'widget_title', $instance['title'] );
					
					if(isset($instance['products_list']) && strlen($instance['products_list']) > 0){
					
						$products_whitelist = explode(',',$instance['products_list']);
					}else{
						$products_whitelist = unserialize($instance['products']);
					}
					
					// form ids
					echo '<script>
					paymill_form_checkout_id = ".checkout";
					paymill_form_checkout_submit_id = "#place_order";
					paymill_shop_name = "paybutton";
					</script>';
					
					if($this->subscriptions === false){
						$this->subscriptions = new paymill_subscriptions('pay_button');
					}
					$offers = $this->subscriptions->offerGetList();

					// html / icons
					echo '<div id="payment" class="paymill_pay_button"><form action="#" method="post" class="checkout">';

					if(file_exists(get_template_directory().'/paymill/pay_button.php')){
						require(get_template_directory().'/paymill/pay_button.php');
					}else{
						require(PAYMILL_DIR.'lib/tpl/pay_button.php');
					}
					echo '<div class="paymill_payment_title">'.__('Payment', 'paymill').'</div>';
					require(PAYMILL_DIR.'lib/tpl/checkout_form.php');
					echo '<input type="submit" id="place_order" value="'.__('Pay now', 'paymill').'"/>';
					echo '</form></div>';
				}
				
				echo $args['after_widget'];
			}elseif(empty($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) || count($GLOBALS['paymill_settings']->paymill_pay_button_settings['products']) == 0){
				echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> You must have set at least one product.</div>';
			}else{
				echo '<div class="paymill_notification paymill_notification_once_only"><strong>Error:</strong> Paymill can be loaded once only on the same page.</div>';
			}

		}
		function update($new_instance, $old_instance){
			$instance = $old_instance;
			$instance['title']			= strip_tags($new_instance['title']);
			$instance['products']		= serialize($new_instance['products']);
			
			return $instance;
		}
		function form($instance) {
			$products_whitelist = unserialize($instance['products']);
			echo'
			<fieldset>
				<legend><h4>'.__('Title:', 'paymill').'</h4></legend>
				<label for="'.$this->get_field_id('title').'">
					<input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.esc_attr($instance['title']).'" />
				</label>
			</fieldset>
			<br />
			<fieldset>
				<legend><h4>'.__('Show these products only:', 'paymill').'</h5></legend>
				<label for="'.$this->get_field_id('products').'">
					<select class="widefat" style="width:220px;overflow:hidden;" id="'.$this->get_field_id('products').'" name="'.$this->get_field_name('products').'[]" multiple>
						<option value=""'.((!is_array($products_whitelist) || $products_whitelist[0] == '') ? ' selected="selected"' : '').'>'.__('All Products', 'paymill').'</option>
';
						foreach($GLOBALS['paymill_settings']->paymill_pay_button_settings['products'] as $id => $product){
							if(strlen($product['title']) > 0){
								echo '<option value="'.$id.'"'.(is_array(unserialize($instance['products'])) && in_array($id,unserialize($instance['products'])) ? ' selected="selected"' : '').'>'.$product['title'].'</option>';
							}
						}
echo '
					</select>
				</label>
			</fieldset>
			<br />
			';
		}
	}
	
	add_action('widgets_init', create_function('','register_widget("paymill_pay_button_widget");'));
	
	// creating shortcodes
	// [sv_cb foo="foo-value"]
	function paymill_pay_button_shortcode($atts){
		ob_start();
		the_widget('paymill_pay_button_widget',$atts,$args);
		return ob_get_clean();
	}
	add_shortcode('paymill_pb', 'paymill_pay_button_shortcode');
?>