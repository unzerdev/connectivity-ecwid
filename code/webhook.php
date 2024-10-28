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

//Reusable Curl Function
function sendCurlRequest($url, $method = 'GET', $headers = [], $body = null){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    if (!empty($headers)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    if (!empty($body) && ($method == 'POST' || $method == 'PUT' || $method == 'DELETE')) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($curl);
    if ($response === false) { $error = curl_error($curl);curl_close($curl);
        return ['success' => false, 'error' => $error];
    }
    curl_close($curl);
    return ['success' => true, 'response' => json_decode($response, true)];
}

function send200OKResponse(){
    header("HTTP/1.1 200 OK");
    echo json_encode(['status' => 'success']);
    exit;
}

// Function to Update Order in Ecwid
function updateOrder($eStoreId, $eOrderId, $eToken, $eAPIMethod, $eParameters){
    $url = "https://app.ecwid.com/api/v3/$eStoreId/orders/$eOrderId";
    $headers = [
        'Authorization: Bearer ' . $eToken,
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    return sendCurlRequest($url, $eAPIMethod, $headers, $eParameters);
}

// Function to Get Order Details from Ecwid
function getOrderDetailsEcwid($ecStoreId, $ecOrderId, $ecToken)
{
    $url = 'https://app.ecwid.com/api/v3/' . $ecStoreId . '/orders/' . $ecOrderId . '?token=' . $ecToken;
    $headers = ['Content-Type: application/json;charset=utf-8'];
    $response = sendCurlRequest($url, 'GET', $headers);
    return $response['success'] ? $response['response'] : null;
}

function getUnzerPaymentById($unzerPaymentId, $u_privateKey){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.unzer.com/v1/payments/{$unzerPaymentId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($u_privateKey.':'),
    ]);
    $responseOfUnzerPayment = curl_exec($ch);
    return json_decode($responseOfUnzerPayment , TRUE);
}

// Webhook Logic
if (isset($wbEventType) && $wbEventType === "order.updated") {
    $crDate = date("Y-m-d h:i:s");
    $webhookResponseLog = array("webhook" => $wehookResponseJsonData);
    file_put_contents('logs/ecwid_webhook/webhook_' . date("Y-m-d") . '.log', json_encode($webhookResponseLog), FILE_APPEND);

    $qForGetSecretToken = mysqli_query($conn, 'SELECT * FROM configurations WHERE e_storeId="' . $wbStoreId . '" ORDER BY id DESC LIMIT 1');
    if (mysqli_num_rows($qForGetSecretToken) > 0) {
        $rForGetSecretToken = mysqli_fetch_assoc($qForGetSecretToken);
        $ecSecretToken = $rForGetSecretToken['e_accessToken'];
        $u_publicKey = $rForGetSecretToken['u_publicKey'];
        $u_privateKey = $rForGetSecretToken['u_privateKey'];
        
        // Get Ecwid Order Details
        $getEcwidOrderDetails = getOrderDetailsEcwid($wbStoreId, $wbOrderId, $ecSecretToken);
        $ecwidInternalOrderId = $getEcwidOrderDetails['internalId'] ?? null;
        $ecwidPaymentStatus = $getEcwidOrderDetails['paymentStatus'] ?? null;
        $ecwidOrderTotal = $getEcwidOrderDetails['total'] ?? null;
        
        if ($ecwidInternalOrderId) {
            mysqli_query($conn, 'UPDATE orders SET amountUpdated = "' . $ecwidOrderTotal . '" WHERE shopDescription = "' . $wbStoreId . '" AND orderId = "' . $ecwidInternalOrderId . '"');
            $qForGetOrderDetails = mysqli_query($conn, 'SELECT * FROM orders WHERE shopDescription="' . $wbStoreId . '" AND orderId="' . $ecwidInternalOrderId . '"');

            if (mysqli_num_rows($qForGetOrderDetails) > 0) {
                $rForGetOrderDetails = mysqli_fetch_assoc($qForGetOrderDetails);
                $unzerPaymentId = $rForGetOrderDetails['paymentId'];
                $unzerAction = $rForGetOrderDetails['action'];
                $unzerAmount = $rForGetOrderDetails['amount'];
                $unzerAmountUpdated = $rForGetOrderDetails['amountUpdated'];
                $unzerReturnURL = $rForGetOrderDetails['returnUrl'];
                $unzerCurrency = $rForGetOrderDetails['currency'];

                $unzer = new Unzer($u_privateKey);

                // Processing Payment Status
                if ($wbPaymentStatus == "PAID" && $unzerPaymentId && $wbOldPaymentStatus != "PAID") {
                    if(($unzerAction === "charge" || $unzerAction == "charge")){
                        $getPaymentDetail = $unzer->fetchPayment($unzerPaymentId);
                        if(($getPaymentDetail->getPaymentType()->getId()) && $getPaymentDetail->getPaymentType()->getId() != ""){
                            $paymentTypeId = $getPaymentDetail->getPaymentType()->getId();
                            $getPaymentType = $unzer->fetchPaymentType($paymentTypeId);
                            $charge = $getPaymentType->charge($unzerAmount, $unzerCurrency, $unzerReturnURL);
                            
                            $firstCheckQuery = mysqli_query($conn,"SELECT * FROM ecwid_webhook WHERE ecwid_store_id = '".$wbStoreId."' AND ecwid_order_id = '".$wbOrderId."' AND unzer_action = '".$unzerAction."' AND unzer_payment_id = '".$unzerPaymentId."' AND ecwid_payment_status = '".$wbPaymentStatus."' AND unzer_api_execute_id = '".$charge->getId()."'");
                            $countFirst = mysqli_num_rows($firstCheckQuery);
                            if($countFirst === 0){
                                $qForGetOrderDetails = mysqli_query($conn, "INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$charge->getId()."')");
                                $paidStatus = array("charge_id"=>$charge->getId(), "action"=>$unzerAction, "date"=>$crDate);
                                $paidStatusJson = json_encode($paidStatus);
                                file_put_contents('logs/ecwid_webhook/PAID_CHARGE_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                            }
                        }
                    }

                    if(($unzerAction === "authorize" || $unzerAction == "authorize")){
                        $fetchAutorization = $unzer->fetchAuthorization($unzerPaymentId);
                        $postCharge = $fetchAutorization->charge($unzerAmountUpdated);
                        $secCheckQuery = mysqli_query($conn,"SELECT * FROM ecwid_webhook WHERE ecwid_store_id = '".$wbStoreId."' AND ecwid_order_id = '".$wbOrderId."' AND unzer_action = '".$unzerAction."' AND unzer_payment_id = '".$unzerPaymentId."' AND ecwid_payment_status = '".$wbPaymentStatus."' AND unzer_api_execute_id = '".$postCharge->getId()."'");
                        $countSec = mysqli_num_rows($secCheckQuery);
                        if($countSec === 0){
                            $qForGetOrderDetails = mysqli_query($conn, "INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$postCharge->getId()."')");
                            $paidStatus = array("amount" => $unzerAmountUpdated, "charge_id"=>$postCharge->getId(), "action"=>$unzerAction, "date"=>$crDate);
                            $paidStatusJson = json_encode($paidStatus);
                            file_put_contents('logs/ecwid_webhook/PAID_AUTHORIZED_STATUS_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                        }
                    }
                }

                if(($wbPaymentStatus == "CANCELLED" || $wbPaymentStatus == "REFUNDED") && $unzerAction == "authorize"){          
                    $getUnzerPaymentById = $unzer->fetchPayment($unzerPaymentId);
                    $authorization = $getUnzerPaymentById->getAuthorization();
                    $charges = $getUnzerPaymentById->getCharges();
                    if (!empty($authorization) && empty($charges)) {
                        $payment = $unzer->fetchAuthorization($unzerPaymentId);
                        $cancellation = $payment->cancel();
                        $thirdCheckQuery = mysqli_query($conn,"SELECT * FROM ecwid_webhook WHERE ecwid_store_id = '".$wbStoreId."' AND ecwid_order_id = '".$wbOrderId."' AND unzer_action = '".$unzerAction."' AND unzer_payment_id = '".$unzerPaymentId."' AND ecwid_payment_status = '".$wbPaymentStatus."' AND unzer_api_execute_id = '".$cancellation->getId()."'");
                        $countThird = mysqli_num_rows($thirdCheckQuery);
                        if($countThird === 0){
                            $qForGetOrderDetails = mysqli_query($conn, "INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$cancellation->getId()."')");
                            $paidStatus = array("CANCELLED_UNZER_RESPONSE" => $executeCharged, "charge_id"=>$cancellation->getId(), "action"=>$cancellation, "date"=>$crDate);
                            $paidStatusJson = json_encode($paidStatus);
                            file_put_contents('logs/ecwid_webhook/CANCELLED_AUTHORIZED_STATUS_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                        }
                    }
                    
                    if (!empty($charges)) {
                        $getUnzerPayment = $unzer->fetchPayment($unzerPaymentId);
                        if(($getUnzerPayment->getCharges()[0]->getId()) && !empty($getUnzerPayment->getCharges()[0]->getId())){
                            $chargeId = $getUnzerPayment->getCharges()[0]->getId();
                            $chargePaymentExecute = $unzer->fetchChargeById($unzerPaymentId, $chargeId);
                            $cancelChargePayment = $chargePaymentExecute->cancel();
                            $fourCheckQuery = mysqli_query($conn,"SELECT * FROM ecwid_webhook WHERE ecwid_store_id = '".$wbStoreId."' AND ecwid_order_id = '".$wbOrderId."' AND unzer_action = '".$unzerAction."' AND unzer_payment_id = '".$unzerPaymentId."' AND ecwid_payment_status = '".$wbPaymentStatus."' AND unzer_api_execute_id = '".$cancelChargePayment->getId()."'");
                            $countForth = mysqli_num_rows($fourCheckQuery);
                            if($countForth === 0){
                                $qForGetOrderDetails = mysqli_query($conn, "INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$cancelChargePayment->getId()."')");
                                $paidStatus = array("CANCELLED_UNZER_RESPONSE" => $cancelChargePayment, "charge_id"=>$cancelChargePayment->getId(), "action"=>$unzerAction, "date"=>$crDate);
                                $paidStatusJson = json_encode($paidStatus);
                                file_put_contents('logs/ecwid_webhook/CANCELLED_AUTHORIZED_CHARGED_STATUS_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                            }
                        }
                    }
                }

                if(($wbPaymentStatus == "CANCELLED" || $wbPaymentStatus == "REFUNDED") && $unzerAction == "charge"){
                    $getUnzerPayment = $unzer->fetchPayment($unzerPaymentId);
                    if(($getUnzerPayment->getCharges()[0]->getId()) && !empty($getUnzerPayment->getCharges()[0]->getId())){
                        $chargeId = $getUnzerPayment->getCharges()[0]->getId();
                        $chargePaymentExecute = $unzer->fetchChargeById($unzerPaymentId, $chargeId);
                        $cancelChargePayment = $chargePaymentExecute->cancel();
                        
                        $fiveCheckQuery = mysqli_query($conn,"SELECT * FROM ecwid_webhook WHERE ecwid_store_id = '".$wbStoreId."' AND ecwid_order_id = '".$wbOrderId."' AND unzer_action = '".$unzerAction."' AND unzer_payment_id = '".$unzerPaymentId."' AND ecwid_payment_status = '".$wbPaymentStatus."' AND unzer_api_execute_id = '".$cancelChargePayment->getId()."'");
                        $countFive = mysqli_num_rows($fiveCheckQuery);
                        if($countFive === 0){
                            $qForGetOrderDetails = mysqli_query($conn, "INSERT INTO ecwid_webhook (ecwid_store_id, ecwid_order_id, unzer_action, unzer_payment_id, ecwid_payment_status, unzer_api_execute_id) VALUES ('".$wbStoreId."', '".$wbOrderId."', '".$unzerAction."', '".$unzerPaymentId."', '".$wbPaymentStatus."', '".$cancelChargePayment->getId()."')");
                            $paidStatus = array("CANCELLED_UNZER_RESPONSE" => $cancelChargePayment, "charge_id"=>$cancelChargePayment->getId(), "action"=>$unzerAction, "date"=>$crDate);
                            $paidStatusJson = json_encode($paidStatus);
                            file_put_contents('logs/ecwid_webhook/CANCELLED_CHARGE_STATUS_'.date("Y-m-d").'.log', $paidStatusJson, FILE_APPEND);
                        }
                    }
                }

                $responseOfUnzerPayment = getUnzerPaymentById($unzerPaymentId, $u_privateKey);
                $rUnzerStateName = $responseOfUnzerPayment['state']['name']  ?? null;

                $ecwidOrderExtraFields = array(
                    'id' => 'UnzerPaymentState',
                    'title' => 'Unzer Payment State',
                    'value' => ucfirst($rUnzerStateName),
                    'customerInputType' => 'TEXT', 
                    'orderDetailsDisplaySection' => 'billing_info',
                    "showInNotifications" => false,
                    "showInInvoice"=> false
                );

                updateOrder($wbStoreId, $wbOrderId, $ecSecretToken, 'PUT', ['orderExtraFields' => [$ecwidOrderExtraFields]]);
            }
        }
    }
    send200OKResponse();
} else {
    send200OKResponse();
}
?>