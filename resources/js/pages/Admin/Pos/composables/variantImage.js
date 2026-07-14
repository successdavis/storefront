const PLACEHOLDER_IMAGE =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96"><rect width="96" height="96" fill="#e5e7eb"/><circle cx="36" cy="34" r="6" fill="#9ca3af"/><path d="M28 62l12-14 9 10 7-8 12 12H28z" fill="#9ca3af"/></svg>',
    )

// Server-resolved URL first (S3/public disk aware), then the legacy public-disk
// path for older records, then an inline placeholder that needs no asset.
export function variantImageUrl(variant) {
    if (variant?.image_url) {
        return variant.image_url
    }

    const legacyPath = variant?.product?.images?.[0]?.path
    if (legacyPath) {
        if (/^https?:\/\//i.test(legacyPath) || legacyPath.startsWith('/')) {
            return legacyPath
        }

        return '/storage/' + legacyPath
    }

    return PLACEHOLDER_IMAGE
}
