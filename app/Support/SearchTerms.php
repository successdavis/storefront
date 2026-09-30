<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Shared word-splitting rules for catalog search, so the storefront and the
 * admin/barcode search bars all match multi-word queries the same way
 * (e.g. "Dell 5480" finds "Dell Latitude 5480").
 */
class SearchTerms
{
    /**
     * Split a query into lowercase word groups, each with its singular form
     * so "laptops" also matches "laptop" (and vice versa via substring).
     * Stopwords and one-character noise are dropped.
     *
     * @return array<int, array<int, string>>
     */
    public static function parse(string $search): array
    {
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'for', 'with', 'of', 'in', 'on', 'to', 'by', 'from'];

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($search)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($tokens)
            ->filter(fn (string $token) => mb_strlen($token) >= 2 && !in_array($token, $stopwords, true))
            ->unique()
            ->take(6)
            ->map(fn (string $token) => collect([$token, Str::singular($token)])
                ->filter(fn (string $term) => mb_strlen($term) >= 2)
                ->unique()
                ->values()
                ->all())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Like parse(), but falls back to the whole phrase as a single group when
     * every token is filtered away (short or stopword-only queries).
     *
     * @return array<int, array<int, string>>
     */
    public static function groups(string $search): array
    {
        $groups = static::parse($search);

        return $groups !== [] ? $groups : [[mb_strtolower(trim($search))]];
    }
}
