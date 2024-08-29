<?php
    include("../config/db_connection.php");

    if(isset($_REQUEST) && !empty($_REQUEST) && $_REQUEST['action'] === "register"){
        $eStorId = $_REQUEST['storeId'];
        $eAccessToken = $_REQUEST['storeAccessToken'];
        $uPublicKey = $_REQUEST['u_public_key'];
        $uPrivateKey = $_REQUEST['u_private_key'];
        $uAuthStatus = $_REQUEST['u_authorized_status'];
        $uCaptureStatus = $_REQUEST['u_captured_status'];
        $uChargedStatus = $_REQUEST['u_charged_status'];

        $qCheckRecordExists = mysqli_query($conn, "SELECT * FROM configurations WHERE e_storeId = '".$eStorId."'");
        $countNumOfStore = mysqli_num_rows($qCheckRecordExists);
        if($countNumOfStore <= 0){
            $qForInsertStoreDetails = mysqli_query($conn, "INSERT INTO configurations(e_storeId, e_accessToken, u_publicKey, u_privateKey, u_authStatus, u_captureStatus, u_chargeStatus) VALUES ('".$eStorId."','".$eAccessToken."','".$uPublicKey."','".$uPrivateKey."','".$uAuthStatus."','".$uCaptureStatus."','".$uChargedStatus."')");
        }else{
            $uDate = date("Y-m-d H:i:s");
            $qForUpdateStoreDetails = mysqli_query($conn, "UPDATE configurations SET e_accessToken='".$eAccessToken."',u_publicKey='".$uPublicKey."',u_privateKey='".$uPrivateKey."',u_authStatus='".$uAuthStatus."',u_captureStatus='".$uCaptureStatus."',u_chargeStatus='".$uChargedStatus."',updatedAt='".$uDate."' WHERE  e_storeId='".$eStorId."'");
        }
        echo 1;
    }
?>