<?php

	// this file should normalize and handle all payments from all ecommerce suites.

class paymill_payment{

	private $paymentData				= false;
	private $preauthData				= false;

	public function __construct($client_id,$amount,$currency){
		if(paymill_BENCHMARK)paymill_doBenchmark(true,'paymill_payment'); // benchmark
		load_paymill(); // this function-call can and should be used whenever working with Paymill API

		if($this->paymentData === false){
			try {
				$GLOBALS['paymill_loader']->request_payment->setToken($_POST['paymillToken']);
				$GLOBALS['paymill_loader']->request_payment->setClient($client_id);

				$this->paymentData	= $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_payment); // Use this for further payment processing
				if(is_object($this->paymentData)){
					$GLOBALS['paymill_loader']->request_preauth->setPayment($this->getPaymentID());
					$GLOBALS['paymill_loader']->request_preauth->setAmount($amount);
					$GLOBALS['paymill_loader']->request_preauth->setCurrency($currency);

					$this->preauthData	= $GLOBALS['paymill_loader']->request->create($GLOBALS['paymill_loader']->request_preauth); // Use this for further payment processing, too
				}
			}
			catch (Exception $e) {
				$GLOBALS['paymill_loader']->paymill_errors->setError(__($e->getMessage(),'paymill'));
			}
		}
		if(paymill_BENCHMARK)paymill_doBenchmark(false,'paymill_payment'); // benchmark
	}
	
	public function getPaymentID(){
		return $this->paymentData->getId();
	}
	
	public function getPreauthID(){
		return $this->preauthData->getId();
	}
	
}
?>