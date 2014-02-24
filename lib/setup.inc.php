<?php
	// load setup routines

	// install the tables
	register_activation_hook(__FILE__,'paymill_install');
	function paymill_install() {
		global $wpdb;
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
$sql = 'CREATE TABLE '.$wpdb->prefix.'paymill_clients (
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_email varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_description longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  wp_member_id int(11) NOT NULL,
  UNIQUE KEY paymill_client_id (paymill_client_id));';

$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_transactions (
  paymill_transaction_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_payment_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_client_id varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  paymill_transaction_time int(11) NOT NULL,
  paymill_transaction_data longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woocommerce_order_id int(11) NOT NULL,
  pay_button_order_id int(11) NOT NULL,
  shopplugin_order_id int(11) NOT NULL,
  UNIQUE KEY paymill_transaction_id (paymill_transaction_id));';
  
$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_cache (
  cache_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  cache_content longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  UNIQUE KEY cache_id (cache_id));';
  
$sql .= 'CREATE TABLE '.$wpdb->prefix.'paymill_subscriptions (
  paymill_sub_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woo_user_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  woo_offer_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  mgm_user_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  mgm_offer_id varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  UNIQUE KEY paymill_sub_id (paymill_sub_id));';
  
		dbDelta($sql);
		
		// paymill webhooks
		// get webhooks list
		$srv = new Services_Paymill_Webhooks($GLOBALS['paymill_settings']->paymill_general_settings['api_key_private'],$GLOBALS['paymill_settings']->paymill_general_settings['api_endpoint']);

		$webhook = $srv->getOne(get_option('paymill_webhook_id'));
		
		if(!$webhook){
			$webhook = $srv->create(array(
				'url'         => get_site_url().'/?paymill_webhook=1',
				'event_types' => array(
					'subscription.created',
					'subscription.deleted',
					'subscription.failed'
				)
			));
		
			add_option('paymill_webhook_id', $webhook['id']);
		}else{
			// @todo: This syntax doesn't work anymore
			$webhook = $srv->update(array(
				'event_types' => array(
					'subscription.created',
					'subscription.deleted',
					'subscription.failed'
				)
			));
		}
		
		if(!get_option('paymill_db_version')){
			add_option('paymill_db_version', PAYMILL_VERSION);
		}elseif(get_option('paymill_db_version') != PAYMILL_VERSION){
			update_option('paymill_db_version', PAYMILL_VERSION);
		}
	}

	if(!get_option('paymill_db_version')){
		paymill_install();
	}elseif(get_option('paymill_db_version') != PAYMILL_VERSION){
		paymill_install();
	}
?>