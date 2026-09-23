<?php

namespace App\Http\Controllers;

use App\Exceptions\AiUnavailable;
use App\Services\AiQuestions;
use App\Services\TaskScorer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiController extends Controller
{
    public function questions(Request $request, AiQuestions $ai)
    {
        $rules = [
            'description' => ['required', 'string', 'min:3', 'max:10000'],
            'fields' => ['sometimes', 'array:'.implode(',', array_keys(TaskScorer::CRITERIA))],
        ];
        foreach (array_keys(TaskScorer::CRITERIA) as $field) {
            $rules['fields.'.$field] = ['sometimes', 'nullable', 'string', 'max:10000'];
        }
        $data = $request->validate($rules, [
            'description.required' => 'Опишите проблему.',
            'description.min' => 'Добавьте описание длиной от трёх символов.',
        ]);

        $length = mb_strlen($data['description']);
        foreach ($data['fields'] ?? [] as $value) {
            $length += mb_strlen($value ?? '');
        }
        if ($length > 20000) {
            throw ValidationException::withMessages(['description' => 'Для AI сократите описание и детали до 20 000 символов суммарно.']);
        }

        try {
            return response()->json(['data' => $ai->generate($data['description'], $data['fields'] ?? [])], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (AiUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => $exception->errorCode], $exception->httpStatus, [], JSON_UNESCAPED_UNICODE);
        }
    }
}
