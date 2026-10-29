#!/usr/bin/env python3
"""Chrono online kiosk. Standard-library only. Device secrets never enter the browser."""
import argparse, getpass, hashlib, hmac, http.server, json, os, pathlib, re, secrets, stat, threading, time, urllib.error, urllib.parse, urllib.request
from pending_store import PendingStore, StoreError, CapacityError, partition_for, positive_immediate
ROOT=pathlib.Path(__file__).resolve().parent

class _CorrelatedRemoteFailure(dict):
    """Ephemeral header metadata, never a JSON field, receipt or stored event."""
    def __init__(self, data, request_id):
        super().__init__(data);self.request_id=request_id

def _remote_error_message(message, result):
    if not isinstance(result,_CorrelatedRemoteFailure):return message
    return f'{message} Anfragekennung: {result.request_id} (kein Buchungsnachweis).'

def _with_remote_error_id(response, data):
    # Only the actual failed response header may annotate an existing error.
    # Body fields, network failures, success and 4xx never supply an identifier.
    try:
        if type(response.status) is not int or not 500<=response.status<=599:return data
        values=response.headers.get_all('X-Request-ID',[])
        if len(values)!=1 or not isinstance(values[0],str) or re.fullmatch(r'[a-f0-9]{32}',values[0]) is None:return data
        result=_CorrelatedRemoteFailure(data,values[0])
        if isinstance(result.get('message'),str):result['message']=_remote_error_message(result['message'],result)
        return result
    except Exception:return data # Presentation must never replace the original failure.

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
        return self.request_raw(raw,signed)
    def request_raw(self, raw, signed=True):
        # Raw event bytes are encoded/persisted once; never normalize on send.
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
            return response.status,_with_remote_error_id(response,data)
        except (ValueError,OSError):return 503,_with_remote_error_id(response,{'success':False,'message':'Keine gültige Serverbestätigung. Vorgang nicht als gebucht behandeln.'})

def identify_record_binding(result):
    """Exact observed own record only. Never repair a present malformed string field."""
    state=result.get('state')
    if 'record_id_string' in result:
        record=result['record_id_string']
        if state=='absent' and record is None and result.get('record_id') is None:return None
        if state in ('working','paused') and isinstance(record,str) and re.fullmatch(r'[1-9][0-9]*',record) and (len(record)<19 or (len(record)==19 and record<='9223372036854775807')) and type(result.get('record_id')) is int and str(result['record_id'])==record:return record
        raise ValueError('Invalid record binding')
    # Older server compatibility only: Python JSON integers are exact; bool is NOT an ID.
    # That older server ignores expected_record, so this is not a stale-session guarantee.
    record=result.get('record_id')
    if state=='absent' and record is None:return None
    if state in ('working','paused') and type(record) is int and 0<record<=9223372036854775807:return str(record)
    raise ValueError('Unknown record binding')


class DurableKiosk:
    """One current interaction, many isolated uncertainties; no recovery event sender."""
    def __init__(self, remote, store):
        self.remote=remote;self.store=store;self.partition=partition_for(remote.config,remote.url)
        self.lock=threading.RLock();self.generation=0;self.identifying=None;self.current=None
    def identify(self,data):
        request=data.get('identify_request_id')
        if not isinstance(request,str) or re.fullmatch(r'[a-f0-9]{32}',request) is None:
            return 400,{'success':False,'message':'Anmeldung bitte in der aktuellen Kioskansicht beginnen.'}
        with self.lock:
            self.generation+=1;owner=(self.generation,request);self.identifying=owner;self.current=None
        status,result=self.remote.request({'op':'identify','employee_code':data.get('employee_code',''),'card':data.get('card',''),'pin':data.get('pin','')})
        with self.lock:
            if self.identifying!=owner:
                return 409,{'success':False,'message':'Diese Anmeldung ist nicht mehr aktuell.'}
            self.identifying=None
            try:
                if status!=200 or not isinstance(result,dict) or result.get('success') is not True:
                    raise ValueError()
                record=identify_record_binding(result);grant=result.get('grant');expires=result.get('expires_in');name=result.get('name')
                if not isinstance(grant,str) or re.fullmatch(r'[a-f0-9]{64}',grant) is None or type(expires) is not int or not 0<expires<=60 or not isinstance(name,str) or len(name.encode())>512:
                    raise ValueError()
                token=secrets.token_hex(16)
                self.current={'token':token,'generation':owner[0],'grant':grant,'record':record,'deadline':time.monotonic()+expires,'claimed':False}
                return 200,{'success':True,'name':name,'state':result['state'],'interaction_token':token,'durable_pending':True}
            except (ValueError,TypeError,KeyError):
                self.current=None
                return 503,{'success':False,'message':_remote_error_message('Keine passende Anmeldung bestätigt. Bitte erneut anmelden.',result)}
    def reset(self,data):
        with self.lock:
            matched=(self.identifying is not None and data.get('identify_request_id')==self.identifying[1])
            matched=matched or (self.current is not None and data.get('interaction_token')==self.current['token'])
            if matched:
                self.generation+=1;self.identifying=None;self.current=None
        # Missing/old selectors must never cancel another person's newer interaction.
        return 200,{'success':True}
    def event(self,data):
        action=data.get('action');confirm=data.get('confirm_end_pause',False)
        if action not in ('start','pause_start','pause_end','end') or type(confirm) is not bool:
            return 400,{'success':False,'message':'Ungültige Aktion.'}
        with self.lock:
            current=self.current
            if current is None or data.get('interaction_token')!=current['token'] or current['claimed']:
                return 409,{'success':False,'message':'Diese Aktion gehört nicht mehr zur aktuellen Anmeldung.'}
            if time.monotonic()>current['deadline']:
                self.current=None
                return 401,{'success':False,'message':'Bitte erneut anmelden.'}
            if self.store is None:
                self.current=None
                return 503,{'success':False,'message':'Privater Klärungsspeicher nicht verfügbar; keine neue Buchung gesendet. Anderen freigegebenen Erfassungsweg nutzen.'}
            if action!='start' and current['record'] is None:
                self.current=None
                return 409,{'success':False,'message':'Sitzungsbindung fehlt. Bitte erneut anmelden.'}
            current['claimed']=True
            payload={'op':'event','grant':current['grant'],'event_id':secrets.token_hex(16),'action':action,'confirm_end_pause':confirm}
            if action!='start':payload['expected_record']=current['record']
            raw=json.dumps(payload,separators=(',',':')).encode()
            try:
                entry=self.store.enqueue(self.partition,raw)
                self.store.claim(entry) # Durable BEFORE network; never claim a stored old entry.
            except CapacityError:
                if self.current is current:self.current=None
                return 503,{'success':False,'message':'Klärungsspeicher voll; keine neue Buchung gesendet. Anderen freigegebenen Erfassungsweg nutzen.'}
            except (StoreError,OSError):
                if self.current is current:self.current=None
                return 503,{'success':False,'message':'Gerätespeicher nicht bestätigt; keine neue Buchung gesendet. Betreiberklärung erforderlich.'}
        # No interaction/store mutex across network: B may proceed while A is in flight.
        try:status,result=self.remote.request_raw(raw)
        except Exception:status,result=503,{}
        positive=positive_immediate(status,result,raw);local_warning=False
        try:
            if positive:self.store.complete_immediate(entry)
            else:self.store.uncertain(entry)
        except (StoreError,OSError):local_warning=True
        with self.lock:
            if self.current is current:self.current=None
        if positive:
            response={'success':True,'message':result['message'],'time':result['time']}
            if local_warning:response['message']='Server hat bestätigt; lokale Ablage nicht bestätigt. Nicht erneut buchen; Betreiberklärung erforderlich.'
            return 200,response
        return 503,{'success':False,'message':_remote_error_message('Nicht bestätigt – ursprünglicher Vorgang bleibt privat zur Klärung erhalten. Keine automatische Wiederholung; bitte neu anmelden oder freigegebenen Korrekturweg nutzen.',result),'durable_pending':True}
    def handle(self,path,data):
        if path=='/identify':return self.identify(data)
        if path=='/event':return self.event(data)
        if path=='/reset':return self.reset(data)
        return 404,{'success':False}

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--config',default=str(pathlib.Path.home()/'.config/chrono-terminal/device.json'))
    parser.add_argument('--pending-dir',help='Explicit initialized private uncertainty store; no automatic setup')
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
    store=None;durable=None
    if args.pending_dir is not None:
        try:store=PendingStore(args.pending_dir,agent=True)
        except (StoreError,OSError):print('Privater Klärungsspeicher nicht verfügbar; neue Buchungen werden vor Versand verweigert.')
        durable=DurableKiosk(remote,store)
    origin=f'http://127.0.0.1:{args.port}'; browser_token=secrets.token_hex(32)
    grant=None; grant_record=None; grant_deadline=0; pending=None;lock=threading.Lock(); stopping=threading.Event()
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
            nonlocal grant,grant_record,grant_deadline,pending
            if not self.valid_host() or self.headers.get('Origin')!=origin or not hmac.compare_digest(self.headers.get('X-Kiosk-CSRF',''),browser_token):self.send_json(403,{'success':False});return
            try:
                size=int(self.headers.get('Content-Length','0'))
                if size<1 or size>4096:raise ValueError()
                data=json.loads(self.rfile.read(size))
                if not isinstance(data,dict):raise ValueError()
            except (ValueError,TypeError):self.send_json(400,{'success':False,'message':'Ungültige Anfrage.'});return
            if durable is not None:
                try:status,result=durable.handle(self.path,data)
                except Exception:status,result=503,{'success':False,'message':'Vorgang nicht bestätigt; Betreiberklärung erforderlich.'}
                self.send_json(status,result);return
            with lock:
                if self.path=='/identify':
                    grant=None;grant_record=None;pending=None
                    status,result=remote.request({'op':'identify','employee_code':data.get('employee_code',''),'card':data.get('card',''),'pin':data.get('pin','')})
                    if status==200:
                        try:grant_record=identify_record_binding(result)
                        except ValueError:
                            status=503;result={'success':False,'message':'Keine gültige Sitzungsbindung erhalten. Bitte erneut anmelden.'}
                        else:
                            result.pop('record_id_string',None) # Agent-owned metadata, not a browser-selected ID.
                            grant=result.pop('grant');grant_deadline=time.monotonic()+result['expires_in']
                    self.send_json(status,result)
                elif self.path=='/event':
                    action=data.get('action');confirm=data.get('confirm_end_pause') is True
                    if action not in ('start','pause_start','pause_end','end'):self.send_json(400,{'success':False});return
                    if not grant or (pending is None and time.monotonic()>grant_deadline):self.send_json(401,{'success':False,'message':'Bitte erneut anmelden.'});return
                    if pending and (pending['action']!=action or pending['confirm_end_pause']!=confirm):self.send_json(409,{'success':False,'message':'Unbestätigte Aktion zuerst erneut versuchen.'});return
                    if pending is None:
                        if action!='start' and grant_record is None:
                            grant=None;grant_record=None;self.send_json(409,{'success':False,'message':'Sitzungsbindung fehlt. Bitte erneut anmelden.'});return
                        pending={'op':'event','grant':grant,'event_id':secrets.token_hex(16),'action':action,'confirm_end_pause':confirm}
                        if action!='start':pending['expected_record']=grant_record
                    # Never add or replace a binding on a retained legacy/current pending payload.
                    status,result=remote.request(pending)
                    if status==200 or status<500:grant=None;grant_record=None;pending=None
                    # With no confirmation, retain only the exact request for safe retries.
                    self.send_json(status,result)
                elif self.path=='/reset':
                    grant=None;grant_record=None;pending=None;self.send_json(200,{'success':True})
                else:self.send_json(404,{'success':False})
    server=http.server.ThreadingHTTPServer(('127.0.0.1',args.port),Handler)
    threading.Thread(target=heartbeat,daemon=True).start()
    print(f'Terminal bereit: {origin} (Online-Buchungen, keine Offline-Warteschlange)')
    try:server.serve_forever()
    except KeyboardInterrupt:pass
    finally:
        stopping.set();server.server_close()
        if store is not None:
            try:store.close()
            except Exception:print('Lokaler Speicherabschluss nicht bestätigt; Betreiberklärung erforderlich.')
if __name__=='__main__':main()
