<?php
    class EcwidFunctions{
        
        function getEcwidPayload($app_secret_key, $data) {
            $encryption_key = substr($app_secret_key, 0, 16);
            $json_data = $this->aes_128_decrypt($encryption_key, $data);
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
    }
?>