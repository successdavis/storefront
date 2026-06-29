<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    seo: {
        type: Object,
        default: () => ({}),
    },
    structuredData: {
        type: [Array, Object],
        default: () => [],
    },
})

const schemas = computed(() => {
    if (Array.isArray(props.structuredData)) {
        return props.structuredData.filter(Boolean)
    }

    return props.structuredData ? [props.structuredData] : []
})

function jsonLd(schema) {
    return JSON.stringify(schema)
}
</script>

<template>
    <Head :title="seo.title || undefined">
        <meta
            v-if="seo.description"
            head-key="description"
            name="description"
            :content="seo.description"
        >
        <meta
            v-if="seo.robots"
            head-key="robots"
            name="robots"
            :content="seo.robots"
        >
        <link
            v-if="seo.canonical"
            head-key="canonical"
            rel="canonical"
            :href="seo.canonical"
        >
        <link
            v-if="seo.pagination?.prev"
            head-key="pagination-prev"
            rel="prev"
            :href="seo.pagination.prev"
        >
        <link
            v-if="seo.pagination?.next"
            head-key="pagination-next"
            rel="next"
            :href="seo.pagination.next"
        >

        <meta
            v-if="seo.title"
            head-key="og:title"
            property="og:title"
            :content="seo.title"
        >
        <meta
            v-if="seo.description"
            head-key="og:description"
            property="og:description"
            :content="seo.description"
        >
        <meta
            v-if="seo.canonical"
            head-key="og:url"
            property="og:url"
            :content="seo.canonical"
        >
        <meta
            head-key="og:type"
            property="og:type"
            :content="seo.type || 'website'"
        >
        <meta
            v-if="seo.siteName"
            head-key="og:site_name"
            property="og:site_name"
            :content="seo.siteName"
        >
        <meta
            v-if="seo.locale"
            head-key="og:locale"
            property="og:locale"
            :content="seo.locale"
        >
        <meta
            v-if="seo.image"
            head-key="og:image"
            property="og:image"
            :content="seo.image"
        >

        <meta
            head-key="twitter:card"
            name="twitter:card"
            :content="seo.image ? 'summary_large_image' : 'summary'"
        >
        <meta
            v-if="seo.title"
            head-key="twitter:title"
            name="twitter:title"
            :content="seo.title"
        >
        <meta
            v-if="seo.description"
            head-key="twitter:description"
            name="twitter:description"
            :content="seo.description"
        >
        <meta
            v-if="seo.image"
            head-key="twitter:image"
            name="twitter:image"
            :content="seo.image"
        >

        <script
            v-for="(schema, index) in schemas"
            :key="index"
            type="application/ld+json"
            :head-key="`json-ld-${index}`"
            v-html="jsonLd(schema)"
        ></script>
    </Head>
</template>
