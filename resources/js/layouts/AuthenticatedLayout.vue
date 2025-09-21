<template>
    <div class="flex">
        <div class="w-72 sidebar fixed overflow-y-scroll h-full hidden md:block" aria-label="Sidebar">
            <!--            <Sidebar></Sidebar>-->
            <component :is="componentToRender"></component>
        </div>
        <div class="ml-72 py-4 px-3 w-full overflow-y-scroll main-content ">
            <Flash
                v-if="flash.success"
                :message="flash.success"
                type="success"
                @hide="clearFlash('success')"
            />
            <Flash
                v-if="flash.error"
                :message="flash.error"
                type="error"
                @hide="clearFlash('error')"
            />
            <Flash
                v-if="flash.message"
                :message="flash.message"
                type="info"
                @hide="clearFlash('info')"
            />
            <slot />
        </div>
    </div>
</template>

<script>
import Operation_Sidebar from "@/Layouts/Partials/Operation_Sidebar.vue";
import Loan_Officer_Sidebar from "@/Layouts/Partials/Loan_Officer_Sidebar.vue";
import Director_Sidebar from "@/Layouts/Partials/Director_Sidebar.vue";
import Treasurer_Sidebar from "@/Layouts/Partials/Treasurer_Sidebar.vue";
import Flash from '@/Components/FlashMessage.vue'
import {usePage} from '@inertiajs/vue3';

export default {
    components: {
        Loan_Officer_Sidebar,
        Operation_Sidebar,
        Director_Sidebar,
        Treasurer_Sidebar,
        Flash
    },
    data() {
        return {
            userRole: 'director'
            // userRole: this.$page.props.auth.role[0]
        }
    },

    methods: {
        clearFlash(type) {
            this.$page.props.flash[type] = null
        }
    },
    computed: {
        componentToRender() {
            switch (this.userRole) {
                case 'operations_manager':
                    return 'Operation_Sidebar';
                case 'loan_officer':
                    return 'Loan_Officer_Sidebar';
                case 'manager':
                    return 'manager_sidebar';
                case 'director':
                    return 'Director_Sidebar';
                case 'treasurer':
                    return 'Treasurer_Sidebar'
                case 'guest':
                    return 'GuestComponent';
                // add additional cases for other user roles
                default:
                    return 'GuestComponent'; // fallback component if role is not recognized
            }
        },

        flash() {
            return usePage().props.flash || {}
        }
    }
}
</script>

<style scoped>
@media print {
    .sidebar {
        display: none !important;
    }

    .main-content {
        width: 100% !important;
        margin: 0 !important;
    }
}
</style>
