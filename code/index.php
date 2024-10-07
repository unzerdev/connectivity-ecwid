<?php
include('config/PATH.php');
include('config/db_connection.php');
include('includes/EcwidFunctions.php');
include("vendor/autoload.php");
use UnzerSDK\Unzer;

function getStoreProfile($storeId, $storeToken){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://app.ecwid.com/api/v3/$storeId/profile");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $headers = array(
        "Authorization: Bearer $storeToken"
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, TRUE);
}

$ecwidFunction = new EcwidFunctions();
$ecwidPayload  = $_GET['payload'];
$ecwidResponse = $ecwidFunction->getEcwidPayload(ECWID_SECRET_KEY,$ecwidPayload);

$ecwidToken = $ecwidResponse['access_token'];
$ecwidStoreId = $ecwidResponse['store_id'];
$ecwidLang = $ecwidResponse['lang'];
$ecwidViewMode = $ecwidResponse['view_mode'];

if (isset($ecwidResponse['public_token'])){
  $ecwidPublicToken = $ecwidResponse['public_token'];
}

if (isset($_GET['app_state'])){
  $ecwidAppState = $_GET['app_state'];
}

$dPublicKey = "";
$dPrivateKey = "";
$dAuthStatus = "";
$dCaptureStatus = "";
$dChargedStatus = "";
$dAutoCaptureStatus = "";

if(isset($ecwidResponse['store_id']) && !empty($ecwidResponse['store_id'])){
    $eStorId = $ecwidResponse['store_id'];
    $eStoreName = $eStorId;
   
    
    $qForGetStoreDetails = mysqli_query($conn, "SELECT u_publicKey,u_privateKey,u_authStatus,u_captureStatus,u_chargeStatus,u_autocapture,e_storeName FROM configurations WHERE e_storeId = '".$eStorId."'");
    $countNumOfStore = mysqli_num_rows($qForGetStoreDetails);
    if($countNumOfStore > 0){
        $rForGetStoreDetails = mysqli_fetch_assoc($qForGetStoreDetails);
        $dPublicKey = $rForGetStoreDetails['u_publicKey'];
        $dPrivateKey = $rForGetStoreDetails['u_privateKey'];
        $dAuthStatus = $rForGetStoreDetails['u_authStatus'];
        $dCaptureStatus = $rForGetStoreDetails['u_captureStatus'];
        $dAutoCaptureStatus = $rForGetStoreDetails['u_autocapture'];
        $dChargedStatus = $rForGetStoreDetails['u_chargeStatus'];
        
        if(empty($rForGetStoreDetails['e_storeName'])){
            $getEcwidStoreDetails = getStoreProfile($eStorId, $ecwidToken);
            if(isset($getEcwidStoreDetails) && !empty($getEcwidStoreDetails['settings']) && !empty($getEcwidStoreDetails['settings']['storeName'])){
                $eStoreName = $getEcwidStoreDetails['settings']['storeName'];
                mysqli_query($conn, "UPDATE configurations SET e_storeName='".$eStoreName."' WHERE e_storeId='".$eStorId."'");
            }
        }
        
		$qForCheckWebhookExists = mysqli_query($conn, "SELECT * FROM configurations WHERE e_storeId = '".$eStorId."' and u_webhookId IS NULL");
		$countWebhook = mysqli_num_rows($qForCheckWebhookExists);
		if($countWebhook > 0 && $dPublicKey != "" && $dPrivateKey != ""){
			$unzer = new UnzerSDK\Unzer($dPrivateKey);
			$existingWebhooks = $unzer->fetchAllWebhooks();
			$webhookUrl = 'https://unzerecwid.mavenhostingservice.com/unzer_webhook_handler.php';
            $webhookEvent = UnzerSDK\Constants\WebhookEvents::ALL;
			
			$isWebhookRegistered = false;
            foreach ($existingWebhooks as $webhook) {
                if ($webhook->getUrl() === $webhookUrl && $webhook->getEvent() === $webhookEvent) {
                    $uWebhookId = $webhook->getId();
			    	mysqli_query($conn, "UPDATE configurations SET u_webhookId='".$uWebhookId."' WHERE e_storeId='".$eStorId."'");
                    $isWebhookRegistered = true;
                    break;
                }
            }
            
            if (!$isWebhookRegistered) {
                $newWebhook = $unzer->createWebhook($webhookUrl, $webhookEvent);
            	if(isset($newWebhook) && !empty($newWebhook)){
    				$uWebhookId = $newWebhook->getId();
    				mysqli_query($conn, "UPDATE configurations SET u_webhookId='".$uWebhookId."' WHERE e_storeId='".$eStorId."'");
		        }
            }
		}
    }
}

$qForGetWebhooks = mysqli_query($conn, "SELECT *  FROM webhook_urls WHERE store_id = '".$eStorId."'");
$countWebhook = mysqli_num_rows($qForGetWebhooks);

?>
<!DOCTYPE html>
<html>

<head>
	<title>Unzer Payment Gateway</title>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="height=device-height, width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
	<script src="https://d35z3p2poghz10.cloudfront.net/ecwid-sdk/js/1.2.9/ecwid-app.js"></script>
	<link rel="stylesheet" href="https://d35z3p2poghz10.cloudfront.net/ecwid-sdk/css/1.3.13/ecwid-app-ui.css" />
	<link rel="stylesheet" href="<?php echo UNZER_ASSETS_URL; ?>/css/custom.css" />
	<link rel="shortcut icon" type="image/x-icon" href="<?php echo UNZER_ASSETS_URL; ?>/images/favicon.png">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.0/jquery.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
</head>

<body>
	<div>
		<div>
			<div class="named-area">
				<div class="named-area__body">
					<div class="">
						<div class="a-card__paddings">
							<div class="payment-method" id="payment-method-dashboard">
								<div class="payment-method__header">
									<div class="payment-method__logo">
										<img src="<?php echo UNZER_ASSETS_URL; ?>/images/unzer-logo.png" width="336" height="144" alt="">
									</div>
								</div>
								<div class="payment-method__title">Accept Payments. Effortlessly and Anywhere</div>
								<div class="payment-method__content">
									<div class="form-area">
										<div class="form-area__content">
											<p>Unzer, Offer the right payment methods online and in store. Unzer helps you with quick and easy integration, full support, and flexible solutions that grow with your business. We are your payment partner for every situation. To start accepting payments, enter the required details from below form from your account, </p>
										</div>

										<div class="columned">
											<div class="columned__item">
												<div class="form-area__title">Your Unzer account:</div>
												<form method="post" id="configurationForm" class="configurationForm">
													<div class="form-area__content">
														<div class="fieldsets-batch">
															<div class="fieldset">
																<div class="fieldset__field-wrapper field--focus">
																	<div class="field field--medium">
																		<span class="fieldset__svg-icon"></span>
																		<label class="field__label">Public Key</label>
																		<input data-name="u_public_key" data-visibility="private" type="text" name="u_public_key" id="u_public_key" tabindex="1" class="field__input" value="<?php echo $dPublicKey; ?>" required>
																		<div class="field__placeholder">Public Key</div>
																		<span style='margin-top:5px;font-size: 12px;margin-left: -8px;'>Get your Public Key from <a href="https://www.unzer.com/en/unzer-insights-login/" target="_blank">Unzer Payment</a></span>
																	</div>
																</div>
															</div>
															<div class="fieldset" style='margin-top:25px;'>
																<div class="fieldset__field-wrapper field--focus">
																	<div class="field field--medium">
																		<span class="fieldset__svg-icon"></span>
																		<label class="field__label">Private Key</label>
																		<input data-name="u_private_key" data-visibility="private" type="text" name="u_private_key" id="u_private_key" tabindex="2" class="field__input" value="<?php echo $dPrivateKey; ?>" required>
																		<div class="field__placeholder">Private Key</div>
																		<span style='margin-top:5px;font-size: 12px;margin-left: -8px;'>Get your Private API Key from <a href="https://www.unzer.com/en/unzer-insights-login/" target="_blank">Unzer Payment</a></span>
																	</div>
																</div>
															</div>
															<div class="fieldset fieldset--select" style='margin-top:25px;margin-bottom:15px;'>
																<div class="field field--medium">
																	<label class="field__label">Order status for authorized payments
																	</label>
																	<select class="field__select" name="u_authorized_status" id="u_authorized_status" required>
																		<option value="">Select Order status for authorized payments</option>
																		<option value="AWAITING_PAYMENT" <?php if($dAuthStatus == 'AWAITING_PAYMENT'): ?> selected="selected"<?php endif; ?>>AWAITING_PAYMENT</option>
																		<option value="PAID" <?php if($dAuthStatus == 'PAID'): ?> selected="selected"<?php endif; ?>>PAID</option>
																		<option value="CANCELLED" <?php if($dAuthStatus == 'CANCELLED'): ?> selected="selected"<?php endif; ?>>CANCELLED</option>
																		<option value="REFUNDED" <?php if($dAuthStatus == 'REFUNDED'): ?> selected="selected"<?php endif; ?>>REFUNDED</option>
																		<option value="PARTIALLY_REFUNDED" <?php if($dAuthStatus == 'PARTIALLY_REFUNDED'): ?> selected="selected"<?php endif; ?>>PARTIALLY_REFUNDED</option>
																		<option value="INCOMPLETE" <?php if($dAuthStatus == 'INCOMPLETE'): ?> selected="selected"<?php endif; ?>>INCOMPLETE</option>
																	</select>

																	<div class="field__placeholder">Select order status for authorized payments</div>
																	<span class="field-state--success"><svg xmlns="http://www.w3.org/2000/svg" width="26px" height="26px" viewBox="0 0 26 26">
																			<path d="M5 12l5.02 4.9L21.15 4c.65-.66 1.71-.66 2.36 0 .65.67.65 1.74 0 2.4l-12.3 14.1c-.33.33-.76.5-1.18.5-.43 0-.86-.17-1.18-.5l-6.21-6.1c-.65-.66-.65-1.74 0-2.41.65-.65 1.71-.65 2.36.01z"></path>
																		</svg></span>
																	<span class="field-state--close"><svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 16 16">
																			<path d="M15.6,15.5c-0.53,0.53-1.38,0.53-1.91,0L8.05,9.87L2.31,15.6c-0.53,0.53-1.38,0.53-1.91,0 c-0.53-0.53-0.53-1.38,0-1.9l5.65-5.64L0.4,2.4c-0.53-0.53-0.53-1.38,0-1.91c0.53-0.53,1.38-0.53,1.91,0l5.64,5.63l5.74-5.73 c0.53-0.53,1.38-0.53,1.91,0c0.53,0.53,0.53,1.38,0,1.91L9.94,7.94l5.66,5.65C16.12,14.12,16.12,14.97,15.6,15.5z"></path>
																		</svg></span>
																	<span class="field__arrow"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26 26">
																			<path d="M7.85 10l5.02 4.9 5.27-4.9c.65-.66 1.71-.66 2.36 0 .65.67.65 1.74 0 2.4l-6.45 6.1c-.33.33-.76.5-1.18.5-.43 0-.86-.17-1.18-.5l-6.21-6.1c-.65-.66-.65-1.74 0-2.41.66-.65 1.72-.65 2.37.01z"></path>
																		</svg></span>
																</div>
															</div>
															<div class="fieldset fieldset--select" style='margin-bottom:15px;'>
																<div class="field field--medium">
																	<label class="field__label">Order status for captured payments
																	</label>
																	<select class="field__select" name="u_captured_status" id="u_captured_status" required>
																		<option value="">Select Order status for captured payments
																		</option>
																		<option value="AWAITING_PAYMENT" <?php if($dCaptureStatus == 'AWAITING_PAYMENT'): ?> selected="selected"<?php endif; ?>>AWAITING_PAYMENT</option>
																		<option value="PAID" <?php if($dCaptureStatus == 'PAID'): ?> selected="selected"<?php endif; ?>>PAID</option>
																		<option value="CANCELLED" <?php if($dCaptureStatus == 'CANCELLED'): ?> selected="selected"<?php endif; ?>>CANCELLED</option>
																		<option value="REFUNDED" <?php if($dCaptureStatus == 'REFUNDED'): ?> selected="selected"<?php endif; ?>>REFUNDED</option>
																		<option value="PARTIALLY_REFUNDED" <?php if($dCaptureStatus == 'PARTIALLY_REFUNDED'): ?> selected="selected"<?php endif; ?>>PARTIALLY_REFUNDED</option>
																		<option value="INCOMPLETE" <?php if($dCaptureStatus == 'INCOMPLETE'): ?> selected="selected"<?php endif; ?>>INCOMPLETE</option>
																	</select>

																	<div class="field__placeholder">Select order status for captured payments
																	</div>
																	<span class="field-state--success"><svg xmlns="http://www.w3.org/2000/svg" width="26px" height="26px" viewBox="0 0 26 26">
																			<path d="M5 12l5.02 4.9L21.15 4c.65-.66 1.71-.66 2.36 0 .65.67.65 1.74 0 2.4l-12.3 14.1c-.33.33-.76.5-1.18.5-.43 0-.86-.17-1.18-.5l-6.21-6.1c-.65-.66-.65-1.74 0-2.41.65-.65 1.71-.65 2.36.01z"></path>
																		</svg></span>
																	<span class="field-state--close"><svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 16 16">
																			<path d="M15.6,15.5c-0.53,0.53-1.38,0.53-1.91,0L8.05,9.87L2.31,15.6c-0.53,0.53-1.38,0.53-1.91,0 c-0.53-0.53-0.53-1.38,0-1.9l5.65-5.64L0.4,2.4c-0.53-0.53-0.53-1.38,0-1.91c0.53-0.53,1.38-0.53,1.91,0l5.64,5.63l5.74-5.73 c0.53-0.53,1.38-0.53,1.91,0c0.53,0.53,0.53,1.38,0,1.91L9.94,7.94l5.66,5.65C16.12,14.12,16.12,14.97,15.6,15.5z"></path>
																		</svg></span>
																	<span class="field__arrow"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26 26">
																			<path d="M7.85 10l5.02 4.9 5.27-4.9c.65-.66 1.71-.66 2.36 0 .65.67.65 1.74 0 2.4l-6.45 6.1c-.33.33-.76.5-1.18.5-.43 0-.86-.17-1.18-.5l-6.21-6.1c-.65-.66-.65-1.74 0-2.41.66-.65 1.72-.65 2.37.01z"></path>
																		</svg></span>
																</div>
															</div>
															<div class="fieldset fieldset--select">
																<div class="field field--medium">
																	<label class="field__label">Order status for chargebacks
																	</label>
																	<select class="field__select" name="u_charged_status" id="u_charged_status" required>
																		<option value="">Select Order status for chargebacks
																		</option>
																		<option value="AWAITING_PAYMENT" <?php if($dChargedStatus == 'AWAITING_PAYMENT'): ?> selected="selected"<?php endif; ?>>AWAITING_PAYMENT</option>
																		<option value="PAID" <?php if($dChargedStatus == 'PAID'): ?> selected="selected"<?php endif; ?>>PAID</option>
																		<option value="CANCELLED" <?php if($dChargedStatus == 'CANCELLED'): ?> selected="selected"<?php endif; ?>>CANCELLED</option>
																		<option value="REFUNDED" <?php if($dChargedStatus == 'REFUNDED'): ?> selected="selected"<?php endif; ?>>REFUNDED</option>
																		<option value="PARTIALLY_REFUNDED" <?php if($dChargedStatus == 'PARTIALLY_REFUNDED'): ?> selected="selected"<?php endif; ?>>PARTIALLY_REFUNDED</option>
																		<option value="INCOMPLETE" <?php if($dChargedStatus == 'INCOMPLETE'): ?> selected="selected"<?php endif; ?>>INCOMPLETE</option>
																	</select>
																	<div class="field__placeholder">Select order status for chargebacks
																	</div>
																	<span class="field-state--success"><svg xmlns="http://www.w3.org/2000/svg" width="26px" height="26px" viewBox="0 0 26 26">
																			<path d="M5 12l5.02 4.9L21.15 4c.65-.66 1.71-.66 2.36 0 .65.67.65 1.74 0 2.4l-12.3 14.1c-.33.33-.76.5-1.18.5-.43 0-.86-.17-1.18-.5l-6.21-6.1c-.65-.66-.65-1.74 0-2.41.65-.65 1.71-.65 2.36.01z"></path>
																		</svg></span>
																	<span class="field-state--close"><svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 16 16">
																			<path d="M15.6,15.5c-0.53,0.53-1.38,0.53-1.91,0L8.05,9.87L2.31,15.6c-0.53,0.53-1.38,0.53-1.91,0 c-0.53-0.53-0.53-1.38,0-1.9l5.65-5.64L0.4,2.4c-0.53-0.53-0.53-1.38,0-1.91c0.53-0.53,1.38-0.53,1.91,0l5.64,5.63l5.74-5.73 c0.53-0.53,1.38-0.53,1.91,0c0.53,0.53,0.53,1.38,0,1.91L9.94,7.94l5.66,5.65C16.12,14.12,16.12,14.97,15.6,15.5z"></path>
																		</svg></span>
																	<span class="field__arrow"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26 26">
																			<path d="M7.85 10l5.02 4.9 5.27-4.9c.65-.66 1.71-.66 2.36 0 .65.67.65 1.74 0 2.4l-6.45 6.1c-.33.33-.76.5-1.18.5-.43 0-.86-.17-1.18-.5l-6.21-6.1c-.65-.66-.65-1.74 0-2.41.66-.65 1.72-.65 2.37.01z"></path>
																		</svg></span>
																</div>
															</div>
															<div class="fieldset fieldset--select">
																<div class="field field--medium">
																	<label class="field__label">Select auto capture payment
																	</label>
																	<select class="field__select" name="u_autocapture" id="u_autocapture" required>
																		<option value="AUTHORIZE" <?php if($dAutoCaptureStatus == 'AUTHORIZE'): ?> selected="selected"<?php endif; ?>>AUTHORIZE</option>
																		<option value="CHARGE" <?php if($dAutoCaptureStatus == 'CHARGE'): ?> selected="selected"<?php endif; ?>>CHARGE</option>
																	</select>
																	<div class="field__placeholder">Select auto capture payment
																	</div>
																	<span class="field-state--success"><svg xmlns="http://www.w3.org/2000/svg" width="26px" height="26px" viewBox="0 0 26 26">
																			<path d="M5 12l5.02 4.9L21.15 4c.65-.66 1.71-.66 2.36 0 .65.67.65 1.74 0 2.4l-12.3 14.1c-.33.33-.76.5-1.18.5-.43 0-.86-.17-1.18-.5l-6.21-6.1c-.65-.66-.65-1.74 0-2.41.65-.65 1.71-.65 2.36.01z"></path>
																		</svg></span>
																	<span class="field-state--close"><svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 16 16">
																			<path d="M15.6,15.5c-0.53,0.53-1.38,0.53-1.91,0L8.05,9.87L2.31,15.6c-0.53,0.53-1.38,0.53-1.91,0 c-0.53-0.53-0.53-1.38,0-1.9l5.65-5.64L0.4,2.4c-0.53-0.53-0.53-1.38,0-1.91c0.53-0.53,1.38-0.53,1.91,0l5.64,5.63l5.74-5.73 c0.53-0.53,1.38-0.53,1.91,0c0.53,0.53,0.53,1.38,0,1.91L9.94,7.94l5.66,5.65C16.12,14.12,16.12,14.97,15.6,15.5z"></path>
																		</svg></span>
																	<span class="field__arrow"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26 26">
																			<path d="M7.85 10l5.02 4.9 5.27-4.9c.65-.66 1.71-.66 2.36 0 .65.67.65 1.74 0 2.4l-6.45 6.1c-.33.33-.76.5-1.18.5-.43 0-.86-.17-1.18-.5l-6.21-6.1c-.65-.66-.65-1.74 0-2.41.66-.65 1.72-.65 2.37.01z"></path>
																		</svg></span>
																</div>
															</div>
														</div>
														
														
														
														
														<input type="hidden" name="action" id="action" value="register">
														<input type="hidden" name="storeId" id="storeId" value="<?php echo $ecwidStoreId; ?>">
														<input type="hidden" name="webhookUrl" id="webhookUrl" value="https://mavenhostingservice.com/unzerecwid/unzer_webhook_handler.php">
														<input type="hidden" name="storeAccessToken" id="storeAccessToken" value="<?php echo $ecwidToken; ?>">
														<button type="submit" class="btn btn-default btn-medium config-submit-button">Submit</button> <img src="<?php echo UNZER_ASSETS_URL; ?>/images/loader.gif" id="loader" class="loader" alt="loader"/>
                                                        
                                                        <?php if((isset($dPublicKey) && !empty($dPublicKey)) && (isset($dPrivateKey) && !empty($dPrivateKey)) && $countWebhook === 0){ ?>
                                                            <button class="btn btn-medium btn-link config-webhook-button" type="button" id="add-webhook-button">Add Webhook</button>
                                                        <?php } ?>
														<div class="ecwid-g-r ecwid-messages">
															<div class="ecwid-u-1 ecwid-message-block">
																<div class="success_message">
																	<div class="alert alert-success">
																			<strong>Record updated successfully...!!</strong>
																	</div>
																</div>
																<div class="delete_message">
																	<div class="alert alert-danger">
																			<strong>Record deleted successfully...!!</strong>
																	</div>
																</div>
																<div class="failed_message">
																	<div class="alert alert-danger">
																			<strong>There are some technical issues. Please try again later.</strong>
																	</div>
																</div>
															</div>
														</div>
												</form>
											</div>
										</div>

										<!-- Payment instructions block START -->
										<div class="columned__item columned__item--shifted">
											<div class="form-area__title">Configure Account</div>
											<div class="form-area__content">
												<ul class="bullet">
													<li>Login to <a href="https://www.unzer.com/en/unzer-insights-login/" target="_blank">Unzer Payment</a>.</li>
													<li>Go to <b>Settings</b> from left side menu.</li>
													<li>Click on <b>Integration</b> tab and then you can copy your Private Key and Public API Key and paste into the textbox in app.</li>
												</ul>
											</div>
										</div>
										<!-- Payment instructions block END -->

									</div>
									
									<!-- Webhook Listing -->
									<?php if((isset($dPublicKey) && !empty($dPublicKey)) && (isset($dPrivateKey) && !empty($dPrivateKey)) && $countWebhook > 0){ ?>
									<div class="a-card a-card--compact" style="margin-top:40px;">
                                       <div class="a-card__paddings">
                                          <h4>Configured Webhook</h4>
                                          <?php while($webhook = mysqli_fetch_assoc($qForGetWebhooks)){ ?>
                                          <div class="list-element list-element--has-hover list-element--compact list-element--inline-mode">
                                             <div class="list-element__content">
                                                <div class="list-element__info">
                                                   <div class="list-element__data-row"><span class="text-default"><b><span class="gwt-InlineLabel"><?php echo $webhook['unzer_webhook_url']  ?></span></b></span></div>
                                                </div>
                                                <div class="list-element__actions">
                                                   <div class="list-element__buttons-set">
                                                      <div class="list-element__button-wrapper">
                                                         <button type="button" class="btn btn-default btn-small delete-webhook" id="delete-webhook" data-value="<?php echo $webhook['id'] ?>">Delete</button>
                                                      </div>
                                                   </div>
                                                </div>
                                             </div>
                                          </div>
                                          <?php } ?>
                                       </div>
                                    </div>
                                    <?php } ?>
                                    
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	</div>
	<script src='<?php echo UNZER_ASSETS_URL; ?>/js/functions.js'></script>
	<script src="https://djqizrxa6f10j.cloudfront.net/ecwid-sdk/css/1.3.6/ecwid-app-ui.min.js"></script>
</body>

</html>