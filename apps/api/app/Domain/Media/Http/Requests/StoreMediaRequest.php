<?php

namespace App\Domain\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMediaRequest extends FormRequest
{
    /**
     * Kilobytes. Kept in step with docker/php/uploads.ini's
     * upload_max_filesize/post_max_size and infra/nginx/default.conf's
     * client_max_body_size — all three must clear this value (with
     * headroom for multipart overhead) or a valid upload gets rejected
     * before it ever reaches this rule.
     */
    public const MAX_FILE_KB = 25600;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'max:'.self::MAX_FILE_KB],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
