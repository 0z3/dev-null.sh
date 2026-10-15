<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApprovePendingCommitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'commit_ids' => ['present', 'array', 'list'],
            'commit_ids.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }
}
