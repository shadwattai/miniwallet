<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Toolbar from 'primevue/toolbar';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Paginator from 'primevue/paginator';
import Skeleton from 'primevue/skeleton';
import IconField from 'primevue/iconfield';
import InputText from 'primevue/inputtext';
import Calendar from 'primevue/calendar';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Dialog from 'primevue/dialog';
import ScrollPanel from 'primevue/scrollpanel';
import Toast from 'primevue/toast';
import Fieldset from 'primevue/fieldset';
import { useToast } from 'primevue/usetoast';

interface AuditTrail {
    id: number;
    key: string;
    action: string;
    description: string;
    table_name: string;
    prev_data: any;
    new_data: any;
    action_time: string;
    user_ip: string;
    user_os: string;
    user_browser: string;
    user_device: string;
    created_by: string;
    created_at: string;
    user_name: string;
    user_email: string;
}

interface AuditStats {
    total_actions: number;
    unique_users: number;
    creates: number;
    reads: number;
    updates: number;
    deletes: number;
    logins: number;
    logouts: number;
}

const toast = useToast();

const loading = ref(true);
const auditTrails = ref<AuditTrail[]>([]);
const stats = ref<AuditStats | null>(null);
const totalRecords = ref(0);
const currentPage = ref(0);
const perPage = ref(15);

// Filters
const searchTerm = ref('');
const dateRange = ref<Date[] | null>(null);
const actionType = ref<string | null>(null);

// Details dialog
const showDetailsDialog = ref(false);
const selectedAuditTrail = ref<AuditTrail | null>(null);

const actionTypes = [
    { label: 'All Actions', value: null },
    { label: 'Create', value: 'create' },
    { label: 'Read', value: 'read' },
    { label: 'Update', value: 'update' },
    { label: 'Delete', value: 'delete' },
    { label: 'Login', value: 'login' },
    { label: 'Logout', value: 'logout' },
    { label: 'Approve', value: 'approve' },
    { label: 'Decline', value: 'decline' },
];

// Computed properties
const dateFrom = computed(() => {
    return dateRange.value?.[0] ? dateRange.value[0].toISOString().split('T')[0] : null;
});

const dateTo = computed(() => {
    return dateRange.value?.[1] ? dateRange.value[1].toISOString().split('T')[0] : null;
});

// Debounced search
let searchTimeout: number;
const debouncedSearch = ref('');

watch(searchTerm, (newValue) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        debouncedSearch.value = newValue;
        currentPage.value = 0;
        loadAuditTrails();
    }, 500);
});

// Watch filters
watch([actionType, dateFrom, dateTo], () => {
    currentPage.value = 0;
    loadAuditTrails();
});

// Load audit trails
const loadAuditTrails = async () => {
    try {
        loading.value = true;

        const params = new URLSearchParams();
        if (debouncedSearch.value) params.append('search', debouncedSearch.value);
        if (actionType.value) params.append('action', actionType.value);
        if (dateFrom.value) params.append('date_from', dateFrom.value);
        if (dateTo.value) params.append('date_to', dateTo.value);
        params.append('page', (currentPage.value + 1).toString());
        params.append('per_page', perPage.value.toString());

        const response = await fetch(`/miniwallet/settings/audit?${params.toString()}`);
        const data = await response.json();

        if (response.ok) {
            auditTrails.value = data.auditTrails.data;
            totalRecords.value = data.auditTrails.total;
            stats.value = data.stats;
        } else {
            throw new Error(data.message || 'Failed to load audit trails');
        }
    } catch (error) {
        console.error('Error loading audit trails:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load audit trails'
        });
    } finally {
        loading.value = false;
    }
};

// Pagination
const onPageChange = (event: any) => {
    currentPage.value = event.page;
    loadAuditTrails();
};

// Action severity mapping
const getActionSeverity = (action: string) => {
    const severityMap: Record<string, string> = {
        create: 'success',
        read: 'info',
        update: 'warn',
        delete: 'danger',
        login: 'success',
        logout: 'secondary',
        approve: 'success',
        decline: 'danger'
    };
    return severityMap[action] || 'info';
};

// Format date
const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString();
};

// Show details
const showDetails = (auditTrail: AuditTrail) => {
    selectedAuditTrail.value = auditTrail;
    showDetailsDialog.value = true;
};

// Clear filters
const clearFilters = () => {
    searchTerm.value = '';
    debouncedSearch.value = '';
    dateRange.value = null;
    actionType.value = null;
    currentPage.value = 0;
    loadAuditTrails();
};

// Load data on mount
onMounted(() => {
    loadAuditTrails();
});
</script>

<template>
    <Toast />

    <div>
        <!-- Statistics Cards -->
        <div class="grid grid-cols-4 gap-4 mb-4" v-if="stats">
            <Card class="text-center border-l-4 border-blue-500">
                <template #content>
                    <div class="text-2xl font-bold text-blue-600">{{ stats.total_actions.toLocaleString() }}</div>
                    <div class="text-sm text-gray-600">Total Actions</div>
                </template>
            </Card>
            <Card class="text-center border-l-4 border-green-500">
                <template #content>
                    <div class="text-2xl font-bold text-green-600">{{ stats.unique_users }}</div>
                    <div class="text-sm text-gray-600">Active Users</div>
                </template>
            </Card>
            <Card class="text-center border-l-4 border-orange-500">
                <template #content>
                    <div class="text-2xl font-bold text-orange-600">{{ (stats.creates + stats.updates +
                        stats.deletes).toLocaleString() }}</div>
                    <div class="text-sm text-gray-600">Data Changes</div>
                </template>
            </Card>
            <Card class="text-center border-l-4 border-purple-500">
                <template #content>
                    <div class="text-2xl font-bold text-purple-600">{{ (stats.logins + stats.logouts).toLocaleString()
                    }}</div>
                    <div class="text-sm text-gray-600">Login Sessions</div>
                </template>
            </Card>
        </div>

        <!-- Filters Toolbar -->
        <Toolbar class="mb-4" :style="{ justifyContent: 'space-between', borderRadius: '0px', marginTop: '-5px' }">
            <template #start>
                <div class="flex gap-2 flex-wrap">
                    <IconField>
                        <InputText v-model="searchTerm" placeholder="Search audit logs..." class="w-120" />
                    </IconField>
                    <Calendar v-model="dateRange" selectionMode="range" placeholder="Date Range"
                        dateFormat="yy-mm-dd" />
                    <Select v-model="actionType" :options="actionTypes" optionLabel="label" optionValue="value"
                        placeholder="Action Type" class="w-48" />
                    <Button label="Clear" icon="pi pi-times" severity="secondary" outlined @click="clearFilters"
                        size="small" />
                </div>
            </template>
        </Toolbar>

        <!-- Loading Skeleton -->
        <Card v-if="loading">
            <template #content>
                <div v-for="i in 5" :key="i" class="flex items-center gap-4 p-4 border-b">
                    <Skeleton width="100px" height="30px"></Skeleton>
                    <Skeleton width="200px" height="20px"></Skeleton>
                    <Skeleton width="150px" height="20px"></Skeleton>
                    <Skeleton width="100px" height="20px"></Skeleton>
                </div>
            </template>
        </Card>

        <!-- Audit Trails Table -->
        <Card v-else>
            <template #content>
                <DataTable :value="auditTrails" stripedRows showGridlines :loading="loading" class="p-datatable-sm">
                    <Column field="action_time" header="Time" style="width: 180px">
                        <template #body="{ data }">
                            <span class="text-sm">{{ formatDate(data.action_time) }}</span>
                        </template>
                    </Column>

                    <Column field="action" header="Action" style="width: 100px">
                        <template #body="{ data }">
                            <Tag :value="data.action.toUpperCase()" :severity="getActionSeverity(data.action)"
                                class="text-xs" />
                        </template>
                    </Column>

                    <Column field="table_name" header="Table" style="width: 120px">
                        <template #body="{ data }">
                            <Badge :value="data.table_name || 'system'" severity="info" class="text-xs" />
                        </template>
                    </Column>

                    <Column field="description" header="Description" style="width: auto">
                        <template #body="{ data }">
                            <span class="text-sm">{{ data.description || 'No description' }}</span>
                        </template>
                    </Column>

                    <Column field="user_ip" header="IP Address" style="width: 130px">
                        <template #body="{ data }">
                            <span class="text-xs font-mono">{{ data.user_ip || 'Unknown' }}</span>
                        </template>
                    </Column>

                    <Column field="created_by" header="User" style="width: 150px">
                        <template #body="{ data }">
                            <div class="text-xs">
                                <div class="font-semibold">{{ data.user_name || 'Unknown User' }}</div>
                                <!-- <div class="font-mono text-gray-500">{{ data.created_by?.substring(0, 8) || 'System' }}...</div> -->
                            </div>
                        </template>
                    </Column>

                    <Column header="Actions" style="width: 100px">
                        <template #body="{ data }">
                            <Button icon="pi pi-eye" severity="info" text size="small" @click="showDetails(data)"
                                v-tooltip="'View Details'" />
                        </template>
                    </Column>
                </DataTable>

                <!-- Pagination -->
                <Paginator v-if="totalRecords > perPage" :rows="perPage" :totalRecords="totalRecords"
                    :first="currentPage * perPage" @page="onPageChange" class="mt-4" />

                <!-- No Data -->
                <div v-if="auditTrails.length === 0 && !loading" class="text-center py-8 text-gray-500">
                    <i class="pi pi-history text-4xl mb-2 text-gray-300"></i>
                    <p>No audit trails found</p>
                </div>
            </template>
        </Card>

        <!-- Details Dialog -->
        <Dialog v-model:visible="showDetailsDialog" modal header="Audit Trail Details" class="w-[800px]">
            <Fieldset legend="Audit Record Information" v-if="selectedAuditTrail">
                <div class="space-y-4">
                    <!-- Basic Info -->
                    <Fieldset legend="Basic Information" class="mb-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Action</label>
                                <Tag :value="selectedAuditTrail.action.toUpperCase()"
                                    :severity="getActionSeverity(selectedAuditTrail.action)" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Time</label>
                                <span class="text-sm">{{ formatDate(selectedAuditTrail.action_time) }}</span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Table</label>
                                <Badge :value="selectedAuditTrail.table_name || 'system'" severity="info" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">IP Address</label>
                                <span class="text-sm font-mono">{{ selectedAuditTrail.user_ip || 'Unknown' }}</span>
                            </div>
                        </div>
                    </Fieldset>

                    <!-- Description -->
                    <Fieldset legend="Description" class="mb-4">
                        <p class="text-sm bg-gray-50 p-2 rounded">{{ selectedAuditTrail.description || 'No description'
                        }}</p>
                    </Fieldset>

                    <!-- User Details -->
                    <Fieldset legend="User Information" class="mb-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">User</label>
                                <div class="text-sm">
                                    <div class="font-semibold">{{ selectedAuditTrail.user_name || 'Unknown User' }}
                                    </div>
                                    <div class="text-xs font-mono text-gray-500">{{ selectedAuditTrail.created_by ||
                                        'System' }}
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">User Agent</label>
                                <span class="text-xs">{{ selectedAuditTrail.user_os || 'Unknown' }}</span>
                            </div>
                        </div>
                    </Fieldset>

                    <!-- Data Changes -->
                    <Fieldset legend="Data Changes" v-if="selectedAuditTrail.prev_data || selectedAuditTrail.new_data">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Previous Data -->
                            <div>
                                <label class="block text-sm font-medium mb-2">Previous Data</label>
                                <pre class="text-xs overflow-auto max-h-64 bg-gray-50 p-3 rounded border whitespace-pre-wrap break-words w-full">{{ selectedAuditTrail.prev_data ? JSON.stringify(selectedAuditTrail.prev_data, null, 2) : 'No previous data' }}</pre>
                            </div>
                            <!-- New Data -->
                            <div>
                                <label class="block text-sm font-medium mb-2">New Data</label>
                                <pre class="text-xs overflow-auto max-h-64 bg-gray-50 p-3 rounded border whitespace-pre-wrap break-words w-full">{{ selectedAuditTrail.new_data ? JSON.stringify(selectedAuditTrail.new_data, null, 2) : 'No new data' }}</pre>
                            </div>
                        </div>
                    </Fieldset>
                </div>
            </Fieldset>
        </Dialog>
    </div>
</template>