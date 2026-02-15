<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}", $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if migration 20260116140500 exists in phinxlog
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM phinxlog WHERE version = ?");
    $stmt->execute(['20260116140500']);
    if ($stmt->fetchColumn() == 0) {
        // Double check table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'refresh_tokens'");
        if ($stmt->rowCount() > 0) {
            echo "Inserting missing migration 20260116140500 into phinxlog..." . PHP_EOL;
            $stmt = $pdo->prepare("INSERT INTO phinxlog (version, migration_name, start_time, end_time, breakpoint) VALUES (?, ?, NOW(), NOW(), 0)");
            $stmt->execute(['20260116140500', 'CreateRefreshTokensTable']);
        }
    }

    // Check if migration 20260121151415 exists in phinxlog
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM phinxlog WHERE version = ?");
    $stmt->execute(['20260121151415']);
    if ($stmt->fetchColumn() == 0) {
        // Double check table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'payments'");
        if ($stmt->rowCount() > 0) {
            echo "Inserting missing migration 20260121151415 into phinxlog..." . PHP_EOL;
            $stmt = $pdo->prepare("INSERT INTO phinxlog (version, migration_name, start_time, end_time, breakpoint) VALUES (?, ?, NOW(), NOW(), 0)");
            $stmt->execute(['20260121151415', 'CreatePaymentsTable']);
        }
    }

    // Now try to run migrate
    passthru('php vendor/bin/phinx migrate -c phinx.php');

} catch (Exception $e) {
    echo $e->getMessage();
}
