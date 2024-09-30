<?php
	include('config/db_connection.php');
	include('vendor/autoload.php');
	use UnzerSDK\Unzer;
    use UnzerSDK\Resources\Customer;
    use UnzerSDK\Resources\EmbeddedResources\Address;
    use UnzerSDK\Constants\Salutations;
    use UnzerSDK\Resources\TransactionTypes\Authorization;
    use UnzerSDK\Resources\Basket;
    use UnzerSDK\Resources\EmbeddedResources\BasketItem;
    use UnzerSDK\Constants\BasketItemTypes;
    use UnzerSDK\Resources\PaymentTypes\Paypage;

	function getEcwidPayload($app_secret_key, $data) {
		$encryption_key = substr($app_secret_key, 0, 16);
		$json_data = aes_128_decrypt($encryption_key, $data);
		$json_decoded = json_decode($json_data, true);
		return $json_decoded;
	}
	function aes_128_decrypt($key, $data) {
		$base64_original = str_replace(array('-', '_'), array('+', '/'), $data);
		$decoded = base64_decode($base64_original);
		$iv = substr($decoded, 0, 16);
		$payload = substr($decoded, 16);
		$json = openssl_decrypt($payload, "aes-128-cbc", $key, OPENSSL_RAW_DATA, $iv);
		return $json;
	}

	$ecwid_payload = $_POST['data'];
	$client_secret = "oizuHCVj9GJlz9CHdeZvVPrd77YIKl0O"; 
	$result = getEcwidPayload($client_secret, $ecwid_payload);
	
	$payloadArray = array("response"=> $result, "date" => date("Y-m-d H:i:s"));
    $payloadArrayJson = json_encode($payloadArray);
    file_put_contents('logs/ecwid_webhook/payment_request_'.date("Y-m-d").'.log', $payloadArrayJson, FILE_APPEND);
	
    if(isset($result) && !empty($result)){
        $orderTotalWithoutTax = 0;
        $myStoreId = $result['storeId'];
        $storeLang = $result['lang'];
        $storeLangInUpper = strtoupper($storeLang);
        $langugage = $storeLang."-".$storeLangInUpper;
        $storeName = "Store-".$myStoreId;
        $myReturnUrl = $result['returnUrl'];
        $orderId = $result['cart']['order']['orderNumber'];
        $orderTotal = number_format($result['cart']['order']['total'],2);
        $orderTotalWithoutTax = number_format($result['cart']['order']['subtotalWithoutTax'],2);
        $orderTotalTax = number_format($result['cart']['order']['tax'],2);
        $orderEmail = $result['cart']['order']['email'];
        $orderItems = $result['cart']['order']['items'];
        $orderItemsCount = count($result['cart']['order']['items']);
        $orderCurrency = $result['cart']['currency'];
        $mySecTok = $result['token'];
        $orderRandNumber = $myStoreId."-".$orderId;
        $myTransId = $result['cart']['order']['referenceTransactionId'];
        $billingAddress = $result['cart']['order']['billingPerson'];
        $ecwidSecretToken = $result['token'];
        $cancelUrl  = $result['returnUrl'];
        $successURL = "https://unzerecwid.mavenhostingservice.com/returnurl.php?order=$orderRandNumber";
        $orderTotal = str_replace(",", "",$orderTotal);
        $orderTotalWithoutTax = str_replace(",", "",$orderTotalWithoutTax);
        $orderTotalTax = str_replace(",", "",$orderTotalTax);
       
        $qForStoreDetails = mysqli_query($conn,"SELECT * FROM configurations WHERE e_storeId='".$myStoreId."'");
        $rForCountStore=mysqli_num_rows($qForStoreDetails);
        $rForStoreDetails=mysqli_fetch_assoc($qForStoreDetails);
        
        $qForUpdateToken = mysqli_query($conn," UPDATE configurations SET e_accessToken='".$ecwidSecretToken."' WHERE e_storeId='".$myStoreId."'");

        if($rForCountStore > 0){
            $eStoreId = $rForStoreDetails['e_storeId'];
            $eStoreToken = $rForStoreDetails['e_accessToken'];
            $u_publicKey = $rForStoreDetails['u_publicKey'];
            $u_privateKey = $rForStoreDetails['u_privateKey'];
            $u_authStatus = $rForStoreDetails['u_authStatus'];
            $u_captureStatus = $rForStoreDetails['u_captureStatus'];
            $u_chargeStatus = $rForStoreDetails['u_chargeStatus'];
            $u_autocapture = $rForStoreDetails['u_autocapture'];
            
            $unzer = new Unzer($u_privateKey);
            
            //SHIPMENT DETAILS
            $shippingRate = 0;
            $shippingMethodName = "N/A";
            if(isset($result['cart']['order']['shippingOption']) && !empty($result['cart']['order']['shippingOption'])){
                $shippingOptions = $result['cart']['order']['shippingOption'];
                $shippingMethodName = (isset($shippingOptions) && $shippingOptions['shippingMethodName']) ? $shippingOptions['shippingMethodName'] : 'N/A';
                if(isset($shippingOptions)  && isset($shippingOptions['shippingRate']) && !empty($shippingOptions['shippingRate'])){
                    $shippingRate = number_format($shippingOptions['shippingRate'], 2);
                }
            }
    
            $orderTotalWithoutTax =  $orderTotalWithoutTax + $shippingRate;
            $orderTotalWithoutTax = number_format($orderTotalWithoutTax,2);
           
            //BILLING ADDRESS
            $bName = ($billingAddress['name']) ? $billingAddress['name'] : 'N/A';
            $bSreet = ($billingAddress['street']) ? $billingAddress['street'] : 'N/A';
            $bPcode = ($billingAddress['postalCode']) ? $billingAddress['postalCode'] : 'N/A';
            $bCity = ($billingAddress['city']) ? $billingAddress['city'] : 'N/A';
            $bCCode = ($billingAddress['countryCode']) ? $billingAddress['countryCode'] : 'N/A';
            $bPhone = ($billingAddress['phone']) ? $billingAddress['phone'] : 'N/A';
            
            $address = (new Address())
                ->setName($bName)
                ->setStreet($bSreet)
                ->setZip($bPcode)
                ->setCity($bCity)
                ->setCountry($bCCode);
        
            $customer = (new Customer())
                ->setFirstname($bName)
                ->setLastname($bName)
                ->setSalutation(Salutations::MR)
                ->setCompany("N/A")
                ->setEmail($orderEmail)
                ->setMobile($bPhone)
                ->setPhone($bPhone)
                ->setBillingAddress($address)
                ->setShippingAddress($address);
            $unzer->createCustomer($customer);
            
            $basket = (new Basket())
                ->setTotalValueGross($orderTotal)
                ->setCurrencyCode($orderCurrency)
                ->setOrderId($orderId);

            foreach($orderItems as $items){
                $basketProductId = $items['id'];
                $basketRefId = "Item-$basketProductId";
                $basketQty = $items['quantity'];
                $basketUnitGross = number_format($items['priceWithoutTax'], 2);
                $basketDiscountPerUnitGross = 0;
                $basketSetVat = number_format($items['tax'], 2);
                $basketSetTitle = $items['name'];
                $basketSubTitle = $items['shortDescription'];
                $basketSetImageUrl = $items['smallThumbnailUrl'];

                $item = (new BasketItem())
                    ->setBasketItemReferenceId($basketRefId)
                    ->setQuantity($basketQty)
                    ->setAmountPerUnitGross($basketUnitGross)
                    ->setAmountDiscountPerUnitGross($basketDiscountPerUnitGross)
                    ->setVat(0)
                    ->setTitle($basketSetTitle)
                    ->setSubTitle($basketSubTitle)
                    ->setImageUrl($basketSetImageUrl)
                    ->setType(BasketItemTypes::GOODS);
                $basket->addBasketItem($item);
            }
            
           if($orderTotalTax > 0){
                $vatAmountItem = (new BasketItem())
                ->setAmountPerUnitGross($orderTotalTax)
                ->setQuantity(1)
                ->setBasketItemReferenceId("VAT-".$orderId)
                ->setTitle("VAT Amount") 
                ->setType(BasketItemTypes::GOODS); 
                $basket->addBasketItem($vatAmountItem);
            }
            
            if($shippingRate > 0){
                $shipment = (new BasketItem())
                ->setAmountPerUnitGross($shippingRate)
                ->setQuantity(1)
                ->setBasketItemReferenceId("Shipping-".$orderId)
                ->setTitle($shippingMethodName) 
                ->setType(BasketItemTypes::SHIPMENT); 
                $basket->addBasketItem($shipment);
            }
            
            $createBasketBox =  $unzer->createBasket($basket);
            
            $paypage = new Paypage($orderTotal, $orderCurrency, $successURL);

            $paypage->setLogoImage('')
                ->setOrderId($orderId)
                ->setShopName($myStoreId)
                ->setInvoiceId($orderId)
                ->setExemptionType(\UnzerSDK\Constants\ExemptionType::LOW_VALUE_PAYMENT)
                ->setEffectiveInterestRate(0);
                
            $paypage->setAdditionalAttribute('disabledcof', true); 
        
            try {
                
                if($u_autocapture === "AUTHORIZE" || $u_autocapture == "AUTHORIZE"){
                    $response = $unzer->initPayPageAuthorize($paypage, $customer, $basket);
                }else{
                    $response = $unzer->initPayPageCharge($paypage, $customer, $basket);
                }
               
                $unzerLogArray = array("ecwid_store"=> $myStoreId, "ecwid_order_id"=> $orderId, "date" => date("Y-m-d H:i:s"));
                $unzerLogArrayJson = json_encode($unzerLogArray);
                file_put_contents('logs/unzer_response_'.date("Y-m-d").'.log', $unzerLogArrayJson, FILE_APPEND);

                if(isset($response) && !empty($response)){
                        $orderId = $response->getOrderId();
                        $data = [
                            'unzer_id' => $response->getId(),
                            'redirectUrl' => $response->getRedirectUrl(),
                            'amount' => $response->getAmount(),
                            'currency' => $response->getCurrency(),
                            'returnUrl' => $response->getReturnUrl(),
                            'shopName' => $response->getShopName(),
                            'shopDescription' => $response->getShopDescription(),
                            'tagline' => $response->getTagline(),
                            'action' => $response->getAction(),
                            'paymentId' => $response->getPaymentId(),
                            'invoiceId' => $response->getInvoiceId(),
                            'customerId' => $response->getPayment()->getCustomer()->getId(),
                            'basketId' => $response->getPayment()->getBasket()->getId(),
                        ];
                        $qForInsertOrder = mysqli_query($conn, "INSERT INTO orders (unzer_id, redirectUrl, amount, currency, returnUrl, shopName, shopDescription, tagline, action, paymentId, orderId, invoiceId, customerId, basketId, failureURL) VALUES ('{$data['unzer_id']}', '{$data['redirectUrl']}', '{$data['amount']}', '{$data['currency']}', '{$data['returnUrl']}', '{$data['shopName']}', '{$data['shopDescription']}', '{$data['tagline']}', '{$data['action']}', '{$data['paymentId']}', '{$orderId}', '{$data['invoiceId']}', '{$data['customerId']}', '{$data['basketId']}', '{$cancelUrl}')
                        ON DUPLICATE KEY UPDATE unzer_id = VALUES(unzer_id),redirectUrl = VALUES(redirectUrl),amount = VALUES(amount),currency = VALUES(currency),returnUrl = VALUES(returnUrl),shopName = VALUES(shopName),shopDescription = VALUES(shopDescription),tagline = VALUES(tagline),action = VALUES(action),
                        paymentId = VALUES(paymentId),invoiceId = VALUES(invoiceId),customerId = VALUES(customerId),basketId = VALUES(basketId),failureURL = VALUES(failureURL),updated_at = CURRENT_TIMESTAMP");
                        
                        $returnURL = $response->getRedirectUrl()."?locale=$storeLang";
                        header("Location: " . $returnURL);
                        header('Content-Type: application/json');
                        exit;
                }
                
            } catch (Exception $e) {
        
                $errorResponse = ['error' => $e->getMessage()];
                header('Content-Type: application/json');
                echo json_encode($errorResponse);
                exit;
            }
        }else{
            echo "There are some technical issue. Please try again later..!!";
            exit;
        }
    }else{
        echo "There are some technical issue. Please try again later..!!";
        exit;
    }
?>