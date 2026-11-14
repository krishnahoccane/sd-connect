<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver' => ['required', 'in:platform_google_workspace,org_smtp,org_google_workspace'],
            'sender_name' => ['required', 'string', 'max:160'],
            'sender_email' => ['required', 'email', 'max:255'],
            'smtp_settings' => ['nullable', 'array'],
            'smtp_settings.host' => ['nullable', 'string', 'max:255'],
            'smtp_settings.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_settings.encryption' => ['nullable', 'in:tls,ssl,none'],
            'smtp_settings.username' => ['nullable', 'string', 'max:255'],
            'secrets' => ['nullable', 'array'],
            'secrets.password' => ['nullable', 'string', 'max:512'],
            'secrets.app_password' => ['nullable', 'string', 'max:512'],
            'otp_subject_template' => ['nullable', 'string', 'max:255'],
            'otp_body_template' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
