<?php
/**
 * XML Data Import
 * Vulnerability: XXE (XML External Entity Injection)
 *
 * In PHP 8.x libxml_disable_entity_loader() is deprecated and has no effect.
 * Instead, external entity loading is controlled via the libxml2 environment
 * variable and the LIBXML_NOENT + LIBXML_DTDLOAD flags passed to loadXML().
 * Passing both flags is sufficient to make XXE work in PHP 8.2.
 */

function parse_xml_records(string $xml): array|string {
    $old = libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    // LIBXML_NOENT  -> substitute entity references (makes &xxe; expand)
    // LIBXML_DTDLOAD -> allow loading external DTDs / entity declarations
    // Together these two flags re-enable XXE in PHP 8.2
    $loaded = $dom->loadXML($xml, LIBXML_NOENT | LIBXML_DTDLOAD);

    if (!$loaded) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        $msg = !empty($errors) ? htmlspecialchars($errors[0]->message) : 'Unknown parse error';
        return "XML parse error: $msg";
    }
    libxml_use_internal_errors($old);

    $records = [];
    foreach ($dom->getElementsByTagName('record') as $node) {
        $name  = $node->getElementsByTagName('name')->item(0)?->textContent ?? '';
        $value = $node->getElementsByTagName('value')->item(0)?->textContent ?? '';
        $records[] = [
            'name'  => htmlspecialchars($name),
            'value' => htmlspecialchars($value),
        ];
    }
    return $records;
}

$result  = null;
$raw_xml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['xml'])) {
    $raw_xml = $_POST['xml'];
    $result  = parse_xml_records($raw_xml);
}

$example = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<records>
  <record>
    <name>server</name>
    <value>prod-01</value>
  </record>
  <record>
    <name>uptime</name>
    <value>99.9%</value>
  </record>
</records>
XML;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>XML Data Import – DataSync Portal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <a class="back-link" href="index.php"><-Back</a>
  <h1>XML Data Import</h1>
  <p>Paste an XML document containing <code>&lt;record&gt;</code> entries.
     Each record needs a <code>&lt;name&gt;</code> and a <code>&lt;value&gt;</code> child.</p>

  <form method="POST">
    <textarea name="xml" placeholder="Paste XML here…"><?= htmlspecialchars($raw_xml ?: $example) ?></textarea>
    <br>
    <input type="submit" value="Import Records">
    <p class="form-hint">Example input is pre-filled above.</p>
  </form>

  <?php if ($result !== null): ?>
    <?php if (is_string($result)): ?>
      <p class="msg-error"><?= $result ?></p>
    <?php elseif (empty($result)): ?>
      <p class="msg-error">No &lt;record&gt; elements found in the submitted XML.</p>
    <?php else: ?>
      <table>
        <tr><th>Name</th><th>Value</th></tr>
        <?php foreach ($result as $row): ?>
          <tr><td><?= $row['name'] ?></td><td><?= $row['value'] ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>