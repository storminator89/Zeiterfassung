<?php
namespace App\Http\Controllers;

use App\Chrono\ChronoReads;
use App\Chrono\OwnStoredValuesExportReads;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use LengthException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Explicit own raw-value attachment, not an alternate successful time/CSV report. */
final class LocalWebStoredValuesExportController extends Controller
{
    public function __construct(private ChronoReads $reads) {}

    public function index(Request $request): Response
    {
        $headers=['Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff'];
        if (!$request->isMethod('GET')) { return new Response('Nur GET ist verfügbar.',405,['Allow'=>'GET','Content-Type'=>'text/plain; charset=UTF-8',...$headers]); }
        if ((string)$request->server->get('QUERY_STRING','')!=='') {
            return new Response('Dieser eigene Rohwertnachweis unterstützt keine Filter oder Zielauswahl.',400,['Content-Type'=>'text/plain; charset=UTF-8',...$headers]);
        }
        try {
            if (!$this->reads instanceof OwnStoredValuesExportReads) { throw new \RuntimeException; }
            $result=$this->reads->ownStoredValuesExport();
            if (!is_string($result['content']??null) || !is_string($result['filename']??null)
                || preg_match('/\AEigene_gespeicherte_Werte_[0-9]{4}-[0-9]{2}-[0-9]{2}\.json\z/',$result['filename'])!==1) { throw new \RuntimeException; }
            return new Response($result['content'],200,['Content-Type'=>'application/json; charset=UTF-8',
                'Content-Disposition'=>'attachment; filename="'.$result['filename'].'"',...$headers]);
        } catch (AuthenticationException|AuthorizationException $error) { throw $error; }
        catch (LengthException) {
            return new Response('Der eigene Rohwertnachweis überschreitet eine feste Grenze (5000 Einträge, 64 KiB pro Feld, 8 MiB Dokument). Es wurde kein Teildownload erstellt. Für größere Bestände die Verwaltung um einen gesondert autorisierten Offline-Nachweis bitten.',413,['Content-Type'=>'text/plain; charset=UTF-8',...$headers]);
        } catch (Throwable) {
            return new Response('Eigene gespeicherte Rohwerte konnten nicht vollständig bereitgestellt werden. Es wurde kein Teildownload erstellt.',503,['Content-Type'=>'text/plain; charset=UTF-8',...$headers]);
        }
    }
}
