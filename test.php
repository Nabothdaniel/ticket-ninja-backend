<?php
// Simple test to see what paths we're getting
header('Content-Type: application/json');

echo json_encode([
    'REQUEST_URI' => $_SERVER['REQUEST_URI'],
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
    'PHP_SELF' => $_SERVER['PHP_SELF'],
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'],
    'parsed_path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
]);
?>
