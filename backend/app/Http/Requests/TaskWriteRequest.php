<?php

namespace App\Http\Requests;

use App\Services\TaskScorer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'required', 'string', 'max:160'],
            'organization' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'scope' => ['sometimes', 'required', Rule::in(['institution', 'kazakhstan'])],
            'confirmedFields' => ['sometimes', 'present', 'array', 'max:7'],
            'confirmedFields.*' => ['required', 'string', 'distinct', Rule::in(array_keys(TaskScorer::CRITERIA))],
        ];
        foreach (array_keys(TaskScorer::CRITERIA) as $field) {
            $rules[$field] = ['sometimes', 'nullable', 'string', 'max:10000'];
        }
        foreach (['id', 'ownerId', 'score', 'scoreBreakdown', 'status', 'confirmedAt', 'publishedAt'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required' => 'Поле обязательно для заполнения.',
            'string' => 'Ожидается строка.',
            'max' => 'Превышена допустимая длина.',
            'array' => 'Ожидается массив.',
            'in' => 'Недопустимое значение.',
            'distinct' => 'Значения не должны повторяться.',
            'prohibited' => 'Это поле управляется сервером.',
        ];
    }
}
