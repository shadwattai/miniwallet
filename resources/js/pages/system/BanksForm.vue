<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import RadioButton from 'primevue/radiobutton';
import Checkbox from 'primevue/checkbox';
import MultiSelect from 'primevue/multiselect';
import Stepper from 'primevue/stepper';
import StepList from 'primevue/steplist';
import Step from 'primevue/step';
import StepPanels from 'primevue/steppanels';
import StepPanel from 'primevue/steppanel';
import Divider from 'primevue/divider';
import { useToast } from 'primevue/usetoast';
import { router } from '@inertiajs/vue3';

interface Bank {
    id?: number;
    key?: string;
    bank_name: string;
    bank_code: string;
    bank_logo?: string;
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
    supported_currencies: string[];
    notes: string;
}

const props = defineProps<{
    visible: boolean;
    bank?: Bank | null;
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
    'bank-saved': [bank: Bank];
}>();

const toast = useToast();
const currentStep = ref('1');
const isSubmitting = ref(false);

// Form data
const formData = ref<Bank>({
    bank_name: '',
    bank_code: '',
    swift_code: '',
    country_code: 'UAE',
    bank_type: 'commercial',
    address: '',
    phone: '',
    email: '',
    website: '',
    is_active: true,
    supports_transfers: true,
    supports_deposits: true,
    supports_withdrawals: true,
    min_balance: 1000,
    max_balance: 50000000,
    daily_transfer_limit: 1000000,
    supported_currencies: ['AED'],
    notes: ''
});

// Form validation errors
const errors = ref({
    bank_name: '',
    bank_code: '',
    swift_code: '',
    country_code: '',
    bank_type: '',
    address: '',
    phone: '',
    email: '',
    website: '',
    min_balance: '',
    max_balance: '',
    daily_transfer_limit: ''
});

// Options for dropdowns
const countryOptions = [
    { label: 'United Arab Emirates', value: 'UAE' },
    { label: 'United States', value: 'US' },
    { label: 'United Kingdom', value: 'UK' },
    { label: 'Saudi Arabia', value: 'SA' },
    { label: 'India', value: 'IN' },
    { label: 'Singapore', value: 'SG' }
];

const currencyOptions = [
    { label: 'AED - UAE Dirham', value: 'AED' },
    // { label: 'USD - US Dollar', value: 'USD' },
    // { label: 'EUR - Euro', value: 'EUR' },
    // { label: 'GBP - British Pound', value: 'GBP' },
    // { label: 'SAR - Saudi Riyal', value: 'SAR' },
    // { label: 'INR - Indian Rupee', value: 'INR' },
    // { label: 'SGD - Singapore Dollar', value: 'SGD' }
];

const bankTypeOptions = [
    { label: 'Commercial Bank', value: 'commercial' },
    { label: 'Islamic Bank', value: 'islamic' }
];

// Computed properties
const isEditMode = computed(() => !!props.bank);
const dialogTitle = computed(() => isEditMode.value ? 'Edit Bank' : 'Add New Bank');

// Validation functions
const validateStep1 = () => {
    let isValid = true;
    errors.value.bank_name = '';
    errors.value.bank_code = '';
    errors.value.swift_code = '';

    if (!formData.value.bank_name.trim()) {
        errors.value.bank_name = 'Bank name is required';
        isValid = false;
    }

    if (!formData.value.bank_code.trim()) {
        errors.value.bank_code = 'Bank code is required';
        isValid = false;
    } else if (formData.value.bank_code.length > 10) {
        errors.value.bank_code = 'Bank code must be 10 characters or less';
        isValid = false;
    }

    if (!formData.value.swift_code.trim()) {
        errors.value.swift_code = 'SWIFT code is required';
        isValid = false;
    } else {
        const swiftCode = formData.value.swift_code.trim().toUpperCase();
        if (swiftCode.length < 8 || swiftCode.length > 11) {
            errors.value.swift_code = 'SWIFT code must be between 8 and 11 characters';
            isValid = false;
        } else if (!/^[A-Z0-9]+$/.test(swiftCode)) {
            errors.value.swift_code = 'SWIFT code can only contain uppercase letters and numbers';
            isValid = false;
        }
    }

    return isValid;
};

const validateStep2 = () => {
    let isValid = true;
    errors.value.address = '';
    errors.value.phone = '';
    errors.value.email = '';
    errors.value.website = '';

    if (!formData.value.address.trim()) {
        errors.value.address = 'Address is required';
        isValid = false;
    }

    if (!formData.value.phone.trim()) {
        errors.value.phone = 'Phone number is required';
        isValid = false;
    }

    if (!formData.value.email.trim()) {
        errors.value.email = 'Email is required';
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.value.email)) {
        errors.value.email = 'Invalid email format';
        isValid = false;
    }

    if (!formData.value.website.trim()) {
        errors.value.website = 'Website is required';
        isValid = false;
    } else if (!/^https?:\/\/.+\..+/.test(formData.value.website)) {
        errors.value.website = 'Invalid website URL';
        isValid = false;
    }

    return isValid;
};

const validateStep4 = () => {
    let isValid = true;
    errors.value.min_balance = '';
    errors.value.max_balance = '';
    errors.value.daily_transfer_limit = '';

    if (formData.value.min_balance < 0) {
        errors.value.min_balance = 'Minimum balance must be positive';
        isValid = false;
    }

    if (formData.value.max_balance <= formData.value.min_balance) {
        errors.value.max_balance = 'Maximum balance must be greater than minimum balance';
        isValid = false;
    }

    if (formData.value.daily_transfer_limit <= 0) {
        errors.value.daily_transfer_limit = 'Daily transfer limit must be positive';
        isValid = false;
    }

    return isValid;
};

// Step navigation functions
const nextStep = (stepValue: string) => {
    switch (currentStep.value) {
        case '1':
            if (validateStep1()) {
                currentStep.value = stepValue;
            }
            break;
        case '2':
            if (validateStep2()) {
                currentStep.value = stepValue;
            }
            break;
        case '3':
            currentStep.value = stepValue;
            break;
        case '4':
            if (validateStep4()) {
                currentStep.value = stepValue;
            }
            break;
        default:
            currentStep.value = stepValue;
    }
};

const previousStep = (stepValue: string) => {
    currentStep.value = stepValue;
};

// Form submission
const submitForm = async () => {
    // Validate all steps
    if (!validateStep1() || !validateStep2() || !validateStep4()) {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please fix the form errors before submitting'
        });
        return;
    }

    try {
        isSubmitting.value = true;

        // Prepare form data for API
        const payload = {
            ...formData.value,
            bank_code: formData.value.bank_code.toUpperCase(),
            swift_code: formData.value.swift_code.toUpperCase(),
            supported_currencies: JSON.stringify(formData.value.supported_currencies)
        };

        const url = isEditMode.value ? `/banks/${props.bank?.key}` : '/banks';
        const method = isEditMode.value ? 'put' : 'post';

        await router[method](url, payload);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Bank ${isEditMode.value ? 'updated' : 'added'} successfully`
        });

        emit('bank-saved', payload);
        closeDialog();

    } catch (error: any) {
        console.error('Form submission error:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error?.response?.data?.message || `Failed to ${isEditMode.value ? 'update' : 'add'} bank`
        });
    } finally {
        isSubmitting.value = false;
    }
};

// Dialog management
const closeDialog = () => {
    emit('update:visible', false);
    currentStep.value = '1';
    resetForm();
};

const resetForm = () => {
    formData.value = {
        bank_name: '',
        bank_code: '',
        swift_code: '',
        country_code: 'UAE',
        bank_type: 'commercial',
        address: '',
        phone: '',
        email: '',
        website: '',
        is_active: true,
        supports_transfers: true,
        supports_deposits: true,
        supports_withdrawals: true,
        min_balance: 1000,
        max_balance: 50000000,
        daily_transfer_limit: 1000000,
        supported_currencies: ['AED'],
        notes: ''
    };

    Object.keys(errors.value).forEach(key => errors.value[key as keyof typeof errors.value] = '');
};

// Watch for bank prop changes (for edit mode)
watch(() => props.bank, (newBank) => {
    if (newBank) {
        formData.value = {
            ...newBank,
            supported_currencies: typeof newBank.supported_currencies === 'string'
                ? JSON.parse(newBank.supported_currencies)
                : newBank.supported_currencies
        };
    }
}, { immediate: true });

// Auto-generate bank code from bank name
watch(() => formData.value.bank_name, (newName) => {
    if (newName && !isEditMode.value) {
        const code = newName
            .split(' ')
            .map(word => word.charAt(0))
            .join('')
            .toUpperCase()
            .substring(0, 10);
        formData.value.bank_code = code;
    }
});
</script>

<template>
    <Dialog :visible="props.visible" @update:visible="emit('update:visible', $event)" modal :header="dialogTitle"
        class="w-[800px]" :closable="!isSubmitting">
        <form @submit.prevent="submitForm">
            <Divider />
            <Stepper v-model:value="currentStep" linear>
                <StepList>
                    <Step value="1">Basic Info</Step>
                    <Step value="2">Contact</Step>
                    <Step value="3">Configuration</Step>
                    <Step value="4">Limits</Step>
                    <Step value="5">Additional</Step>
                </StepList>
                <Divider />

                <StepPanels class="mt-4">
                    <!-- Step 1: Basic Information -->
                    <StepPanel value="1">
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold">Basic Information</h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Bank Name *</label>
                                    <InputText v-model="formData.bank_name" :class="{ 'p-invalid': errors.bank_name }"
                                        placeholder="Enter bank name" class="w-full" />
                                    <small class="text-red-500" v-if="errors.bank_name">{{ errors.bank_name }}</small>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Bank Code *</label>
                                    <InputText v-model="formData.bank_code" :class="{ 'p-invalid': errors.bank_code }"
                                        placeholder="e.g., ENBD001" class="w-full" maxlength="10" />
                                    <small class="text-red-500" v-if="errors.bank_code">{{ errors.bank_code }}</small>
                                    <small class="text-gray-500" v-else>Max 10 characters</small>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">SWIFT Code *</label>
                                    <InputText v-model="formData.swift_code" :class="{ 'p-invalid': errors.swift_code }"
                                        placeholder="e.g., EBILAEAD" class="w-full" maxlength="11" />
                                    <small class="text-red-500" v-if="errors.swift_code">{{ errors.swift_code }}</small>
                                    <small class="text-gray-500" v-else>8-11 characters</small>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Country *</label>
                                    <Dropdown v-model="formData.country_code" :options="countryOptions"
                                        optionLabel="label" optionValue="value" placeholder="Select country"
                                        class="w-full" />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">Bank Type *</label>
                                <div class="flex gap-4">
                                    <div v-for="option in bankTypeOptions" :key="option.value"
                                        class="flex items-center">
                                        <RadioButton v-model="formData.bank_type" :inputId="option.value"
                                            :value="option.value" />
                                        <label :for="option.value" class="ml-2">{{ option.label }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Divider />
                        <div class="flex justify-end mt-6">
                            <Button label="Next" icon="pi pi-arrow-right" @click="nextStep('2')" />
                        </div>
                    </StepPanel>

                    <!-- Step 2: Contact Details -->
                    <StepPanel value="2">
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold">Contact Information</h3>

                            <div>
                                <label class="block text-sm font-medium mb-1">Address *</label>
                                <Textarea v-model="formData.address" :class="{ 'p-invalid': errors.address }"
                                    placeholder="Enter bank address" rows="3" class="w-full" />
                                <small class="text-red-500" v-if="errors.address">{{ errors.address }}</small>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Phone *</label>
                                    <InputText v-model="formData.phone" :class="{ 'p-invalid': errors.phone }"
                                        placeholder="+971-4-1234567" class="w-full" />
                                    <small class="text-red-500" v-if="errors.phone">{{ errors.phone }}</small>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Email *</label>
                                    <InputText v-model="formData.email" :class="{ 'p-invalid': errors.email }"
                                        placeholder="contact@bank.com" class="w-full" type="email" />
                                    <small class="text-red-500" v-if="errors.email">{{ errors.email }}</small>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Website *</label>
                                <InputText v-model="formData.website" :class="{ 'p-invalid': errors.website }"
                                    placeholder="https://www.bank.com" class="w-full" type="url" />
                                <small class="text-red-500" v-if="errors.website">{{ errors.website }}</small>
                            </div>
                        </div>

                        <Divider />
                        <div class="flex justify-between mt-6">
                            <Button label="Previous" outlined icon="pi pi-arrow-left" severity="secondary"
                                @click="previousStep('1')" />
                            <Button label="Next" icon="pi pi-arrow-right" @click="nextStep('3')" />
                        </div>
                    </StepPanel>

                    <!-- Step 3: Banking Configuration -->
                    <StepPanel value="3">
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold">Banking Configuration</h3>

                            <div>
                                <label class="block text-sm font-medium mb-2">Bank Status</label>
                                <div class="flex items-center">
                                    <Checkbox v-model="formData.is_active" inputId="is_active" :binary="true" />
                                    <label for="is_active" class="ml-2">Bank is Active</label>
                                </div>
                            </div>

                            <Divider />

                            <div>
                                <label class="block text-sm font-medium mb-2">Supported Services</label>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <Checkbox v-model="formData.supports_transfers" inputId="supports_transfers"
                                            :binary="true" />
                                        <label for="supports_transfers" class="ml-2">Supports Transfers</label>
                                    </div>
                                    <div class="flex items-center">
                                        <Checkbox v-model="formData.supports_deposits" inputId="supports_deposits"
                                            :binary="true" />
                                        <label for="supports_deposits" class="ml-2">Supports Deposits</label>
                                    </div>
                                    <div class="flex items-center">
                                        <Checkbox v-model="formData.supports_withdrawals" inputId="supports_withdrawals"
                                            :binary="true" />
                                        <label for="supports_withdrawals" class="ml-2">Supports Withdrawals</label>
                                    </div>
                                </div>
                            </div>

                            <Divider />

                            <div>
                                <label class="block text-sm font-medium mb-1">Supported Currencies</label>
                                <MultiSelect v-model="formData.supported_currencies" :options="currencyOptions"
                                    optionLabel="label" optionValue="value" placeholder="Select currencies"
                                    class="w-full" :maxSelectedLabels="3" />
                            </div>
                        </div>

                        <Divider />
                        <div class="flex justify-between mt-6">
                            <Button label="Previous" outlined icon="pi pi-arrow-left" severity="secondary"
                                @click="previousStep('2')" />
                            <Button label="Next" icon="pi pi-arrow-right" @click="nextStep('4')" />
                        </div>
                    </StepPanel>

                    <!-- Step 4: Financial Limits -->
                    <StepPanel value="4">
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold">Financial Limits</h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Minimum Balance *</label>
                                    <InputNumber v-model="formData.min_balance"
                                        :class="{ 'p-invalid': errors.min_balance }" mode="currency" currency="AED"
                                        locale="en-US" class="w-full" :min="0" />
                                    <small class="text-red-500" v-if="errors.min_balance">{{ errors.min_balance
                                        }}</small>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Maximum Balance *</label>
                                    <InputNumber v-model="formData.max_balance"
                                        :class="{ 'p-invalid': errors.max_balance }" mode="currency" currency="AED"
                                        locale="en-US" class="w-full" :min="1" />
                                    <small class="text-red-500" v-if="errors.max_balance">{{ errors.max_balance
                                        }}</small>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Daily Transfer Limit *</label>
                                <InputNumber v-model="formData.daily_transfer_limit"
                                    :class="{ 'p-invalid': errors.daily_transfer_limit }" mode="currency" currency="AED"
                                    locale="en-US" class="w-full" :min="1" />
                                <small class="text-red-500" v-if="errors.daily_transfer_limit">{{
                                    errors.daily_transfer_limit }}</small>
                            </div>
                        </div>

                        <Divider />
                        <div class="flex justify-between mt-6">
                            <Button label="Previous" outlined icon="pi pi-arrow-left" severity="secondary"
                                @click="previousStep('3')" />
                            <Button label="Next" icon="pi pi-arrow-right" @click="nextStep('5')" />
                        </div>
                    </StepPanel>

                    <!-- Step 5: Additional Settings -->
                    <StepPanel value="5">
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold">Additional Settings</h3>

                            <div>
                                <label class="block text-sm font-medium mb-1">Notes</label>
                                <Textarea v-model="formData.notes"
                                    placeholder="Add any additional notes about this bank..." rows="4" class="w-full" />
                            </div>
                        </div>

                        <Divider />
                        <div class="flex justify-between mt-6">
                            <Button label="Previous" outlined icon="pi pi-arrow-left" severity="secondary"
                                @click="previousStep('4')" />
                            <Button :label="isEditMode ? 'Update Bank' : 'Add Bank'" icon="pi pi-check" type="submit"
                                :loading="isSubmitting" />
                        </div>
                    </StepPanel>
                </StepPanels>
            </Stepper>
        </form>

        <!-- <template #footer>
            <Button label="Cancel" severity="secondary" @click="closeDialog" :disabled="isSubmitting" />
        </template> -->
    </Dialog>
</template>