<script setup lang="ts">
import { useWalletForm } from '@/composables/useWalletForm';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    ArrowUp,
    Ban,
    Banknote,
    Building2,
    CreditCard,
    DollarSign,
    Eye,
    EyeOff,
    Hash,
    MessageSquare,
    MinusCircle,
    PiggyBank,
    Plus,
    Save,
    Search,
    Send,
    TrendingUp,
    User,
    UserCheck,
    Wallet2 as WalletIcon,
    X,
} from 'lucide-vue-next';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Wallets',
        href: dashboard().url,
    },
];

interface Wallet {
    id: number;
    key: string;
    user_key: string;
    bank_key: string;
    account_number: string;
    account_name: string;
    account_type: string;
    currency: string;
    balance: number;
    is_active: boolean;
    is_default: boolean;
    created_at: string;
    owner_name?: string;
    owner_email?: string;
    bank_name?: string;
}

interface Bank {
    key: string;
    bank_name: string;
    bank_code: string;
    swift_code: string;
    supported_currencies: string[];
}

const props = defineProps<{
    User?: any;
    wallets?: Wallet[];
    banks?: Bank[];
}>();

const hideBalances = ref(false);
const showCreateDialog = ref(false);

// Deposit dialog state
const showDepositDialog = ref(false);
const selectedWalletForDeposit = ref<Wallet | null>(null);
const depositAmount = ref('');

// Withdraw dialog state
const showWithdrawDialog = ref(false);
const selectedWalletForWithdraw = ref<Wallet | null>(null);
const withdrawAmount = ref('');

// Top Up dialog state
const showTopUpDialog = ref(false);
const selectedWalletForTopUp = ref<Wallet | null>(null);
const topUpAmount = ref('');
const selectedSourceAccount = ref('');

// Transfer dialog state
const showTransferDialog = ref(false);
const selectedWalletForTransfer = ref<Wallet | null>(null);
const transferAmount = ref('');
const selectedReceiverWallet = ref('');
const walletSearchQuery = ref('');
const isSearchingWallets = ref(false);
const searchResults = ref([]);

// Deposit form
const depositForm = useForm({
    wallet_key: '',
    amount: 0,
    description: '',
});

// Withdraw form
const withdrawForm = useForm({
    wallet_key: '',
    amount: 0,
    description: '',
});

// Top Up form
const topUpForm = useForm({
    wallet_key: '',
    source_account_key: '',
    amount: 0,
    description: '',
});

// Transfer form
const transferForm = useForm({
    sender_wallet_key: '',
    receiver_wallet_key: '',
    amount: 0,
    description: '',
});

// Use confirmation dialog
const confirm = useConfirm();

// Use toast notifications
const toast = useToast();

// Use wallet form composable
const { form, accountTypes, selectedAccountType, getAvailableCurrencies, resetCurrencyForBank, submitForm: submitWalletForm } = useWalletForm();

// Available currencies based on selected bank
const availableCurrencies = computed(() => {
    return getAvailableCurrencies(form.bank_key, props.banks || []);
});

const totalBalance = computed(() => {
    if (!props.wallets || !Array.isArray(props.wallets)) return 0;
    return props.wallets.filter((wallet) => wallet.is_active).reduce((sum, wallet) => sum + Number(wallet.balance), 0);
});

const getAccountIcon = (type: string) => {
    switch (type) {
        case 'savings':
            return PiggyBank;
        case 'checking':
            return Banknote;
        case 'wallet':
        default:
            return CreditCard;
    }
};

const getAccountTypeColor = (type: string) => {
    switch (type) {
        case 'savings':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'checking':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'wallet':
        default:
            return 'bg-purple-100 text-purple-800 border-purple-200';
    }
};

const formatBalance = (balance: number, currency: string) => {
    if (hideBalances.value) return '••••••••••••';
    return `${currency} ${Number(balance).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
};

const activeWallets = computed(() => {
    if (!props.wallets || !Array.isArray(props.wallets)) return [];
    return props.wallets.filter((wallet) => wallet.is_active);
});

const availableSavingsAccounts = computed(() => {
    if (!props.wallets || !Array.isArray(props.wallets)) return [];
    return props.wallets.filter(
        (wallet) => wallet.account_type === 'savings' && wallet.is_active && Number(wallet.balance) > 1000, // Has more than minimum balance
    );
});

const maxTopUpAmount = computed(() => {
    if (!selectedSourceAccount.value) return 0;
    const sourceAccount = availableSavingsAccounts.value.find((account) => account.key === selectedSourceAccount.value);
    return sourceAccount ? Math.max(0, Number(sourceAccount.balance) - 1000) : 0;
});

// Commission calculation for transfers
const commissionRate = 0.015; // 1.5%
const calculatedCommission = computed(() => {
    const amount = parseFloat(transferAmount.value) || 0;
    return amount * commissionRate;
});

const totalDebitAmount = computed(() => {
    const amount = parseFloat(transferAmount.value) || 0;
    return amount + calculatedCommission.value;
});

const maxTransferAmount = computed(() => {
    if (!selectedWalletForTransfer.value) return 0;
    const balance = Number(selectedWalletForTransfer.value.balance);
    // Calculate max transfer considering commission
    return balance / (1 + commissionRate);
});

// Group search results by user
const groupedSearchResults = computed(() => {
    if (!searchResults.value || searchResults.value.length === 0) return [];

    // Group wallets by user
    const userGroups = {};

    searchResults.value.forEach((wallet) => {
        if (!userGroups[wallet.user_key]) {
            userGroups[wallet.user_key] = {
                user_key: wallet.user_key,
                user_name: wallet.user_name,
                user_email: wallet.user_email,
                user_handle: wallet.user_handle,
                wallets: [],
            };
        }
        userGroups[wallet.user_key].wallets.push(wallet);
    });

    return Object.values(userGroups);
});

const inactiveWallets = computed(() => {
    if (!props.wallets || !Array.isArray(props.wallets)) return [];
    return props.wallets.filter((wallet) => !wallet.is_active);
});

const openCreateDialog = () => {
    showCreateDialog.value = true;
    form.clearErrors();
};

const submitForm = () => {
    submitWalletForm(() => {
        showCreateDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Wallet Created',
            detail: 'Your new wallet has been created successfully',
            life: 4000,
        });
    });
};

const closeCreateDialog = () => {
    showCreateDialog.value = false;
    form.reset();
    form.clearErrors();
};

// Watch for bank selection changes to reset currency
watch(
    () => form.bank_key,
    (newBankKey) => {
        resetCurrencyForBank(newBankKey, props.banks || []);
    },
);

// Deactivate wallet function
const deactivateWallet = (wallet: Wallet) => {
    confirm.require({
        message: `Are you sure you want to deactivate "${wallet.account_name}"? This wallet will no longer be accessible for transactions.`,
        header: 'Deactivate Wallet',
        icon: 'pi pi-exclamation-triangle',
        rejectClass: 'p-button-secondary p-button-outlined',
        rejectLabel: 'Cancel',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Deactivate',
        accept: () => {
            // Submit deactivation request
            router.post(
                '/miniwallet/deactivatewallet',
                {
                    wallet_key: wallet.key,
                },
                {
                    onSuccess: (page) => {
                        toast.add({
                            severity: 'success',
                            summary: 'Wallet Deactivated',
                            detail: `"${wallet.account_name}" has been successfully deactivated`,
                            life: 5000,
                        });
                    },
                    onError: (errors) => {
                        console.error('Failed to deactivate wallet:', errors);
                        toast.add({
                            severity: 'error',
                            summary: 'Deactivation Failed',
                            detail: errors.general || 'Failed to deactivate wallet. Please try again.',
                            life: 5000,
                        });
                    },
                },
            );
        },
        reject: () => {
            toast.add({
                severity: 'info',
                summary: 'Cancelled',
                detail: 'Wallet deactivation was cancelled',
                life: 3000,
            });
        },
    });
};

// Reactivate wallet function
const reactivateWallet = (wallet: Wallet) => {
    confirm.require({
        message: `Are you sure you want to reactivate "${wallet.account_name}"? This wallet will become accessible for transactions again.`,
        header: 'Reactivate Wallet',
        icon: 'pi pi-question-circle',
        rejectClass: 'p-button-secondary p-button-outlined',
        rejectLabel: 'Cancel',
        acceptClass: 'p-button-success',
        acceptLabel: 'Reactivate',
        accept: () => {
            // Submit reactivation request
            router.post(
                '/miniwallet/reactivatewallet',
                {
                    wallet_key: wallet.key,
                },
                {
                    onSuccess: (page) => {
                        toast.add({
                            severity: 'success',
                            summary: 'Wallet Reactivated',
                            detail: `"${wallet.account_name}" has been successfully reactivated`,
                            life: 5000,
                        });
                    },
                    onError: (errors) => {
                        console.error('Failed to reactivate wallet:', errors);
                        toast.add({
                            severity: 'error',
                            summary: 'Reactivation Failed',
                            detail: errors.general || 'Failed to reactivate wallet. Please try again.',
                            life: 5000,
                        });
                    },
                },
            );
        },
        reject: () => {
            toast.add({
                severity: 'info',
                summary: 'Cancelled',
                detail: 'Wallet reactivation was cancelled',
                life: 3000,
            });
        },
    });
};

// Open deposit dialog
const openDepositDialog = (wallet: Wallet) => {
    selectedWalletForDeposit.value = wallet;
    depositForm.wallet_key = wallet.key;
    depositForm.amount = 0;
    depositForm.description = '';
    depositAmount.value = '';
    depositForm.clearErrors();
    showDepositDialog.value = true;
};

// Close deposit dialog
const closeDepositDialog = () => {
    showDepositDialog.value = false;
    selectedWalletForDeposit.value = null;
    depositAmount.value = '';
    depositForm.reset();
    depositForm.clearErrors();
};

// Open withdraw dialog
const openWithdrawDialog = (wallet: Wallet) => {
    selectedWalletForWithdraw.value = wallet;
    withdrawForm.wallet_key = wallet.key;
    withdrawForm.amount = 0;
    withdrawForm.description = '';
    withdrawAmount.value = '';
    withdrawForm.clearErrors();
    showWithdrawDialog.value = true;
};

// Close withdraw dialog
const closeWithdrawDialog = () => {
    showWithdrawDialog.value = false;
    selectedWalletForWithdraw.value = null;
    withdrawAmount.value = '';
    withdrawForm.reset();
    withdrawForm.clearErrors();
};

// Open top up dialog
const openTopUpDialog = (wallet: Wallet) => {
    selectedWalletForTopUp.value = wallet;
    topUpForm.wallet_key = wallet.key;
    topUpForm.source_account_key = '';
    topUpForm.amount = 0;
    topUpForm.description = '';
    topUpAmount.value = '';
    selectedSourceAccount.value = '';
    topUpForm.clearErrors();
    showTopUpDialog.value = true;
};

// Close top up dialog
const closeTopUpDialog = () => {
    showTopUpDialog.value = false;
    selectedWalletForTopUp.value = null;
    selectedSourceAccount.value = '';
    topUpAmount.value = '';
    topUpForm.reset();
    topUpForm.clearErrors();
};

// Search for receiver wallets
const searchReceiverWallets = async (query: string) => {
    if (query.length < 2) {
        searchResults.value = [];
        return;
    }

    isSearchingWallets.value = true;

    try {
        const response = await axios.get('/api/search-wallets', {
            params: {
                query: query,
                exclude_wallet: selectedWalletForTransfer.value?.key,
                currency: selectedWalletForTransfer.value?.currency,
            },
        });
        searchResults.value = response.data.wallets;
    } catch (error) {
        console.error('Error searching wallets:', error);
        searchResults.value = [];
    } finally {
        isSearchingWallets.value = false;
    }
};

// Watch search query for debounced searching
watch(
    walletSearchQuery,
    (newQuery) => {
        if (newQuery) {
            searchReceiverWallets(newQuery);
        } else {
            searchResults.value = [];
        }
    },
    { debounce: 300 },
);

// Select receiver wallet
const selectReceiverWallet = (wallet: any) => {
    selectedReceiverWallet.value = wallet.key;
};

// Open transfer dialog
const openTransferDialog = (wallet: Wallet) => {
    selectedWalletForTransfer.value = wallet;
    transferForm.sender_wallet_key = wallet.key;
    transferForm.receiver_wallet_key = '';
    transferForm.amount = 0;
    transferForm.description = '';
    transferAmount.value = '';
    selectedReceiverWallet.value = '';
    walletSearchQuery.value = '';
    searchResults.value = [];
    transferForm.clearErrors();
    showTransferDialog.value = true;
};

const closeTransferDialog = () => {
    showTransferDialog.value = false;
    selectedWalletForTransfer.value = null;
    selectedReceiverWallet.value = '';
    transferAmount.value = '';
    walletSearchQuery.value = '';
    searchResults.value = [];
    transferForm.reset();
    transferForm.clearErrors();
};

// Submit deposit form
const submitDepositForm = () => {
    // Convert string amount to number
    depositForm.amount = parseFloat(depositAmount.value) || 0;

    depositForm.post('/miniwallet/deposit', {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Deposit Successful',
                detail: `${selectedWalletForDeposit.value?.currency} ${depositForm.amount?.toLocaleString('en-US', { minimumFractionDigits: 2 })} has been deposited to "${selectedWalletForDeposit.value?.account_name}"`,
                life: 5000,
            });
            closeDepositDialog();
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Deposit Failed',
                detail: errors.general || 'Failed to deposit money. Please try again.',
                life: 5000,
            });
        },
    });
};

// Submit withdraw form
const submitWithdrawForm = () => {
    // Convert string amount to number
    withdrawForm.amount = parseFloat(withdrawAmount.value) || 0;

    withdrawForm.post('/miniwallet/withdraw', {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Withdrawal Successful',
                detail: `${selectedWalletForWithdraw.value?.currency} ${withdrawForm.amount?.toLocaleString('en-US', { minimumFractionDigits: 2 })} has been withdrawn from "${selectedWalletForWithdraw.value?.account_name}"`,
                life: 5000,
            });
            closeWithdrawDialog();
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Withdrawal Failed',
                detail: errors.general || errors.amount || 'Failed to withdraw money. Please try again.',
                life: 5000,
            });
        },
    });
};

// Submit top up form
const submitTopUpForm = () => {
    topUpForm.source_account_key = selectedSourceAccount.value;
    topUpForm.amount = parseFloat(topUpAmount.value) || 0;

    topUpForm.post('/miniwallet/topup', {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Top Up Successful',
                detail: `${selectedWalletForTopUp.value?.currency} ${topUpForm.amount?.toLocaleString('en-US', { minimumFractionDigits: 2 })} has been transferred to "${selectedWalletForTopUp.value?.account_name}"`,
                life: 5000,
            });
            closeTopUpDialog();
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Top Up Failed',
                detail: errors.general || errors.amount || 'Failed to transfer money. Please try again.',
                life: 5000,
            });
        },
    });
};

// Submit transfer form
const submitTransferForm = () => {
    transferForm.receiver_wallet_key = selectedReceiverWallet.value;
    transferForm.amount = parseFloat(transferAmount.value) || 0;

    transferForm.post('/api/transactions', {
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Transfer Successful',
                detail: `${selectedWalletForTransfer.value?.currency} ${transferForm.amount?.toLocaleString('en-US', { minimumFractionDigits: 2 })} transferred successfully (Fee: ${calculatedCommission.value.toLocaleString('en-US', { minimumFractionDigits: 2 })})`,
                life: 6000,
            });
            closeTransferDialog();
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Transfer Failed',
                detail: errors.general || errors.amount || 'Failed to send money. Please try again.',
                life: 5000,
            });
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :User="props.User">
        <Head title="My Wallets" />

        <!-- Toast Component -->
        <Toast />

        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-2">
            <!-- Header Section -->
            <div class="rounded-2xl p-6">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h1 class="mb-1 text-2xl font-bold text-gray-800">My Wallets</h1>
                        <p class="text-gray-600">Managing my financial accounts</p>
                    </div>
                    <button
                        @click="openCreateDialog"
                        class="rounded-xl bg-blue-500 p-3 text-white shadow-md transition-all duration-200 hover:bg-blue-600 hover:shadow-lg"
                    >
                        <Plus class="h-6 w-6" />
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-700">Total Balance</h3>
                            <button @click="hideBalances = !hideBalances" class="rounded-lg p-1 transition-colors hover:bg-gray-100">
                                <Eye v-if="hideBalances" class="h-4 w-4 text-gray-600" />
                                <EyeOff v-else class="h-4 w-4 text-gray-600" />
                            </button>
                        </div>
                        <p class="text-2xl font-bold text-gray-800">
                            {{
                                hideBalances
                                    ? '••••••••••••'
                                    : `AED ${totalBalance.toLocaleString('en-US', {
                                          minimumFractionDigits: 2,
                                      })}`
                            }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-3 flex items-center gap-2">
                            <div class="rounded-lg bg-blue-100 p-1">
                                <WalletIcon class="h-4 w-4 text-blue-600" />
                            </div>
                            <h3 class="text-sm font-medium text-gray-700">Active Accounts</h3>
                        </div>
                        <p class="text-2xl font-bold text-gray-800">{{ activeWallets.length }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-3 flex items-center gap-2">
                            <div class="rounded-lg bg-emerald-100 p-1">
                                <TrendingUp class="h-4 w-4 text-emerald-600" />
                            </div>
                            <h3 class="text-sm font-medium text-gray-700">This Month</h3>
                        </div>
                        <p class="text-2xl font-bold text-emerald-600">+12.5%</p>
                    </div>
                </div>
            </div>

            <!-- Active Wallets -->
            <div v-if="activeWallets.length > 0" class="pl-4">
                <div class="mb-4 flex items-center gap-2">
                    <h2 class="text-xl font-semibold text-gray-800">Active Wallets</h2>
                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                        {{ activeWallets.length }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 pr-5 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="wallet in activeWallets"
                        :key="wallet.id"
                        class="group overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm transition-all duration-200 hover:border-blue-300 hover:shadow-md"
                    >
                        <div class="p-6">
                            <div class="mb-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div :class="getAccountTypeColor(wallet.account_type)" class="rounded-xl border p-2">
                                        <component :is="getAccountIcon(wallet.account_type)" class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-semibold text-gray-900">{{ wallet.account_name }}</h3>
                                            <span
                                                v-if="wallet.is_default"
                                                class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800"
                                            >
                                                Default
                                            </span>
                                        </div>
                                        <p class="font-mono text-xs tracking-wider text-gray-500 uppercase">
                                            {{ wallet.account_number }} - {{ wallet.account_type }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500">Owner</p>
                                    <p class="text-sm font-medium text-blue-600">{{ wallet.owner_name || props.User?.name || 'Account Owner' }}</p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <p class="mb-1 text-sm text-gray-500">Current Balance</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    {{ formatBalance(wallet.balance, wallet.currency) }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <!-- Digital Wallet: Only Transfer and Top Up -->
                                <template v-if="wallet.account_type === 'wallet'">
                                    <Button
                                        outlined
                                        severity="info"
                                        @click="openTransferDialog(wallet)"
                                        class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-orange-500 px-3 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-200 hover:bg-orange-600"
                                    >
                                        <Send class="h-4 w-4" />
                                        Transfer
                                    </Button>

                                    <Button
                                        outlined
                                        severity="help"
                                        @click="openTopUpDialog(wallet)"
                                        class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-blue-500 px-3 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-200 hover:bg-blue-600"
                                    >
                                        <ArrowUp class="h-4 w-4" />
                                        Top up
                                    </Button>

                                    <Button
                                        outlined
                                        severity="danger"
                                        @click="deactivateWallet(wallet)"
                                        class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-red-500 px-3 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-200 hover:bg-red-600"
                                    >
                                        <Ban class="h-4 w-4" />
                                        Deactivate
                                    </Button>
                                </template>

                                <!-- Savings Account: Only Deposit -->
                                <template v-else>
                                    <Button
                                        outlined
                                        severity="success"
                                        @click="openDepositDialog(wallet)"
                                        class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-emerald-500 px-3 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-200 hover:bg-emerald-600"
                                    >
                                        <Plus class="h-4 w-4" />
                                        Deposit
                                    </Button>

                                    <Button
                                        outlined
                                        severity="warn"
                                        @click="openWithdrawDialog(wallet)"
                                        class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-yellow-500 px-3 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-200 hover:bg-yellow-600"
                                    >
                                        <MinusCircle class="h-4 w-4" />
                                        Withdraw
                                    </Button>

                                    <Button
                                        outlined
                                        severity="danger"
                                        @click="deactivateWallet(wallet)"
                                        class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-red-500 px-3 py-2 text-sm font-medium text-white shadow-sm transition-colors duration-200 hover:bg-red-600"
                                    >
                                        <Ban class="h-4 w-4" />
                                        Deactivate
                                    </Button>
                                </template>
                            </div>
                        </div>

                        <div class="border-t bg-gray-50 px-6 py-3">
                            <div class="flex items-center justify-between text-sm">
                                <div>
                                    <span class="text-gray-500">{{ wallet.bank_name || 'Unknown Bank' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">CREATED</span>
                                    <span class="ml-1 text-gray-700">{{ new Date(wallet.created_at).toLocaleDateString() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inactive Wallets -->
            <div v-if="inactiveWallets.length > 0" class="mt-8 pl-4">
                <div class="mb-4 flex items-center gap-2">
                    <h2 class="text-xl font-semibold text-gray-800">Inactive Wallets</h2>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                        {{ inactiveWallets.length }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="wallet in inactiveWallets"
                        :key="wallet.id"
                        class="overflow-hidden rounded-2xl border border-gray-300 bg-white opacity-60 shadow-sm"
                    >
                        <div class="p-6">
                            <div class="mb-4 flex items-center gap-3">
                                <div class="rounded-xl border border-gray-200 bg-gray-100 p-2 text-gray-400">
                                    <component :is="getAccountIcon(wallet.account_type)" class="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-600">{{ wallet.account_name }}</h3>
                                    <p class="text-sm text-gray-400">{{ wallet.account_number }}</p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <p class="mb-1 text-sm text-gray-400">Balance</p>
                                <p class="text-xl font-bold text-gray-500">
                                    {{ formatBalance(wallet.balance, wallet.currency) }}
                                </p>
                            </div>

                            <button
                                @click="reactivateWallet(wallet)"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-500 px-3 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-green-600"
                            >
                                <Plus class="h-4 w-4" />
                                Reactivate Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!props.wallets || props.wallets.length === 0" class="py-16 text-center">
                <div class="mx-auto mb-4 flex h-24 w-24 items-center justify-center rounded-full bg-gray-100">
                    <WalletIcon class="h-10 w-10 text-gray-400" />
                </div>
                <h3 class="mb-2 text-xl font-semibold text-gray-900">No wallets yet</h3>
                <p class="mx-auto mb-6 max-w-md text-gray-500">Create your first wallet to start managing your finances and make transactions.</p>
                <button
                    @click="openCreateDialog"
                    class="mx-auto flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 font-medium text-white transition-colors duration-200 hover:bg-blue-700"
                >
                    <Plus class="h-5 w-5" />
                    Create Your First Wallet
                </button>
            </div>
        </div>

        <!-- Wallet Creation Dialog -->
        <Dialog v-model:visible="showCreateDialog" modal header="Create New Wallet" :style="{ width: '38rem' }" class="p-fluid">
            <Divider />
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
                </div>

                <!-- Default Account -->
                <div class="flex items-center gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                    <Checkbox v-model="form.is_default" binary />
                    <label class="text-sm font-medium text-indigo-800"> Set as default account </label>
                </div>
            </form>

            <Divider />

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button @click="closeCreateDialog" severity="secondary" outlined class="px-4 py-2">
                        <X class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button @click="submitForm" :loading="form.processing" class="bg-blue-500 px-4 py-2 text-white hover:bg-blue-600">
                        <Save class="mr-2 h-4 w-4" />
                        Create Wallet
                    </Button>
                </div>
            </template>
        </Dialog>

        <!-- Deposit Money Dialog -->
        <Dialog v-model:visible="showDepositDialog" modal header="Deposit Money" class="w-[500px]">
            <div class="space-y-6">
                <!-- Wallet Information -->
                <div v-if="selectedWalletForDeposit" class="rounded-lg bg-gray-50 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-green-100 p-2">
                            <PiggyBank class="h-5 w-5 text-green-600" />
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">{{ selectedWalletForDeposit.account_name }}</h4>
                            <p class="text-sm text-gray-600">{{ selectedWalletForDeposit.account_number }}</p>
                            <p class="text-sm font-medium text-green-600">
                                Current Balance: {{ selectedWalletForDeposit.currency }}
                                {{
                                    Number(selectedWalletForDeposit.balance).toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Deposit Form -->
                <form @submit.prevent="submitDepositForm" class="space-y-4">
                    <!-- Amount Field -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <DollarSign class="h-4 w-4" />
                            Deposit Amount ({{ selectedWalletForDeposit?.currency || 'AED' }})
                        </label>
                        <InputText
                            v-model="depositAmount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="w-full"
                            :class="{ 'p-invalid': depositForm.errors.amount }"
                            inputId="deposit-amount"
                        />
                        <small v-if="depositForm.errors.amount" class="text-red-500">{{ depositForm.errors.amount }}</small>
                        <small v-else class="text-gray-500">Enter the amount you want to deposit</small>
                    </div>

                    <!-- Description Field -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <Hash class="h-4 w-4" />
                            Description (Optional)
                        </label>
                        <InputText v-model="depositForm.description" placeholder="Enter deposit description" maxlength="255" class="w-full" />
                        <small class="text-gray-500">Add a note for this deposit (optional)</small>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <Button type="button" outlined @click="closeDepositDialog" class="flex-1" :disabled="depositForm.processing"> Cancel </Button>
                        <Button
                            type="submit"
                            :loading="depositForm.processing"
                            :disabled="!depositAmount || parseFloat(depositAmount) <= 0"
                            class="flex-1 bg-green-500 hover:bg-green-600"
                        >
                            Deposit
                        </Button>
                    </div>
                </form>
            </div>
        </Dialog>

        <!-- Withdraw Money Dialog -->
        <Dialog v-model:visible="showWithdrawDialog" modal header="Withdraw Money" class="w-[500px]">
            <div class="space-y-6">
                <!-- Wallet Information -->
                <div v-if="selectedWalletForWithdraw" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-red-100 p-2">
                            <MinusCircle class="h-5 w-5 text-red-600" />
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">{{ selectedWalletForWithdraw.account_name }}</h4>
                            <p class="text-sm text-gray-600">{{ selectedWalletForWithdraw.account_number }}</p>
                            <p class="text-sm font-medium text-green-600">
                                Available Balance: {{ selectedWalletForWithdraw.currency }}
                                {{
                                    Number(selectedWalletForWithdraw.balance).toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })
                                }}
                            </p>
                            <!-- Show maximum withdrawable amount -->
                            <p class="text-xs text-orange-600">
                                Maximum withdrawal: {{ selectedWalletForWithdraw.currency }}
                                {{
                                    Math.max(0, Number(selectedWalletForWithdraw.balance) - 1000).toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Withdraw Form -->
                <form @submit.prevent="submitWithdrawForm" class="space-y-4">
                    <!-- Amount Field -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <DollarSign class="h-4 w-4" />
                            Withdrawal Amount ({{ selectedWalletForWithdraw?.currency || 'AED' }})
                        </label>
                        <InputText
                            v-model="withdrawAmount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="Math.max(0, Number(selectedWalletForWithdraw?.balance || 0) - 1000)"
                            placeholder="0.00"
                            class="w-full"
                            :class="{ 'p-invalid': withdrawForm.errors.amount }"
                            inputId="withdraw-amount"
                        />
                        <small v-if="withdrawForm.errors.amount" class="text-red-500">{{ withdrawForm.errors.amount }}</small>
                        <small v-else class="text-gray-500"> Enter amount to withdraw (Minimum AED 1,000 must remain in account) </small>
                    </div>

                    <!-- Description Field -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <Hash class="h-4 w-4" />
                            Reason for Withdrawal (Optional)
                        </label>
                        <InputText v-model="withdrawForm.description" placeholder="Enter withdrawal reason" maxlength="255" class="w-full" />
                        <small class="text-gray-500">Add a note for this withdrawal (optional)</small>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <Button type="button" outlined @click="closeWithdrawDialog" class="flex-1" :disabled="withdrawForm.processing">
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :loading="withdrawForm.processing"
                            :disabled="
                                !withdrawAmount ||
                                parseFloat(withdrawAmount) <= 0 ||
                                parseFloat(withdrawAmount) > Math.max(0, Number(selectedWalletForWithdraw?.balance || 0) - 1000)
                            "
                            class="flex-1 bg-red-500 hover:bg-red-600"
                        >
                            <MinusCircle class="mr-2 h-4 w-4" />
                            Withdraw
                        </Button>
                    </div>
                </form>
            </div>
        </Dialog>

        <!-- Confirmation Dialog -->
        <ConfirmDialog />

        <!-- Top Up Wallet Dialog -->
        <Dialog v-model:visible="showTopUpDialog" modal header="Top Up Digital Wallet" class="w-[600px]">
            <div class="space-y-6">
                <!-- Target Wallet Information -->
                <div v-if="selectedWalletForTopUp" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-blue-100 p-2">
                            <WalletIcon class="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">{{ selectedWalletForTopUp.account_name }}</h4>
                            <p class="text-sm text-gray-600">Digital Wallet - {{ selectedWalletForTopUp.account_number }}</p>
                            <p class="text-sm font-medium text-blue-600">
                                Current Balance: {{ selectedWalletForTopUp.currency }}
                                {{ Number(selectedWalletForTopUp.balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Source Account Selection -->
                <div v-if="availableSavingsAccounts.length > 0" class="space-y-4">
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <PiggyBank class="h-4 w-4" />
                            Select Source Savings Account
                        </label>
                        <Dropdown
                            v-model="selectedSourceAccount"
                            :options="availableSavingsAccounts"
                            option-label="account_name"
                            option-value="key"
                            placeholder="Choose which savings account to transfer from"
                            class="w-full"
                            :class="{ 'p-invalid': topUpForm.errors.source_account_key }"
                        >
                            <template #option="slotProps">
                                <div class="flex w-full items-center justify-between">
                                    <div>
                                        <div class="font-medium">{{ slotProps.option.account_name }}</div>
                                        <div class="text-sm text-gray-500">{{ slotProps.option.account_number }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-green-600">
                                            {{ slotProps.option.currency }}
                                            {{ Number(slotProps.option.balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Available: {{ slotProps.option.currency }}
                                            {{
                                                Math.max(0, Number(slotProps.option.balance) - 1000).toLocaleString('en-US', {
                                                    minimumFractionDigits: 2,
                                                })
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Dropdown>
                        <small v-if="topUpForm.errors.source_account_key" class="text-red-500">{{ topUpForm.errors.source_account_key }}</small>
                    </div>

                    <!-- Selected Source Account Details -->
                    <div v-if="selectedSourceAccount" class="rounded-lg border border-green-200 bg-green-50 p-4">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-green-100 p-2">
                                <PiggyBank class="h-5 w-5 text-green-600" />
                            </div>
                            <div>
                                <h5 class="font-medium text-gray-800">
                                    {{ availableSavingsAccounts.find((acc) => acc.key === selectedSourceAccount)?.account_name }}
                                </h5>
                                <p class="text-sm text-green-600">
                                    Maximum transfer: {{ selectedWalletForTopUp?.currency }}
                                    {{ maxTopUpAmount.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Savings Accounts Available -->
                <div v-else class="rounded-lg border border-orange-200 bg-orange-50 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-orange-100 p-2">
                            <AlertCircle class="h-5 w-5 text-orange-600" />
                        </div>
                        <div>
                            <h5 class="font-medium text-gray-800">No Savings Accounts Available</h5>
                            <p class="text-sm text-orange-600">
                                You need at least one active savings account with sufficient balance to top up your digital wallet.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Top Up Form -->
                <form v-if="availableSavingsAccounts.length > 0" @submit.prevent="submitTopUpForm" class="space-y-4">
                    <!-- Amount Field -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <ArrowUp class="h-4 w-4" />
                            Top Up Amount ({{ selectedWalletForTopUp?.currency || 'AED' }})
                        </label>
                        <InputText
                            v-model="topUpAmount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="maxTopUpAmount"
                            placeholder="0.00"
                            class="w-full"
                            :class="{ 'p-invalid': topUpForm.errors.amount }"
                            :disabled="!selectedSourceAccount"
                            inputId="topup-amount"
                        />
                        <small v-if="topUpForm.errors.amount" class="text-red-500">{{ topUpForm.errors.amount }}</small>
                        <small v-else-if="selectedSourceAccount" class="text-gray-500">
                            Maximum: {{ selectedWalletForTopUp?.currency }} {{ maxTopUpAmount.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                            (AED 1,000 minimum must remain in savings account)
                        </small>
                        <small v-else class="text-gray-500">Please select a source account first</small>
                    </div>

                    <!-- Description Field -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <Hash class="h-4 w-4" />
                            Transfer Description (Optional)
                        </label>
                        <InputText v-model="topUpForm.description" placeholder="Enter reason for top up" maxlength="255" class="w-full" />
                        <small class="text-gray-500">Add a note for this transfer (optional)</small>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <Button type="button" outlined @click="closeTopUpDialog" class="flex-1" :disabled="topUpForm.processing"> Cancel </Button>
                        <Button
                            type="submit"
                            :loading="topUpForm.processing"
                            :disabled="
                                !selectedSourceAccount || !topUpAmount || parseFloat(topUpAmount) <= 0 || parseFloat(topUpAmount) > maxTopUpAmount
                            "
                            class="flex-1 bg-blue-500 hover:bg-blue-600"
                        >
                            <ArrowUp class="mr-2 h-4 w-4" />
                            Transfer
                            {{
                                topUpAmount
                                    ? selectedWalletForTopUp?.currency +
                                      ' ' +
                                      parseFloat(topUpAmount).toLocaleString('en-US', { minimumFractionDigits: 2 })
                                    : ''
                            }}
                        </Button>
                    </div>
                </form>
            </div>
        </Dialog>

        <!-- Transfer Money Dialog -->
        <Dialog v-model:visible="showTransferDialog" modal header="Transfer Money" class="w-[650px]">
            <div class="space-y-6">
                <!-- Sender Wallet Information -->
                <div v-if="selectedWalletForTransfer" class="rounded-lg border border-orange-200 bg-orange-50 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-orange-100 p-2">
                            <Send class="h-5 w-5 text-orange-600" />
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">From: {{ selectedWalletForTransfer.account_name }}</h4>
                            <p class="text-sm text-gray-600">{{ selectedWalletForTransfer.account_number }}</p>
                            <p class="text-sm font-medium text-orange-600">
                                Available Balance: {{ selectedWalletForTransfer.currency }}
                                {{ Number(selectedWalletForTransfer.balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Receiver Wallet Search -->
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <Search class="h-4 w-4" />
                            Search Receiver Wallet
                        </label>
                        <div class="relative">
                            <InputText
                                v-model="walletSearchQuery"
                                placeholder="Search users by name, email, or @username"
                                class="w-full pr-10"
                                :class="{ 'p-invalid': transferForm.errors.receiver_wallet_key }"
                            />
                            <Search class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 transform text-gray-400" />
                        </div>
                        <small v-if="transferForm.errors.receiver_wallet_key" class="text-red-500">{{
                            transferForm.errors.receiver_wallet_key
                        }}</small>
                        <small v-else class="text-gray-500">Search for users by name, email, @username, or account details</small>
                    </div>

                    <!-- Search Results with User Grouping -->
                    <div v-if="walletSearchQuery && searchResults.length > 0" class="max-h-80 space-y-4 overflow-y-auto">
                        <div class="mb-2 text-sm font-medium text-gray-700">
                            Search Results ({{ searchResults.length }} wallet{{ searchResults.length > 1 ? 's' : '' }} found)
                        </div>

                        <!-- Group results by user -->
                        <div v-for="userGroup in groupedSearchResults" :key="userGroup.user_key" class="space-y-2">
                            <!-- User Header -->
                            <div class="rounded-lg border-l-4 border-blue-500 bg-gray-100 px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <User class="h-4 w-4 text-blue-600" />
                                    <div>
                                        <div class="font-medium text-gray-800">{{ userGroup.user_name }}</div>
                                        <div class="text-xs text-gray-500">{{ userGroup.user_email }} • @{{ userGroup.user_handle }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- User's Wallets -->
                            <div class="ml-4 space-y-1">
                                <div
                                    v-for="wallet in userGroup.wallets"
                                    :key="wallet.key"
                                    @click="selectReceiverWallet(wallet)"
                                    class="cursor-pointer rounded-lg border bg-white p-3 transition-colors hover:bg-gray-50"
                                    :class="{ 'border-orange-500 bg-orange-50 ring-2 ring-orange-200': selectedReceiverWallet === wallet.key }"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="rounded-lg bg-purple-100 p-1.5">
                                                <WalletIcon class="h-4 w-4 text-purple-600" />
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-800">{{ wallet.account_name }}</div>
                                                <div class="font-mono text-sm text-gray-600">{{ wallet.account_number }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-green-600">
                                                {{ wallet.currency }}
                                            </div>
                                            <div class="text-xs text-gray-500">Digital Wallet</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Results -->
                    <div
                        v-else-if="walletSearchQuery && !isSearchingWallets && searchResults.length === 0"
                        class="rounded-lg border border-gray-200 bg-gray-50 p-6 text-center text-gray-500"
                    >
                        <Search class="mx-auto mb-3 h-8 w-8 text-gray-300" />
                        <h4 class="mb-1 font-medium text-gray-800">No users or wallets found</h4>
                        <p class="text-sm">No results for "{{ walletSearchQuery }}"</p>
                        <div class="mt-3 space-y-1 text-xs text-gray-400">
                            <div>Try searching by:</div>
                            <div>• User name (e.g., "Karen Smith")</div>
                            <div>• Email address (e.g., "karen@email.com")</div>
                            <div>• Username (e.g., "@karen")</div>
                            <div>• Account name or number</div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div v-if="isSearchingWallets" class="p-4 text-center">
                        <div class="mx-auto mb-2 h-6 w-6 animate-spin rounded-full border-2 border-orange-500 border-t-transparent"></div>
                        <p class="text-sm text-gray-500">Searching wallets...</p>
                    </div>
                </div>

                <!-- Selected Receiver Information -->
                <div v-if="selectedReceiverWallet" class="rounded-lg border border-green-200 bg-green-50 p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-green-100 p-2">
                            <UserCheck class="h-5 w-5 text-green-600" />
                        </div>
                        <div>
                            <h5 class="font-medium text-gray-800">
                                To: {{ searchResults.find((w) => w.key === selectedReceiverWallet)?.account_name }}
                            </h5>
                            <p class="text-sm text-green-600">
                                {{ searchResults.find((w) => w.key === selectedReceiverWallet)?.account_number }} - Owner:
                                {{ searchResults.find((w) => w.key === selectedReceiverWallet)?.user_name }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Transfer Form -->
                <form v-if="selectedReceiverWallet" @submit.prevent="submitTransferForm" class="space-y-4">
                    <!-- Amount Field -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <Send class="h-4 w-4" />
                            Transfer Amount ({{ selectedWalletForTransfer?.currency || 'AED' }})
                        </label>
                        <InputText
                            v-model="transferAmount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="maxTransferAmount"
                            placeholder="0.00"
                            class="w-full"
                            :class="{ 'p-invalid': transferForm.errors.amount }"
                        />
                        <small v-if="transferForm.errors.amount" class="text-red-500">{{ transferForm.errors.amount }}</small>
                        <small v-else class="text-gray-500">
                            Maximum: {{ selectedWalletForTransfer?.currency }}
                            {{ maxTransferAmount.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                        </small>
                    </div>

                    <!-- Commission Info -->
                    <div v-if="transferAmount" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                        <h5 class="mb-2 flex items-center gap-2 font-medium text-gray-800">
                            <AlertCircle class="h-4 w-4 text-yellow-600" />
                            Transfer Breakdown
                        </h5>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>Transfer Amount:</span>
                                <span class="font-medium"
                                    >{{ selectedWalletForTransfer?.currency }}
                                    {{ parseFloat(transferAmount).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span
                                >
                            </div>
                            <div class="flex justify-between">
                                <span>Commission Fee (1.5%):</span>
                                <span class="font-medium text-orange-600"
                                    >{{ selectedWalletForTransfer?.currency }}
                                    {{ calculatedCommission.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span
                                >
                            </div>
                            <hr class="my-2" />
                            <div class="flex justify-between font-medium text-red-600">
                                <span>Total Deducted:</span>
                                <span
                                    >{{ selectedWalletForTransfer?.currency }}
                                    {{ totalDebitAmount.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Description Field -->
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <MessageSquare class="h-4 w-4" />
                            Transfer Note (Optional)
                        </label>
                        <InputText v-model="transferForm.description" placeholder="Enter transfer reason or message" maxlength="255" class="w-full" />
                        <small class="text-gray-500">Add a note for this transfer (optional)</small>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <Button type="button" outlined @click="closeTransferDialog" class="flex-1" :disabled="transferForm.processing">
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :loading="transferForm.processing"
                            :disabled="
                                !transferAmount ||
                                parseFloat(transferAmount) <= 0 ||
                                totalDebitAmount > Number(selectedWalletForTransfer?.balance || 0)
                            "
                            class="flex-1 bg-orange-500 hover:bg-orange-600"
                        >
                            <Send class="mr-2 h-4 w-4" />
                            Send
                            {{
                                transferAmount
                                    ? selectedWalletForTransfer?.currency +
                                      ' ' +
                                      parseFloat(transferAmount).toLocaleString('en-US', { minimumFractionDigits: 2 })
                                    : ''
                            }}
                        </Button>
                    </div>
                </form>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.group:hover .group-hover\:translate-y-0 {
    transform: translateY(0);
}
</style>
