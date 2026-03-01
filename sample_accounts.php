<?php
/**
 * sample_accounts.php
 * Creates demo Admin + Client accounts with proper password hashing.
 * Delete this file after running for security.
 */

require_once __DIR__ . '/includes/db.php';

echo "<h2>Creating Sample Accounts...</h2>";

try {

    // ============================
    // 1) CREATE ADMIN ACCOUNT
    // ============================
    $adminUsername = 'admin';
    $adminPassword = 'Admin@123';
    $adminHash     = password_hash($adminPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("
        INSERT INTO users 
        (username, password_hash, role_id, status, full_name, business_name, phone, email, theme, sms_balance, created_at)
        VALUES (?, ?, 1, 'active', 'System Admin', 'AlmaTech', '256700000000', 'admin@almatech.local', 'theme_dark', 0, NOW())
        ON DUPLICATE KEY UPDATE username=username
    ");
    $stmt->bind_param("ss", $adminUsername, $adminHash);
    $stmt->execute();
    $stmt->close();

    echo "✅ Admin account created<br>";


    // ============================
    // 2) CREATE CLIENT ACCOUNT
    // ============================
    $clientUsername = 'almatech';
    $clientPassword = 'Alma@123';
    $clientHash     = password_hash($clientPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("
        INSERT INTO users 
        (username, password_hash, role_id, status, full_name, business_name, phone, email, address, theme, sms_balance, created_at)
        VALUES (?, ?, 2, 'active', 'Tech', 'almatech', '256700868939', 'allanomwesi70@gmail.com', 'Mbarara', 'theme_blue', 5796, NOW())
        ON DUPLICATE KEY UPDATE username=username
    ");
    $stmt->bind_param("ss", $clientUsername, $clientHash);
    $stmt->execute();
    $stmt->close();

    echo "✅ Client account created<br>";


    // ============================
    // 3) GET CLIENT ID
    // ============================
    $result = $db->query("SELECT id FROM users WHERE username='almatech' LIMIT 1");
    $client = $result->fetch_assoc();
    $clientId = (int)$client['id'];


    // ============================
    // 4) DEFAULT PRICING (17 UGX)
    // ============================
    $networks = $db->query("SELECT id FROM networks");

    while ($network = $networks->fetch_assoc()) {
        $networkId = (int)$network['id'];

        $stmt = $db->prepare("
            INSERT IGNORE INTO user_network_pricing (user_id, network_id, price_per_sms, currency)
            VALUES (?, ?, 17, 'UGX')
        ");
        $stmt->bind_param("ii", $clientId, $networkId);
        $stmt->execute();
        $stmt->close();
    }

    echo "✅ Default pricing assigned<br>";


    // ============================
    // 5) CREATE DEFAULT CONTACT GROUP "All"
    // ============================
    $stmt = $db->prepare("
        INSERT IGNORE INTO contact_groups (user_id, name, created_at)
        VALUES (?, 'All', NOW())
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $stmt->close();

    echo "✅ Default contact group created<br>";


    // ============================
    // 6) SUCCESS MESSAGE
    // ============================
    echo "<hr>";
    echo "<h3>Login Credentials</h3>";
    echo "Admin → Username: <b>admin</b> | Password: <b>Admin@123</b><br>";
    echo "Client → Username: <b>almatech</b> | Password: <b>Alma@123</b><br>";
    echo "<br><strong>⚠ IMPORTANT:</strong> Delete this file after running.";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
