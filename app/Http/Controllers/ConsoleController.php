<?php

namespace App\Http\Controllers;

use App\Models\Console;
use App\Models\Game;
use App\Http\Requests\ConsoleRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConsoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Consoles/Index', [
            'consoles' => Console::with('games')->withTrashed()->orderBy('name')->get(),
            'games'    => Game::orderBy('name')->get(),
        ]);
    }

    public function store(ConsoleRequest $request): RedirectResponse
    {
        $console = Console::create($request->validated());
        if ($request->game_ids) {
            $console->games()->sync($request->game_ids);
        }
        return back()->with('success', 'Console berhasil ditambahkan.');
    }

    public function update(ConsoleRequest $request, Console $console): RedirectResponse
    {
        $console->update($request->validated());
        if ($request->has('game_ids')) {
            $console->games()->sync($request->game_ids ?? []);
        }
        return back()->with('success', 'Console berhasil diperbarui.');
    }

    public function destroy(Console $console): RedirectResponse
    {
        $console->delete();
        return back()->with('success', 'Console berhasil dihapus.');
    }
}
