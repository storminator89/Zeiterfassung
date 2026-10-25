#!/usr/bin/env python3
"""Chrono online kiosk. Standard-library only. Device secrets never enter the browser."""
import argparse, getpass, hashlib, hmac, http.server, json, os, pathlib, secrets, stat, threading, time, urllib.error, urllib.parse, urllib.request
ROOT=pathlib.Path(__file__).resolve().parent
class Remote:
    def __init__(self, config, allow_http=False):
        self.config=config
        url=urllib.parse.urlsplit(config['url'])
        if url.scheme!='https' and not (allow_http and url.scheme=='http' and url.hostname in ('127.0.0.1','localhost','::1')):
            raise ValueError('HTTPS required (HTTP allowed only for explicit localhost development).')
        if url.username or url.password or url.query or url.fragment: raise ValueError('Invalid server URL')
        self.url=config['url'].rstrip('/')+'/terminal_api.php'
        # Never follow a redirect with a PIN, pairing code or device signature.
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args, **kwargs): return None
        self.client=urllib.request.build_opener(NoRedirect())
    def request(self, payload, signed=True):
        raw=json.dumps(payload,separators=(',',':')).encode()
        headers={'Content-Type':'application/json','Accept':'application/json'}
        if signed:
            device=str(self.config['device_id']); stamp=str(int(time.time())); nonce=secrets.token_hex(16)
            message=f'terminal-v1\n{device}\n{stamp}\n{nonce}\n'.encode()+raw
            headers.update({'X-Terminal-Id':device,'X-Terminal-Time':stamp,'X-Terminal-Nonce':nonce,'X-Terminal-Signature':hmac.new(self.config['key'].encode(),message,hashlib.sha256).hexdigest()})
        try:
            response=self.client.open(urllib.request.Request(self.url,data=raw,headers=headers),timeout=8)
        except urllib.error.HTTPError as error: response=error
        except (OSError,TimeoutError): return 503,{'success':False,'message':'Nicht bestätigt – Verbindung fehlt. Erneut versuchen oder Korrekturantrag stellen.'}
        try:
            data=json.loads(response.read(16384))
            if not isinstance(data,dict):raise ValueError()
            return response.status,data
        except (ValueError,OSError):return 503,{'success':False,'message':'Keine gültige Serverbestätigung. Vorgang nicht als gebucht behandeln.'}

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--config',default=str(pathlib.Path.home()/'.config/chrono-terminal/device.json'))
    parser.add_argument('--pair',action='store_true');parser.add_argument('--url');parser.add_argument('--device-id',type=int);parser.add_argument('--port',type=int,default=8765);parser.add_argument('--allow-http-localhost',action='store_true')
    args=parser.parse_args();path=pathlib.Path(args.config).expanduser()
    if args.pair:
        if path.exists():raise SystemExit('Configuration exists. Revoke the old device first and use a new configuration path.')
        if not args.url or not args.device_id:parser.error('--url and --device-id required for pairing')
        remote=Remote({'url':args.url},args.allow_http_localhost)
        status,data=remote.request({'op':'pair','device_id':args.device_id,'code':getpass.getpass('Einmalcode: ')},signed=False)
        if status!=200:raise SystemExit(data.get('message','Pairing failed'))
        path.parent.mkdir(parents=True,exist_ok=True,mode=0o700)
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'w') as f:json.dump({'url':args.url,'device_id':data['device_id'],'key':data['key']},f)
        print('Gerät gekoppelt. Gerätedienst ohne --pair starten.');return
    if path.is_symlink() or not stat.S_ISREG(path.stat().st_mode) or path.stat().st_uid != os.getuid() or stat.S_IMODE(path.stat().st_mode)&0o077:raise SystemExit('Configuration must be a private regular file (chmod 600).')
    config=json.loads(path.read_text());remote=Remote(config,args.allow_http_localhost)
    origin=f'http://127.0.0.1:{args.port}'; browser_token=secrets.token_hex(32)
    grant=None; grant_deadline=0; pending=None;lock=threading.Lock(); stopping=threading.Event()
    def heartbeat():
        while not stopping.wait(30):remote.request({'op':'heartbeat'})
    class Handler(http.server.BaseHTTPRequestHandler):
        def log_message(self,*args):pass # Do not log PINs, IDs or grants.
        def send_json(self,status,data):self.send_data(status,json.dumps(data).encode(),'application/json')
        def send_data(self,status,body,kind):
            self.send_response(status);self.send_header('Content-Type',kind);self.send_header('Content-Length',str(len(body)));self.send_header('Cache-Control','no-store');self.send_header('X-Content-Type-Options','nosniff');self.send_header('Content-Security-Policy',"default-src 'self'; script-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");self.end_headers();self.wfile.write(body)
        def valid_host(self):return self.headers.get('Host')==f'127.0.0.1:{args.port}'
        def do_GET(self):
            if not self.valid_host():self.send_json(403,{'success':False});return
            if self.path=='/':self.send_data(200,(ROOT/'kiosk.html').read_bytes().replace(b'__CSRF__',browser_token.encode()),'text/html; charset=utf-8')
            elif self.path in ('/kiosk.js','/kiosk.css'):self.send_data(200,(ROOT/self.path[1:]).read_bytes(),'text/javascript' if self.path.endswith('.js') else 'text/css')
            else:self.send_json(404,{'success':False})
        def do_POST(self):
            nonlocal grant,grant_deadline,pending
            if not self.valid_host() or self.headers.get('Origin')!=origin or not hmac.compare_digest(self.headers.get('X-Kiosk-CSRF',''),browser_token):self.send_json(403,{'success':False});return
            try:
                size=int(self.headers.get('Content-Length','0'))
                if size<1 or size>4096:raise ValueError()
                data=json.loads(self.rfile.read(size))
                if not isinstance(data,dict):raise ValueError()
            except (ValueError,TypeError):self.send_json(400,{'success':False,'message':'Ungültige Anfrage.'});return
            with lock:
                if self.path=='/identify':
                    grant=None;pending=None
                    status,result=remote.request({'op':'identify','employee_code':data.get('employee_code',''),'card':data.get('card',''),'pin':data.get('pin','')})
                    if status==200:
                        grant=result.pop('grant');grant_deadline=time.monotonic()+result['expires_in']
                    self.send_json(status,result)
                elif self.path=='/event':
                    action=data.get('action');confirm=data.get('confirm_end_pause') is True
                    if action not in ('start','pause_start','pause_end','end'):self.send_json(400,{'success':False});return
                    if not grant or (pending is None and time.monotonic()>grant_deadline):self.send_json(401,{'success':False,'message':'Bitte erneut anmelden.'});return
                    if pending and (pending['action']!=action or pending['confirm_end_pause']!=confirm):self.send_json(409,{'success':False,'message':'Unbestätigte Aktion zuerst erneut versuchen.'});return
                    if pending is None:pending={'op':'event','grant':grant,'event_id':secrets.token_hex(16),'action':action,'confirm_end_pause':confirm}
                    status,result=remote.request(pending)
                    if status==200 or status<500:grant=None;pending=None
                    # With no confirmation, retain only the exact request for safe retries.
                    self.send_json(status,result)
                elif self.path=='/reset':
                    grant=None;pending=None;self.send_json(200,{'success':True})
                else:self.send_json(404,{'success':False})
    server=http.server.ThreadingHTTPServer(('127.0.0.1',args.port),Handler)
    threading.Thread(target=heartbeat,daemon=True).start()
    print(f'Terminal bereit: {origin} (Online-Buchungen, keine Offline-Warteschlange)')
    try:server.serve_forever()
    except KeyboardInterrupt:pass
    finally:stopping.set();server.server_close()
if __name__=='__main__':main()
