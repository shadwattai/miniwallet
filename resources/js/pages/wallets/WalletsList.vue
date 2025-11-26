<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Wallet2 as WalletIcon } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Wallets',
        href: dashboard().url,
    },
];

interface Wallet {
    id: number;
    key: string;
    user_key: string;
    bank_key: string;
    bank_name: string;
    account_number: string;
    account_name: string;
    account_type: string;
    currency: string;
    balance: number;
    is_active: boolean;
    created_at: string;
    owner_name: string;
    owner_email: string;
}

const props = defineProps<{
    User?: any;
    wallets?: Wallet[];
}>();

</script>

<template>

    <AppLayout :breadcrumbs="breadcrumbs" :User="props.User">

        <Head title="HOME" />

        <div class="flex h-full flex-1 gap-4 overflow-x-auto rounded-xl p-4">
            <div class="w-full flex flex-col">
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="px-6 py-4 border-b">
                        <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                            <WalletIcon class="w-5 h-5" />
                            Wallets
                        </h2>
                    </div>
                    
                    <div v-if="props.wallets && props.wallets.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="wallet in props.wallets" :key="wallet.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ wallet.account_name }}</div>
                                            <div class="text-sm text-gray-500">{{ wallet.account_number }}</div>
                                            <small class="text-sm text-gray-700 uppercase">{{ wallet.bank_name }}</small>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ wallet.owner_name }}</div>
                                            <div class="text-sm text-gray-500">{{ wallet.owner_email }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ wallet.account_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ Number(wallet.balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ wallet.currency }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="wallet.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" 
                                              class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                            {{ wallet.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ new Date(wallet.created_at).toLocaleDateString() }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div v-else class="px-6 py-12 text-center">
                        <WalletIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No wallets</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating a new wallet.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.tooltip-sm .p-tooltip-text {
    font-size: 0.75rem !important;
}
</style>
