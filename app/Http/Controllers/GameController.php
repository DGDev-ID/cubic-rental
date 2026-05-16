<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Http\Requests\GameRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class GameController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Games/Index', [
            'games' => Game::withTrashed()->orderBy('name')->get(),
        ]);
    }

    public function store(GameRequest $request): RedirectResponse
    {
        Game::create($request->validated());
        return back()->with('success', 'Game berhasil ditambahkan.');
    }

    public function update(GameRequest $request, Game $game): RedirectResponse
    {
        $game->update($request->validated());
        return back()->with('success', 'Game berhasil diperbarui.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        $game->delete();
        return back()->with('success', 'Game berhasil dihapus.');
    }
}
