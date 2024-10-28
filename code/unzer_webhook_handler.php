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
	return true;
}

$input = file_get_contents('php://input');
$webhookData = json_decode($input, true);
$cDate = date('Y-m-d H:i:s');
$uPaymentStatus = "";

$payloadArray = array("webhook_date"=> $webhookData,"Webhook_event" =>$webhookData['event'],  "date" => date("Y-m-d H:i:s"));
$payloadArrayJson = json_encode($payloadArray);
file_put_contents('logs/unzer_webhook/uWebhook_'.date("Y-m-d").'.log', $payloadArrayJson, FILE_APPEND);

if(isset($webhookData) && !empty($webhookData)){
    if(isset($webhookData['event']) && !empty($webhookData['event']) && isset($webhookData['publicKey']) && !empty($webhookData['publicKey']) && isset($webhookData['retrieveUrl']) && !empty($webhookData['retrieveUrl']) && isset($webhookData['paymentId']) && !empty($webhookData['paymentId'])){
        $webhookEvent = $webhookData['event'];
        $webhookPublicKey = $webhookData['publicKey'];
        $webhookRetriveURL = $webhookData['retrieveUrl'];
        $webhookPaymentId = $webhookData['paymentId'];
        
        $qForStoreDetails = mysqli_query($conn,"SELECT * FROM configurations WHERE u_publicKey='".$webhookPublicKey."' LIMIT 1");
        $rForCountStore=mysqli_num_rows($qForStoreDetails);
        if($rForCountStore > 0){
            $rForStoreDetails= mysqli_fetch_assoc($qForStoreDetails);
            $ecwidStoreId = $rForStoreDetails['e_storeId'];
            $ecwidStoreToken = $rForStoreDetails['e_accessToken'];
            $uAuthorizeStatus = $rForStoreDetails['u_authStatus'];
            $uChargeStatus = $rForStoreDetails['u_captureStatus'];
            $uRefundStatus = $rForStoreDetails['u_chargeStatus'];
            
            $qForGetWebhook =  mysqli_query($conn,"SELECT * FROM webhook_urls WHERE store_id='".$ecwidStoreId."'");
            $countWebhook = mysqli_num_rows($qForGetWebhook);
            if($countWebhook > 0){
                $qForGetOrderDetails = mysqli_query($conn,"SELECT orderId FROM orders WHERE paymentId='".$webhookPaymentId."' AND shopDescription='".$ecwidStoreId."'");
                $rForGetOrderDetails= mysqli_fetch_assoc($qForGetOrderDetails);
                $ecwidOrderId = $rForGetOrderDetails['orderId'];
                
                $qForGetWebhookDetails = mysqli_query($conn,"SELECT * FROM unzer_webhook WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id='".$webhookPaymentId."'");
                $qForCountWebhook =mysqli_num_rows($qForGetWebhookDetails);
                if($qForCountWebhook === 0 || $qForCountWebhook == 0){
                    $qForInsertWebhookDetails = mysqli_query($conn, "INSERT INTO unzer_webhook(unzer_event, unzer_public_key, unzer_retrieve_url, unzer_payment_id, created_at, ecwid_store_id, ecwid_store_token, ecwid_order_id) VALUES ('".$webhookEvent."','".$webhookPublicKey."','".$webhookRetriveURL."','".$webhookPaymentId."','".$cDate."','".$ecwidStoreId."','".$ecwidStoreToken."','".$ecwidOrderId."')");
                }else{
                    //$qForUpdateWebhookDetails = mysqli_query($conn, "UPDATE unzer_webhook SET ecwid_store_id='".$ecwidStoreId."',ecwid_store_token='".$ecwidStoreToken."',ecwid_order_id='".$ecwidOrderId."',unzer_event='".$webhookEvent."',unzer_retrieve_url='".$webhookRetriveURL."',updated_at='".$cDate."' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                }
                
                //AUTHORIZE EVENTS
                if($webhookEvent === "authorize" || $webhookEvent == "authorize"){
                    $uPaymentStatus = $uAuthorizeStatus;
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='authorize' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=>$uAuthorizeStatus);
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                if($webhookEvent === "authorize.succeeded" || $webhookEvent == "authorize.succeeded"){
                    $uPaymentStatus = $uAuthorizeStatus;
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='authorize.succeeded' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=>$uAuthorizeStatus);
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                if($webhookEvent === "authorize.failed" || $webhookEvent == "authorize.failed"){
                    $uPaymentStatus = "CANCELLED";
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='authorize.failed' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=>'CANCELLED');
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                if($webhookEvent === "authorize.pending" || $webhookEvent == "authorize.pending"){
                     $uPaymentStatus = $uAuthorizeStatus;
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='authorize.pending' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=> $uPaymentStatus);
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                if($webhookEvent === "authorize.canceled" || $webhookEvent == "authorize.canceled"){
                    $uPaymentStatus = "CANCELLED";
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='authorize.canceled' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=>"CANCELLED");
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                //CHARGE EVENTS
                if($webhookEvent === "charge" || $webhookEvent == "charge"){
                    $uPaymentStatus = $uChargeStatus;
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='charge' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=>$uChargeStatus);
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                if($webhookEvent === "charge.succeeded" || $webhookEvent == "charge.succeeded"){
                    $uPaymentStatus = $uChargeStatus;
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='charge.succeeded' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=> $uChargeStatus);
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                if($webhookEvent === "charge.failed" || $webhookEvent == "charge.failed"){
                    $uPaymentStatus = "CANCELLED";
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='charge.failed' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=>"CANCELLED");
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                if($webhookEvent === "charge.pending" || $webhookEvent == "charge.pending"){
                    $uPaymentStatus = $uChargeStatus;
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='charge.pending' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=> $uChargeStatus);
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                if($webhookEvent === "charge.canceled" || $webhookEvent == "charge.canceled"){
                    $uPaymentStatus = "CANCELLED";
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='charge.canceled' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=>"CANCELLED");
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
                
                //REFUND_EVENTS/CHARGE_BACK_EVENTS
                if($webhookEvent === "chargeback" || $webhookEvent == "chargeback"){
                    $uPaymentStatus = $uRefundStatus;
                    $qForUpdateWebhookStatus = mysqli_query($conn, "UPDATE unzer_webhook SET unzer_payment_status='".$uPaymentStatus."',updated_at='".$cDate."',unzer_event='chargeback' WHERE unzer_public_key='".$webhookPublicKey."' AND unzer_payment_id= '".$webhookPaymentId."'");
                    $ecwidOrderStatusArray = array('paymentStatus'=>$uRefundStatus);
                    updateOrder($ecwidStoreId, $ecwidOrderId, $ecwidStoreToken, 'PUT', $ecwidOrderStatusArray);
                }
            }
        }
    }
}else{
    echo "There are some technical issues. Please try again later.";
    exit;
}
// Respond with a 200 OK to acknowledge receipt
http_response_code(200);
?>
