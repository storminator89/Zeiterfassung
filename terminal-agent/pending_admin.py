#!/usr/bin/env python3
"""Owner-local uncertainty clarification; NEVER a mutating event/replay client."""
import argparse
import getpass
import json
import os
import pathlib
import stat
import sys
from agent import Remote
from pending_store import PendingStore, StoreError, EntryBusy, partition_for, receipt_request, positive_historical


def private_configuration(value):
    path=pathlib.Path(value)
    info=path.lstat()
    if not path.is_absolute() or path.resolve()!=path or not stat.S_ISREG(info.st_mode) or info.st_uid!=os.geteuid() or info.st_nlink!=1 or stat.S_IMODE(info.st_mode)&0o077:
        raise StoreError('Eigene private Gerätekonfiguration erforderlich.')
    return json.loads(path.read_text())


def check_receipt(store, entry_id, remote):
    entry=store.entry(entry_id,partition_for(remote.config,remote.url))
    # This is a DIFFERENT receipt envelope. No event bytes/request_raw/replay fallback.
    status,result=remote.request(receipt_request(entry['raw']))
    if not positive_historical(status,result,entry['event_id']):
        return False
    store.resolve(entry_id,'receipt_confirmed','Matched original historical server receipt.',str(result['received_at']))
    return True


def read_private_note(prompt,limit):
    if sys.stdin.isatty():
        value=getpass.getpass(prompt)
    else:
        raw=sys.stdin.buffer.readline(limit+2)
        if len(raw)>limit+1:
            raise StoreError('Klärungstext zu lang.')
        value=raw.decode('utf-8').rstrip('\r\n')
    if len(value.encode())>limit:
        raise StoreError('Klärungstext zu lang.')
    return value


def main():
    parser=argparse.ArgumentParser(description='Local OS-owner clarification only; no app-admin identity, no booking/replay.')
    parser.add_argument('action',choices=['init','list','receipt-check','resolve'])
    parser.add_argument('--pending-dir',required=True)
    parser.add_argument('--entry')
    parser.add_argument('--config',help='Existing private device configuration, only for receipt lookup')
    args=parser.parse_args();store=None
    try:
        if args.action=='init':
            PendingStore.initialize(args.pending_dir)
            print('Privater Speicher initialisiert. Keine Einrichtung des Dienstes und keine Buchung ausgeführt.');return 0
        store=PendingStore(args.pending_dir)
        if args.action=='list':
            for row in store.safe_list():print(json.dumps(row,separators=(',',':')))
            return 0
        if not args.entry:raise StoreError('Expliziter Vorgangsverweis erforderlich.')
        if args.action=='receipt-check':
            if not args.config:raise StoreError('Explizite private Konfiguration für reine Quittungsprüfung erforderlich.')
            remote=Remote(private_configuration(args.config))
            found=check_receipt(store,args.entry,remote)
            print('Ursprüngliche historische Quittung bestätigt; lokal abgeschlossen.' if found else 'Nicht klärbar. Vorgang bleibt erhalten; keine neue Buchung ausgeführt.')
            return 0 if found else 2
        reason=read_private_note('Kurzer externer Klärungsgrund (keine Namen/PINs/Schlüssel): ',1000)
        reference=read_private_note('Optionaler nichtgeheimer Klärungsverweis: ',500)
        store.resolve(args.entry,'externally_clarified',reason,reference)
        print('Nur lokal als extern geklärt dokumentiert. Keine Serverbuchung oder Korrektur ausgeführt.');return 0
    except EntryBusy:
        print('Dieser lokale Versuch läuft noch; andere Personen bleiben unabhängig.',file=sys.stderr);return 2
    except Exception:
        # Never print config path, raw body/grant, remote dict, reason, PIN or error details.
        print('Klärung nicht bestätigt. Private Ablage und Berechtigung prüfen; keine Buchung wiederholen.',file=sys.stderr);return 1
    finally:
        if store is not None:
            try:store.close()
            except Exception:print('Speicherabschluss unbestätigt; Betreiberklärung erforderlich.',file=sys.stderr)

if __name__=='__main__':raise SystemExit(main())
