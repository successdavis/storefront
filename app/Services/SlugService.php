<?php

// app/Services/SlugService.php
namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SlugService
{
    public function makeUnique(string $base, string $table, array $scope = []): string
    {
        $slug = Str::slug($base);
        $slug = Str::limit($slug, 150, '');        // headroom for suffix
        $slug = $this->ensureNotReserved($slug);

        $i = 1;
        $candidate = $slug;
        while ($this->exists($table, $candidate, $scope)) {
            $i++;
            $candidate = Str::limit($slug, 150 - strlen('-'.$i), '') . '-' . $i;
        }
        return $candidate;
    }

    protected function exists(string $table, string $slug, array $scope): bool
    {
        $q = DB::table($table)->where('slug', $slug);
        foreach ($scope as $col => $val) $q->where($col, $val);
        return $q->exists();
    }

    protected function ensureNotReserved(string $slug): string
    {
        $reserved = ['cart','checkout','admin','login','api'];
        return in_array($slug, $reserved, true) ? $slug.'-1' : $slug;
    }
}
