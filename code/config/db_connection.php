<?php 
    
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

	$host = 'localhost';
	$username = 'nirvir_unzer';
	$password = 'HEXvu9JTq[fF';
	$database = 'nirvir_unzerecwid';

	global $conn;
	$conn = mysqli_connect($host,$username,$password,$database);
	if (mysqli_connect_errno()){
		echo "Failed to connect to MySQL: " . mysqli_connect_error();
	}
?>