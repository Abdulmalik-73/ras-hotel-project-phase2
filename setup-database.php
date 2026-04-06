<?php
/**
 * Automatic Database Setup Script
 * Creates database and imports setup.sql
 */

// Database configuration
$host = 'localhost';
$port = 3307;
$user = 'root';
$pass = '';
$db_name = 'harar_ras_hotel';

echo "<h2>Harar Ras Hotel - Database Setup</h2>";
echo "<hr>";

// Step 1: Connect to MySQL (without database)
echo "<h3>Step 1: Connecting to MySQL...</h3>";
$conn = @new mysqli($host, $user, $pass, '', $port);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error . "<br>Please make sure MySQL is running.");
}
echo "✅ Connected to MySQL<br><br>";

// Step 2: Create database if not exists
echo "<h3>Step 2: Creating database...</h3>";
$sql = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql)) {
    echo "✅ Database '$db_name' created or already exists<br><br>";
} else {
    die("❌ Error creating database: " . $conn->error);
}

// Step 3: Select database
echo "<h3>Step 3: Selecting database...</h3>";
if ($conn->select_db($db_name)) {
    echo "✅ Database selected<br><br>";
} else {
    die("❌ Error selecting database: " . $conn->error);
}

// Step 4: Import SQL file
echo "<h3>Step 4: Importing setup.sql...</h3>";
$sql_file = __DIR__ . '/database/setup.sql';

if (!file_exists($sql_file)) {
    die("❌ setup.sql file not found at: $sql_file");
}

echo "Reading SQL file...<br>";
$sql_content = file_get_contents($sql_file);

if (!$sql_content) {
    die("❌ Could not read SQL file");
}

echo "File size: " . strlen($sql_content) . " bytes<br>";
echo "Executing SQL commands...<br>";

// Split SQL file into individual queries
$queries = array_filter(
    array_map('trim', 
        preg_split('/;[\r\n]+/', $sql_content)
    )
);

$success_count = 0;
$error_count = 0;
$errors = [];

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

foreach ($queries as $query) {
    // Skip empty queries and comments
    if (empty($query) || strpos($query, '--') === 0) {
        continue;
    }
    
    // Execute query
    if ($conn->multi_query($query)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        $success_count++;
    } else {
        $error_count++;
        $errors[] = substr($query, 0, 100) . "... - Error: " . $conn->error;
    }
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "<br>";
echo "✅ Executed $success_count queries successfully<br>";
if ($error_count > 0) {
    echo "⚠️ $error_count queries had errors<br>";
    echo "<details><summary>Show errors</summary><pre>";
    foreach ($errors as $error) {
        echo htmlspecialchars($error) . "\n";
    }
    echo "</pre></details>";
}

// Step 5: Verify tables
echo "<br><h3>Step 5: Verifying tables...</h3>";
$result = $conn->query("SHOW TABLES");
if ($result) {
    $table_count = $result->num_rows;
    echo "✅ Found $table_count tables:<br>";
    echo "<ul>";
    while ($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "⚠️ Could not verify tables<br>";
}

$conn->close();

echo "<hr>";
echo "<h3>✅ Setup Complete!</h3>";
echo "<p>Your database is ready. You can now:</p>";
echo "<ul>";
echo "<li><a href='index.php'>Go to Home Page</a></li>";
echo "<li><a href='test-db.php'>Test Database Connection</a></li>";
echo "<li><a href='test-chapa.php'>Test Chapa Integration</a></li>";
echo "</ul>";
?>
