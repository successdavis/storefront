<template>

    <aside class="h-full">
        <div class="overflow-y-auto py-4 px-3 bg-gray-800 h-full rounded">
            <ul class="space-y-2 text-gray-200">
                <li>
                    <Link :href="'/' + $page.props.auth.role[0] + '/dashboard'" class="flex items-center p-2 text-base font-normal rounded-lg hover:bg-gray-100">
                        <span class="ml-3">STORE</span>
                    </Link>
                </li>
                <accordion-menu name="Products">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z"/>
                        </svg>
                    </template>
                    <main>
                        <li>
                            <Link href="/admin/products/create" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-700">Add New Product</Link>
                        </li>
                        <li>
                            <Link href="/admin/products" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-700">All Products</Link>
                        </li>
                        <li>
                            <Link href="/admin/categories" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-700">Category</Link>
                        </li>
                        <li>
                            <Link href="/admin/brands" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-700">Brand</Link>
                        </li>
                        <li>
                            <Link href="/admin/variant-types" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-700">Attribute</Link>
                        </li>
<!--                        <li>-->
<!--                            <Link href="/categories" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-700">Attribute</Link>-->
<!--                        </li>-->
                    </main>
                </accordion-menu>

                <accordion-menu name="Stocks Entry">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z"/>
                        </svg>
                    </template>
                    <main>
                        <li>
                            <Link href="/admin/stock-entries" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-700">Stocks</Link>
                        </li>
<!--                        <li>-->
<!--                            <Link href="/categories" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-700">Attribute</Link>-->
<!--                        </li>-->
                    </main>
                </accordion-menu>


                <li>
                    <Link href="/logout" method="post" as="button" class="flex items-center p-2 text-base font-normal rounded-lg hover:bg-gray-700 w-full">
                        <svg aria-hidden="true" class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 group-hover" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1h4a1 1 0 001-1v-1h-5v2zm0-4h5V8h-5v2zM9 8H4v2h5V8z" clip-rule="evenodd"></path></svg>
                        <span class=" ml-3 whitespace-nowrap">Exit Dashboard</span>
                    </Link>
                </li>
            </ul>

        </div>
    </aside>

</template>

<script setup>
import {Link} from '@inertiajs/vue3'
import AccordionMenu from "@/Layouts/Partials/AccordionMenu.vue";
</script>

<style >
/* For Webkit browsers (Chrome, Safari) */
::-webkit-scrollbar {
    width: 4px; /* You can adjust this value to make it thinner or thicker */
}

::-webkit-scrollbar-thumb {
    background-color: #888; /* Color of the scrollbar thumb */
}

/* For Firefox */
/* Note: Firefox does not support styling the scrollbar width directly,
   but you can use the color property for the scrollbar thumb */
* {
    scrollbar-color: #888 transparent;
}

/* For Microsoft Edge */
/* Note: Microsoft Edge (Chromium-based) supports styling similar to Webkit browsers */
* {
    scrollbar-width: thin;
    scrollbar-color: #888 transparent;
}

</style>
