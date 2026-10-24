<?php
/**
 * Internal Flag Endpoint – SSRF target
 * Only accessible from 127.0.0.1 (the server itself).
 */

$allowed = ['127.0.0.1', '::1'];
$remote  = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($remote, $allowed, true)) {
    http_response_code(403);
    echo "403 Forbidden – This resource is only accessible internally.";
    exit;
}

$flag = getenv('SSRF_FLAG') ?: 'FLAG_PLACEHOLDER_ssrf';
header('Content-Type: text/plain');
echo "Internal systems report\n";
echo "=======================\n";
echo "flag: $flag\n";