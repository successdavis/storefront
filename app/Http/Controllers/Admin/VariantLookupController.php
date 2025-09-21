<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Models\VariantType;


class VariantLookupController extends Controller
{
    public function index()
    {
        $types = VariantType::with('values:id,variant_type_id,value')->orderBy('name')->get(['id','name']);
        return response()->json($types);
    }
}
