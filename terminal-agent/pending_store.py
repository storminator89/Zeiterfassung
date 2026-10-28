"""Private uncertainty store, not an offline event sender or authoritative audit log."""
import contextlib
import hashlib
import json
import os
import pathlib
import re
import secrets
import sqlite3
import stat
import threading
import time
try:
    import fcntl
except ImportError:
    fcntl = None

MAX_PENDING = 1000
MAX_ACCOUNTED_BYTES = 16 * 1024 * 1024
COMPLETION_BUDGET = 4096
MAX_BODY = 8192
APPLICATION_ID = 0x43505131
SCHEMA = """
CREATE TABLE intents(entry_id TEXT PRIMARY KEY,event_id TEXT NOT NULL,action TEXT NOT NULL,partition TEXT NOT NULL,raw BLOB NOT NULL,digest TEXT NOT NULL,created_at INTEGER NOT NULL,UNIQUE(partition,event_id));
CREATE TABLE attempts(entry_id TEXT PRIMARY KEY REFERENCES intents(entry_id) ON DELETE CASCADE,state TEXT NOT NULL CHECK(state IN ('prepared','attempting','uncertain','quarantine')),owner TEXT NOT NULL);
CREATE TABLE resolutions(id INTEGER PRIMARY KEY AUTOINCREMENT,entry_id TEXT NOT NULL,event_id TEXT NOT NULL,digest TEXT NOT NULL,uid INTEGER NOT NULL,at INTEGER NOT NULL,kind TEXT NOT NULL CHECK(kind IN ('receipt_confirmed','externally_clarified')),reason TEXT NOT NULL,reference TEXT NOT NULL);
CREATE TRIGGER immutable_intent BEFORE UPDATE ON intents BEGIN SELECT RAISE(ABORT,'Immutable pending intent'); END;
CREATE TRIGGER immutable_resolution_update BEFORE UPDATE ON resolutions BEGIN SELECT RAISE(ABORT,'Retain resolution metadata'); END;
CREATE TRIGGER immutable_resolution_delete BEFORE DELETE ON resolutions BEGIN SELECT RAISE(ABORT,'Retain resolution metadata'); END;
"""

class StoreError(Exception):
    """Fixed redacted errors only; never attach a raw database/intent exception."""
class CapacityError(StoreError):
    pass
class EntryBusy(StoreError):
    pass


def _private_file(path, mode=0o600):
    info = path.lstat()
    if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1 or info.st_uid != os.geteuid() or stat.S_IMODE(info.st_mode) != mode:
        raise StoreError('Privater Gerätespeicher nicht sicher verfügbar.')
    return info


def _directory(value):
    if fcntl is None or not hasattr(os, 'geteuid'):
        raise StoreError('Privater POSIX-Gerätespeicher wird benötigt.')
    path = pathlib.Path(value)
    if str(path) != os.fspath(value):
        raise StoreError('Kanonisches privates Verzeichnis erforderlich.')
    if not path.is_absolute() or '\0' in str(path) or any(part in ('.', '..') for part in str(value).split('/')):
        raise StoreError('Explizites privates Verzeichnis erforderlich.')
    current = pathlib.Path('/')
    for part in path.parts[1:]:
        current /= part
        if stat.S_ISLNK(current.lstat().st_mode):
            raise StoreError('Speicheralias nicht erlaubt.')
    info = path.lstat()
    if path.resolve() != path or not stat.S_ISDIR(info.st_mode) or info.st_uid != os.geteuid() or stat.S_IMODE(info.st_mode) != 0o700:
        raise StoreError('Speicherverzeichnis muss privat und eigentümergeführt sein.')
    return path


def _schema(connection):
    return connection.execute("SELECT type,name,tbl_name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name").fetchall()


def _expected_schema():
    connection = sqlite3.connect(':memory:', isolation_level=None)
    try:
        connection.executescript(SCHEMA)
        return _schema(connection)
    finally:
        connection.close()


def _decode(raw):
    def pairs(items):
        result = {}
        for key, value in items:
            if key in result:
                raise ValueError()
            result[key] = value
        return result
    try:
        if not isinstance(raw, bytes) or not 1 <= len(raw) <= MAX_BODY:
            raise ValueError()
        data = json.loads(raw, object_pairs_hook=pairs)
        if not isinstance(data, dict) or set(data) not in ({'op','grant','event_id','action','confirm_end_pause'}, {'op','grant','event_id','action','confirm_end_pause','expected_record'}):
            raise ValueError()
        if data['op'] != 'event' or not isinstance(data['grant'], str) or re.fullmatch(r'[a-f0-9]{64}', data['grant']) is None:
            raise ValueError()
        if not isinstance(data['event_id'], str) or re.fullmatch(r'[A-Za-z0-9_-]{16,80}', data['event_id']) is None or data['action'] not in ('start','pause_start','pause_end','end') or type(data['confirm_end_pause']) is not bool:
            raise ValueError()
        if 'expected_record' in data:
            record = data['expected_record']
            if not isinstance(record, str) or re.fullmatch(r'[1-9][0-9]*', record) is None or len(record)>19 or (len(record)==19 and record>'9223372036854775807'):
                raise ValueError()
        if data['action'] != 'start' and 'expected_record' not in data:
            raise ValueError()
        return data
    except (ValueError, TypeError, UnicodeError, KeyError):
        raise StoreError('Gespeicherter Vorgang ist nicht zuverlässig lesbar; Klärung erforderlich.') from None


def partition_for(config, endpoint):
    """Only a fingerprint is stored, never a second copy of the device key."""
    values = ['chrono-pending-v1', endpoint, str(config['device_id']), hashlib.sha256(config['key'].encode()).hexdigest()]
    return hashlib.sha256(json.dumps(values, separators=(',', ':')).encode()).hexdigest()


def receipt_request(raw):
    """Separate LOOKUP envelope; the immutable event body is never edited or resent."""
    original = _decode(raw)
    lookup = dict(original)
    lookup['op'] = 'receipt'
    return lookup


def positive_immediate(status, result, raw):
    try:
        original = _decode(raw)
        expected = {'start':'working','pause_start':'paused','pause_end':'working','end':'absent'}[original['action']]
        record = result.get('record_id') if isinstance(result, dict) else None
        if type(status) is not int or status != 200 or type(result.get('success')) is not bool or result['success'] is not True or result.get('event_id') != original['event_id'] or result.get('state') != expected or type(record) is not int or not 0 < record <= 9223372036854775807:
            return False
        if 'expected_record' in original and str(record) != original['expected_record']:
            return False
        if 'record_id_string' in result and result['record_id_string'] != str(record):
            return False
        stamp = result.get('time')
        if not isinstance(stamp, str) or re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+00:00', stamp) is None:
            return False
        from datetime import datetime
        datetime.fromisoformat(stamp)
        return isinstance(result.get('message'), str) and len(result['message'].encode()) <= 2048
    except (StoreError, ValueError, TypeError, AttributeError):
        return False


def positive_historical(status, result, event_id):
    return (type(status) is int and status == 200 and isinstance(result, dict) and set(result) == {'success','historical_receipt','event_id','received_at'}
            and result.get('success') is True and type(result.get('success')) is bool
            and result.get('historical_receipt') is True and type(result.get('historical_receipt')) is bool
            and result.get('event_id') == event_id and type(result.get('received_at')) is int
            and 0 <= result['received_at'] <= 9223372036854775807)


class PendingStore:
    @staticmethod
    def initialize(directory):
        """Explicit operator action only; directory must already be privately admitted."""
        path = _directory(directory) / 'pending.sqlite'
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, 'O_NOFOLLOW', 0), 0o600)
        os.close(fd)
        connection = None
        try:
            connection = sqlite3.connect(path, isolation_level=None, timeout=1)
            connection.execute('PRAGMA journal_mode=DELETE')
            connection.execute('PRAGMA synchronous=FULL')
            connection.executescript('BEGIN IMMEDIATE;'+SCHEMA+f'PRAGMA application_id={APPLICATION_ID};PRAGMA user_version=1;COMMIT;')
        except (OSError, sqlite3.Error):
            raise StoreError('Initialisierung nicht bestätigt; privaten Speicher prüfen.') from None
        finally:
            if connection is not None:
                connection.close()

    def __init__(self, directory, agent=False):
        self.directory = _directory(directory)
        self.path = self.directory / 'pending.sqlite'
        self.agent = agent
        self.owner = secrets.token_hex(16)
        self._lock = threading.RLock()
        self._lease_fd = None
        self.connection = None
        try:
            self._validate_files()
            if agent:
                self._lease_fd = self._lease()
            self.connection = sqlite3.connect(self.path.as_uri()+'?mode=rw', uri=True, isolation_level=None, timeout=1, check_same_thread=False)
            self.connection.execute('PRAGMA busy_timeout=1000')
            self.connection.execute('PRAGMA synchronous=FULL')
            self.connection.execute('PRAGMA foreign_keys=ON')
            if self.connection.execute('PRAGMA journal_mode').fetchone()[0] != 'delete' or self.connection.execute('PRAGMA synchronous').fetchone()[0] != 2 or self.connection.execute('PRAGMA application_id').fetchone()[0] != APPLICATION_ID or self.connection.execute('PRAGMA user_version').fetchone()[0] != 1 or _schema(self.connection) != _expected_schema():
                raise StoreError('Unbekannter Gerätespeicher; keine automatische Reparatur.')
            if agent:
                with self._transaction():
                    # Kernel lease proves no other agent; stale local attempts are STILL unknown.
                    self.connection.execute("UPDATE attempts SET state='uncertain' WHERE state IN ('prepared','attempting')")
        except (OSError, sqlite3.Error, StoreError):
            self.close()
            raise StoreError('Privater Gerätespeicher nicht verfügbar; keine neue Buchung gesendet.') from None

    def _validate_files(self):
        _directory(str(self.directory))
        before = _private_file(self.path)
        # Refuse persistent WAL BEFORE SQLite can create/recover/checkpoint sidecars.
        fd = os.open(self.path, os.O_RDONLY | getattr(os, 'O_NOFOLLOW', 0))
        try:
            opened = os.fstat(fd)
            if (opened.st_dev, opened.st_ino) != (before.st_dev, before.st_ino):
                raise StoreError('Speicherbesitz gewechselt.')
            header = os.read(fd, 100)
        finally:
            os.close(fd)
        after = _private_file(self.path)
        if (after.st_dev, after.st_ino) != (before.st_dev, before.st_ino):
            raise StoreError('Speicherbesitz gewechselt.')
        if len(header) < 100 or header[:16] != b'SQLite format 3\0' or header[18] != 1 or header[19] != 1:
            raise StoreError('Nur vorhandener Rollbackjournal-Speicher wird zugelassen.')
        for suffix in ('-wal', '-shm'):
            if os.path.lexists(str(self.path)+suffix):
                raise StoreError('Unbekannte Speichernebenform; Betreiberklärung erforderlich.')
        journal = pathlib.Path(str(self.path)+'-journal')
        if os.path.lexists(journal):
            _private_file(journal)  # SQLite alone may recover its owned hot rollback journal.

    def _lease(self):
        path = self.directory / '.agent.lock'
        fd = os.open(path, os.O_RDWR | os.O_CREAT | getattr(os, 'O_NOFOLLOW', 0), 0o600)
        try:
            info = _private_file(path)
            opened = os.fstat(fd)
            if (info.st_dev, info.st_ino) != (opened.st_dev, opened.st_ino):
                raise StoreError('Speicherbesitz gewechselt.')
            fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
            return fd
        except (OSError, StoreError):
            os.close(fd)
            raise EntryBusy('Gerätedienst besitzt den Speicher bereits.') from None

    @contextlib.contextmanager
    def _transaction(self):
        with self._lock:
            try:
                self._validate_files()
                self.connection.execute('BEGIN IMMEDIATE')
                yield
                self.connection.execute('COMMIT')
            except (OSError, sqlite3.Error):
                try:
                    if self.connection.in_transaction:
                        self.connection.execute('ROLLBACK')
                except sqlite3.Error:
                    pass
                raise StoreError('Gerätespeicheränderung nicht bestätigt; nicht automatisch erneut buchen.') from None
            except BaseException:
                try:
                    if self.connection.in_transaction:
                        self.connection.execute('ROLLBACK')
                except sqlite3.Error:
                    raise StoreError('Lokaler Transaktionsausgang unklar; Betreiberklärung erforderlich.') from None
                raise

    def _usage(self):
        pending = self.connection.execute('SELECT count(*),COALESCE(sum(length(raw)+?),0) FROM intents', (COMPLETION_BUDGET,)).fetchone()
        notes = self.connection.execute('SELECT COALESCE(sum(length(CAST(reason AS BLOB))+length(CAST(reference AS BLOB))+512),0) FROM resolutions').fetchone()[0]
        return pending[0], pending[1]+notes

    def enqueue(self, partition, raw):
        if not self.agent or re.fullmatch(r'[a-f0-9]{64}', partition or '') is None:
            raise StoreError('Aktueller Speicherbesitz erforderlich.')
        data = _decode(raw)
        entry_id = secrets.token_hex(16)
        with self._transaction():
            count, size = self._usage()
            if count >= MAX_PENDING or size+len(raw)+COMPLETION_BUDGET > MAX_ACCOUNTED_BYTES:
                raise CapacityError('Klärungsspeicher voll; keine neue Buchung gesendet. Anderen freigegebenen Erfassungsweg nutzen.')
            self.connection.execute('INSERT INTO intents VALUES(?,?,?,?,?,?,?)', (entry_id,data['event_id'],data['action'],partition,raw,hashlib.sha256(raw).hexdigest(),int(time.time())))
            self.connection.execute('INSERT INTO attempts VALUES(?,?,?)', (entry_id,'prepared',self.owner))
        return entry_id

    def entry(self, entry_id, partition=None):
        with self._lock:
            row = self.connection.execute('SELECT i.entry_id,i.event_id,i.action,i.partition,i.raw,i.digest,a.state,a.owner FROM intents i JOIN attempts a USING(entry_id) WHERE entry_id=?', (entry_id,)).fetchone()
        if row is None:
            raise StoreError('Vorgang nicht verfügbar.')
        raw = bytes(row[4]); data = _decode(raw)
        if hashlib.sha256(raw).hexdigest() != row[5] or data['event_id'] != row[1] or data['action'] != row[2] or (partition is not None and partition != row[3]):
            raise StoreError('Vorgang passt nicht zum ursprünglichen privaten Kontext.')
        return {'entry_id':row[0],'event_id':row[1],'action':row[2],'partition':row[3],'raw':raw,'digest':row[5],'state':row[6],'owner':row[7]}

    def claim(self, entry_id):
        if not self.agent:
            raise StoreError('Nur der laufende ursprüngliche Agent darf einen neuen Versuch beginnen.')
        with self._transaction():
            updated = self.connection.execute("UPDATE attempts SET state='attempting' WHERE entry_id=? AND state='prepared' AND owner=?", (entry_id,self.owner)).rowcount
            if updated != 1:
                raise StoreError('Vorgang wurde bereits versucht; kein erneuter Versand.')

    def uncertain(self, entry_id):
        with self._transaction():
            self.connection.execute("UPDATE attempts SET state='uncertain' WHERE entry_id=? AND owner=?", (entry_id,self.owner))

    def complete_immediate(self, entry_id):
        if not self.agent:
            raise StoreError('Keine unmittelbare Agentenbestätigung.')
        with self._transaction():
            self.connection.execute("DELETE FROM intents WHERE entry_id=? AND EXISTS(SELECT 1 FROM attempts WHERE entry_id=? AND owner=?)", (entry_id,entry_id,self.owner))

    def safe_list(self):
        with self._lock:
            rows = self.connection.execute('SELECT i.entry_id,i.event_id,i.action,i.created_at,i.partition,a.state FROM intents i JOIN attempts a USING(entry_id) ORDER BY i.created_at,i.entry_id').fetchall()
        return [{'entry_id':r[0],'event_id':r[1],'action':r[2],'created_at':r[3],'partition':r[4][:12],'state':r[5]} for r in rows]

    def resolve(self, entry_id, kind, reason, reference=''):
        if kind not in ('receipt_confirmed','externally_clarified') or not isinstance(reason,str) or not 5 <= len(reason.encode()) <= 1000 or not isinstance(reference,str) or len(reference.encode())>500:
            raise StoreError('Begrenzte ausdrückliche Klärung erforderlich; keine Geheimnisse eingeben.')
        probe = None
        try:
            current = self.entry(entry_id)
            if current['state']=='attempting' and kind!='receipt_confirmed':
                probe = self._lease()  # Refuse removal while a live agent owns its attempt.
            with self._transaction():
                current = self.entry(entry_id)
                if current['state']=='attempting' and kind!='receipt_confirmed' and probe is None:
                    raise EntryBusy('Dieser lokale Versuch läuft noch; andere Personen bleiben unabhängig.')
                self.connection.execute('INSERT INTO resolutions(entry_id,event_id,digest,uid,at,kind,reason,reference) VALUES(?,?,?,?,?,?,?,?)',
                    (entry_id,current['event_id'],current['digest'],os.geteuid(),int(time.time()),kind,reason,reference))
                self.connection.execute('DELETE FROM intents WHERE entry_id=?',(entry_id,))
        finally:
            if probe is not None:
                os.close(probe)

    def close(self):
        try:
            if self.connection is not None:
                self.connection.close()
        finally:
            self.connection = None
            if self._lease_fd is not None:
                os.close(self._lease_fd)
                self._lease_fd = None
