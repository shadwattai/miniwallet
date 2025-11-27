<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Badge from 'primevue/badge';
import Card from 'primevue/card';
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
    Clock,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Calendar as CalendarIcon,
    TrendingUp,
    TrendingDown,
    Eye,
    MoreHorizontal,
    Receipt,
    User,
    Building2
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

// Mock data for development - replace with props.transactions in production
const mockTransactions = ref<Transaction[]>([
    {
        key: '1',
        ref_number: 'DP202411270001',
        sender_acct_key: 'acc_001',
        receiver_acct_key: 'acc_002',
        description: 'Monthly salary deposit',
        type: 'deposit',
        amount: 5000.00,
        commission_fee: 0,
        status: 'completed',
        created_at: '2024-11-27T10:30:00Z',
        updated_at: '2024-11-27T10:30:00Z',
        sender_account_name: 'Initial Account',
        receiver_account_name: 'Main Savings',
        sender_user_name: 'System',
        receiver_user_name: 'John Doe',
        currency: 'AED'
    },
    {
        key: '2', 
        ref_number: 'TR202411270002',
        sender_acct_key: 'acc_002',
        receiver_acct_key: 'acc_003',
        description: 'Money transfer to friend',
        type: 'transfer',
        amount: 150.00,
        commission_fee: 2.25,
        status: 'completed',
        created_at: '2024-11-27T14:15:00Z',
        updated_at: '2024-11-27T14:15:00Z',
        sender_account_name: 'Main Wallet',
        receiver_account_name: 'Sarah\'s Wallet',
        sender_user_name: 'John Doe',
        receiver_user_name: 'Sarah Smith',
        currency: 'AED'
    },
    {
        key: '3',
        ref_number: 'WD202411270003', 
        sender_acct_key: 'acc_002',
        receiver_acct_key: 'acc_001',
        description: 'ATM withdrawal',
        type: 'withdrawal',
        amount: 200.00,
        commission_fee: 0,
        status: 'completed',
        created_at: '2024-11-26T16:45:00Z',
        updated_at: '2024-11-26T16:45:00Z',
        sender_account_name: 'Main Savings',
        receiver_account_name: 'Initial Account',
        sender_user_name: 'John Doe',
        receiver_user_name: 'System',
        currency: 'AED'
    },
    {
        key: '4',
        ref_number: 'TU202411270004',
        sender_acct_key: 'acc_002',
        receiver_acct_key: 'acc_004',
        description: 'Top up digital wallet',
        type: 'topup',
        amount: 300.00,
        commission_fee: 0,
        status: 'pending',
        created_at: '2024-11-27T09:20:00Z',
        updated_at: '2024-11-27T09:20:00Z',
        sender_account_name: 'Main Savings',
        receiver_account_name: 'Digital Wallet',
        sender_user_name: 'John Doe',
        receiver_user_name: 'John Doe',
        currency: 'AED'
    }
]);

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
    let filtered = props.transactions || mockTransactions.value;

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
    const transactions = props.transactions || mockTransactions.value;
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
                                >
                                    <MoreHorizontal class="w-4 h-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>

<style>
.tooltip-sm .p-tooltip-text {
    font-size: 0.75rem !important;
}
</style>
