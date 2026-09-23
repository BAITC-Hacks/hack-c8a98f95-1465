<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfferController extends Controller
{
    public function store(Request $request, Task $task)
    {
        abort_unless($task->status === 'published', 404);
        $data = $request->validate([
            'idea' => ['required', 'string', 'max:10000'],
            'plan' => ['required', 'string', 'max:10000'],
            'timeline' => ['required', 'string', 'max:255'],
            'prototypeLink' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'teamId' => ['prohibited'],
            'decision' => ['prohibited'],
        ], [
            'required' => 'Поле обязательно для заполнения.',
            'url' => 'Укажите корректную ссылку с http:// или https://.',
            'prohibited' => 'Это поле управляется сервером.',
        ]);
        $offer = $task->offers()->create([
            'team_id' => $request->attributes->get('demoId'),
            'idea' => $data['idea'], 'plan' => $data['plan'], 'timeline' => $data['timeline'],
            'prototype_link' => $data['prototypeLink'] ?? null,
            'decision' => 'pending',
        ]);

        return (new OfferResource($offer->load('team')))->response()->setStatusCode(201);
    }

    public function index(Request $request, Task $task)
    {
        abort_unless($task->owner_id === $request->attributes->get('demoId'), 403);

        return OfferResource::collection($task->offers()->with('team')->latest('id')->get());
    }

    public function mine(Request $request)
    {
        return OfferResource::collection(
            Offer::where('team_id', $request->attributes->get('demoId'))->with('team')->latest('id')->get()
        );
    }

    public function decision(Request $request, Offer $offer): OfferResource
    {
        abort_unless($offer->task->owner_id === $request->attributes->get('demoId'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['selected', 'rejected'])],
        ], ['decision.in' => 'Допустимые решения: selected или rejected.']);
        $offer->update(['decision' => $data['decision'], 'decided_at' => now()]);

        return new OfferResource($offer->load('team'));
    }
}
