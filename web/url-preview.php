<?php
/**
 * URL Preview Service
 * Vulnerability: SSRF (Server-Side Request Forgery)
 *
 * The application fetches arbitrary URLs without restricting internal hosts.
 * Targets reachable from the server but not from outside:
 *   - http://secret-service/ -> Flag 2
 *   - http://metadata.internal/latest/meta-data/iam/security-credentials/datasync-role -> Flag 4
 */

function fetch_url(string $url): array {
    if (!preg_match('#^https?://#i', $url)) {
        return ['error' => 'Only http:// and https:// URLs are supported.'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'DataSync-Preview/1.0',
        // No SSRF protection: private/loopback addresses are NOT blocked
    ]);

    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['error' => 'Request failed: ' . htmlspecialchars($err)];
    }

    return [
        'status'       => $info['http_code'],
        'content_type' => $info['content_type'] ?? '',
        'body'         => $body,
    ];
}

$result = null;
$url    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url    = trim($_POST['url']);
    $result = fetch_url($url);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>URL Preview – DataSync Portal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <a class="back-link" href="index.php">← Back</a>
  <h1>URL Preview Service</h1>
  <p>Enter a URL to fetch and preview its content (first 4 KB shown).</p>

  <form method="POST">
    <input type="text" name="url"
           value="<?= htmlspecialchars($url) ?>"
           placeholder="https://example.com">
    <input type="submit" value="Fetch">
  </form>

  <?php if ($result !== null): ?>
    <?php if (isset($result['error'])): ?>
      <p class="msg-error"><?= $result['error'] ?></p>
    <?php else: ?>
      <p class="msg-meta">
        HTTP <?= (int)$result['status'] ?> &nbsp;|&nbsp;
        Content-Type: <?= htmlspecialchars($result['content_type']) ?>
      </p>
      <pre><?= htmlspecialchars(mb_substr($result['body'], 0, 4096)) ?></pre>
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>
