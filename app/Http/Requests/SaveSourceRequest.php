<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SaveSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'topic_id' => [
                'nullable',
                'integer',
                Rule::exists('topics', 'id')->where('user_id', Auth::id()),
            ],
            'adapter_type' => ['required', 'string', 'in:rss,github,json'],
            'fetch_interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
        ];
    }
}