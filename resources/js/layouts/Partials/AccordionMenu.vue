<template>
    <li>
        <button type="button"
                @click="active = !active"
                class="flex items-center p-2 w-full text-base font-normal rounded-lg transition duration-75 group hover:text-gray-100 hover:bg-gray-700"
                aria-controls="dropdown-example" data-collapse-toggle="dropdown-example">

         <slot name="icon"></slot>

            <span class="flex-1 ml-3 text-left whitespace-nowrap" sidebar-toggle-item>{{ name }}</span>
            <svg sidebar-toggle-item class="w-6 h-6 transition-all" :class="active ? 'close-accordion transition-all' : '' " fill="currentColor" viewBox="0 0 20 20"
                 xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd"
                      d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                      clip-rule="evenodd"></path>
            </svg>
        </button>

        <!--              Add hidden to class to hide sub menu-->
        <ul id="dropdown-example" class=" py-2 space-y-2 transition-all" :class="active ? '' : 'hidden transition'">
            <slot></slot>
        </ul>
    </li>
</template>
<script>
export default {
    name: 'AccordionMenu',
    props: ['name'],

    data () {
        return {
            active: false
        }
    }
}
</script>
<style>
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

.close-accordion {
    transform: rotate(180deg);
    transition: all .7s;
}

</style>
