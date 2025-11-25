<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import Fieldset from 'primevue/fieldset';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import TabPanels from 'primevue/tabpanels';
import Tab from 'primevue/tab';
import TabPanel from 'primevue/tabpanel';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

import Users from './Users.vue';
import Banks from './Banks.vue';
import Audit from './Audit.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: dashboard().url,
    }, {
        title: 'System settings',
        href: '',
    },
];

interface Bank {
    id: number;
    key: string;
    bank_name: string;
    bank_code: string;
    bank_logo: string;
    swift_code: string;
    country_code: string;
    bank_type: string;
    address: string;
    phone: string;
    email: string;
    website: string;
    is_active: boolean;
    supports_transfers: boolean;
    supports_deposits: boolean;
    supports_withdrawals: boolean;
    min_balance: number;
    max_balance: number;
    daily_transfer_limit: number;
    supported_currencies: string;
    notes: string;
    created_at: string;
}

interface User {
    id: number;
    key: string;
    name: string;
    email: string;
    phone: string;
    status: string;
    creator: string;
    role: string;
    created_at: string;
    avatar?: string;
}

const props = defineProps<{
    User?: any;
    users?: User[];
    banks?: Bank[];
}>();

const loading = ref(true);

// Handle banks update
const handleBanksUpdated = () => {
    // Refresh the page to get updated banks data
    router.reload({ only: ['banks'] });
};

</script>

<template> 
    <AppLayout :breadcrumbs="breadcrumbs" :User="props.User">
        <div class="card">
            <Tabs value="0">
                <TabList>
                    <Tab value="0">Users</Tab>
                    <Tab value="1">Banks</Tab>
                    <Tab value="2">Audit trail</Tab>
                </TabList>

                <TabPanels>
                    <TabPanel value="0">
                        <Fieldset legend="Users Management">
                            <Users :users="props.users" />
                        </Fieldset>
                    </TabPanel>
                    
                    <TabPanel value="1">
                        <Fieldset legend="Banks Management">
                            <Banks 
                                :banks="props.banks" 
                                @banks-updated="handleBanksUpdated"
                            />
                        </Fieldset>
                    </TabPanel>
                    
                    <TabPanel value="2">
                        <Fieldset legend="Audit Trail">
                            <Audit />
                        </Fieldset>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div> 
    </AppLayout>
</template>

<style>
.tooltip-sm .p-tooltip-text {
    font-size: 0.75rem !important;
}
</style>
