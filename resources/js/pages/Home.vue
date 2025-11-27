<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { User as UserIcon, Activity, CreditCard, Settings } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Home',
        href: dashboard().url,
    },
];

const props = defineProps<{
    User?: any;
}>();

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
                <div class="rounded-lg bg-white p-4 shadow-md">
                    <div class="flex items-center gap-4">
                        <UserIcon class="h-8 w-8 text-blue-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-500">Account Balance</p>
                            <p class="text-lg font-bold text-gray-800">$1,250.00</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-md">
                    <div class="flex items-center gap-4">
                        <Activity class="h-8 w-8 text-green-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-500">Recent Transactions</p>
                            <p class="text-lg font-bold text-gray-800">15</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-md">
                    <div class="flex items-center gap-4">
                        <CreditCard class="h-8 w-8 text-purple-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-500">Cards Linked</p>
                            <p class="text-lg font-bold text-gray-800">3</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-md">
                    <div class="flex items-center gap-4">
                        <Settings class="h-8 w-8 text-yellow-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-500">Settings Updated</p>
                            <p class="text-lg font-bold text-gray-800">2 days ago</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="rounded-lg bg-white p-6 shadow-md">
                <h2 class="text-lg font-bold text-gray-800">Quick Actions</h2>
                <div class="mt-4 flex gap-4">
                    <button class="rounded-lg bg-blue-500 px-4 py-2 text-white shadow-md hover:bg-blue-600">
                        Add Funds
                    </button>
                    <button class="rounded-lg bg-green-500 px-4 py-2 text-white shadow-md hover:bg-green-600">
                        Transfer Money
                    </button>
                    <button class="rounded-lg bg-gray-500 px-4 py-2 text-white shadow-md hover:bg-gray-600">
                        View Transactions
                    </button>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <div class="rounded-lg bg-white p-6 shadow-md">
                <h2 class="text-lg font-bold text-gray-800">Recent Activity</h2>
                <ul class="mt-4 space-y-2">
                    <li class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Payment to John Doe</span>
                        <span class="text-sm font-bold text-gray-800">-$50.00</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Funds Added</span>
                        <span class="text-sm font-bold text-gray-800">+$200.00</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Subscription Payment</span>
                        <span class="text-sm font-bold text-gray-800">-$15.00</span>
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
