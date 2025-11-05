<?php

namespace App\Http\Controllers;

use App\Models\PosTerminal;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PosTerminalController extends Controller
{
    /**
     * Display a listing of POS terminals
     */
    public function index()
    {
        $terminals = PosTerminal::with('warehouse:id,name')
            ->latest()
            ->paginate(10)
            ->through(fn ($terminal) => [
                'id' => $terminal->id,
                'name' => $terminal->name,
                'location' => $terminal->location,
                'warehouse' => $terminal->warehouse ? $terminal->warehouse->name : null,
            ]);

        return Inertia::render('PosTerminals/Index', [
            'terminals' => $terminals,
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('PosTerminals/Form', [
            'warehouses' => Warehouse::select('id', 'name')->get()
        ]);
    }

    /**
     * Store new POS terminal
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
        ]);

        PosTerminal::create($validated);

        return redirect()->route('admin.pos-terminals.index')
            ->with('success', 'POS Terminal created successfully.');
    }

    /**
     * Show edit form
     */
    public function edit(PosTerminal $posTerminal)
    {
        return Inertia::render('PosTerminals/Form', [
            'terminal' => [
                'id' => $posTerminal->id,
                'name' => $posTerminal->name,
                'location' => $posTerminal->location,
                'warehouse_id' => $posTerminal->warehouse_id,
            ],
            'warehouses' => Warehouse::select('id', 'name')->get()
        ]);
    }

    /**
     * Update POS terminal
     */
    public function update(Request $request, PosTerminal $posTerminal)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
        ]);

        $posTerminal->update($validated);

        return redirect()->route('admin.pos-terminals.index')
            ->with('success', 'POS Terminal updated successfully.');
    }

    /**
     * Delete POS terminal
     */
    public function destroy(PosTerminal $posTerminal)
    {
        $posTerminal->delete();

        return redirect()->route('pos-terminals.index')
            ->with('success', 'POS Terminal deleted successfully.');
    }
}
