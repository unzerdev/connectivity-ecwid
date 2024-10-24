<?php
    include('config/db_connection.php');
    include('vendor/autoload.php');
    use UnzerSDK\Unzer;
    
    function updateOrder($eStoreId, $eOrderId, $eToken, $eAPIMethod, $eParameters){
		$eParameters = json_encode($eParameters);
		$ecwidCurl = curl_init();
		curl_setopt($ecwidCurl, CURLOPT_URL, "https://app.ecwid.com/api/v3/$eStoreId/orders/$eOrderId");
		curl_setopt($ecwidCurl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ecwidCurl, CURLOPT_CUSTOMREQUEST, $eAPIMethod);
		curl_setopt($ecwidCurl, CURLOPT_POSTFIELDS, $eParameters);
		$ecwidHeaders = array();
		$ecwidHeaders[] = 'Authorization: Bearer '.$eToken;
		$ecwidHeaders[] = 'Accept: application/json';
		$ecwidHeaders[] = 'Content-Type: application/json';
		$ecwidHeaders[] = 'Content-Length: ' . strlen($eParameters);
		curl_setopt($ecwidCurl, CURLOPT_HTTPHEADER, $ecwidHeaders);

		$result = curl_exec($ecwidCurl);
		if (curl_errno($ecwidCurl)) {
			echo 'Error:' . curl_error($ecwidCurl);
		}
		curl_close($ecwidCurl);
		//return $result;
	}
    
    if(isset($_GET['order']) && !empty($_GET['order'] != "")){
        $orderId = $_GET['order'];
        $explodeOrderIds = explode("-",$orderId);
        $cStoreId = $explodeOrderIds[0];
        $cPaymentId = $explodeOrderIds[1];
        $crPaymentMethodName = "Unzer Payment";
        
        $qForGetOrderDetails = mysqli_query($conn, "SELECT action,failureURL,paymentId FROM orders WHERE orderId='".$cPaymentId."' AND shopDescription='".$cStoreId."'");
        $rForGetOrderDetails = mysqli_fetch_assoc($qForGetOrderDetails);
        
        $u_action = $rForGetOrderDetails['action'];
        $u_cancelURL = $rForGetOrderDetails['failureURL'];
        $u_paymentId = $rForGetOrderDetails['paymentId'];
        
        $qForGetStoreDetails = mysqli_query($conn, "SELECT *  FROM configurations WHERE e_storeId='".$cStoreId."'");
        $rForGetStoreDetails = mysqli_fetch_assoc($qForGetStoreDetails);
        
        $eStoreToken = $rForGetStoreDetails['e_accessToken'];
        $u_publicKey = $rForGetStoreDetails['u_publicKey'];
        $u_privateKey = $rForGetStoreDetails['u_privateKey'];
        $u_authStatus = $rForGetStoreDetails['u_authStatus'];
        $u_captureStatus = $rForGetStoreDetails['u_captureStatus'];
        $u_chargeStatus = $rForGetStoreDetails['u_chargeStatus'];
        $u_autocapture = $rForGetStoreDetails['u_autocapture'];
        $ecwidPaymentStatus = "AWAITING_PAYMENT";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.unzer.com/v1/payments/{$u_paymentId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($u_privateKey.':'),
        ]);
        $responseOfUnzerPayment = curl_exec($ch);
        $responseOfUnzerPayment = json_decode($responseOfUnzerPayment , TRUE);
        $rUnzerStateId = $responseOfUnzerPayment['state']['id'];
        $rUnzerStateName = $responseOfUnzerPayment['state']['name'];
        $paymentTypeId = $responseOfUnzerPayment['resources']['typeId'];
        $paymentId = $responseOfUnzerPayment['id'];
    
        if($rUnzerStateId === 6 || $rUnzerStateId == "6"){
            header('Location:'.$u_cancelURL);
            exit;
        }

        //GET UNZER PAYMENT METHOD NAME BASED ON THEIR ID
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.unzer.com/v1/types/{$paymentTypeId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($u_privateKey.":"),
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {} else {
            $data = json_decode($response, true);
            if (isset($data['method'])) {
                $crPaymentMethodName = ucfirst($data['method']);
            }
        }
        curl_close($ch);
        
        $qForUpdateOrder = mysqli_query($conn, "UPDATE orders SET paymentMethodName = '".$crPaymentMethodName."',updated_at = CURRENT_TIMESTAMP WHERE orderId = '".$cPaymentId."' AND shopDescription = '".$cStoreId."'");
        
        if($u_action === "authorize" || $u_action == "authorize"){
            if ($rUnzerStateId === 1 || $rUnzerStateId == "1") {
                $ecwidPaymentStatus = "PAID"; //Completed
            }elseif ($rUnzerStateId === 2 || $rUnzerStateId == "2") {
                $ecwidPaymentStatus = "CANCELLED"; //Cancelled
            } elseif ($rUnzerStateId === 3 || $rUnzerStateId == "3") {
                $ecwidPaymentStatus = "PARTIALLY_REFUNDED"; //Partially Refund
            }elseif ($rUnzerStateId === 4 || $rUnzerStateId == "4") {
                $ecwidPaymentStatus = "AWAITING_PAYMENT"; //Partially Refund
            } else {
                //$ecwidPaymentStatus = "INCOMPLETE";
                $ecwidPaymentStatus = "AWAITING_PAYMENT"; //0 - Pending
            }
        }
        
        if($u_action === "charge" || $u_action == "charge"){
            $ecwidPaymentStatus = $u_captureStatus;
        }
        
        if($u_action === "refund" || $u_action == "refund"){
            $ecwidPaymentStatus = $u_chargeStatus;
        }
        
        if(($u_action === "charge" || $u_action == "charge")  && ($crPaymentMethodName === "Prepayment" || $crPaymentMethodName == "Prepayment")){
        	$chargePrePaymentAPIURl = "https://api.unzer.com/v1/payments/".$paymentId."/charges/s-chg-1";
        	$ch = curl_init();
        	curl_setopt($ch, CURLOPT_URL, $chargePrePaymentAPIURl);
        	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        	curl_setopt($ch, CURLOPT_HTTPHEADER, [
        		"Authorization: Basic " . base64_encode($u_privateKey . ":"),
        		"Content-Type: application/json"
        	]);
        	$cpResponse = curl_exec($ch);
        	if(isset($cpResponse) && !empty($cpResponse)){
        		$cpResponse = json_decode($cpResponse, true);
        		if(isset($cpResponse) && isset($cpResponse['processing']) && !empty($cpResponse['processing'])){
        				$paymentArray = array("store_id" => $cStoreId, "prepayment_response" => $cpResponse['processing'], "date" => date('Y-m-d h:i:s'));
        				$paymentArrayJson = json_encode($paymentArray);
        				file_put_contents('logs/unzer_pre_payment_response_'.date("Y-m-d").'.log', $paymentArrayJson, FILE_APPEND);
        				$chargeResponse = $cpResponse['processing'];
        				$chargeHolder = $chargeResponse['holder'];
        				$chargeIBAN = $chargeResponse['iban'];
        				$chargeBIC = $chargeResponse['bic'];
        				$chargeDescriptor = $chargeResponse['descriptor'];
        				$cpValue = "Please transfer the amount to the following account: Holder:".$chargeHolder.", IBAN:".$chargeIBAN.", BIC:".$chargeBIC.", Descriptor:  Please use only this identification number as the descriptor ".$chargeDescriptor;
        				/* $prepaymentCustomField = array(
        					'id' => 'prepaymentField',
        					'title' => 'Please transfer the amount to the following account',
        					'value' => $cpValue,
        					'customerInputType' => 'TEXT', 
        					'orderDetailsDisplaySection' => 'order_comments',
        					"showInNotifications" => true,
        					"showInInvoice"=> true
        				); */
        				//$prepaymentArray = array('orderExtraFields' => array($prepaymentCustomField));
        				$prepaymentArray = array('orderComments' => $cpValue);
        				updateOrder($cStoreId, $cPaymentId, $eStoreToken, 'PUT', $prepaymentArray);
        		}
        	}
        }
        
        $ecwidOrderExtraFields = array(
            'id' => 'UnzerPaymentState',
            'title' => 'Unzer Payment State',
            'value' => ucfirst($rUnzerStateName),
            'customerInputType' => 'TEXT', 
            'orderDetailsDisplaySection' => 'billing_info',
            "showInNotifications" => false,
            "showInInvoice"=> false
        );
        
        $paymentArray = array("store_id" => $cStoreId, "ecwid_order_id"=>$cPaymentId, "payment_id"=>$paymentId, "payment_status"=> $rUnzerStateId, "ecwid_payment_status" => $ecwidPaymentStatus, "unzer_payment_method" => $crPaymentMethodName, "unzer_payment_action" => $u_action, "date" => date('Y-m-d h:i:s'));
        $paymentArrayJson = json_encode($paymentArray);
        file_put_contents('logs/unzer_payment_capture_'.date("Y-m-d").'.log', $paymentArrayJson, FILE_APPEND);
        
        $ecwidReturnURL = "https://app.ecwid.com/custompaymentapps/$cStoreId?orderId=$cPaymentId&clientId=custom-app-15083087-15";
        $ecwidUpdateOrderStatus = array('paymentStatus'=>$ecwidPaymentStatus, 'externalTransactionId'=>"$paymentId", 'paymentMethod'=>"$crPaymentMethodName", 'orderExtraFields' => array($ecwidOrderExtraFields));
        updateOrder($cStoreId, $cPaymentId, $eStoreToken, 'PUT', $ecwidUpdateOrderStatus);
        header('Location:'.$ecwidReturnURL);
    }else{
        echo "There are some technical issues. Please try again later..!!";
    }
?>