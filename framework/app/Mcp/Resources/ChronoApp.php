<?php

namespace App\Mcp\Resources;

use App\Mcp\AppsCapability;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;

#[AppMeta(connectDomains: [], resourceDomains: [], frameDomains: [], baseUriDomains: [], permissions: [], prefersBorder: true, domain: false)]
final class ChronoApp extends AppResource
{
    public const URI = 'ui://chrono/attendance';

    protected string $uri = self::URI;
    protected string $name = 'chrono_app';
    protected string $title = 'Quodara Chrono';
    protected string $description = 'Optional own-account attendance and stored records. All interactions use the existing Chrono MCP tools.';

    public function shouldRegister(): bool
    {
        return AppsCapability::available();
    }

    public function handle(Request $request): Response
    {
        if (! AppsCapability::available()) {
            return Response::error('MCP Apps is disabled or unsupported by this host. Use the text tools.');
        }

        return Response::view('mcp.chrono-app', ['title' => $this->title()]);
    }

    public function resolvedAppMeta(): array
    {
        // v1.1.2's builder omits empty arrays. Keep explicit deny-by-default
        // metadata on both resources/list and each resources/read content item.
        return array_replace(parent::resolvedAppMeta(), [
            'csp' => ['connectDomains' => [], 'resourceDomains' => [], 'frameDomains' => [], 'baseUriDomains' => []],
            'permissions' => (object) [],
        ]);
    }
}
