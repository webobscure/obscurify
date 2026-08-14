<?php

namespace App\Domain\Cms\Http\Requests;

use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBlogPostRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash'],
            // Rule::exists is a raw query, bypassing BelongsToTenant's
            // global scope — scope it explicitly or a request could
            // attach another store's Author to this post.
            'author_id' => ['nullable', Rule::exists('authors', 'id')->where('store_id', app(TenantContext::class)->storeId())],
            'excerpt' => ['nullable', 'string'],
            'body' => ['required', 'string'],
            'featured_image_path' => ['nullable', 'string', 'max:2048'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
