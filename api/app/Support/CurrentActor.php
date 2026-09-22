<?php

namespace App\Support;

use App\Enums\ActorType;
use App\Models\ApiToken;
use Illuminate\Support\Facades\Request as RequestFacade;

/**
 * A request authenticated via Bearer token still resolves $request->user()
 * to the token's owning User (see ApiTokenAuth), so Policies/services that
 * only need "who is this" work unchanged. Code that needs to know whether
 * this is a scoped API token call (to check token_bucket_scopes instead of
 * bucket_user_permissions) reads the token off the request via this helper.
 */
class CurrentActor
{
    public static function token(): ?ApiToken
    {
        return RequestFacade::instance()->attributes->get('api_token');
    }

    public static function isTokenRequest(): bool
    {
        return self::token() !== null;
    }

    public static function actorType(): ActorType
    {
        return self::isTokenRequest() ? ActorType::Token : ActorType::User;
    }

    /**
     * The ID to record on an audit_logs row: the token's ID for token
     * requests (so rotated/revoked tokens remain individually traceable),
     * otherwise the authenticated user's ID.
     */
    public static function actorId(): ?int
    {
        if ($token = self::token()) {
            return $token->id;
        }

        return RequestFacade::instance()->user()?->id;
    }
}
