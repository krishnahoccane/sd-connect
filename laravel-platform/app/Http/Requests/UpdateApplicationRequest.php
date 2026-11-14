<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'app_url' => ['sometimes', 'url', 'max:512'],
            'description' => ['nullable', 'string', 'max:2000'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'redirect_uris' => ['sometimes', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'string', 'max:1024'],
            'post_logout_redirect_uris' => ['nullable', 'array'],
            'post_logout_redirect_uris.*' => ['required', 'string', 'max:1024'],
            'allowed_scopes' => ['sometimes', 'array'],
            'allowed_scopes.*' => ['string', 'in:openid,profile,email,phone,offline_access'],
            'require_pkce' => ['sometimes', 'boolean'],
            'access_token_ttl_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'refresh_token_ttl_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'auth_methods' => ['sometimes', 'array'],
            'access_policy' => ['sometimes', 'array'],
        ];
    }
}
