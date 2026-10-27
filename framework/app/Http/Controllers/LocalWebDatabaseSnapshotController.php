<?php

namespace App\Http\Controllers;

use App\Chrono\DatabaseSnapshotExport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Fixed whole-database attachment only; no ranges, offload, selectors or restore. */
final class LocalWebDatabaseSnapshotController extends Controller
{
    public function __construct(private readonly DatabaseSnapshotExport $exports) {}

    public function index(Request $request): Response
    {
        $artifact = null;
        try {
            $this->exports->authorizeHead();
            if ($request->isMethod('HEAD')) {
                // Normal auth middleware may read accounts; no export handle,
                // private file, backup, length generation or response body.
                return new Response('', 200, [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="timetracking.sqlite"',
                    'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff',
                ]);
            }
            if (! $request->isMethod('GET')) {
                return new Response('Method not allowed.', 405, ['Allow' => 'GET, HEAD', 'Cache-Control' => 'no-store']);
            }
            if ($request->headers->has('Range') || $request->headers->has('If-Range')) {
                return new Response('Snapshot ranges are not supported.', 400, [
                    'Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store',
                ]);
            }
            $artifact = $this->exports->create();

            // Fixed callback, never caller-supplied; no BinaryFileResponse,
            // conditional/range processing, X-Sendfile or private path headers.
            return new StreamedResponse([$artifact, 'stream'], 200, $artifact->headers());
        } catch (AuthenticationException|AuthorizationException $error) {
            $artifact?->discard();
            throw $error;
        } catch (Throwable) {
            $artifact?->discard();
            return new Response('Database snapshot could not be created.', 500, [
                'Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store',
            ]);
        }
    }
}
