<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\SeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SeoController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
    ) {}

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /account/',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Disallow: /store/cart',
            'Disallow: /store/search',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /settings/',
            'Disallow: /payment/',
            'Disallow: /order/success',
            'Allow: /store',
            'Allow: /store/catalog',
            'Allow: /store/featured',
            'Allow: /store/latest',
            'Sitemap: ' . route('seo.sitemap'),
        ];

        return response(implode("\n", $lines) . "\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $urls = collect([
            $this->sitemapUrl(route('store.home'), now(), 'daily', '1.0'),
            $this->sitemapUrl(route('store.catalog'), now(), 'daily', '0.9'),
            $this->sitemapUrl(route('store.featured'), now(), 'daily', '0.8'),
            $this->sitemapUrl(route('store.latest'), now(), 'daily', '0.8'),
        ]);

        Category::query()
            ->select(['id', 'name', 'slug', 'updated_at'])
            ->whereNotNull('slug')
            ->whereHas('products', fn (Builder $query) => $query
                ->where('is_active', true)
                ->whereHas('variants', fn (Builder $variantQuery) => $variantQuery->where('is_active', true)))
            ->orderBy('name')
            ->chunk(250, function ($categories) use ($urls) {
                foreach ($categories as $category) {
                    $urls->push($this->sitemapUrl(
                        route('store.category', ['category' => $category->slug]),
                        $category->updated_at,
                        'weekly',
                        '0.8',
                    ));
                }
            });

        Product::query()
            ->select(['id', 'slug', 'updated_at'])
            ->active()
            ->whereNotNull('slug')
            ->whereHas('variants', fn (Builder $query) => $query->where('is_active', true))
            ->orderByDesc('updated_at')
            ->chunk(500, function ($products) use ($urls) {
                foreach ($products as $product) {
                    $urls->push($this->sitemapUrl(
                        route('store.product', $product->slug),
                        $product->updated_at,
                        'weekly',
                        '0.7',
                    ));
                }
            });

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    protected function sitemapUrl(string $loc, ?Carbon $lastmod = null, string $changefreq = 'weekly', string $priority = '0.5'): array
    {
        return [
            'loc' => $loc,
            'lastmod' => ($lastmod ?? now())->toAtomString(),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }
}
