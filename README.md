# DataSync Portal – Vulnerable Web App Challenge

A web app with built-in security vulnerabilities, made for practicing real
exploitation techniques. This project was built as a learning exercise to
understand how common web vulnerabilities work in practice. It comes with a
Docker setup and a full [writeup](WRITEUP.md) that explains how to solve
every flag step by step.

## Challenges

| # | Vulnerability | Endpoint |
|---|----------------|----------|
| 1 | XXE (XML External Entity Injection) | `xml-import.php` |
| 2 | SSRF (Server-Side Request Forgery) | `url-preview.php` |
| 3 | PHP Type Juggling (Magic Hash) | `login.php` |
| 4 | SSRF on a simulated cloud metadata service | `url-preview.php` |

There are 4 flags to find in total. The full solution with all payloads is
in [WRITEUP.md](WRITEUP.md).

## Tech Stack

- PHP 8.2 (Apache)
- Docker / Docker Compose
- Python 3.11 (internal microservices: `secret-service`, `metadata`)

## Setup

You need Docker and Docker Compose installed.

Build and start:

```bash
$ docker compose build
$ docker compose up
```

The web app will then be available at <http://localhost:8000>.

## Vulnerabilities

### XXE – XML External Entity Injection

XML parsers support what are called external entities – references to local
files or URLs that the parser resolves automatically while processing the
document. If an application accepts XML input from users without disabling
external entities, an attacker can trick the parser into reading local files
and showing their content in the output. This way, sensitive data like
credentials or private keys can be leaked, even without any explicit file
read in the application code. The parser can also make HTTP requests, which
means XXE can be used as an SSRF vector as well.

### SSRF – Server-Side Request Forgery

SSRF happens when an application fetches a URL chosen by the user without
limiting which hosts it can reach. Since the request comes from the server
itself, internal services that are normally not reachable from outside
become accessible. An attacker can use the web server as a proxy to reach
internal microservices or cloud metadata endpoints. In real cloud
environments, a metadata service is reachable at a well-known internal
address and returns temporary credentials – a successful attack can lead to
a full cloud account takeover, as happened in the Capital One breach in
2019.

### PHP Type Juggling

PHP's loose comparison operator `==` automatically converts types before
comparing values. Strings that match the pattern `0e[0-9]+` are interpreted
as scientific notation and evaluated to `0.0`. Two MD5 hashes that both
start with `0e` are therefore considered equal under `==`, even though they
are completely different strings. Login systems that compare hashes with
`==` instead of `===` can be bypassed this way, using a publicly known input
value – without ever knowing the real password.

## Disclaimer

This project was built purely for learning and demonstration purposes. The
techniques shown here should only be used against your own systems or
systems you have clear permission to test.
