<script setup lang="ts">
import { ref } from 'vue';
const currentYear = new Date().getFullYear();


const props = defineProps({  
    logo: String, 
    version: String, 
    company: String,
    bns_status: String,
    const: Object,
}); 
 
const appConst = props.const ?? {};
const footerCompany = props.company || ''; 
const footerVersion = props.version || '';
const companyStatus = props.bns_status || ''; 
const footerCompanyLogo = ref(appConst.bns_logo || appConst.business?.logo_path || '/avatars/empty-user.jpg');

</script>

<template> 
    <footer
        class="fixed bottom-0 left-0 right-0 h-10 bg-gray-100 text-gray-900 flex items-center justify-between px-6 text-xs z-10"
        style="border: 1px solid #d1d5db;">
        <span v-if="footerCompanyLogo">
            <img :src="footerCompanyLogo" alt="Company Logo"
                class="h-6 w-6 object-cover rounded-full border border-gray-300" />
        </span>
        &nbsp;
        <span class="font-semibold text-gray-600">
            {{ footerCompany }}  
            <small class="uppercase" :class="{
                'text-green-600': (companyStatus)?.toLowerCase() === 'active',
                'text-red-600': (companyStatus)?.toLowerCase() !== 'active'
            }">
                ({{ companyStatus }} business)
            </small>
        </span>
        &nbsp;
        <span class="text-center flex-1">
            COPYRIGHT &copy; 2015 - {{ currentYear }}. ALL RIGHTS RESERVED.
            <a href="https://workforce.co.tz" class="text-green-600" target="_blank">WORKFORCE DYNAMICS </a>
            IS A PRODUCT OF
            <a href="https://amatics.co.tz" class="text-green-600" target="_blank">AMATICS TECHNOLOGIES</a>.
        </span>
        <span class="text-right font-semibold uppercase">{{ footerVersion }}</span>
    </footer>

</template>
