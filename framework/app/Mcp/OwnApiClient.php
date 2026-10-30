<?php

namespace App\Mcp;

/** Only the existing own-account HTTP API; no framework/domain/session shortcuts. */
final class OwnApiClient
{
    public function __construct(private readonly LocalProfile $profile) {}

    public function call(string $endpoint, array $query = [], ?array $body = null): array
    {
        if (! in_array($endpoint, ['users', 'attendance', 'timeentries', 'corrections', 'work-model', 'work-models'], true)
            || ($body !== null && $endpoint !== 'attendance')) { throw new ApiFailure('endpoint_refused'); }
        // Every call rechecks upstream identity/revocation. Failure never decides
        // the outcome of an earlier unconfirmed mutation, and clears no receipt.
        $identity = $this->request('users', [], null);
        $rows = $identity['data'] ?? null;
        if (($identity['success'] ?? null) !== true || ! is_array($rows) || ! array_is_list($rows) || count($rows) !== 1
            || ! is_array($rows[0]) || ! isset($rows[0]['id'])
            || (! is_int($rows[0]['id']) && ! is_string($rows[0]['id']))
            || ! LocalProfile::id((string) $rows[0]['id']) || (string) $rows[0]['id'] !== $this->profile->account) {
            throw new ApiFailure('account_binding_refused');
        }
        $wire = $endpoint === 'users' ? $identity : $this->request($endpoint, $query, $body);
        $result = OwnProjection::make($endpoint, $wire, $this->profile->account, $query, $body);
        return ['success' => true, 'scope' => 'own', 'profile_binding' => $this->profile->binding] + $result;
    }

    public function binding(): string { return $this->profile->binding; }

    private function request(string $endpoint, array $query, ?array $body): array
    {
        $buffer = '';
        $retryAfter = null;
        $handle = curl_init($this->profile->origin.'/api.php/'.$endpoint.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986)));
        if ($handle === false) { throw new ApiFailure('transport_unconfirmed'); }
        try {
            curl_setopt_array($handle, [
                CURLOPT_CUSTOMREQUEST => $body === null ? 'GET' : 'POST',
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: '.$this->profile->authorization()],
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_PROTOCOLS_STR => 'https', CURLOPT_REDIR_PROTOCOLS_STR => 'https',
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT_MS => 3000, CURLOPT_TIMEOUT_MS => 10000,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$buffer): int {
                    if (strlen($buffer) + strlen($chunk) > 262144) { return 0; }
                    $buffer .= $chunk;
                    return strlen($chunk);
                },
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$retryAfter): int {
                    if (preg_match('/\ARetry-After: *([0-9]{1,6})\s*\z/iD', $line, $match)) { $retryAfter = (int) $match[1]; }
                    return strlen($line);
                },
            ]);
            if ($body !== null) { curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR)); }
            $ok = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $type = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
            if ($ok === false) { throw new ApiFailure('transport_unconfirmed'); }
            if ($status !== 200) { throw new ApiFailure('upstream_refused', $status, $retryAfter); }
            if (! is_string($type) || ! preg_match('~\Aapplication/json(?:\s*;|\z)~i', $type)) { throw new ApiFailure('response_refused'); }
            $data = json_decode($buffer, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            if (! is_array($data) || ($data['success'] ?? null) !== true) { throw new ApiFailure('response_refused'); }
            return $data;
        } catch (ApiFailure $error) { throw $error;
        } catch (\Throwable) { throw new ApiFailure('response_refused');
        } finally { unset($handle); }
    }
}
