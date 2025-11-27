<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar'; 
import Badge from 'primevue/badge';
import Card from 'primevue/card';
import Dialog from 'primevue/dialog';
import {
    Search,
    Filter,
    Download,
    ArrowUpRight,
    ArrowDownRight,
    ArrowRightLeft,
    Plus,
    Minus,
    DollarSign, 
    Calendar as CalendarIcon,
    TrendingUp,
    TrendingDown, 
    MoreHorizontal,
    Receipt,
    User, 
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Transactions',
        href: '/transactions',
    },
];

interface Transaction {
    key: string;
    ref_number: string;
    sender_acct_key: string;
    receiver_acct_key: string;
    description: string;
    type: 'deposit' | 'withdrawal' | 'transfer' | 'topup';
    amount: number;
    commission_fee: number;
    status: 'pending' | 'completed' | 'failed' | 'cancelled';
    created_at: string;
    updated_at: string;
    sender_account_name?: string;
    receiver_account_name?: string;
    sender_user_name?: string;
    receiver_user_name?: string;
    currency?: string;
}

const props = defineProps<{
    User?: any;
    transactions?: Transaction[];
}>();

// State management
const searchQuery = ref('');
const selectedType = ref('');
const selectedStatus = ref('');
const selectedDateRange = ref([]);
const showFilters = ref(false);
const showDetailsDialog = ref(false);
const selectedTransaction = ref<Transaction | null>(null);

// Use backend transactions only
const transactionsData = computed(() => props.transactions || []);

// Filter options
const transactionTypes = [
    { label: 'All Types', value: '' },
    { label: 'Deposit', value: 'deposit' },
    { label: 'Withdrawal', value: 'withdrawal' },
    { label: 'Transfer', value: 'transfer' },
    { label: 'Top Up', value: 'topup' }
];

const transactionStatuses = [
    { label: 'All Statuses', value: '' },
    { label: 'Completed', value: 'completed' },
    { label: 'Pending', value: 'pending' },
    { label: 'Failed', value: 'failed' },
    { label: 'Cancelled', value: 'cancelled' }
];

// Computed properties
const filteredTransactions = computed(() => {
    let filtered = transactionsData.value;

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(transaction => 
            transaction.ref_number.toLowerCase().includes(query) ||
            transaction.description.toLowerCase().includes(query) ||
            transaction.sender_user_name?.toLowerCase().includes(query) ||
            transaction.receiver_user_name?.toLowerCase().includes(query) ||
            transaction.sender_account_name?.toLowerCase().includes(query) ||
            transaction.receiver_account_name?.toLowerCase().includes(query)
        );
    }

    // Type filter
    if (selectedType.value) {
        filtered = filtered.filter(transaction => transaction.type === selectedType.value);
    }

    // Status filter
    if (selectedStatus.value) {
        filtered = filtered.filter(transaction => transaction.status === selectedStatus.value);
    }

    // Date range filter
    if (selectedDateRange.value && selectedDateRange.value.length === 2) {
        const dateRange = selectedDateRange.value as unknown as [Date, Date];
        const [startDate, endDate] = dateRange;
        filtered = filtered.filter(transaction => {
            const transactionDate = new Date(transaction.created_at);
            return transactionDate >= startDate && transactionDate <= endDate;
        });
    }

    return filtered.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
});

const transactionStats = computed(() => {
    const transactions = transactionsData.value;
    const today = new Date();
    const thisMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    
    const thisMonthTransactions = transactions.filter(t => 
        new Date(t.created_at) >= thisMonth && t.status === 'completed'
    );
    
    const totalIncome = thisMonthTransactions
        .filter(t => t.type === 'deposit')
        .reduce((sum, t) => sum + (Number(t.amount) || 0), 0);
        
    const totalExpense = thisMonthTransactions
        .filter(t => t.type === 'withdrawal' || t.type === 'transfer')
        .reduce((sum, t) => sum + (Number(t.amount) || 0) + (Number(t.commission_fee) || 0), 0);
        
    const totalFees = thisMonthTransactions
        .reduce((sum, t) => sum + (Number(t.commission_fee) || 0), 0);

    return {
        totalTransactions: transactions.length,
        thisMonthCount: thisMonthTransactions.length,
        totalIncome: totalIncome || 0,
        totalExpense: totalExpense || 0,
        totalFees: totalFees || 0,
        netAmount: (totalIncome || 0) - (totalExpense || 0)
    };
});

// Helper functions
const getTransactionIcon = (type: string) => {
    switch (type) {
        case 'deposit': return Plus;
        case 'withdrawal': return Minus;
        case 'transfer': return ArrowRightLeft;
        case 'topup': return ArrowUpRight;
        default: return DollarSign;
    }
};

const getTransactionColor = (type: string) => {
    switch (type) {
        case 'deposit': return 'text-green-600 bg-green-100';
        case 'withdrawal': return 'text-red-600 bg-red-100';
        case 'transfer': return 'text-blue-600 bg-blue-100';
        case 'topup': return 'text-purple-600 bg-purple-100';
        default: return 'text-gray-600 bg-gray-100';
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed': return 'success';
        case 'pending': return 'warning';
        case 'failed': return 'danger';
        case 'cancelled': return 'secondary';
        default: return 'info';
    }
};

const formatAmount = (amount: number, currency: string = 'AED') => {
    // Handle NaN, null, undefined, or invalid numbers
    if (isNaN(amount) || amount === null || amount === undefined) {
        amount = 0;
    }
    
    return `${currency} ${Number(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedType.value = '';
    selectedStatus.value = '';
    selectedDateRange.value = [];
};

function openTransactionDetails(transaction: Transaction) {
    selectedTransaction.value = transaction;
    showDetailsDialog.value = true;
}
function closeTransactionDetails() {
    showDetailsDialog.value = false;
    selectedTransaction.value = null;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :User="props.User">
        <Head title="Transactions" />

        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6">
            <!-- Header Section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Transaction History</h1>
                    <p class="mt-1 text-sm text-gray-500">Track all your financial activities and transactions</p>
                </div>
                <div class="flex gap-3"> 
                    <Button 
                        @click="showFilters = !showFilters"
                        :class="showFilters ? 'bg-blue-500 text-white' : ''"
                        outlined
                        class="flex items-center gap-2"
                    >
                        <Filter class="w-4 h-4" />
                        Filters
                    </Button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Transactions -->
                <Card class="shadow-sm border border-gray-200">
                    <template #content>
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-blue-100 rounded-full">
                                <Receipt class="w-6 h-6 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Transactions</p>
                                <p class="text-2xl font-bold text-gray-900">{{ transactionStats.totalTransactions }}</p>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- This Month Income -->
                <Card class="shadow-sm border border-gray-200">
                    <template #content>
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-green-100 rounded-full">
                                <TrendingUp class="w-6 h-6 text-green-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">This Month Income</p>
                                <p class="text-2xl font-bold text-green-600">{{ formatAmount(transactionStats.totalIncome) }}</p>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- This Month Expense -->
                <Card class="shadow-sm border border-gray-200">
                    <template #content>
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-red-100 rounded-full">
                                <TrendingDown class="w-6 h-6 text-red-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">This Month Expense</p>
                                <p class="text-2xl font-bold text-red-600">{{ formatAmount(transactionStats.totalExpense) }}</p>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Total Fees -->
                <Card class="shadow-sm border border-gray-200">
                    <template #content>
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-orange-100 rounded-full">
                                <DollarSign class="w-6 h-6 text-orange-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Fees</p>
                                <p class="text-2xl font-bold text-orange-600">{{ formatAmount(transactionStats.totalFees) }}</p>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Search and Filters -->
            <div class="space-y-4">
                <!-- Search Bar -->
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-5 w-5 text-gray-400" />
                    </div>
                    <InputText
                        v-model="searchQuery"
                        placeholder="Search by reference number, description, or user name..."
                        class="pl-10 w-full"
                    />
                </div>

                <!-- Filter Panel -->
                <div v-if="showFilters" class="bg-gray-50 border border-gray-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <!-- Type Filter -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700">Transaction Type</label>
                        <Dropdown
                            v-model="selectedType"
                            :options="transactionTypes"
                            option-label="label"
                            option-value="value"
                            placeholder="Select type"
                            class="w-full"
                        />
                    </div>

                    <!-- Status Filter -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700">Status</label>
                        <Dropdown
                            v-model="selectedStatus"
                            :options="transactionStatuses"
                            option-label="label"
                            option-value="value"
                            placeholder="Select status"
                            class="w-full"
                        />
                    </div>

                    <!-- Date Range -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-700">Date Range</label>
                        <Calendar
                            v-model="selectedDateRange"
                            selection-mode="range"
                            :manual-input="false"
                            placeholder="Select dates"
                            class="w-full"
                        />
                    </div>

                    <!-- Clear Filters -->
                    <div class="flex items-end">
                        <Button
                            @click="clearFilters"
                            outlined
                            class="w-full text-gray-600 border-gray-300 hover:bg-gray-50"
                        >
                            Clear Filters
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Transactions List -->
            <Card class="shadow-sm border border-gray-200 flex-1">
                <template #content>
                    <div v-if="filteredTransactions.length === 0" class="text-center py-12">
                        <div class="mx-auto h-12 w-12 text-gray-400">
                            <Receipt class="h-12 w-12" />
                        </div>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No transactions found</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ searchQuery || selectedType || selectedStatus ? 'Try adjusting your filters or search terms.' : 'Your transactions will appear here once you start making them.' }}
                        </p>
                    </div>

                    <!-- Transaction Items -->
                    <div v-else class="space-y-4">
                        <div
                            v-for="transaction in filteredTransactions"
                            :key="transaction.key"
                            class="flex items-center justify-between p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow duration-200"
                        >
                            <!-- Left Side: Icon, Type & Description -->
                            <div class="flex items-center space-x-4">
                                <!-- Transaction Icon -->
                                <div :class="`p-3 rounded-full ${getTransactionColor(transaction.type)}`">
                                    <component :is="getTransactionIcon(transaction.type)" class="w-5 h-5" />
                                </div>

                                <!-- Transaction Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-medium text-gray-900 uppercase ">
                                            {{ transaction.type }}
                                        </h4>
                                    </div>
                                    <p class="text-sm text-gray-600 truncate">{{ transaction.description }}</p>
                                    <div class="flex items-center gap-4 mt-1">
                                        <span class="text-xs text-gray-500 font-mono">{{ transaction.ref_number }}</span>
                                        <span class="text-xs text-gray-500 uppercase">
                                            {{ formatDate(transaction.created_at) }}
                                            - {{transaction.status.toUpperCase()}}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Middle: Account Information -->
                            <div class="hidden lg:flex flex-col items-center text-center min-w-0 flex-1">
                                <div class="flex items-center gap-2 text-sm">
                                    <!-- From Account -->
                                    <div class="flex items-center gap-1 text-gray-600">
                                        <User class="w-3 h-3" />
                                        <span class="truncate max-w-24">{{ transaction.sender_user_name }}</span>
                                    </div>
                                    
                                    <!-- Arrow -->
                                    <ArrowDownRight class="w-4 h-4 text-gray-400" />
                                    
                                    <!-- To Account -->
                                    <div class="flex items-center gap-1 text-gray-600">
                                        <User class="w-3 h-3" />
                                        <span class="truncate max-w-24">{{ transaction.receiver_user_name }}</span>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">
                                    {{ transaction.sender_account_name }} → {{ transaction.receiver_account_name }}
                                </div>
                            </div>

                            <!-- Right Side: Amount & Actions -->
                            <div class="flex items-center gap-3">
                                <!-- Status Badge -->
                                <!-- <Badge 
                                    :value="transaction.status.toUpperCase()" 
                                    :severity="getStatusColor(transaction.status)"
                                    class="text-xs"
                                /> -->
                                
                                <!-- Amount -->
                                <div class="text-right">
                                    <div class="text-sm font-semibold" 
                                         :class="{
                                             'text-green-600': transaction.type === 'deposit',
                                             'text-red-600': transaction.type === 'withdrawal' || transaction.type === 'transfer',
                                             'text-blue-600': transaction.type === 'topup'
                                         }">
                                        {{ transaction.type === 'deposit' || transaction.type === 'topup' ? '+' : '-' }}{{ formatAmount(transaction.amount, transaction.currency) }}
                                    </div>
                                    <div v-if="transaction.commission_fee > 0" class="text-xs text-orange-600">
                                        Fee: {{ formatAmount(transaction.commission_fee, transaction.currency) }}
                                    </div>
                                </div>

                                <!-- Actions -->
                                <Button
                                    outlined
                                    size="small"
                                    class="text-gray-400 border-gray-200 hover:bg-gray-50"
                                    @click="openTransactionDetails(transaction)"
                                >
                                    <MoreHorizontal class="w-4 h-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Transaction Details Dialog -->
            <Dialog v-model:visible="showDetailsDialog" modal header="Transaction Details" class="w-[600px]" @hide="closeTransactionDetails">
                <fieldset class="border border-gray-200 rounded-lg p-6">
                    <legend class="px-2 text-base font-semibold text-gray-700">Transaction Details</legend>
                    <div v-if="selectedTransaction" class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div :class="`p-3 rounded-full ${getTransactionColor(selectedTransaction.type)}`">
                                <component :is="getTransactionIcon(selectedTransaction.type)" class="w-6 h-6" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold capitalize">{{ selectedTransaction.type }}</h2>
                                <span class="text-xs text-gray-500">{{ formatDate(selectedTransaction.created_at) }}</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Reference</span>
                                <span class="font-mono">{{ selectedTransaction.ref_number }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Status</span>
                                <Badge :value="selectedTransaction.status.toUpperCase()" :severity="getStatusColor(selectedTransaction.status)" />
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Amount</span>
                                <span :class="{
                                    'text-green-600': selectedTransaction.type === 'deposit',
                                    'text-red-600': selectedTransaction.type === 'withdrawal' || selectedTransaction.type === 'transfer',
                                    'text-blue-600': selectedTransaction.type === 'topup'
                                }">
                                    {{ selectedTransaction.type === 'deposit' || selectedTransaction.type === 'topup' ? '+' : '-' }}{{ formatAmount(selectedTransaction.amount, selectedTransaction.currency) }}
                                </span>
                            </div>
                            <div v-if="selectedTransaction.commission_fee > 0" class="flex justify-between text-sm">
                                <span class="text-gray-500">Fee</span>
                                <span class="text-orange-600">{{ formatAmount(selectedTransaction.commission_fee, selectedTransaction.currency) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">From</span>
                                <span>{{ selectedTransaction.sender_user_name }} ({{ selectedTransaction.sender_account_name }})</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">To</span>
                                <span>{{ selectedTransaction.receiver_user_name }} ({{ selectedTransaction.receiver_account_name }})</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Description</span>
                                <span class="text-right">{{ selectedTransaction.description }}</span>
                            </div>
                        </div>
                    </div>
                </fieldset>
                <br>
                <Accordion class="mt-6" :activeIndex="[0]">
        <AccordionTab header="Double Entry Details">
            <div v-if="selectedTransaction">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Debit Entry -->
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <div class="font-semibold text-gray-700 mb-2">Debit</div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-500">Account</span>
                            <span>{{ selectedTransaction.sender_account_name }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-500">User</span>
                            <span>{{ selectedTransaction.sender_user_name }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-500">Amount</span>
                            <span class="text-red-600">-{{ formatAmount(selectedTransaction.amount, selectedTransaction.currency) }}</span>
                        </div>
                    </div>
                    <!-- Credit Entry -->
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <div class="font-semibold text-gray-700 mb-2">Credit</div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-500">Account</span>
                            <span>{{ selectedTransaction.receiver_account_name }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-500">User</span>
                            <span>{{ selectedTransaction.receiver_user_name }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-500">Amount</span>
                            <span class="text-green-600">+{{ formatAmount(selectedTransaction.amount, selectedTransaction.currency) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </AccordionTab>
    </Accordion>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style>
.tooltip-sm .p-tooltip-text {
    font-size: 0.75rem !important;
}
</style>
