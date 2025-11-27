<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { User as UserIcon, Activity, CreditCard, Settings } from 'lucide-vue-next';
import { defineProps } from 'vue';
import Chart from 'primevue/chart';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: dashboard().url,
    },
];

const props = defineProps<{
    User?: any;
    AccountBalance: number;
    RecentTransactions: Array<{ description: string; amount: number; created_at: string }>;
    TransactionStatistics?: Array<{ type: string; count: number; total_amount: number }>;
}>();

const chartData = {
    labels: props.TransactionStatistics?.map(stat => stat.type) || [],
    datasets: [
        {
            label: 'Transaction Count',
            backgroundColor: ['#42A5F5', '#66BB6A', '#FFA726', '#26C6DA'],
            data: props.TransactionStatistics?.map(stat => stat.count) || [],
        },
    ],
};

const chartOptions = {
    responsive: true,
    plugins: {
        legend: {
            position: 'top',
        },
    },
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :User="props.User">
        <Head title="HOME" />

        <div class="flex flex-col gap-6 p-4">
            <!-- Welcome Section -->
            <div class="rounded-lg bg-blue-500 p-6 text-white shadow-md">
                <h1 class="text-2xl font-bold">Welcome back, {{ props.User?.name || 'User' }}!</h1>
                <p class="mt-2 text-sm">Here’s what’s happening with your wallet today.</p>
            </div>

            <!-- Stats Section -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-gradient-to-r from-blue-100 to-blue-50 p-4 shadow-md border border-blue-200">
                    <div class="flex items-center gap-4">
                        <UserIcon class="h-8 w-8 text-blue-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-500">Account Balance</p>
                            <p class="text-lg font-bold text-gray-800">{{ Number(props.AccountBalance || 0).toFixed(2) }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg bg-gradient-to-r from-green-100 to-green-50 p-4 shadow-md border border-green-200">
                    <div class="flex items-center gap-4">
                        <Activity class="h-8 w-8 text-green-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-500">Recent Transactions</p>
                            <p class="text-lg font-bold text-gray-800">{{ props.RecentTransactions.length }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg bg-gradient-to-r from-purple-100 to-purple-50 p-4 shadow-md border border-purple-200">
                    <div class="flex items-center gap-4">
                        <CreditCard class="h-8 w-8 text-purple-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-500">Cards Linked</p>
                            <p class="text-lg font-bold text-gray-800">3</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg bg-gradient-to-r from-yellow-100 to-yellow-50 p-4 shadow-md border border-yellow-200">
                    <div class="flex items-center gap-4">
                        <Settings class="h-8 w-8 text-yellow-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-500">Settings Updated</p>
                            <p class="text-lg font-bold text-gray-800">2 days ago</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Statistics Chart -->
            <div class="rounded-lg bg-white p-6 shadow-md">
                <h2 class="text-lg font-bold text-gray-800">Transaction Statistics</h2>
                <Chart type="bar" :data="chartData" :options="chartOptions" class="mt-4" />
            </div>

            <!-- Recent Activity Section -->
            <div class="rounded-lg bg-white p-6 shadow-md">
                <h2 class="text-lg font-bold text-gray-800">Recent Activity</h2>
                <ul class="mt-4 space-y-2">
                    <li v-for="transaction in props.RecentTransactions" :key="transaction.created_at" class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">{{ transaction.description }}</span>
                        <span :class="transaction.amount > 0 ? 'text-green-500' : 'text-red-500'" class="text-sm font-bold">
                            {{ transaction.amount > 0 ? '+' : '' }}${{ transaction.amount.toFixed(2) }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.tooltip-sm .p-tooltip-text {
    font-size: 0.75rem !important;
}
</style>
