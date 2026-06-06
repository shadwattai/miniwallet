<script setup lang="ts">
import { useWalletForm, type Bank } from '@/composables/useWalletForm';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Building2, DollarSign, Hash, Save, User, Wallet2 as WalletIcon, X } from 'lucide-vue-next';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import { computed, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Create Wallet',
        href: dashboard().url,
    },
];

const props = defineProps<{
    User?: any;
    banks?: Bank[];
}>();

// Use wallet form composable
const { form, accountTypes, selectedAccountType, getAvailableCurrencies, resetCurrencyForBank, submitForm: submitWalletForm } = useWalletForm();

// Available currencies based on selected bank
const availableCurrencies = computed(() => {
    return getAvailableCurrencies(form.bank_key, props.banks || []);
});

const submitForm = () => {
    submitWalletForm(() => {
        // Redirect back to MyWalletsList after successful wallet creation
        router.visit('/miniwallet/mywallets');
    });
};

const cancelForm = () => {
    // Redirect back to MyWalletsList when canceling
    router.visit('/miniwallet/mywallets');
};

// Watch for bank selection changes to reset currency
watch(
    () => form.bank_key,
    (newBankKey) => {
        resetCurrencyForBank(newBankKey, props.banks || []);
    },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :User="props.User">
        <Head title="Create Wallet" />

        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-2">
            <!-- Header Section -->
            <div class="rounded-2xl p-6">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h1 class="mb-1 text-2xl font-bold text-gray-800">Create New Wallet</h1>
                        <p class="text-gray-600">Set up a new financial account</p>
                    </div>
                </div>

                <!-- Wallet Types Overview -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div
                        v-for="accountType in accountTypes"
                        :key="accountType.value"
                        class="cursor-pointer rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-md"
                        :class="{ 'border-blue-500 bg-blue-50': form.account_type === accountType.value }"
                        @click="form.account_type = accountType.value"
                    >
                        <div class="mb-4 flex items-center gap-3">
                            <div class="rounded-xl bg-blue-100 p-3">
                                <component :is="accountType.icon" class="h-6 w-6 text-blue-600" />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">{{ accountType.label }}</h3>
                        </div>
                        <p class="text-sm text-gray-600">
                            <span v-if="accountType.value === 'wallet'">Perfect for daily transactions and digital payments</span>
                            <span v-else-if="accountType.value === 'savings'">Ideal for long-term savings and earning interest</span>
                            <span v-else>Great for regular expenses and bill payments</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Wallet Creation Form -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-6 flex items-center gap-3">
                    <div class="rounded-lg bg-blue-100 p-2">
                        <component :is="selectedAccountType?.icon" class="h-5 w-5 text-blue-600" />
                    </div>
                    <h2 class="text-xl font-semibold">Create {{ selectedAccountType?.label }}</h2>
                </div>

                <form @submit.prevent="submitForm" class="space-y-6">
                    <!-- Account Name -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <User class="h-4 w-4" />
                            Account Name
                        </label>
                        <InputText
                            v-model="form.account_name"
                            placeholder="e.g., Main Wallet, Emergency Fund"
                            :class="{ 'p-invalid': form.errors.account_name }"
                            class="w-full"
                        />
                        <small v-if="form.errors.account_name" class="text-red-500">{{ form.errors.account_name }}</small>
                    </div>

                    <!-- Account Type -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <WalletIcon class="h-4 w-4" />
                            Account Type
                        </label>
                        <Dropdown
                            v-model="form.account_type"
                            :options="[
                                { label: 'Digital Wallet', value: 'wallet' },
                                { label: 'Savings Account', value: 'savings' },
                            ]"
                            option-label="label"
                            option-value="value"
                            placeholder="Select account type"
                            :class="{ 'p-invalid': form.errors.account_type }"
                            class="w-full"
                        />
                        <small v-if="form.errors.account_type" class="text-red-500">{{ form.errors.account_type }}</small>
                        <small v-else class="text-blue-500">Current: {{ form.account_type }}</small>
                    </div>

                    <!-- Bank Selection -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <Building2 class="h-4 w-4" />
                            Bank
                        </label>
                        <Dropdown
                            v-model="form.bank_key"
                            :options="props.banks"
                            option-label="bank_name"
                            option-value="key"
                            placeholder="Select a bank"
                            :class="{ 'p-invalid': form.errors.bank_key }"
                            class="w-full"
                        />
                        <small v-if="form.errors.bank_key" class="text-red-500">{{ form.errors.bank_key }}</small>
                    </div>

                    <!-- Currency -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <DollarSign class="h-4 w-4" />
                            Currency
                        </label>
                        <Dropdown
                            v-model="form.currency"
                            :options="availableCurrencies"
                            option-label="label"
                            option-value="value"
                            placeholder="Select currency"
                            :class="{ 'p-invalid': form.errors.currency }"
                            :disabled="!form.bank_key"
                            class="w-full"
                        />
                        <small v-if="form.errors.currency" class="text-red-500">{{ form.errors.currency }}</small>
                        <small v-else-if="!form.bank_key" class="text-gray-500">Select a bank first to see available currencies</small>
                        <small v-else-if="availableCurrencies.length === 0" class="text-orange-500">
                            No currencies available for selected bank. Available currencies: {{ availableCurrencies.length }}
                        </small>
                        <small v-else class="text-green-500">{{ availableCurrencies.length }} currencies available</small>
                    </div>

                    <!-- Initial Balance -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <Hash class="h-4 w-4" />
                            Initial Balance (Optional)
                        </label>
                        <InputNumber
                            v-model="form.initial_balance"
                            :min="0"
                            :max-fraction-digits="2"
                            placeholder="0.00"
                            :class="{ 'p-invalid': form.errors.initial_balance }"
                            class="w-full"
                        />
                        <small v-if="form.errors.initial_balance" class="text-red-500">{{ form.errors.initial_balance }}</small>
                    </div>

                    <!-- Default Account -->
                    <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <Checkbox v-model="form.is_default" binary />
                        <label class="text-sm font-medium text-amber-800"> Set as default account </label>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end gap-3 pt-4">
                        <Button @click="cancelForm" severity="secondary" outlined class="px-4 py-2">
                            <X class="mr-2 h-4 w-4" />
                            Cancel
                        </Button>
                        <Button @click="submitForm" :loading="form.processing" class="bg-blue-500 px-4 py-2 text-white hover:bg-blue-600">
                            <Save class="mr-2 h-4 w-4" />
                            Create Wallet
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.p-fluid .p-inputtext,
.p-fluid .p-dropdown,
.p-fluid .p-inputnumber {
    width: 100%;
}
</style>
