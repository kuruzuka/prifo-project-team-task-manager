<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ProjectStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Project::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
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
            'name.required' => 'Project name is required.',
            'name.max' => 'Project name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 255 characters.',
            'due_date.after_or_equal' => 'Due date must be today or later.',
        ];
    }
}
