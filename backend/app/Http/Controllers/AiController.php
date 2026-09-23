<?php

namespace App\Http\Controllers;

use App\Services\AiQuestions;
use App\Services\TaskScorer;
use Illuminate\Http\Request;

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

        return response()->json(['data' => $ai->generate($data['description'], $data['fields'] ?? [])], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
