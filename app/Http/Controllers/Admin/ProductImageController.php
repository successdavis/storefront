<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Image\StoreProductImageRequest;
use App\Models\Admin\ProductImage;
use App\Models\Product;
use App\Services\ImageOptimizationService;
use App\Services\ProductService;


class ProductImageController extends Controller
{
    public function index(Product $product): \Illuminate\Http\JsonResponse
    {
        return response()->json($product->images);
    }


    public function store(StoreProductImageRequest $request, Product $product, ProductService $svc)
    {
        // If the key is absent, do nothing. If it is present but empty, wipe all.
        $images = $request->validated('images', null);

        if ($images === null) {
            return back()->with('info', 'No image changes submitted.');
        }

        $svc->syncImages($product, $images);


        return back()->with('success', 'Product Image Created');
    }


    public function update(StoreProductImageRequest $request, Product $product, ProductImage $image): \Illuminate\Http\JsonResponse
    {

        if (request('is_primary')) {
            $product->images()->update(['is_primary' => false]);
        }
        $image->update(request()->only('alt','is_primary','sort_order'));
        return response()->json($image);
    }


    public function destroy(Product $product, ProductImage $image, ImageOptimizationService $images): \Illuminate\Http\Response
    {
        $images->deleteResponsiveImage(
            $image->path,
            $image->responsive_paths,
            (string) config('filesystems.uploads_disk')
        );
        $image->delete();
        return response()->noContent();
    }
}
