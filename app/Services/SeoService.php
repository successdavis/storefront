<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeoService
{
    public const INDEX_ROBOTS = 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';

    public const NOINDEX_ROBOTS = 'noindex,follow';

    public function siteName(): string
    {
        return $this->setting('business_name') ?: (string) config('app.name', 'S-Tech-Max');
    }

    public function tagline(): ?string
    {
        return $this->setting('business_tagline') ?: null;
    }

    public function currency(): string
    {
        $currency = strtoupper((string) ($this->setting('business_currency') ?: 'NGN'));

        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'NGN';
    }

    public function page(
        string $title,
        ?string $description = null,
        ?string $canonical = null,
        array $options = [],
    ): array {
        $description = $this->description($description);
        $canonical = $canonical ? $this->absoluteUrl($canonical) : url()->current();
        $image = $this->absoluteUrl($options['image'] ?? null);
        $robots = $options['robots'] ?? self::INDEX_ROBOTS;

        return array_filter([
            'title' => $this->title($title),
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'image' => $image,
            'type' => $options['type'] ?? 'website',
            'siteName' => $this->siteName(),
            'locale' => str_replace('_', '-', app()->getLocale()),
            'pagination' => $options['pagination'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function paginationLinks(LengthAwarePaginator $paginator): array
    {
        return array_filter([
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ]);
    }

    public function organizationSchema(): array
    {
        $url = $this->siteUrl();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $url . '#organization',
            'name' => $this->siteName(),
            'url' => $url,
            'description' => $this->tagline(),
            'email' => $this->setting('business_email') ?: null,
            'telephone' => $this->setting('business_phone') ?: null,
            'address' => $this->setting('business_address') ?: null,
        ], fn ($value) => filled($value));
    }

    public function websiteSchema(): array
    {
        $url = $this->siteUrl();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $url . '#website',
            'name' => $this->siteName(),
            'url' => $url,
            'publisher' => [
                '@id' => $url . '#organization',
            ],
        ]);
    }

    public function breadcrumbSchema(array $items): ?array
    {
        $items = collect($items)
            ->filter(fn (array $item) => filled($item['name'] ?? null) && filled($item['url'] ?? null))
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
                ->map(fn (array $item, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $this->absoluteUrl($item['url']),
                ])
                ->all(),
        ];
    }

    public function itemListSchema(array|Collection $products, string $name): ?array
    {
        $items = collect($products)
            ->filter(fn (array $product) => filled($product['name'] ?? null) && filled($product['slug'] ?? null))
            ->take(24)
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'itemListElement' => $items
                ->map(fn (array $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => route('store.product', $product['slug']),
                    'name' => $product['name'],
                ])
                ->all(),
        ];
    }

    public function productSchema(array $product, string $canonical): array
    {
        $images = $this->productImages($product);
        $variants = collect($product['variants'] ?? []);
        $prices = $variants
            ->pluck('price.current')
            ->filter(fn ($price) => is_numeric($price))
            ->map(fn ($price) => round((float) $price, 2))
            ->values();

        if ($prices->isEmpty() && is_numeric(data_get($product, 'price.current'))) {
            $prices = collect([round((float) data_get($product, 'price.current'), 2)]);
        }

        $isInStock = $variants->contains(fn (array $variant) => (bool) data_get($variant, 'stock.is_in_stock'))
            || (bool) data_get($product, 'stock.is_in_stock');

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $this->absoluteUrl($canonical) . '#product',
            'name' => $product['name'] ?? null,
            'description' => $this->description($product['meta_description'] ?? $product['description'] ?? null),
            'url' => $this->absoluteUrl($canonical),
            'image' => $images,
            'sku' => $variants->pluck('sku')->filter()->first() ?: ($product['slug'] ?? null),
            'brand' => filled(data_get($product, 'brand.name')) ? [
                '@type' => 'Brand',
                'name' => data_get($product, 'brand.name'),
            ] : null,
            'category' => collect($product['categories'] ?? [])->pluck('name')->filter()->implode(' > ') ?: null,
        ], fn ($value) => filled($value));

        if ($prices->isNotEmpty()) {
            $offer = [
                '@type' => $prices->count() > 1 || $variants->count() > 1 ? 'AggregateOffer' : 'Offer',
                'priceCurrency' => $this->currency(),
                'availability' => $isInStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $this->absoluteUrl($canonical),
                'itemCondition' => 'https://schema.org/NewCondition',
            ];

            if ($offer['@type'] === 'AggregateOffer') {
                $offer['lowPrice'] = $prices->min();
                $offer['highPrice'] = $prices->max();
                $offer['offerCount'] = max($variants->count(), 1);
            } else {
                $offer['price'] = $prices->min();
            }

            $schema['offers'] = $offer;
        }

        return $schema;
    }

    public function faqSchema(array $faqs): ?array
    {
        $items = collect($faqs)
            ->filter(fn (array $faq) => filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items
                ->map(fn (array $faq) => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['answer'],
                    ],
                ])
                ->all(),
        ];
    }

    public function siteUrl(): string
    {
        $configured = $this->setting('business_website') ?: config('app.url');

        return rtrim($this->absoluteUrl((string) $configured) ?: url('/'), '/');
    }

    public function absoluteUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        $url = trim($url);

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url($url);
    }

    public function description(?string $value): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/\s+/', ' ', $text) ?: '';

        if ($text === '') {
            $text = trim(implode(' ', array_filter([
                $this->tagline(),
                'Shop authentic products, compare prices, and order online.',
            ])));
        }

        return Str::limit($text, 155, '');
    }

    public function title(string $value): string
    {
        return Str::limit(trim($value), 65, '');
    }

    protected function productImages(array $product): array
    {
        return collect($product['images'] ?? [])
            ->pluck('url')
            ->prepend($product['image'] ?? null)
            ->filter()
            ->map(fn (string $url) => $this->absoluteUrl($url))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function setting(string $key): ?string
    {
        if (! Schema::hasTable('settings')) {
            return null;
        }

        $value = Setting::get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
