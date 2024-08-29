<?php 
	$host = 'localhost';
	$username = 'mavenhostingserv_nirvir';
	$password = '9M@st#;2qy,4';
	$database = 'mavenhostingserv_unzerecwid';

	global $conn;
	$conn = mysqli_connect($host,$username,$password,$database);
	if (mysqli_connect_errno()){
		echo "Failed to connect to MySQL: " . mysqli_connect_error();
	}
?>