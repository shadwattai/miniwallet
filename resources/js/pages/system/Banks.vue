<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputText from 'primevue/inputtext';
import Badge from 'primevue/badge';
import Tag from 'primevue/tag';
import { FilterMatchMode } from '@primevue/core/api';
import BanksForm from './BanksForm.vue';

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

const props = defineProps<{
    banks?: Bank[];
}>();

const emit = defineEmits<{
    'banks-updated': [];
}>();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const debouncedSearchTerm = ref('');
const showBankForm = ref(false);
const selectedBank = ref<Bank | null>(null);

// Debounce search input for better performance
let searchTimeout: number;
watch(() => filters.value.global.value, (newValue) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        debouncedSearchTerm.value = newValue || '';
    }, 300);
});

// Optimized computed property to filter banks based on debounced search
const filteredBanks = computed(() => {
    if (!props.banks) return [];

    const searchTerm = debouncedSearchTerm.value;
    if (!searchTerm) return props.banks;

    const lowerSearchTerm = searchTerm.toLowerCase();

    return props.banks.filter(bank =>
        bank.bank_name.toLowerCase().includes(lowerSearchTerm) ||
        bank.bank_code.toLowerCase().includes(lowerSearchTerm) ||
        bank.swift_code.toLowerCase().includes(lowerSearchTerm) ||
        bank.country_code.toLowerCase().includes(lowerSearchTerm) ||
        bank.bank_type.toLowerCase().includes(lowerSearchTerm)
    );
});

// Helper functions
const getBankTypeVariant = (type: string) => {
    return type === 'islamic' ? 'success' : 'info';
};

const getStatusSeverity = (isActive: boolean) => {
    return isActive ? 'success' : 'danger';
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'AED',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
};

const parseCurrencies = (currenciesJson: string) => {
    try {
        return JSON.parse(currenciesJson);
    } catch {
        return [];
    }
};

const openBankDetails = (bank: Bank) => {
    console.log('Opening bank details for:', bank.bank_name);
    // TODO: Implement bank details modal/drawer
};

const openAddBankDialog = () => {
    selectedBank.value = null;
    showBankForm.value = true;
};

const openEditBankDialog = (bank: Bank) => {
    selectedBank.value = bank;
    showBankForm.value = true;
};

const handleBankSaved = (bank: Bank) => {
    // Emit event to parent to refresh data
    emit('banks-updated');
    showBankForm.value = false;
};

const openWebsite = (url: string) => {
    window.open(url, '_blank');
};

// Remove unused loading ref
// const loading = ref(true);

</script>

<template>
    <div>
        <Toolbar class="mb-4" :style="{ justifyContent: 'space-between', borderRadius: '0px', marginTop: '-5px' }">
            <template #start>
                <IconField>
                    <InputText v-model="filters.global.value" placeholder="Search banks..." class="w-120" />
                </IconField>
            </template>
            <template #end>
                <Button label="ADD BANK" icon="pi pi-plus-circle" severity="info" @click="openAddBankDialog" />
            </template>
        </Toolbar>

        <!-- No Banks Message -->
        <div class="text-center py-12 text-gray-500" v-if="!props.banks || props.banks.length === 0">
            <i class="pi pi-building text-6xl mb-4 text-gray-300"></i>
            <h3 class="text-xl mb-2">No Banks Found</h3>
            <p class="mb-4">There are currently no banks configured in the system.</p>
            <p class="text-sm">Click the "ADD BANK" button to add your first bank.</p>
        </div>

        <!-- Banks Grid -->
        <div class="grid grid-cols-3 gap-4" style="margin-top: 12px; margin: 12px;"
            v-if="props.banks && props.banks.length > 0">
            <Card v-for="bank in filteredBanks" :key="bank.key"
                :style="{ 'box-shadow': '0 2px 12px 0 rgba(0, 0, 0, 0.1)', 'border-radius': '6px', 'overflow': 'hidden' }">
                <template #header>
                    <div class="bg-gradient-to-r from-blue-400 to-cyan-500 h-12 flex items-center justify-between px-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="pi pi-building text-white text-sm"></i>
                            </div>
                            <span class="text-white font-medium text-sm">{{ bank.bank_code }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button icon="pi pi-external-link" 
                                    severity="secondary" 
                                    rounded
                                    @click="openWebsite(bank.website)" 
                                    size="small"
                                    class="bg-white text-blue-500 hover:bg-gray-100"
                                    v-tooltip="'Visit Website'" />
                            <Button icon="pi pi-pencil" 
                                    severity="secondary" 
                                    rounded
                                    @click="openEditBankDialog(bank)" 
                                    size="small"
                                    class="bg-white text-blue-500 hover:bg-gray-100"
                                    v-tooltip="'Edit Bank'" />
                            <Tag :severity="getStatusSeverity(bank.is_active)"
                                :value="bank.is_active ? 'Active' : 'Inactive'" class="text-xs" />
                        </div>
                    </div>
                </template>

                <template #title>
                    <span class="font-semibold text-lg">{{ bank.bank_name }}</span>
                </template>

                <template #subtitle>
                    <div class="flex flex-wrap gap-1 mb-3">
                        <Badge :value="bank.bank_code" severity="info" class="text-xs" />
                        <Badge :value="bank.swift_code" severity="secondary" class="text-xs" />
                        <Tag :value="bank.bank_type" :severity="getBankTypeVariant(bank.bank_type)" class="text-xs" />
                    </div>
                </template>

                <template #content>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center">
                            <i class="pi pi-map-marker text-gray-400 mr-2"></i>
                            <span class="truncate">{{ bank.country_code }}</span>
                        </div>

                        <div class="flex items-center">
                            <i class="pi pi-phone text-gray-400 mr-2"></i>
                            <span class="truncate">{{ bank.phone }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-500">Min Balance:</span>
                                <div class="font-medium">{{ formatCurrency(bank.min_balance) }}</div>
                            </div>
                            <div>
                                <span class="text-gray-500">Daily Limit:</span>
                                <div class="font-medium">{{ formatCurrency(bank.daily_transfer_limit) }}</div>
                            </div>
                        </div>

                        <div>
                            <span class="text-gray-500 text-xs">Supported Currencies:</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <Badge v-for="currency in parseCurrencies(bank.supported_currencies)" :key="currency"
                                    :value="currency" severity="contrast" class="text-xs" />
                            </div>
                        </div>

                        <div class="flex gap-1 text-xs">
                            <Badge v-if="bank.supports_transfers" value="Transfers" severity="success" />
                            <Badge v-if="bank.supports_deposits" value="Deposits" severity="info" />
                            <Badge v-if="bank.supports_withdrawals" value="Withdrawals" severity="warn" />
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Show message when no results found -->
            <div v-if="filteredBanks.length === 0 && filters.global.value"
                class="col-span-3 text-center py-8 text-gray-500">
                No banks found matching "{{ filters.global.value }}"
            </div>
        </div>

        <!-- Banks Form Dialog -->
        <BanksForm 
            v-model:visible="showBankForm" 
            :bank="selectedBank"
            @bank-saved="handleBankSaved"
        />
    </div>
</template>