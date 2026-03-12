<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization requires:
     * - User can create tasks (Admin or Manager)
     * - User manages the specified project
     */
    public function authorize(): bool
    {
        // Bypass global scope for authorization check
        $project = Project::withoutGlobalScopes()->find($this->input('project_id'));

        return Gate::allows('create', [Task::class, $project]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tasks', 'title')->where('project_id', $this->input('project_id')),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
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
            'project_id.required' => 'Please select a project.',
            'project_id.exists' => 'The selected project does not exist.',
            'title.required' => 'Task name is required.',
            'title.max' => 'Task name cannot exceed 255 characters.',
            'title.unique' => 'A task with this name already exists in the project.',
            'priority.required' => 'Please select a priority.',
            'priority.in' => 'Priority must be low, medium, high, or critical.',
            'due_date.after_or_equal' => 'Due date must be today or later.',
        ];
    }
}
