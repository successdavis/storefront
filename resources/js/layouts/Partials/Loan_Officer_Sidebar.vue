<template>

    <aside class="h-full">
        <div class="overflow-y-auto py-4 px-3 bg-gray-800 text-gray-300 h-full rounded">
            <ul class="space-y-2">
                <li>
                    <Link href="/home" class="flex items-center p-2 text-base font-normal rounded-lg hover:bg-gray-100">
                        <svg aria-hidden="true" class="w-6 h-6 text-gray-300 transition duration-75 group-hover" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path><path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path></svg>
                        <span class="ml-3 text-white">Dashboard</span>
                    </Link>
                </li>

                <li>
                    <button type="button" class="flex items-center p-2 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-100" aria-controls="dropdown-example" data-collapse-toggle="dropdown-example">
                        <svg aria-hidden="true" class="flex-shrink-0 w-6 h-6 text-gray-300 transition duration-75 group-hover" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"></path></svg>
                        <span class="flex-1 text-gray-300 ml-3 text-left whitespace-nowrap" sidebar-toggle-item>Members</span>
                        <svg sidebar-toggle-item class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                    </button>

                    <!--              Add hidden to class to hide sub menu-->
                    <ul id="dropdown-example" class=" py-2 space-y-2">
                        <li>
                            <Link href="/members" class="flex items-center p-2 pl-11 w-full text-base font-normal text-gray-300 rounded-lg transition duration-75 group hover:bg-gray-100">Find User</Link>
                        </li>
                        <li>
                            <Link href="/members/register" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-100">Register User</Link>
                        </li>
                        <!--                  <li>-->
                        <!--                     <a href="" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Invoice</a>-->
                        <!--                  </li>-->
                    </ul>
                </li>

                <li>
                    <button type="button" class="flex items-center p-2 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-100" aria-controls="dropdown-example" data-collapse-toggle="dropdown-example">
                        <svg aria-hidden="true" class="flex-shrink-0 w-6 h-6 text-white transition duration-75 group-hover" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"></path></svg>
                        <span class="flex-1 ml-3 text-left whitespace-nowrap" sidebar-toggle-item>Loans</span>
                        <svg sidebar-toggle-item class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                    </button>

                    <!--              Add hidden to class to hide sub menu-->
                    <ul id="dropdown-example" class=" py-2 space-y-2">
                        <li>
                            <Link href="/loans/create" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-100">Loan Application</Link>
                        </li>
                        <li>
                            <Link href="/loans/active-loans" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-100">Active Loans</Link>
                        </li>
                        <li>
                            <Link href="/loans/due-loans" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-100">Due Loans</Link>
                        </li>
                        <li>
                            <Link href="/loans/over-due" class="flex items-center p-2 pl-11 w-full text-base font-normal rounded-lg transition duration-75 group hover:bg-gray-100">OverDue Loans</Link>
                        </li>

                    </ul>
                </li>
                <!--         <li>-->
                <!--            <a href="#" class="flex items-center p-2 text-base font-normal rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">-->
                <!--               <svg aria-hidden="true" class="flex-shrink-0 w-6 h-6 text-white transition duration-75 dark:text-gray-400 group-hover dark:group-hover:text-white" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>-->
                <!--               <span class="flex-1 ml-3 whitespace-nowrap">Repayments</span>-->
                <!--            </a>-->
                <!--         </li>-->

                <li>
                    <Link href="/today-report" class="flex items-center p-2 text-base font-normal rounded-lg hover:bg-gray-100">
                        <svg aria-hidden="true" class="flex-shrink-0 w-6 h-6 text-white transition duration-75 group-hover" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"></path></svg>
                        <span class="flex-1 ml-3 whitespace-nowrap">Today Report</span>
                    </Link>
                </li>
                <li>
                    <Link href="/transfer-request" class="flex items-center p-2 text-base font-normal rounded-lg hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>

                        <span class="flex-1 ml-3 whitespace-nowrap">Transfer Request</span>
                    </Link>
                </li>

                <li>
                    <a href="/home" class="flex items-center p-2 text-base font-normal rounded-lg hover:bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        <span class="flex-1 ml-3 whitespace-nowrap">Exit Dashboard</span>
                    </a>
                </li>
            </ul>

        </div>
    </aside>

</template>

<script setup>
import { Link } from '@inertiajs/vue3'

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
