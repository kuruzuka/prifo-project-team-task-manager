<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CommentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Users can only update their own comments (or Admins/Managers).
     */
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        return Gate::allows('update', $comment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment_text' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment_text.required' => 'Please enter a comment.',
            'comment_text.max' => 'Comment cannot exceed 5000 characters.',
        ];
    }
}
