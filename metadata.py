import http.server
import json
import os

FLAG = os.environ.get('METADATA_FLAG', 'FLAG_PLACEHOLDER_metadata')

ROUTES = {
    '/':                                          b'1.0\n2007-01-19\nlatest\n',
    '/latest':                                    b'dynamic\nmeta-data\nuser-data\n',
    '/latest/meta-data':                          b'ami-id\nhostname\niam/\ninstance-id\nlocal-ipv4\n',
    '/latest/meta-data/iam':                      b'security-credentials/\n',
    '/latest/meta-data/iam/security-credentials': b'datasync-role\n',
}

class H(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        path = self.path.rstrip('/') or '/'
        if path == '/latest/meta-data/iam/security-credentials/datasync-role':
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({
                'Code': 'Success',
                'Type': 'AWS-HMAC',
                'AccessKeyId': 'ASIAXXXXXXXXXXXXXXXQ',
                'SecretAccessKey': FLAG,
                'Token': 'IQoXXXXXXXXXXXXXXXXX',
                'Expiration': '2099-01-01T00:00:00Z'
            }, indent=2).encode())
        elif path in ROUTES:
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(ROUTES[path])
        else:
            self.send_response(404)
            self.end_headers()
            self.wfile.write(b'404 Not Found\n')

    def log_message(self, *a):
        pass

http.server.HTTPServer(('0.0.0.0', 80), H).serve_forever()
