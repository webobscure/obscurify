<?php

namespace App\Domain\Cms\Http\Requests;

use App\Domain\Cms\Enums\BlogPostStatus;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateBlogPostRequest extends FormRequest
{
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
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash'],
            'author_id' => ['sometimes', 'nullable', Rule::exists('authors', 'id')->where('store_id', app(TenantContext::class)->storeId())],
            'excerpt' => ['sometimes', 'nullable', 'string'],
            'body' => ['sometimes', 'string'],
            'status' => ['sometimes', new Enum(BlogPostStatus::class)],
            'featured_image_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
