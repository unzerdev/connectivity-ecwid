<?php 
    include('config/db_connection.php');
    include('vendor/autoload.php');
    use UnzerSDK\Unzer;
    use UnzerSDK\Resources\TransactionTypes\Charge;

	$wehookResponse = file_get_contents('php://input');
    $wehookResponseJsonData = json_decode($wehookResponse);
    $wbStoreId = $wehookResponseJsonData->storeId;
    $wbOrderId = $wehookResponseJsonData->entityId; 
    $wbEventType = $wehookResponseJsonData->eventType; 
    $wbEventId = $wehookResponseJsonData->eventId;
    $wbPaymentStatus = $wehookResponseJsonData->data->newPaymentStatus;
    $wbOldPaymentStatus = $wehookResponseJsonData->data->oldPaymentStatus;
    
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
			//return true;
		}
    
    function getOrderDetailsEcwid($ecStoreId, $ecOrderId, $ecToken){
        $getOrderDetails ='https://app.ecwid.com/api/v3/'.$ecStoreId.'/orders/'.$ecOrderId.'?token='.$ecToken;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$getOrderDetails);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=utf-8'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $orderResponse = curl_exec($ch);
        curl_close ($ch);
        return json_decode($orderResponse);
    }
    
    if(isset($wbEventType)){ 
        if($wbEventType == "order.updated"){
                $crDate = date("Y-m-d h:i:s");
                $webhookResponseLog = array("webhook" => $wehookResponseJsonData);
                $webhookResponseLogJsonArray = json_encode($webhookResponseLog);
                file_put_contents('logs/ecwid_webhook/webhook_'.date("Y-m-d").'.log', $webhookResponseLogJsonArray, FILE_APPEND);
                
                $qForGetSecretToken = mysqli_query($conn,'select * from configurations WHERE e_storeId="'.$wbStoreId.'" ORDER BY id desc LIMIT 1');
                $countNumberOfStore = mysqli_num_rows($qForGetSecretToken);
                if($countNumberOfStore > 0){
                    $rForGetSecretToken = mysqli_fetch_assoc($qForGetSecretToken);
                    $ecSecretToken = $rForGetSecretToken['e_accessToken'];
                    $u_publicKey = $rForGetSecretToken['u_publicKey'];
                    $u_privateKey = $rForGetSecretToken['u_privateKey'];
                    $u_authStatus = $rForGetSecretToken['u_authStatus'];
                    $u_captureStatus = $rForGetSecretToken['u_captureStatus'];
                    $u_chargeStatus = $rForGetSecretToken['u_chargeStatus'];
                    $u_autocapture = $rForGetSecretToken['u_autocapture'];
                    $u_webhookId = $rForGetSecretToken['u_webhookId'];
                    
                    //GET ORDER INFORMATION FROM ECWID
                    $getEcwidOrderDetails = getOrderDetailsEcwid($wbStoreId, $wbOrderId, $ecSecretToken);
                    $ecwidInternalOrderId = $getEcwidOrderDetails->internalId;
                    $ecwidPaymentStatus = $getEcwidOrderDetails->paymentStatus;
                    $ecwidOrderTotal = $getEcwidOrderDetails->total;
                    
                    $qForUpdateOrderAmount = mysqli_query($conn, 'UPDATE orders SET amountUpdated = "'.$ecwidOrderTotal.'" WHERE shopName = "'.$wbStoreId.'" AND orderId = "'.$ecwidInternalOrderId.'"');
                    
                    if($ecwidInternalOrderId != ""){
                        $qForGetOrderDetails = mysqli_query($conn,'select orderId,paymentId,shopName,action,paymentMethodName,amount,amountUpdated,currency,returnUrl from orders where shopName="'.$wbStoreId.'" AND orderId="'.$ecwidInternalOrderId.'"');
                        $countOrderDetails = mysqli_num_rows($qForGetOrderDetails);
                        if($countOrderDetails  > 0){
                            $rForGetOrderDetails = mysqli_fetch_assoc($qForGetOrderDetails);
                            $unzerPaymentId = $rForGetOrderDetails['paymentId'];
                            $unzerAction = $rForGetOrderDetails['action'];
                            $unzerAmount = $rForGetOrderDetails['amount'];
                            $unzerAmountUpdated = $rForGetOrderDetails['amountUpdated'];
                            $unzerReturnURL = $rForGetOrderDetails['returnUrl'];
                            $unzerCurrency = $rForGetOrderDetails['currency'];
                            
                            if($unzerPaymentId != "" && $u_publicKey != "" && $u_privateKey != "" && $wbStoreId != "" && $ecwidPaymentStatus != ""){
                                $unzer = new Unzer($u_privateKey);
                                
                                if($wbPaymentStatus == "PAID"){
                                        if(($unzerAction === "charge" || $unzerAction == "charge")){
                                            $getPaymentDetail = $unzer->fetchPayment($unzerPaymentId);
                                            if(($getPaymentDetail->getPaymentType()->getId()) && $getPaymentDetail->getPaymentType()->getId() != ""){
                                            	$paymentTypeId = $getPaymentDetail->getPaymentType()->getId();
                                            	$getPaymentType = $unzer->fetchPaymentType($paymentTypeId);
                                            	$charge = $getPaymentType->charge($unzerAmount, $unzerCurrency, $unzerReturnURL);
                                            	$qForGetOrderDetails = mysqli_query($conn,"INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$charge->getId()."') ON DUPLICATE KEY UPDATE   ecwid_store_id = '".$wbStoreId."', ecwid_order_id = '".$wbOrderId."', unzer_action = '".$unzerAction."',  unzer_payment_id = '".$unzerPaymentId."', ecwid_payment_status = '".$wbPaymentStatus."', unzer_api_execute_id = '".$charge->getId()."'");
                                                $paidStatus = array("PAID_UNZER_RESPONSE" => $charge, "charge_id"=>$charge->getId(), "action"=>$unzerAction, "date"=>$crDate);
                                                $paidStatusJson = json_encode($paidStatus);
                                                file_put_contents('logs/ecwid_webhook/PAID_CHARGE_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                                            }
                                        }
                                        
                                        if(($unzerAction === "authorize" || $unzerAction == "authorize")){
                                            $fetchAutorization = $unzer->fetchAuthorization($unzerPaymentId);
                                            $postCharge = $fetchAutorization->charge($unzerAmountUpdated);
                                        	$qForGetOrderDetails = mysqli_query($conn,"INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$postCharge->getId()."') ON DUPLICATE KEY UPDATE ecwid_store_id = '".$wbStoreId."', ecwid_order_id = '".$wbOrderId."', unzer_action = '".$unzerAction."',  unzer_payment_id = '".$unzerPaymentId."', ecwid_payment_status = '".$wbPaymentStatus."', unzer_api_execute_id = '".$postCharge->getId()."'");
                                            $paidStatus = array("amount" => $unzerAmountUpdated, "charge_id"=>$postCharge->getId(), "action"=>$unzerAction, "date"=>$crDate);
                                            $paidStatusJson = json_encode($paidStatus);
                                            file_put_contents('logs/ecwid_webhook/PAID_AUTHORIZED_STATUS_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                                        }
                                }
                                
                                if(($wbPaymentStatus == "CANCELLED" || $wbPaymentStatus == "REFUNDED") && $unzerAction == "authorize"){
                                    
                                    $getUnzerPaymentById = $unzer->fetchPayment($unzerPaymentId);
                                    $authorization = $getUnzerPaymentById->getAuthorization();
                                    $charges = $getUnzerPaymentById->getCharges();
                                    if (!empty($authorization) && empty($charges)) {
                                        $payment = $unzer->fetchAuthorization($unzerPaymentId);
                                        $cancellation = $payment->cancel();
                                        $qForGetOrderDetails = mysqli_query($conn,"INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$cancellation->getId()."') ON DUPLICATE KEY UPDATE   ecwid_store_id = '".$wbStoreId."', ecwid_order_id = '".$wbOrderId."', unzer_action = '".$unzerAction."',  unzer_payment_id = '".$unzerPaymentId."', ecwid_payment_status = '".$wbPaymentStatus."', unzer_api_execute_id = '".$cancellation->getId()."'");
                                        $paidStatus = array("CANCELLED_UNZER_RESPONSE" => $executeCharged, "charge_id"=>$cancellation->getId(), "action"=>$cancellation, "date"=>$crDate);
                                        $paidStatusJson = json_encode($paidStatus);
                                        file_put_contents('logs/ecwid_webhook/CANCELLED_AUTHORIZED_STATUS_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                                    }
                                    
                                    if (!empty($charges)) {
                                        $getUnzerPayment = $unzer->fetchPayment($unzerPaymentId);
                                        if(($getUnzerPayment->getCharges()[0]->getId()) && !empty($getUnzerPayment->getCharges()[0]->getId())){
                                            $chargeId = $getUnzerPayment->getCharges()[0]->getId();
                                            $chargePaymentExecute = $unzer->fetchChargeById($unzerPaymentId, $chargeId);
                                            $cancelChargePayment = $chargePaymentExecute->cancel();
                                            $qForGetOrderDetails = mysqli_query($conn,"INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$cancelChargePayment->getId()."') ON DUPLICATE KEY UPDATE   ecwid_store_id = '".$wbStoreId."', ecwid_order_id = '".$wbOrderId."', unzer_action = '".$unzerAction."',  unzer_payment_id = '".$unzerPaymentId."', ecwid_payment_status = '".$wbPaymentStatus."', unzer_api_execute_id = '".$cancelChargePayment->getId()."'");
                                            $paidStatus = array("CANCELLED_UNZER_RESPONSE" => $cancelChargePayment, "charge_id"=>$cancelChargePayment->getId(), "action"=>$unzerAction, "date"=>$crDate);
                                            $paidStatusJson = json_encode($paidStatus);
                                            file_put_contents('logs/ecwid_webhook/CANCELLED_AUTHORIZED_CHARGED_STATUS_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                                        }
                                    }
                                }
                              
                                if(($wbPaymentStatus == "CANCELLED" || $wbPaymentStatus == "REFUNDED") && $unzerAction == "charge"){
                                    $getUnzerPayment = $unzer->fetchPayment($unzerPaymentId);
                                    if(($getUnzerPayment->getCharges()[0]->getId()) && !empty($getUnzerPayment->getCharges()[0]->getId())){
                                        $chargeId = $getUnzerPayment->getCharges()[0]->getId();
                                        $chargePaymentExecute = $unzer->fetchChargeById($unzerPaymentId, $chargeId);
                                        $cancelChargePayment = $chargePaymentExecute->cancel();
                                        $qForGetOrderDetails = mysqli_query($conn,"INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$cancelChargePayment->getId()."') ON DUPLICATE KEY UPDATE   ecwid_store_id = '".$wbStoreId."', ecwid_order_id = '".$wbOrderId."', unzer_action = '".$unzerAction."',  unzer_payment_id = '".$unzerPaymentId."', ecwid_payment_status = '".$wbPaymentStatus."', unzer_api_execute_id = '".$cancelChargePayment->getId()."'");
                                        $paidStatus = array("CANCELLED_UNZER_RESPONSE" => $cancelChargePayment, "charge_id"=>$cancelChargePayment->getId(), "action"=>$unzerAction, "date"=>$crDate);
                                        $paidStatusJson = json_encode($paidStatus);
                                        file_put_contents('logs/ecwid_webhook/CANCELLED_CHARGE_STATUS_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                                    }
                                }
                            }
                        }
                    }
                }
        }
    }
	
?>