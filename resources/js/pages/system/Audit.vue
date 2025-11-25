<script setup lang="ts">
import { ref, onMounted } from 'vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Toolbar from 'primevue/toolbar';
import Skeleton from 'primevue/skeleton';
import IconField from 'primevue/iconfield';
import InputText from 'primevue/inputtext';
import Calendar from 'primevue/calendar';
import Select from 'primevue/select';

const loading = ref(true);
const dateRange = ref();
const actionType = ref();

const actionTypes = [
    { label: 'All Actions', value: null },
    { label: 'User Login', value: 'login' },
    { label: 'User Registration', value: 'registration' },
    { label: 'Transaction', value: 'transaction' },
    { label: 'Settings Change', value: 'settings' },
];

// Simulate initial loading
onMounted(() => {
    setTimeout(() => {
        loading.value = false;
    }, 500);
});
</script>

<template>
    <div>
        <Toolbar class="mb-4" :style="{ justifyContent: 'space-between', borderRadius: '0px', marginTop: '-5px' }">
            <template #start>
                <div class="flex gap-2">
                    <IconField>
                        <InputText placeholder="Search audit logs..." class="w-120" disabled />
                    </IconField>
                    <Calendar v-model="dateRange" selectionMode="range" placeholder="Date Range" disabled />
                    <Select v-model="actionType" :options="actionTypes" 
                        optionLabel="label" optionValue="value" 
                        placeholder="Action Type" class="w-48" disabled />
                </div>
            </template>
            <template #end>
                <Button label="EXPORT" icon="pi pi-download" severity="secondary" outlined disabled />
            </template>
        </Toolbar>

        <div class="rounded border border-surface-200 dark:border-surface-700 p-6 bg-surface-0 dark:bg-surface-900"
            v-if="loading">
            <div v-for="i in [1, 2, 3, 4, 5]" :key="i">
                <Skeleton width="100%" height="60px" class="mb-2"></Skeleton>
            </div>
        </div>

        <div v-else class="text-center py-12 text-gray-500">
            <i class="pi pi-history text-6xl mb-4 text-gray-300"></i>
            <h3 class="text-xl mb-2">Audit Trail</h3>
            <p class="mb-4">Comprehensive system activity tracking will be implemented here.</p>
            <p class="text-sm">Features will include:</p>
            <ul class="text-sm mt-2 text-left max-w-md mx-auto">
                <li>• User activity logging</li>
                <li>• Transaction audit trails</li>
                <li>• System configuration changes</li>
                <li>• Security event monitoring</li>
                <li>• Detailed reporting and exports</li>
            </ul>
        </div>
    </div>
</template>