<?php

class paymill_subscriptions{

	var $store						= false;
	var $subscriptionsObject		= false;
	var $cache						= false;

	public function __construct($store){
		$this->store				= $store;
	}

	public function getList(){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_getList'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		
		$response = $request->getAll($GLOBALS['paymill_loader']->request_subscription);
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_getList'); // benchmark
		return $response;
	}
	public function details($sub_id){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_details'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
	
		$GLOBALS['paymill_loader']->request_subscription->setId($sub_id);
		$response = $GLOBALS['paymill_loader']->request->getOne($GLOBALS['paymill_loader']->request_subscription);
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_details'); // benchmark
		return $response;
	}
	public function create($client, $offer, $payment){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_create'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		
		$GLOBALS['paymill_loader']->request_subscription->setClient($client);
		$GLOBALS['paymill_loader']->request_subscription->setOffer($offer);
		$GLOBALS['paymill_loader']->request_subscription->setPayment($payment);

		// @todo: handle response
		$subscription = $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_subscription);
	
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_create'); // benchmark
		return $subscription->getId();
	}/* @todo
	public function update(){
		$params = array(
			'id'					=> '',
			'cancel_at_period_end'	=> true,
			'offer'					=> '',
			'payment'				=> ''
		);
		$subscription				= $this->subscriptionsObject->update($params);
	}*/
	public function remove($sub_id){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_remove'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		
		$GLOBALS['paymill_loader']->request_subscription->setId($sub_id);
		// @todo: handle response
		$response = $GLOBALS['paymill_loader']->request->delete($GLOBALS['paymill_loader']->request_subscription);
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_remove'); // benchmark
		return $response;
	}
	
	public function offerGetList($reCache=false){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_offerGetList'); // benchmark
		global $wpdb;
	
		if($reCache === true){
			load_paymill(); // this function-call can and should be used whenever working with Paymill API
			$offersList = $GLOBALS['paymill_loader']->request->getAll($GLOBALS['paymill_loader']->request_offer);
			
			foreach($offersList as $offer){
				$offersListSorted[$offer['id']] = $offer;
			}

			$wpdb->query($wpdb->prepare('REPLACE INTO '.$wpdb->prefix.'paymill_cache SET cache_id="subscription_plans",cache_content=%s',
			array(
				serialize($offersListSorted)
			)));
			
			$this->cache['subscription_plans'] = $offersListSorted;
		}elseif(empty($this->cache['subscription_plans'])){
			$query				= 'SELECT * FROM '.$wpdb->prefix.'paymill_cache WHERE cache_id="subscription_plans"';
			$offersList			= $wpdb->get_results($query,ARRAY_A);
			$offersList			= unserialize($offersList[0]['cache_content']);
			$this->cache['subscription_plans'] = $offersList;
		}

		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_offerGetList'); // benchmark
		return $this->cache['subscription_plans'];
	}
	public function offerGetDetailByID($id){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_offerGetDetailByID'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API

		$GLOBALS['paymill_loader']->request_offer->setId($id);
		// @todo: handle response
		$response = $GLOBALS['paymill_loader']->request->getOne($GLOBALS['paymill_loader']->request_offer);
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_offerGetDetailByID'); // benchmark
		return $response;
	}
	public function offerGetDetailByName($name){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_offerGetDetailByName'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
	
		$GLOBALS['paymill_loader']->request_offer->setName($name);
		// @todo: handle response
		$response = $GLOBALS['paymill_loader']->request->getOne($GLOBALS['paymill_loader']->request_offer);
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_offerGetDetailByName'); // benchmark
		return $response;
	}
	
	public function offerCreate($params){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_subscription_offerCreate'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API
		
		$GLOBALS['paymill_loader']->request_offer->setAmount($params['amount']);
		$GLOBALS['paymill_loader']->request_offer->setCurrency($params['currency']);
		$GLOBALS['paymill_loader']->request_offer->setInterval($params['interval']);
		$GLOBALS['paymill_loader']->request_offer->setName($params['name']);
		$GLOBALS['paymill_loader']->request_offer->setTrialPeriodDays($params['trial_period_days']);

		// @todo: handle response
		$response = $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_offer);
		
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_subscription_offerCreate'); // benchmark
		return $response;
	}
}