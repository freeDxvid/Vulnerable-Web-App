import http.server
import os

FLAG = os.environ.get('SSRF_FLAG', 'FLAG_PLACEHOLDER_ssrf')

class H(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(b'DataSync Internal Service\n')
        self.wfile.write(b'==========================\n')
        self.wfile.write(('flag: ' + FLAG + '\n').encode())

    def log_message(self, *a):
        pass

http.server.HTTPServer(('0.0.0.0', 80), H).serve_forever()
