<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:100'],
            'twsd_subdomain' => ['nullable', 'string', 'alpha_dash', 'max:100'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:512'],
            'description' => ['nullable', 'string', 'max:2000'],
            'application_type' => ['required', 'in:web_confidential,spa_public,mobile_public,service'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'string', 'max:1024'],
            'post_logout_redirect_uris' => ['nullable', 'array'],
            'post_logout_redirect_uris.*' => ['required', 'string', 'max:1024'],
            'allowed_scopes' => ['nullable', 'array'],
            'allowed_scopes.*' => ['string', 'in:openid,profile,email,phone,offline_access'],
            'allowed_grant_types' => ['nullable', 'array'],
            'allowed_grant_types.*' => ['string', 'in:authorization_code,refresh_token'],
            'require_pkce' => ['nullable', 'boolean'],
            'access_token_ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'refresh_token_ttl_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'auth_methods' => ['nullable', 'array'],
            'access_policy' => ['nullable', 'array'],
        ];
    }
}
