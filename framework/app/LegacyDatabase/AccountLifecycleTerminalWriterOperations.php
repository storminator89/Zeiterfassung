<?php

namespace App\LegacyDatabase;

use App\Terminal\AdmittedTerminalKeys;
use App\Terminal\LegacyTerminalRequest;
use Closure;

/** Dormant V5-only terminal continuations; no route, source opener or Native activation. */
trait AccountLifecycleTerminalWriterOperations
{
    /** Device-only pair/heartbeat are not account authorities. Runtime admission stays closed. */
    private function terminalGenerationBound(LegacyTerminalRequest $request, AdmittedTerminalKeys $keys): array
    {
        $this->assertIntakeProfile();
        $this->terminalRequireEnabled();
        $input = $request->input;
        if (($input['op'] ?? '') === 'pair') {
            terminal_limit($this->pdo, 'pair:'.$request->remoteAddress, 20);
            $device = $this->executeLocked(function () use ($input): array {
                $this->assertIntakeProfileLocked(requireV4: true);

                return terminal_pair_locked($this->pdo, $input);
            }, lifecycleReady: true);
            $response = terminal_pair_response($device, fn (): string => $keys->deviceSecret());
            $this->assertIdle();

            return $response;
        }

        // Nonce consumption and failed-attempt limits deliberately autocommit.
        // Rolling them back with failed account authorization would erase limits.
        $device = terminal_auth_core($this->pdo, $request->body, [
            'HTTP_X_TERMINAL_ID' => $request->terminalId,
            'HTTP_X_TERMINAL_TIME' => $request->terminalTime,
            'HTTP_X_TERMINAL_NONCE' => $request->terminalNonce,
            'HTTP_X_TERMINAL_SIGNATURE' => $request->terminalSignature,
        ], fn (): string => $keys->deviceSecret());
        $this->assertIdle();
        if (($input['op'] ?? '') === 'identify') {
            $identity = $this->prepareTerminalLifecycleIdentity($device, $input, fn (): string => $keys->cardSecret());
            $this->assertIdle();

            return $this->executeLocked(fn (): array => $this->issueTerminalLifecycleGrantLocked($device, $identity), lifecycleReady: true);
        }

        return $this->executeLocked(fn (): array => $this->dispatchTerminalLifecycleLocked($device, $input), lifecycleReady: true);
    }

    /**
     * Persistent limits and expensive PIN verification precede the lock. One JOIN
     * captures credential/account authority together; no generation is adopted
     * from a later lookup after the PIN check. The capsule never leaves this writer.
     */
    private function prepareTerminalLifecycleIdentity(array $device, #[\SensitiveParameter] array $input, Closure $cardKey): array
    {
        terminal_limit($this->pdo, 'identify-device:'.$device['id'], 60);
        $code = $input['employee_code'] ?? '';
        $card = $input['card'] ?? '';
        $pin = $input['pin'] ?? '';
        if (! is_string($code) || ! is_string($card) || ! is_string($pin)
            || strlen($code) > 64 || strlen($card) > 128 || ! preg_match('/^[0-9]{8}$/D', $pin)) {
            throw new \AttendanceError('Anmeldung fehlgeschlagen.', 401);
        }
        $select = 'SELECT c.*,u.account_status AS lifecycle_status,u.auth_generation AS lifecycle_generation FROM employee_credentials c JOIN users u ON u.id=c.user_id WHERE ';
        $credential = $card !== ''
            ? att_one($this->pdo, $select.'c.card_hash=?', [terminal_card_from_source($card, $cardKey)])
            : att_one($this->pdo, $select.'c.employee_code=?', [$code]);
        terminal_limit($this->pdo, 'identify-person:'.($credential['user_id'] ?? hash('sha256', $code.$card)), 10);
        if (! $credential || ! $credential['enabled'] || (int) $credential['site_id'] !== (int) $device['site_id']
            || ! password_verify($pin, $credential['pin_hash']) || $credential['lifecycle_status'] !== 'active'
            || ! is_int($credential['lifecycle_generation']) || $credential['lifecycle_generation'] < 1) {
            throw new \AttendanceError('Anmeldung fehlgeschlagen.', 401);
        }

        return ['user_id' => (int) $credential['user_id'], 'credential_version' => terminal_version($credential),
            'account_generation' => $credential['lifecycle_generation']];
    }

    /** Exactly one existing IMMEDIATE writer transaction owns recheck and grant INSERT. */
    private function issueTerminalLifecycleGrantLocked(array $device, #[\SensitiveParameter] array $identity): array
    {
        $current = $this->terminalLifecycleDeviceLocked($device);
        $credential = att_one($this->pdo, 'SELECT * FROM employee_credentials WHERE user_id=?', [$identity['user_id']]);
        $user = att_one($this->pdo, 'SELECT id,username,account_status,auth_generation FROM users WHERE id=?', [$identity['user_id']]);
        if (! $credential || ! $credential['enabled'] || (int) $credential['site_id'] !== (int) $current['site_id']
            || ! hash_equals($identity['credential_version'], terminal_version($credential))
            || ! $this->terminalLifecycleAccountMatches($user, $identity['account_generation'])) {
            throw new \AttendanceError('Anmeldung fehlgeschlagen.', 401);
        }
        $token = bin2hex(random_bytes(32));
        att_exec($this->pdo, 'DELETE FROM terminal_grants WHERE expires<?', [time() - 86400]);
        att_exec($this->pdo, 'INSERT INTO terminal_grants(token_hash,terminal_id,user_id,credential_version,expires,account_generation) VALUES(?,?,?,?,?,?)',
            [hash('sha256', $token), $current['id'], $user['id'], terminal_version($credential), time() + 10, $identity['account_generation']]);
        $status = attendance_status($this->pdo, (int) $user['id']);

        // executeLocked returns these bytes only after confirmed COMMIT.
        return attendance_record_id_string(['success' => true, 'grant' => $token, 'expires_in' => 10, 'name' => $user['username']] + $status);
    }

    /** Current authority precedes event/replay AND read-only historical receipt alike. */
    private function dispatchTerminalLifecycleLocked(array $device, #[\SensitiveParameter] array $input): array
    {
        $this->terminalLifecycleDeviceLocked($device);
        $op = $input['op'] ?? '';
        if ($op === 'event' || $op === 'receipt') {
            $unresolved = 'Historische Bestätigung nicht verfügbar. Vorgang bleibt ungeklärt.';
            $grant = $input['grant'] ?? '';
            if (! is_string($grant)) {
                throw new \AttendanceError($op === 'receipt' ? $unresolved : 'Ungültige Anfrage.', 400);
            }
            $issued = att_one($this->pdo, 'SELECT user_id,account_generation FROM terminal_grants WHERE token_hash=? AND terminal_id=?',
                [hash('sha256', $grant), $device['id']]);
            $user = $issued ? att_one($this->pdo, 'SELECT account_status,auth_generation FROM users WHERE id=?', [$issued['user_id']]) : null;
            if (! $issued || ! $this->terminalLifecycleAccountMatches($user, $issued['account_generation'] ?? null)) {
                throw new \AttendanceError($op === 'receipt' ? $unresolved : 'Bitte erneut anmelden.', 401);
            }
        }

        // The exact old core still enforces enabled credential, device/site,
        // expiry, used-event, payload replay and receipt-only semantics, under
        // this same lock. Neither heartbeat nor pair borrows a user identity.
        return terminal_dispatch_locked($this->pdo, $device, $input,
            fn (string $actor, string $reason, Closure $operation): mixed => $this->context->run($actor, $reason, $operation));
    }

    private function terminalLifecycleDeviceLocked(array $device): array
    {
        $this->assertIntakeProfileLocked(requireV4: true);
        attendance_require_enabled($this->pdo);
        $current = att_one($this->pdo, 'SELECT * FROM terminals WHERE id=? AND enabled=1', [$device['id']]);
        if (! $current || ! hash_equals($current['generation'], $device['generation'])) {
            throw new \AttendanceError('Gerät gesperrt.', 403);
        }

        return $current;
    }

    private function terminalLifecycleAccountMatches(?array $user, mixed $generation): bool
    {
        return $user !== null && $user['account_status'] === 'active'
            && is_int($generation) && $generation >= 1
            && is_int($user['auth_generation']) && $user['auth_generation'] === $generation;
    }
}
