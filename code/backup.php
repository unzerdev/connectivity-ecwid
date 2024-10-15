<?php
// Database connection details
$host = 'localhost';         // Database host
$username = 'mavenhostingserv_nirvir';          // Database username
$password = '9M@st#;2qy,4'; // Database password
$database = 'mavenhostingserv_unzerecwid'; // Database name

// File path setup
$backupDir = 'backup/';    // Path to the directory to store the backup
$backupFile = $backupDir . $database . '_' . date('Y-m-d') . '.sql'; // Backup file name


// Step 1: Clear the folder (delete all existing files)
$files = scandir($backupDir); // Get all files in the directory
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        unlink($backupDir . $file); // Delete the file
    }
}

// Ensure the backup directory exists
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0777, true); // Create directory if it doesn't exist
}

// Connect to the database
$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Create backup file and write structure
$handle = fopen($backupFile, 'w+');
if ($handle === false) {
    die("Error creating backup file.");
}

// Add table structure and data
$tables = $mysqli->query("SHOW TABLES");
while ($row = $tables->fetch_row()) {
    $table = $row[0];
    
    // Add CREATE TABLE statement
    $createTableResult = $mysqli->query("SHOW CREATE TABLE $table");
    $createTableRow = $createTableResult->fetch_row();
    fwrite($handle, "\n\n" . $createTableRow[1] . ";\n\n");

    // Add INSERT INTO statements
    $tableData = $mysqli->query("SELECT * FROM $table");
    while ($dataRow = $tableData->fetch_assoc()) {
        $columns = array_keys($dataRow);
        $values = array_map([$mysqli, 'real_escape_string'], array_values($dataRow));
        $sql = "INSERT INTO `$table` (`" . implode("`, `", $columns) . "`) VALUES ('" . implode("', '", $values) . "');\n";
        fwrite($handle, $sql);
    }
}

fclose($handle);
$mysqli->close();

echo "Database successfully exported to: $backupFile";
?>
?>
