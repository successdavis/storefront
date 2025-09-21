<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminSkuController extends Controller
{
    public function check(Request $r, \App\Services\SkuGenerator $gen)
    {
        $sku   = (string) $r->query('sku', '');
        $ignoreId = $r->integer('ignore_id');
        $storeId  = optional($r->user())->store_id; // or null

        $result = $gen->acceptOrSuggest($storeId, $sku, $ignoreId);
        return response()->json([
            'available'  => $result['accepted'],
            'suggestion' => $result['accepted'] ? null : $result['sku'],
        ]);
    }

}
