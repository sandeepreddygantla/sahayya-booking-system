<?php
/**
 * Direct Database Table Creator
 * This bypasses WordPress entirely and creates tables directly
 * Access via: http://yoursite.com/wp-content/plugins/sahayya-booking-system/direct-table-create.php
 */

// Load WordPress config to get database credentials
// Try multiple possible paths
$possible_paths = array(
    __DIR__ . '/../../../../wp-config.php',
    __DIR__ . '/../../../wp-config.php',
    __DIR__ . '/../../wp-config.php',
    '/var/www/html/wp-config.php',
    '/app/wp-config.php',
);

$wp_config_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $wp_config_path = $path;
        break;
    }
}

if (!$wp_config_path) {
    echo '<h1>Searching for wp-config.php</h1>';
    echo '<p>Tried these paths:</p><ul>';
    foreach ($possible_paths as $path) {
        echo '<li>' . $path . ' - ' . (file_exists($path) ? 'EXISTS' : 'NOT FOUND') . '</li>';
    }
    echo '</ul>';
    echo '<p>Current directory: ' . __DIR__ . '</p>';
    echo '<p>Contents of parent directory:</p><pre>';
    print_r(scandir(__DIR__ . '/../../../../'));
    echo '</pre>';
    die('Cannot find wp-config.php in any expected location');
}

require_once($wp_config_path);

// Connect directly to MySQL
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo '<h1>Direct Table Creation Test</h1>';
echo '<style>body{font-family:monospace;padding:20px;} .success{color:green;} .error{color:red;}</style>';

echo '<h2>Database Connection</h2>';
echo '<p class="success">✓ Connected to MySQL successfully!</p>';
echo '<ul>';
echo '<li>Host: ' . DB_HOST . '</li>';
echo '<li>Database: ' . DB_NAME . '</li>';
echo '<li>User: ' . DB_USER . '</li>';
echo '<li>Server: ' . $mysqli->server_info . '</li>';
echo '</ul>';

// Get table prefix from wp-config
$table_prefix = isset($table_prefix) ? $table_prefix : 'wp_';

echo '<h2>Creating Service Categories Table</h2>';

$table_name = $table_prefix . 'sahayya_service_categories';

// Drop table if exists (for testing)
$sql = "DROP TABLE IF EXISTS `$table_name`";
if ($mysqli->query($sql)) {
    echo '<p class="success">✓ Dropped existing table (if any)</p>';
} else {
    echo '<p class="error">✗ Error dropping table: ' . $mysqli->error . '</p>';
}

// Create table
$sql = "CREATE TABLE `$table_name` (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    status enum('active','inactive') DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($mysqli->query($sql)) {
    echo '<p class="success">✓ Table created successfully!</p>';

    // Insert test data
    $sql = "INSERT INTO `$table_name` (name, description, status) VALUES
            ('Test Category', 'This is a test category created directly', 'active')";

    if ($mysqli->query($sql)) {
        echo '<p class="success">✓ Test data inserted successfully!</p>';
    } else {
        echo '<p class="error">✗ Error inserting test data: ' . $mysqli->error . '</p>';
    }

    // Query the table
    $result = $mysqli->query("SELECT * FROM `$table_name`");
    if ($result) {
        echo '<h3>Table Contents:</h3>';
        echo '<table border="1"><tr><th>ID</th><th>Name</th><th>Description</th><th>Status</th><th>Created At</th></tr>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . $row['name'] . '</td>';
            echo '<td>' . $row['description'] . '</td>';
            echo '<td>' . $row['status'] . '</td>';
            echo '<td>' . $row['created_at'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

} else {
    echo '<p class="error">✗ Error creating table: ' . $mysqli->error . '</p>';
    echo '<p>SQL: ' . htmlspecialchars($sql) . '</p>';
}

echo '<h2>Check All Sahayya Tables</h2>';
$result = $mysqli->query("SHOW TABLES LIKE '" . $table_prefix . "sahayya%'");
if ($result) {
    $tables = array();
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    if (count($tables) > 0) {
        echo '<p class="success">Found ' . count($tables) . ' Sahayya tables:</p>';
        echo '<ul>';
        foreach ($tables as $table) {
            echo '<li>' . $table . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="error">No Sahayya tables found</p>';
    }
}

$mysqli->close();

echo '<hr>';
echo '<p><a href="' . $_SERVER['PHP_SELF'] . '">Run Test Again</a></p>';
