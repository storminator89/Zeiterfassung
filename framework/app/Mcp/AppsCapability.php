<?php

namespace App\Mcp;

final class AppsCapability
{
    public static function available(): bool
    {
        if (config('chrono_mcp.apps_enabled') !== true || ! app()->bound('mcp.request')) { return false; }
        $meta = app('mcp.request')->meta() ?? [];
        $ui = $meta['io.modelcontextprotocol/clientCapabilities']['extensions']['io.modelcontextprotocol/ui'] ?? null;
        return is_array($ui) && in_array('text/html;profile=mcp-app', $ui['mimeTypes'] ?? [], true);
    }
}
