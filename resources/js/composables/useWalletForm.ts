import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { 
    CreditCard,
    PiggyBank
} from 'lucide-vue-next';

export interface Bank {
    key: string;
    bank_name: string;
    bank_code: string;
    swift_code: string;
    supported_currencies: string[] | string;
}

export interface WalletFormData {
    account_name: string;
    account_type: string;
    bank_key: string;
    currency: string;
    initial_balance: number;
    is_default: boolean;
}

export function useWalletForm(banks?: Bank[]) {
    // Account types configuration
    const accountTypes = [
        { label: 'Digital Wallet', value: 'wallet', icon: CreditCard },
        { label: 'Savings Account', value: 'savings', icon: PiggyBank }
    ];

    // Currency mapping for display labels
    const currencyLabels: Record<string, string> = {
        'AED': 'AED - UAE Dirham',
        'USD': 'USD - US Dollar',
        'EUR': 'EUR - Euro',
        'GBP': 'GBP - British Pound',
        'SAR': 'SAR - Saudi Riyal',
        'INR': 'INR - Indian Rupee',
        'SGD': 'SGD - Singapore Dollar',
        'JPY': 'JPY - Japanese Yen',
        'CHF': 'CHF - Swiss Franc',
        'CAD': 'CAD - Canadian Dollar',
        'AUD': 'AUD - Australian Dollar'
    };

    // Form setup
    const form = useForm<WalletFormData>({
        account_name: '',
        account_type: 'wallet',
        bank_key: '',
        currency: 'AED',
        initial_balance: 0,
        is_default: false
    });

    // Parse supported currencies (handles both string and array formats)
    const parseSupportedCurrencies = (supportedCurrencies: string[] | string): string[] => {
        if (typeof supportedCurrencies === 'string') {
            try {
                return JSON.parse(supportedCurrencies);
            } catch (e) {
                console.error('Failed to parse supported_currencies:', e);
                return [];
            }
        }
        return Array.isArray(supportedCurrencies) ? supportedCurrencies : [];
    };

    // Get available currencies for selected bank
    const getAvailableCurrencies = (bankKey: string, banksData: Bank[]) => {
        if (!bankKey || !banksData) return [];
        
        const selectedBank = banksData.find(bank => bank.key === bankKey);
        if (!selectedBank) return [];
        
        const supportedCurrencies = parseSupportedCurrencies(selectedBank.supported_currencies);
        
        return supportedCurrencies.map(currency => ({
            label: currencyLabels[currency] || `${currency} - ${currency}`,
            value: currency
        }));
    };

    // Get selected account type
    const selectedAccountType = computed(() => {
        return accountTypes.find(type => type.value === form.account_type);
    });

    // Reset currency when bank changes
    const resetCurrencyForBank = (bankKey: string, banksData: Bank[]) => {
        if (bankKey) {
            const selectedBank = banksData?.find(bank => bank.key === bankKey);
            if (selectedBank) {
                const supportedCurrencies = parseSupportedCurrencies(selectedBank.supported_currencies);
                if (supportedCurrencies.length > 0) {
                    form.currency = supportedCurrencies[0];
                } else {
                    form.currency = 'AED';
                }
            } else {
                form.currency = 'AED';
            }
        } else {
            form.currency = 'AED';
        }
    };

    // Submit form
    const submitForm = (onSuccess?: () => void) => {
        form.post('/miniwallet/wallet', {
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            }
        });
    };

    return {
        // Data
        form,
        accountTypes,
        currencyLabels,
        
        // Computed
        selectedAccountType,
        
        // Methods
        parseSupportedCurrencies,
        getAvailableCurrencies,
        resetCurrencyForBank,
        submitForm
    };
}