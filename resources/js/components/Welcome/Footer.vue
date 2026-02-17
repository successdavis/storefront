<template>
    <footer class="bg-primary text-secondary">
        <div class="max-w-6xl mx-auto px-6 py-12">

            <!-- Top Section -->
            <div class="grid gap-10 md:grid-cols-[auto_1fr_1fr]">

                <!-- Logo Section -->
                <div class="md:col-auto">
                    <AppLogo class="md:col-auto w-32 text-secondary" />
                </div>

                <!-- Shop + Information -->
                <div class="grid grid-cols-2 gap-8 flex flex-1">
                    <!-- Shop -->
                    <div>
                        <h3 class="font-semibold ">Shop</h3>
                        <ul class="space-y-2">
                            <li><Link href="#" class="hover:opacity-80">Laptops</Link></li>
                            <li><Link href="#" class="hover:opacity-80">Phones</Link></li>
                            <li><Link href="#" class="hover:opacity-80">Desktops</Link></li>
                            <li><Link href="#" class="hover:opacity-80">Gadgets</Link></li>
                            <li><Link href="#" class="hover:opacity-80">Accessories</Link></li>
                            <li><Link href="#" class="hover:opacity-80">Components</Link></li>
                        </ul>
                    </div>

                    <!-- Information -->
                    <div>
                        <h3 class="font-semibold ">Information</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="hover:opacity-80">Shipping Policy</a></li>
                            <li><a href="#" class="hover:opacity-80">Refund Policy</a></li>
                            <li><a href="#" class="hover:opacity-80">Warranty Policy</a></li>
                            <li><a href="#" class="hover:opacity-80">FAQs</a></li>
                            <li><a href="#" class="hover:opacity-80">Contact Us</a></li>
                        </ul>

                    </div>
                    <div class=" text-xl">
                        <p>+234(0)7061989638</p>
                        <p>support@shopinverse.com</p>
                    </div>
                </div>

                <!-- Newsletter Section -->
                <div>
                    <h3 class="font-semibold mb-4">
                        Be the first to know about exclusive laptop deals.
                    </h3>

                    <EmailComp />

                    <p class="mt-3 text-sm">
                        Subscribe now and get insider-only updates and offers.
                    </p>

                    <!-- Social Icons -->
                    <SocialIcons />
                </div>
            </div>

            <!-- Bottom Section -->
            <div class="mt-6 pt-6 border-t border-secondary">
                <div class="flex flex-col gap-4 text-sm">

                    <div>
                        © 2026 StechMax store.
                    </div>

                    <div class="flex flex-wrap gap-4 text-xs italic">
                        <Link href="#" class="hover:opacity-80">Refund policy</Link>
                        <Link href="#" class="hover:opacity-80">Privacy policy</Link>
                        <Link href="#" class="hover:opacity-80">Terms of service</Link>
                        <Link href="#" class="hover:opacity-80">Shipping policy</Link>
                        <Link href="#" class="hover:opacity-80">Contact information</Link>
                    </div>

                </div>
            </div>

        </div>
    </footer>
</template>

<script setup>
import AppLogo from "@/components/AppLogo.vue";
import {Link} from "@inertiajs/vue3";
import SocialIcons from "@/components/Welcome/FooterComps/SocialIcons.vue";
import EmailComp from "@/components/Welcome/FooterComps/EmailComp.vue";

</script>
