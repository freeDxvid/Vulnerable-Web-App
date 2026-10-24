<?php
/**
 * Admin Login
 * Vulnerability: PHP Type Juggling (Magic Hash / Loose Comparison)
 */

// md5("240610708") = 0e462097431906509019562988736854
define('ADMIN_TOKEN_HASH', '0e462097431906509019562988736854');

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $submitted      = $_POST['token'];
    $submitted_hash = md5($submitted);

    // VULNERABLE: loose == comparison
    if ($submitted_hash == ADMIN_TOKEN_HASH) {
        $success = true;
        $flag    = getenv('TYPE_JUGGLING_FLAG') ?: 'FLAG_PLACEHOLDER_type_juggling';
        $message = "Access granted! Flag: <strong>" . htmlspecialchars($flag) . "</strong>"
                 . "<span class='login-hint'>Hint: An internal microservice is running at "
                 . "<code>http://secret-service/</code> — not reachable from outside.</span>";
    } else {
        $message = "Access denied. Invalid token. (submitted hash: <code>"
                 . htmlspecialchars($submitted_hash) . "</code>)";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login – DataSync Portal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <a class="back-link" href="index.php"><-Back</a>
  <h1>Admin Login</h1>
  <p>Enter your admin token to authenticate.</p>

  <form method="POST">
    <input type="text" name="token" placeholder="Enter token…" autocomplete="off">
    <input type="submit" value="Login">
    <p class="form-hint">The system validates your token by comparing its MD5 hash against the stored hash.</p>
  </form>

  <?php if ($message): ?>
    <p class="<?= $success ? 'msg-success' : 'msg-error' ?>"><?= $message ?></p>
  <?php endif; ?>
</body>
</html>