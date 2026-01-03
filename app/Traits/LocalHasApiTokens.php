<?php

namespace App\Traits;

use App\Models\ApiToken;
use Illuminate\Support\Str;

trait LocalHasApiTokens
{
    public function createToken(string $name, array $abilities = ['*'])
    {
        $plain = Str::random(64);

        $token = ApiToken::create([
            'user_id' => $this->id,
            'token' => $plain,
            'abilities' => $abilities,
        ]);

        return (object) ['plainTextToken' => $plain, 'token' => $token];
    }

    /**
     * Relationship to stored tokens.
     */
    public function tokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * Return the token model for the current request's bearer token.
     */
    public function currentAccessToken()
    {
        $token = request()->bearerToken();

        if (! $token) {
            return null;
        }

        return ApiToken::where('token', $token)->where('user_id', $this->id)->first();
    }
}