#!/usr/bin/env python3
"""One coarse real STDIO -> verified HTTPS -> native API integration journey.

Requires PHP 8.5 with curl/mbstring/pdo_sqlite, framework Composer dependencies,
Python 3 and openssl. Run: python3 tests/mcp_journey.py [--php /path/to/php]
Only a freshly marked private temporary directory is used. No real credentials.
--http-client-only runs the production HTTP client/projection without the MCP SDK;
it does not validate JSON-RPC, OwnTool confirmations, Apps, or Blade rendering.
"""
import argparse
import http.client
import http.server
import json
import os
import re
from pathlib import Path
import select
import socket
import sqlite3
import ssl
import subprocess
import tempfile
import threading
import time

ROOT = Path(__file__).resolve().parents[1]
HELPER = ROOT / 'framework/tools/rehearse-mcp.php'
TOOLS = {'chrono_' + name for name in (
    'identity', 'attendance', 'time_entries', 'corrections', 'work_model',
    'work_models', 'start', 'pause_start', 'pause_end', 'end')}
META = {'io.modelcontextprotocol/protocolVersion': '2026-07-28',
        'io.modelcontextprotocol/clientCapabilities': {}}
PASSWORD = 'LocalAudit!Password2026'  # tests/seed.php, disposable synthetic account


def ensure(condition, message):
    if not condition:
        raise AssertionError(message)


def unused_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


class Boundary(http.server.BaseHTTPRequestHandler):
    """TLS only; forwards unchanged requests to native api.php on loopback.

    Faults are injected after upstream commit, or before upstream admission.
    Neither synthetic fault emulates a positive native mutation receipt.
    """
    api_port = 0
    api_off = False
    lose_next_write = False
    requests_seen = []
    committed_losses = 0

    def log_message(self, *_):
        pass

    def do_GET(self):
        self.forward()

    def do_POST(self):
        self.forward()

    def forward(self):
        cls = type(self)
        body = self.rfile.read(int(self.headers.get('Content-Length', '0')))
        cls.requests_seen.append((self.command, self.path))  # Never credentials.
        if cls.api_off:
            payload = b'{"success":false}'
            self.send_response(503)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)
            return
        connection = http.client.HTTPConnection('127.0.0.1', cls.api_port, timeout=10)
        try:
            headers = {key: value for key, value in self.headers.items()
                       if key.lower() not in ('host', 'connection')}
            headers['Connection'] = 'close'
            connection.request(self.command, self.path, body=body, headers=headers)
            response = connection.getresponse()
            payload = response.read()
            if cls.lose_next_write and self.command == 'POST' and self.path == '/api.php/attendance':
                ensure(response.status == 200, 'Loss fixture must follow a real commit')
                cls.lose_next_write = False
                cls.committed_losses += 1
                self.close_connection = True
                self.connection.shutdown(socket.SHUT_RDWR)
                return
            self.send_response(response.status)
            self.send_header('Content-Type', response.getheader('Content-Type', 'application/json'))
            self.send_header('Content-Length', str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)
        finally:
            connection.close()


class Stdio:
    def __init__(self, php, env, cert, directory):
        self.stderr = open(directory / 'mcp-stderr.log', 'ab')
        self.process = subprocess.Popen(
            [php, '-d', 'curl.cainfo=' + str(cert), str(ROOT / 'framework/artisan'), 'mcp:start', 'chrono'],
            cwd=ROOT / 'framework', env=env, stdin=subprocess.PIPE,
            stdout=subprocess.PIPE, stderr=self.stderr, bufsize=0)
        self.sequence = 0
        self.meta = META

    def close(self):
        self.process.stdin.close()
        try:
            self.process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            self.process.kill()
            self.process.wait()
        self.process.stdout.close()
        self.stderr.close()

    def rpc(self, method, params=None, modern=True):
        self.sequence += 1
        params = dict(params or {})
        if modern:
            params['_meta'] = self.meta
        request = {'jsonrpc': '2.0', 'id': self.sequence, 'method': method, 'params': params}
        self.process.stdin.write((json.dumps(request) + '\n').encode())
        self.process.stdin.flush()
        deadline = time.monotonic() + 20
        data = bytearray()
        while time.monotonic() < deadline:
            ready, _, _ = select.select([self.process.stdout], [], [], max(0, deadline - time.monotonic()))
            ensure(ready, 'STDIO timeout for ' + method)
            byte = self.process.stdout.read(1)
            ensure(byte, 'STDIO exited during ' + method)
            data.extend(byte)
            if byte == b'\n':
                reply = json.loads(data)
                ensure(reply.get('jsonrpc') == '2.0' and reply.get('id') == self.sequence,
                       'Invalid STDIO JSON-RPC envelope')
                return reply
        raise AssertionError('STDIO timeout for ' + method)

    def result(self, method, params=None, modern=True):
        reply = self.rpc(method, params, modern)
        ensure('result' in reply and 'error' not in reply, 'JSON-RPC refused ' + method)
        return reply['result']

    def call(self, name, arguments=None, success=True, modern=True):
        result = self.result('tools/call', {'name': 'chrono_' + name, 'arguments': arguments or {}}, modern)
        data = result.get('structuredContent')
        ensure(isinstance(data, dict), 'Missing structured tool content for ' + name)
        ensure(data.get('success') is success, 'Unexpected tool outcome for ' + name + ': ' + str(data.get('error')))
        ensure(bool(result.get('isError', False)) is (not success), 'Tool error bit mismatch')
        return data


class CoreClient:
    """JSON-line fixture driver around actual PHP classes, not an MCP adapter."""
    def __init__(self, php, env, cert, directory):
        self.stderr = open(directory / 'core-client-stderr.log', 'ab')
        self.process = subprocess.Popen(
            [php, '-d', 'curl.cainfo=' + str(cert), str(HELPER), 'core-client'],
            cwd=ROOT, env=env, stdin=subprocess.PIPE, stdout=subprocess.PIPE,
            stderr=self.stderr, bufsize=0)

    close = Stdio.close

    def request(self, endpoint, query=None, body=None, success=True):
        self.process.stdin.write((json.dumps({'endpoint': endpoint, 'query': query or {}, 'body': body}) + '\n').encode())
        self.process.stdin.flush()
        deadline = time.monotonic() + 20
        data = bytearray()
        while time.monotonic() < deadline:
            ready, _, _ = select.select([self.process.stdout], [], [], max(0, deadline - time.monotonic()))
            ensure(ready, 'Partial HTTP-client driver timed out')
            byte = self.process.stdout.read(1)
            ensure(byte, 'Partial HTTP-client driver exited')
            data.extend(byte)
            if byte == b'\n':
                reply = json.loads(data)
                ensure(reply.get('success') is success,
                       'Unexpected partial HTTP-client result: ' + str(reply.get('error')))
                return reply
        raise AssertionError('Partial HTTP-client driver timed out')


def http_client_flow(php, env, cert, directory, processes, sql, helper, token):
    """Continue the same native HTTPS fixture through the production client only."""
    client = CoreClient(php, env, cert, directory)
    processes.append(client)
    identity = client.request('users')
    ensure(identity['data'][0]['id'] == '2' and identity['scope'] == 'own', 'Partial own identity mismatch')
    ensure(client.request('attendance')['state'] == 'absent', 'Partial fixture must begin absent')
    entries = client.request('timeentries', {'limit': 1, 'offset': 0, 'entry_kind': 'work'})
    ensure(entries['data'][0]['id'] == '9007199254740993' and entries['data'][0]['user_id'] == '2'
           and entries['pagination']['next_offset'] == 1, 'Partial large read ID/pagination mismatch')
    ensure(client.request('timeentries', {'limit': 1, 'offset': 1})['data'][0]['id'] == '1', 'Partial next page mismatch')
    client.request('corrections', {'limit': 1, 'offset': 0, 'status': 'all'})
    client.request('work-model', {'date': '2026-10-01'})
    client.request('work-models', {'limit': 1, 'offset': 0})
    paths = {path.split('?')[0] for method, path in Boundary.requests_seen if method == 'GET'}
    ensure(paths == {'/api.php/' + endpoint for endpoint in ('users', 'attendance', 'timeentries', 'corrections', 'work-model', 'work-models')},
           'Partial six-endpoint boundary mismatch')
    requests_before = len(Boundary.requests_seen)
    ensure(client.request('admin', success=False)['error'] == 'endpoint_refused'
           and len(Boundary.requests_seen) == requests_before, 'Client allowed an unsupported endpoint')
    ensure(token not in json.dumps([identity, entries]), 'Partial credential projection leak')
    changed = CoreClient(php, dict(env, CHRONO_MCP_DEPLOYMENT='synthetic-changed-profile-0001'), cert, directory)
    processes.append(changed)
    ensure(changed.request('users')['profile_binding'] != identity['profile_binding'], 'Deployment change did not change profile binding')
    wrong = CoreClient(php, dict(env, CHRONO_MCP_ACCOUNT_ID='1'), cert, directory)
    processes.append(wrong)
    ensure(wrong.request('users', success=False)['error'] == 'account_binding_refused', 'Wrong account profile was admitted')

    def count():
        return sql('SELECT count(*) FROM attendance_events')[0][0]

    def write(action, event, record=None, success=True, **extra):
        body = {'action': action, 'event_id': event}
        if record is not None:
            body['expected_record'] = record
        body.update(extra)
        return client.request('attendance', body=body, success=success), body

    before = count()
    started, start_body = write('start', 'synthetic-http-start-001')
    record = started['record_id_string']
    ensure(record == '9007199254740994' and started['outcome'] == 'confirmed', 'Partial large write receipt mismatch')
    ensure(client.request('attendance', body=start_body) == started and count() == before + 1, 'Partial exact replay duplicated mutation')
    write('pause_start', 'synthetic-http-pause-001', '9007199254740993', success=False)
    ensure(count() == before + 1, 'Stale exact expected_record mutated state')
    write('pause_start', 'synthetic-http-pause-001', record)
    write('pause_end', 'synthetic-http-resume-01', record)
    write('pause_start', 'synthetic-http-pause-002', record)
    paused_count = count()
    write('end', 'synthetic-http-end-00001', record, success=False)
    write('end', 'synthetic-http-end-00001', record, success=False, confirm_end_pause='true')
    ensure(count() == paused_count, 'Native paused end accepted missing/nonboolean consent')
    ended, end_body = write('end', 'synthetic-http-end-00001', record, confirm_end_pause=True)
    ensure(ended['state'] == 'absent' and client.request('attendance', body=end_body) == ended, 'Partial end/replay mismatch')
    before = count()
    posts_before = Boundary.requests_seen.count(('POST', '/api.php/attendance'))
    Boundary.lose_next_write = True
    uncertain, lost_body = write('start', 'synthetic-http-lost-0001', success=False)
    ensure(uncertain['error'] == 'transport_unconfirmed' and Boundary.committed_losses == 1 and count() == before + 1,
           'Partial response loss did not follow a single native commit')
    ensure(Boundary.requests_seen.count(('POST', '/api.php/attendance')) == posts_before + 1,
           'HTTP client automatically retried after response loss')
    Boundary.api_off = True
    refused = client.request('attendance', body=lost_body, success=False)
    ensure(refused['http_status'] == 503 and count() == before + 1
           and uncertain['error'] == 'transport_unconfirmed',
           'Later refusal mismatch: ' + str(refused) + '; event delta=' + str(count() - before))
    ensure(Boundary.requests_seen.count(('POST', '/api.php/attendance')) == posts_before + 1,
           'API-off preflight forwarded a mutation')
    Boundary.api_off = False
    recovered = client.request('attendance', body=lost_body)
    ensure(recovered['event_id'] == lost_body['event_id'] and recovered['outcome'] == 'confirmed' and count() == before + 1,
           'Partial manual exact replay failed to recover the receipt')
    helper('revoke')
    before = count()
    ensure(client.request('attendance', body=lost_body, success=False)['http_status'] == 401 and count() == before,
           'Revoked credential mutated native state')
    client.request('users', success=False)
    ensure(sql('SELECT DISTINCT user_id,source FROM attendance_events') == [(2, 'api:2')], 'Partial mutations escaped own account')
    print('Partial HTTP-client journey passed: actual LocalProfile/OwnApiClient/OwnProjection; verified TLS/native login; six own reads; large IDs; four action types; stale record refusal; native paused consent; exact replay; committed response loss; later API-off refusal; revocation; profile/account binding. MCP SDK/OwnTool/Blade were not exercised.')


def journey(php, directory, http_client_only=False):
    # Remove inherited runtime selectors so this journey cannot select a deployment.
    env = {key: value for key, value in os.environ.items()
           if not key.startswith(('CHRONO_', 'DB_', 'LARAVEL_'))}
    env.update(APP_DATA_DIR=str(directory / 'data'), APP_TEST_MODE='1', APP_DEBUG='false',
               APP_ENV='testing', APP_KEY='base64:' + 'QUFB' * 10 + 'QUE=',
               CHRONO_MCP_JOURNEY_DIR=str(directory), CHRONO_MCP_ENABLED='false',
               LOG_CHANNEL='stderr', CACHE_STORE='array', SESSION_DRIVER='array',
               DB_CONNECTION='sqlite', DB_DATABASE=':memory:',
               NO_PROXY='localhost,127.0.0.1', no_proxy='localhost,127.0.0.1')
    (directory / 'synthetic-fixture').touch(mode=0o600)
    (directory / 'data').mkdir(mode=0o700)
    (directory / 'views').mkdir(mode=0o700)
    env['VIEW_COMPILED_PATH'] = str(directory / 'views')

    def helper(mode):
        subprocess.run([php, str(HELPER), mode], cwd=ROOT, env=env, check=True,
                       stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30)

    if not http_client_only:
        ensure((ROOT / 'framework/vendor/autoload.php').is_file(),
               'Full MCP journey blocked: framework/vendor/autoload.php is missing; install Composer dependencies before running the SDK/Blade journey.')
        helper('default')
    helper('setup')
    key, cert = directory / 'tls.key', directory / 'tls.pem'
    subprocess.run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
                    '-keyout', str(key), '-out', str(cert), '-days', '1',
                    '-subj', '/CN=localhost', '-addext', 'subjectAltName=DNS:localhost',
                    '-addext', 'basicConstraints=critical,CA:TRUE'], check=True,
                   stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    key.chmod(0o600)
    Boundary.api_port = unused_port()
    native_log = open(directory / 'native-stderr.log', 'wb')
    native = subprocess.Popen([php, '-S', '127.0.0.1:' + str(Boundary.api_port), str(HELPER)],
                              cwd=ROOT, env=env, stdout=native_log, stderr=native_log)
    server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Boundary)
    tls = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    tls.load_cert_chain(cert, key)
    server.socket = tls.wrap_socket(server.socket, server_side=True)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    origin = 'https://localhost:' + str(server.server_port)
    trusted = ssl.create_default_context(cafile=str(cert))
    processes = []

    def sql(query):
        with sqlite3.connect(directory / 'data/timetracking.sqlite') as database:
            return database.execute(query).fetchall()

    def event_count():
        return sql('SELECT count(*) FROM attendance_events')[0][0]

    def write(tool, event, binding, record=None, success=True, **extra):
        arguments = {'event_id': event, 'confirmed_event_id': event, 'profile_binding': binding}
        if record is not None:
            arguments.update(expected_record=record, confirmed_record_id=record)
        arguments.update(extra)
        return modern.call(tool, arguments, success), arguments

    try:
        deadline = time.monotonic() + 10
        while True:
            try:
                with socket.create_connection(('127.0.0.1', Boundary.api_port), timeout=.2):
                    break
            except OSError:
                ensure(native.poll() is None and time.monotonic() < deadline, 'Native fixture failed to start')
                time.sleep(.05)
        # Obtain credentials through the real login endpoint, over verified TLS.
        connection = http.client.HTTPSConnection('localhost', server.server_port, context=trusted)
        connection.request('POST', '/api.php/login', json.dumps({'username': 'audituser', 'password': PASSWORD}),
                           {'Content-Type': 'application/json'})
        response = connection.getresponse()
        login = json.loads(response.read())
        ensure(response.status == 200 and login.get('success') is True, 'Synthetic login failed')
        connection.close()
        token = login['token']
        token_file = directory / 'token'
        token_file.write_text(token)
        token_file.chmod(0o600)
        env.update(CHRONO_MCP_ENABLED='true', CHRONO_MCP_API_ORIGIN=origin,
                   CHRONO_MCP_DEPLOYMENT='synthetic-mcp-journey-0001', CHRONO_MCP_ACCOUNT_ID='2',
                   CHRONO_MCP_TOKEN_FILE=str(token_file), CHRONO_MCP_APPS_ENABLED='false')
        if http_client_only:
            http_client_flow(php, env, cert, directory, processes, sql, helper, token)
            return
        modern = Stdio(php, env, cert, directory)
        processes.append(modern)
        discovery = modern.result('server/discover')
        ensure(set(discovery['supportedVersions']) == {'2026-07-28', '2025-11-25'}, 'Protocol discovery mismatch')
        tools = modern.result('tools/list')['tools']
        ensure({tool['name'] for tool in tools} == TOOLS, 'Tool catalog mismatch')
        ensure(all(tool['inputSchema'].get('additionalProperties') is False for tool in tools), 'Open tool schema')
        ensure(all('ui' not in tool.get('_meta', {}) for tool in tools), 'Apps leaked while disabled')
        ensure(modern.result('resources/list').get('resources', []) == [], 'Disabled Apps resource exposed')
        ensure('error' in modern.rpc('resources/read', {'uri': 'ui://chrono/attendance'}), 'Disabled Apps resource readable')
        ensure('error' in modern.rpc('tools/call', {'name': 'chrono_admin', 'arguments': {}}), 'Unknown tool accepted')
        before = len(Boundary.requests_seen)
        modern.call('identity', {'user_id': '1'}, False)
        modern.call('time_entries', {'limit': '2'}, False)
        modern.call('work_model', {'date': '2026-02-30'}, False)
        ensure(len(Boundary.requests_seen) == before, 'Invalid arguments reached HTTPS')

        identity = modern.call('identity')
        binding = identity['profile_binding']
        ensure(identity['data'][0]['id'] == '2' and identity['scope'] == 'own', 'Own identity mismatch')
        attendance = modern.call('attendance', {'workflow': True})
        ensure(attendance['state'] == 'absent', 'Fixture should begin absent')
        entries = modern.call('time_entries', {'limit': 1, 'offset': 0, 'entry_kind': 'work'})
        ensure(len(entries['data']) == 1 and entries['data'][0]['user_id'] == '2', 'Own entry scope failed')
        ensure(entries['pagination']['next_offset'] == 1 and entries['pagination']['snapshot'] is False, 'Pagination contract mismatch')
        next_entries = modern.call('time_entries', {'limit': 1, 'offset': 1})
        ensure(next_entries['data'][0]['id'] == '1', 'Native bounded pagination mismatch')
        ensure(entries['data'][0]['beschreibung'].startswith('<script>'), 'Untrusted business text was not retained as data')
        ensure(entries['data'][0]['id'] == '9007199254740993', 'Large read ID was rounded')
        modern.call('corrections', {'limit': 1, 'status': 'all'})
        modern.call('work_model', {'date': '2026-10-01'})
        modern.call('work_models', {'limit': 1})
        ensure(token not in json.dumps([identity, attendance, entries, tools, discovery]), 'Credential leaked into output')
        paths = {path.split('?')[0] for method, path in Boundary.requests_seen if method == 'GET'}
        ensure(paths == {'/api.php/' + endpoint for endpoint in ('users', 'attendance', 'timeentries', 'corrections', 'work-model', 'work-models')}, 'Read endpoint boundary mismatch')

        # A second real process exercises the older initialize/list/call handshake.
        legacy = Stdio(php, env, cert, directory)
        processes.append(legacy)
        initialized = legacy.result('initialize', {'protocolVersion': '2025-11-25', 'capabilities': {},
                                                  'clientInfo': {'name': 'synthetic-journey', 'version': '1'}}, False)
        ensure(initialized['protocolVersion'] == '2025-11-25', 'Legacy version mismatch')
        ensure({tool['name'] for tool in legacy.result('tools/list', modern=False)['tools']} == TOOLS, 'Legacy tool catalog mismatch')
        ensure(legacy.call('identity', modern=False)['data'][0]['id'] == '2', 'Legacy call failed')

        # The actual Blade resource is available only for an admitted Apps host.
        apps = Stdio(php, dict(env, CHRONO_MCP_APPS_ENABLED='true'), cert, directory)
        processes.append(apps)
        ensure(apps.result('resources/list').get('resources', []) == [], 'Apps exposed without host capability')
        apps.meta = dict(META, **{'io.modelcontextprotocol/clientCapabilities': {
            'extensions': {'io.modelcontextprotocol/ui': {'mimeTypes': ['text/html;profile=mcp-app']}}}})
        app_tools = apps.result('tools/list')['tools']
        ensure(all(tool.get('_meta', {}).get('ui', {}).get('resourceUri') == 'ui://chrono/attendance'
                   for tool in app_tools), 'Apps host did not receive UI tool metadata')
        resources = apps.result('resources/list')['resources']
        ensure(len(resources) == 1 and resources[0]['uri'] == 'ui://chrono/attendance', 'Apps resource catalog mismatch')
        content = apps.result('resources/read', {'uri': 'ui://chrono/attendance'})['contents'][0]
        ensure(content['mimeType'] == 'text/html;profile=mcp-app', 'Apps MIME mismatch')
        html = content['text']
        ensure('<html' in html and 'chrono-app' in html and 'confirm-action' in html
               and '<x-mcp' not in html and '@include' not in html, 'Blade resource was not rendered')
        ensure(not re.search(r'<(?:script|link|iframe)[^>]+(?:src|href)\s*=\s*[\"\'](?:https?:)?//', html, re.I),
               'Apps HTML depends on an external CDN/frame')
        ensure(token not in html and PASSWORD not in html, 'Credential leaked into Apps HTML')
        for resource in [resources[0], content]:
            ui = resource['_meta']['ui']
            ensure(ui['csp'] == {'connectDomains': [], 'resourceDomains': [], 'frameDomains': [], 'baseUriDomains': []}
                   and ui['permissions'] == {}, 'Apps CSP/permissions were not closed')

        before = event_count()
        _, start_payload = write('start', 'synthetic-start-00001', binding, success=False,
                                 confirmed_event_id='synthetic-other-00001')
        ensure(event_count() == before, 'Mismatched confirmation wrote an event')
        started, start_payload = write('start', 'synthetic-start-00001', binding)
        record = started['record_id_string']
        ensure(record == '9007199254740994' and started['outcome'] == 'confirmed', 'Large write receipt ID mismatch')
        ensure(modern.call('start', start_payload) == started and event_count() == before + 1, 'Same event replay duplicated mutation')
        modern.call('start', dict(start_payload, event_id='synthetic-start-00002'), False)
        ensure(event_count() == before + 1, 'Changed event escaped confirmation binding')
        modern.call('attendance', {'workflow': True})
        write('pause_start', 'synthetic-pause-00001', binding, record)
        modern.call('attendance', {'workflow': True})
        write('pause_end', 'synthetic-resume-0001', binding, record)
        modern.call('attendance', {'workflow': True})
        write('pause_start', 'synthetic-pause-00002', binding, record)
        modern.call('attendance', {'workflow': True})
        paused_count = event_count()
        write('end', 'synthetic-end-0000001', binding, record, success=False)
        write('end', 'synthetic-end-0000001', binding, record, success=False, confirm_end_pause='true')
        write('end', 'synthetic-end-0000001', binding, record, success=False,
              confirmed_record_id='9007199254740993', confirm_end_pause=True)
        ensure(event_count() == paused_count, 'End bypassed exact paused confirmation')
        ended, end_payload = write('end', 'synthetic-end-0000001', binding, record, confirm_end_pause=True)
        ensure(ended['state'] == 'absent' and modern.call('end', end_payload) == ended, 'Paused end/replay failed')

        # Upstream commits, but its response is dropped at the HTTPS boundary.
        modern.call('attendance', {'workflow': True})
        before = event_count()
        Boundary.lose_next_write = True
        uncertain, lost_payload = write('start', 'synthetic-lost-000001', binding, success=False)
        ensure(Boundary.committed_losses == 1 and event_count() == before + 1, 'Response-loss fixture did not commit exactly once')
        ensure(uncertain['outcome'] == 'unconfirmed' and uncertain['automatic_retry'] is False, 'Lost response was misreported')
        Boundary.api_off = True
        refusal = modern.call('start', lost_payload, False)
        ensure(refusal['outcome'] == 'unconfirmed' and refusal['automatic_retry'] is False and event_count() == before + 1,
               'Later API-off refusal destroyed uncertainty or retried the mutation')
        Boundary.api_off = False
        recovered = modern.call('start', lost_payload)
        ensure(recovered['event_id'] == lost_payload['event_id'] and recovered['outcome'] == 'confirmed'
               and event_count() == before + 1, 'Manual exact replay did not recover the positive receipt')
        helper('revoke')
        before = event_count()
        revoked = modern.call('start', lost_payload, False)
        ensure(revoked['http_status'] == 401 and revoked['outcome'] == 'unconfirmed' and event_count() == before,
               'Revoked token mutated state or erased uncertainty')
        modern.call('identity', success=False)
        ensure(sql('SELECT DISTINCT user_id,source FROM attendance_events') == [(2, 'api:2')], 'Mutation escaped own-account source')
        ensure(all(path.startswith('/api.php/') for _, path in Boundary.requests_seen), 'Unexpected upstream URL')
        print('MCP journey passed: inert boot; both STDIO protocols; rendered Apps capability boundary; six own HTTPS reads; strict confirmations; large IDs; replay; committed response loss; API-off; revocation.')
    finally:
        for process in reversed(processes):
            process.close()
        server.shutdown()
        server.server_close()
        native.terminate()
        try:
            native.wait(timeout=5)
        except subprocess.TimeoutExpired:
            native.kill()
            native.wait()
        native_log.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', default='php')
    parser.add_argument('--http-client-only', action='store_true', help='Run only actual HTTP client/projection against verified TLS/native API; no SDK, OwnTool or Blade coverage')
    args = parser.parse_args()
    with tempfile.TemporaryDirectory(prefix='chrono-mcp-journey-') as temporary:
        directory = Path(temporary).resolve()
        directory.chmod(0o700)
        journey(args.php, directory, args.http_client_only)
