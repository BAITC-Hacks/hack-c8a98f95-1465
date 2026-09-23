<?php

namespace App\Http\Controllers;

use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    public function profiles(Request $request)
    {
        abort_unless(config('demo.enabled'), 503);

        return response()->json(['data' => [
            'customers' => config('demo.customers'),
            'teams' => Team::orderBy('id')->get()->map(fn (Team $team) => array_merge(
                (new TeamResource($team))->resolve($request), ['role' => 'team']
            )),
        ]], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function briefs()
    {
        abort_unless(config('demo.enabled'), 503);

        return response()->json(['data' => json_decode(
            file_get_contents(database_path('seeders/data/briefs.json')), true, 512, JSON_THROW_ON_ERROR
        )], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function teams()
    {
        return TeamResource::collection(Team::orderBy('id')->get());
    }
}
