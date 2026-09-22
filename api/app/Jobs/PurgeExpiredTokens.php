<?php

namespace App\Jobs;

use App\Enums\TokenStatus;
use App\Models\ApiToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tokens are already rejected at auth time once expires_at has passed
 * (ApiToken::isActive()) — this just keeps the "active" status column
 * honest for admin listings/metrics instead of relying solely on the
 * lazy check.
 */
class PurgeExpiredTokens implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        ApiToken::query()
            ->where('status', TokenStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => TokenStatus::Revoked->value]);
    }
}
