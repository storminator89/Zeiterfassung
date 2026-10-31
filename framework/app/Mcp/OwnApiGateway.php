<?php
namespace App\Mcp;
interface OwnApiGateway
{
    public function call(string $endpoint, array $query = [], ?array $body = null): array;
    public function binding(): string;
}
