<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VariantTypeRequest;
use App\Models\VariantType;
use App\Models\VariantValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VariantTypeController extends Controller
{
    public function index(): Response
    {
        $types = VariantType::query()
            ->withCount('values')
            ->orderBy('name')
            ->paginate(12)
            ->through(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'values_count' => $t->values_count,
                    'created_at' => $t->created_at->toDateTimeString(),
                ];
            });

        return Inertia::render('Admin/VariantTypes/Index', [
            'types' => $types,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/VariantTypes/Upsert', [
            'variantType' => null,
            'values' => [],
        ]);
    }

    public function store(VariantTypeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $type = VariantType::create([
                'name' => $request->string('name'),
            ]);

            foreach ($request->input('values', []) as $row) {
                if (!empty($row['value'])) {
                    $type->values()->create(['value' => $row['value']]);
                }
            }
        });

        // Stay on the same page and reset via front end
        return back()->with('success', 'Variant type created.');
    }

    public function edit(VariantType $variantType): Response
    {
        $variantType->load('values:id,variant_type_id,value');

        return Inertia::render('Admin/VariantTypes/Upsert', [
            'variantType' => [
                'id' => $variantType->id,
                'name' => $variantType->name,
            ],
            'values' => $variantType->values
                ->sortBy('value')
                ->values()
                ->map(fn ($v) => ['id' => $v->id, 'value' => $v->value]),
        ]);
    }

    public function update(VariantTypeRequest $request, VariantType $variantType): RedirectResponse
    {
        DB::transaction(function () use ($request, $variantType) {
            $variantType->update(['name' => $request->string('name')]);

            $submitted = collect($request->input('values', []));

            // Update or create
            $keptIds = [];
            foreach ($submitted as $row) {
                if (!empty($row['id'])) {
                    $value = VariantValue::where('id', $row['id'])
                        ->where('variant_type_id', $variantType->id)
                        ->firstOrFail();
                    $value->update(['value' => $row['value']]);
                    $keptIds[] = $value->id;
                } else {
                    $value = $variantType->values()->create(['value' => $row['value']]);
                    $keptIds[] = $value->id;
                }
            }

            // Delete removed
            $variantType->values()
                ->whereNotIn('id', $keptIds)
                ->delete();
        });

        // Stay on page
        return back()->with('success', 'Variant type updated.');
    }

    public function destroy(VariantType $variantType): RedirectResponse
    {
        $variantType->delete();
        return back()->with('success', 'Variant type deleted.');
    }
}
