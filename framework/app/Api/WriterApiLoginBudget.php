<?php

namespace App\Api;

use App\Auth\WriterLoginBudget;
use Illuminate\Http\Exceptions\HttpResponseException;
use Throwable;

/** Wire mapping only; the shared operation has already committed before a 429. */
final readonly class WriterApiLoginBudget implements LegacyApiLoginBudget
{
    public function __construct(private WriterLoginBudget $budget) {}

    public function shares(WriterLoginBudget $budget): bool
    {
        return $this->budget === $budget;
    }

    public function consume(string $username, string $remoteAddress): void
    {
        try {
            $limited = $this->budget->limited($username, $remoteAddress);
        } catch (Throwable) {
            // No SQL/path/previous exception, bad-credentials result or retry.
            throw new HttpResponseException(LegacyApiResponse::json([
                'success' => false, 'message' => 'Internal server error',
            ], 500));
        }
        if ($limited) {
            $response = LegacyApiResponse::text('Too many login attempts. Try again later.', 429);
            $response->headers->set('Retry-After', '600');
            throw new HttpResponseException($response);
        }
    }
}
