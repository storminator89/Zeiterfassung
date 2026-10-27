<?php

namespace App\Http\Controllers\Terminal;

use App\Terminal\LegacyTerminalRequest;
use App\Terminal\LegacyTerminalResponse;
use App\Terminal\TerminalProtocol;
use AttendanceError;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Dormant transport prerequisite only: no route, provider or backend binding. */
final class LegacyTerminalController
{
    // Required interface: an unresolved binding cannot fall back to native bootstrap,
    // a web identity, an API token, a new key or a second database connection.
    public function __construct(private readonly TerminalProtocol $protocol) {}

    public function __invoke(Request $request): Response
    {
        try {
            $this->protocol->requireEnabled();
            if ($request->server('REQUEST_METHOD') !== 'POST') {
                return LegacyTerminalResponse::error('POST erforderlich.', 405);
            }
            if ((int) $request->server('CONTENT_LENGTH', 0) > 8192) {
                return LegacyTerminalResponse::error('Anfrage zu groß.', 413);
            }

            // Borrow the maintained Request's raw stream; never input()/all(),
            // getPayload(), JSON normalization, or an unbounded getContent().
            // The extra byte detects oversized bodies even without Content-Length.
            $stream = $request->getContent(true);
            if (! is_resource($stream) || ($body = stream_get_contents($stream, 8193)) === false) {
                throw new RuntimeException('Terminal request body unavailable.');
            }
            if (strlen($body) > 8192) {
                return LegacyTerminalResponse::error('Anfrage zu groß.', 413);
            }
            $transport = LegacyTerminalRequest::capture($request, $body);
            if ($transport === null) {
                return LegacyTerminalResponse::error('JSON erforderlich.', 400);
            }

            return LegacyTerminalResponse::success($this->protocol->handle($transport));
        } catch (AttendanceError $error) {
            return LegacyTerminalResponse::error($error->getMessage(), $error->status);
        } catch (Throwable) {
            // No request body, PIN, card, grant, signature or exception detail logged.
            return LegacyTerminalResponse::unconfirmed();
        }
    }
}
