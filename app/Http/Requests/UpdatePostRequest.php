<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $post = $this->route('post');
        if (! $post instanceof Post) {
            $post = Post::find($post);
        }

        return $post && $this->user() && $this->user()->can('update', $post);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $post = $this->route('post');
        $postId = $post instanceof Post ? $post->id : $post;

        $status = $this->input('status', $post instanceof Post ? $post->status->value : 'draft');
        $isPublishing = in_array($status, ['published', 'scheduled'], true);

        return [
            'status' => ['nullable', 'string', 'in:draft,scheduled,published,archived'],
            'title' => [
                $isPublishing ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/i',
                Rule::unique('posts', 'slug')->ignore($postId),
            ],
            'category_id' => ['nullable', 'exists:categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'cover_image_path' => ['nullable', 'string', 'max:1000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'content_json' => ['nullable', 'array'],
            'content_html' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($isPublishing) {
                    if ($isPublishing) {
                        $wordCount = str_word_count(strip_tags((string) $value));
                        if ($wordCount < 100) {
                            $fail('At least 100 words are required to publish a story.');
                        }
                    }
                },
            ],
            'tags' => ['nullable', 'array', 'max:5'],
            'tags.*' => ['nullable'],
            'published_at' => [
                $status === 'scheduled' ? 'required' : 'nullable',
                'date',
                $status === 'scheduled' ? 'after:now' : 'nullable',
            ],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A title is required to publish a story.',
            'tags.max' => 'You cannot add more than 5 tags to a story.',
            'published_at.after' => 'The scheduled publication date must be in the future.',
        ];
    }
}
