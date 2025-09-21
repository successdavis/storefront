<?php

namespace App\Services;

use App\Models\Brand;

class BrandService
{
    public function getAll()
    {
        return Brand::all();
    }

    public function create(array $data)
    {
        return Brand::create($data);
    }

    public function update(Brand $brand, array $data)
    {
        $brand->update($data);
        return $brand;
    }

    public function delete(Brand $brand)
    {
        $brand->delete();
    }
}
