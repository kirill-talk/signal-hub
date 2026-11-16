<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SaveFilterRuleRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:include,exclude,highlight'],
            'match_field' => ['required', 'string', 'in:title,summary,both'],
            'source_id' => [
                'nullable',
                'integer',
                Rule::exists('sources', 'id')->where('user_id', Auth::id()),
            ],
            'topic_id' => [
                'nullable',
                'integer',
                Rule::exists('topics', 'id')->where('user_id', Auth::id()),
            ],
            'keywords' => ['required', 'array', 'min:1'],
            'keywords.*' => ['required', 'string', 'max:80'],
            'is_active' => ['boolean'],
        ];
    }
}