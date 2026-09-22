<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TokenService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{token: ApiToken, raw: string}
     */
    public function create(User $owner, array $data, User $createdBy): array
    {
        $raw = ApiToken::generateRawToken();

        $token = DB::transaction(function () use ($owner, $data, $createdBy, $raw) {
            $token = ApiToken::query()->create([
                'user_id' => $owner->id,
                'name' => $data['name'],
                'token_hash' => ApiToken::hashToken($raw),
                'token_prefix' => ApiToken::prefixFor($raw),
                'abilities' => $data['abilities'],
                'status' => 'active',
                'expires_at' => $data['expires_at'] ?? null,
                'allowed_ips' => $data['allowed_ips'] ?? null,
                'created_by' => $createdBy->id,
            ]);

            $this->syncBucketScopes($token, $data['bucket_scopes'] ?? []);

            return $token;
        });

        return ['token' => $token->fresh(['user', 'bucketScopes.bucket']), 'raw' => $raw];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ApiToken $token, array $data): ApiToken
    {
        $token->update(Arr::only($data, ['name', 'abilities', 'expires_at', 'allowed_ips']));

        if (array_key_exists('bucket_scopes', $data)) {
            $this->syncBucketScopes($token, $data['bucket_scopes'] ?? []);
        }

        return $token->fresh(['user', 'bucketScopes.bucket']);
    }

    /**
     * @return array{token: ApiToken, raw: string}
     */
    public function rotate(ApiToken $token): array
    {
        $raw = ApiToken::generateRawToken();

        $token->update([
            'token_hash' => ApiToken::hashToken($raw),
            'token_prefix' => ApiToken::prefixFor($raw),
            'status' => 'active',
        ]);

        return ['token' => $token->fresh(['user', 'bucketScopes.bucket']), 'raw' => $raw];
    }

    public function revoke(ApiToken $token): ApiToken
    {
        $token->update(['status' => 'revoked']);

        return $token;
    }

    /**
     * @param  array<int, array{bucket: string, path_prefix?: ?string, permissions: array<int, string>}>  $scopes
     */
    private function syncBucketScopes(ApiToken $token, array $scopes): void
    {
        $token->bucketScopes()->delete();

        foreach ($scopes as $scope) {
            $bucket = Bucket::query()->where('name', $scope['bucket'])->firstOrFail();

            $token->bucketScopes()->create([
                'bucket_id' => $bucket->id,
                'path_prefix' => $scope['path_prefix'] ?? null,
                'permissions' => $scope['permissions'],
            ]);
        }
    }
}
