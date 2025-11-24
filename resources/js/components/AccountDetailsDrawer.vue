<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import Card from 'primevue/card'; 
import Avatar from 'primevue/avatar';
import ProgressSpinner from 'primevue/progressspinner';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Tag from 'primevue/tag';
import Fieldset from 'primevue/fieldset';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import AddModuleDialog from '@/components/AddModuleDialog.vue';
import InputText from 'primevue/inputtext';
import Dialog from 'primevue/dialog';
import { router } from '@inertiajs/vue3'; 
import Textarea from 'primevue/textarea';
import FloatLabel from 'primevue/floatlabel';
import InputMask from 'primevue/inputmask';
import Select from 'primevue/select';



const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    account: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['update:visible']);

interface AccountDetails {
    acc_name: string;
    description?: string;
    status: string;
    industry_name?: string;
    start_date?: string | number | Date;
    created_at?: string | number | Date;
    max_bnses?: number;
    max_staff?: number;
    sender_name?: string;
    sender_email?: string;
    logo_path?: string;
}

const toast = useToast();
const confirm = useConfirm();
const loading = ref(false);
const activeTab = ref("0");
const accountDetails = ref<AccountDetails | null>(null);
interface Business {
    key: string;
    status: string;
    [key: string]: any;
}
const businesses = ref<Business[]>([]);
interface Module {
    key: string;
    app_key: string;
    brand_label?: string;
    brand_name?: string;
    app_name?: string;
    logo_path?: string;
    description?: string;
    [key: string]: any;
}
const modules = ref<Module[]>([]);
const removingModuleKey = ref(null);
const showAddModuleDialog = ref(false);
const showEditFieldModal = ref(false);
const editField = ref('');
const editValue = ref('');
const logoUploadRefs = ref<Record<string, HTMLInputElement | null>>({});
const showAddBusinessDialog = ref(false);
const newBusiness = reactive({
    bns_name: '',
    description: '',
    tin_num: '',
    vat_num: '',
    bns_phone: '',
    bns_email: '',
    contact_person: '',
    phys_address: '',
    logo: null
});
const logoPreview = ref<string | null>(null);
const businessErrors = reactive({
    bns_name: '',
    tin_num: '',
    bns_email: '',
    description: ''
});
const isBusinessFormValid = ref(true);
const showAccountStatusDialog = ref(false);
const selectedAccountStatus = ref('');
const accountStatusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },

    { label: 'Closed', value: 'closed' },
    { label: 'Pending', value: 'pending' },
    { label: 'Stopped', value: 'stopped' },

    { label: 'Suspended', value: 'suspended' },
];

const showEditBusinessDialog = ref(false);
const editingBusiness = reactive({
    key: '',
    bns_name: '',
    description: '',
    status: ''
});
const editBusinessErrors = reactive({
    bns_name: '',
    description: ''
});
const isEditBusinessFormValid = ref(true);

const businessStatusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Suspended', value: 'suspended' },
    { label: 'Closed', value: 'closed' }
]; 


// Watch for account changes to fetch details
watch(() => props.account, async (newAccount) => {
    if (newAccount && props.visible) {
        await fetchAccountDetails(newAccount.key);
    }
});

// Watch for visibility changes
watch(() => props.visible, async (isVisible) => {
    if (isVisible && props.account) {
        await fetchAccountDetails(props.account.key);
    }
});

const fetchAccountDetails = async (accountKey: string) => {
    try {
        loading.value = true;

        const response = await axios.get(`/internals/accounts/${accountKey}/details`);

        accountDetails.value = response.data.account;
        businesses.value = response.data.businesses || [];
        modules.value = response.data.modules || [];

    } catch (error) {
        console.error('Error fetching account details:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load account details',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const closeDrawer = () => {
    emit('update:visible', false);
    accountDetails.value = null;
    businesses.value = [];
    modules.value = [];
    activeTab.value = "0";
};

const getStatusSeverity = (status: string) => {
    switch (status?.toLowerCase()) {
        case 'active': return 'success';
        case 'inactive': return 'danger';
        case 'pending': return 'warning';
        default: return 'info';
    }
};
const formatDate = (date: string | number | Date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString();
};

const confirmRemoveModule = (module: any) => {
    confirm.require({
        message: `Are you sure you want to remove the "${module.brand_label || module.brand_name || module.app_name}" module from this account?`,
        header: 'REMOVE MODULE',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true
        },
        acceptProps: {
            label: 'Remove',
            severity: 'danger'
        },
        accept: () => {
            removeModule(module);
        },
        reject: () => {
            // User cancelled, do nothing
        }
    });
};

const removeModule = async (module: any) => {
    try {
        removingModuleKey.value = module.app_key; // Use app_key for loading state since that's what we're removing

        const response = await axios.delete(`/internals/accounts/${props.account.key}/modules/${module.app_key}`);

        // Remove the module from the local array using the unique key from auth_accounts_modules
        modules.value = modules.value.filter(m => m.key !== module.key);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Module removed successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error removing module:', error);
        const err = error as any;
        const errorMessage = err.response?.data?.error || 'Failed to remove module';
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: errorMessage,
            life: 3000
        });
    } finally {
        removingModuleKey.value = null;
    }
};

const openAddModuleDialog = () => {
    showAddModuleDialog.value = true;
};

const onModuleAdded = async () => {
    // Refresh the account details to show the newly added module
    if (props.account) {
        await fetchAccountDetails(props.account.key);
    }
};

function editFieldData(label: string, value: any) {
    // console.log('Editing field data:', { label, value });
    editField.value = label;
    editValue.value = value;
    showEditFieldModal.value = true;
}

async function saveEditField() {
    if (!props.account || !editField.value) {
        showEditFieldModal.value = false;
        return;
    }
    loading.value = true;
    // Map field label to backend field name
    const fieldMap: { [key: string]: string } = {
        'Account': 'acc_name',
        'Description': 'description',
        'Status': 'status',
        'Industry': 'industry_name',
        'Effective date': 'start_date',
        'Subscription end': 'expiry_date',
        'Created': 'created_at',
        'Max Businesses': 'max_bnses',
        'Max Employees': 'max_staff',
        'Sender Name': 'sender_name',
        'Sender Email': 'sender_email'
    };
    const fieldName = fieldMap[editField.value] || editField.value;
    const payload = { [fieldName]: editValue.value };
    router.put(`/internals/accounts/${props.account.key}`, payload, {
        preserveState: true,
        onSuccess: () => {
            if (accountDetails.value) {
                (accountDetails.value as Record<string, any>)[fieldName] = editValue.value;
            }
            showEditFieldModal.value = false;
            toast.add({ severity: 'success', summary: 'Saved', detail: `${editField.value} updated.`, life: 2000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update field.', life: 3000 });
        },
        onFinish: () => {
            loading.value = false;
        }
    });
}

function cancelEditField() {
    showEditFieldModal.value = false;
}

function triggerLogoUpload(businessKey: string) {
    if (logoUploadRefs.value[businessKey]) {
        logoUploadRefs.value[businessKey].click();
    }
}

function setLogoUploadRef(el: HTMLInputElement | null, key: string) {
    if (el) logoUploadRefs.value[key] = el;
}

async function handleLogoUpload(event: Event, business: any) {
    const fileInput = event.target as HTMLInputElement;
    const file = fileInput.files && fileInput.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('logo', file);
    formData.append('business_key', business.key);
    try {
        const response = await axios.post('/internals/companies/upload-logo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        if (response.data.logo_path) {
            business.logo_path = response.data.logo_path;
            toast.add({ severity: 'success', summary: 'Logo Uploaded', detail: 'Logo updated successfully.', life: 2000 });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to upload logo.', life: 3000 });
    }
}

function openAddBusinessDialog() {
    showAddBusinessDialog.value = true;
}

function handleBusinessLogoUpload(event: any) {
    const file = event.files?.[0] || (event.target?.files && event.target.files[0]);
    newBusiness.logo = file;
    if (file) {
        const reader = new FileReader();
        reader.onload = e => logoPreview.value = e.target?.result as string;
        reader.readAsDataURL(file);
    } else {
        logoPreview.value = null;
    }
}

function resetNewBusiness() {
    Object.assign(newBusiness, {
        bns_name: '',
        description: '',
        tin_num: '',
        vat_num: '',
        bns_phone: '',
        bns_email: '',
        contact_person: '',
        phys_address: '',
        logo: null
    });
}

function validateBusinessForm() {
    businessErrors.bns_name = newBusiness.bns_name.trim() ? '' : 'Business name is required.';
    businessErrors.tin_num = newBusiness.tin_num.trim() ? '' : 'TIN number is required.';
    businessErrors.bns_email = newBusiness.bns_email.trim() && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(newBusiness.bns_email) ? '' : 'Valid email is required.';
    isBusinessFormValid.value = !businessErrors.bns_name && !businessErrors.tin_num && !businessErrors.bns_email;
}

watch(newBusiness, validateBusinessForm, { deep: true });

function submitNewBusiness() {
    validateBusinessForm();
    if (!isBusinessFormValid.value) return;

    const formData = new FormData();
    Object.entries(newBusiness).forEach(([key, value]) => {
        // Ignore logo at this step
        if (key !== 'logo' && value) formData.append(key, value);
    });
    formData.append('account_key', props.account.key);

    router.post('/internals/accounts/add-business', formData, {
        onSuccess: () => {
            showAddBusinessDialog.value = false;
            resetNewBusiness();
            fetchAccountDetails(props.account.key);
            toast.add({ severity: 'success', summary: 'Business Added', detail: 'Business created successfully.', life: 2000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to add business.', life: 3000 });
        }
    });
}

async function confirmBusinessStatusAction(business: any) {
    // Toggle between 'active' and 'suspended'
    const newStatus = business.status === 'active' ? 'suspended' : 'active';
    confirm.require({
        message: `Are you sure you want to ${newStatus === 'active' ? 'activate' : 'suspend'} this business?`,
        header: `${newStatus === 'active' ? 'ACTIVATE' : 'SUSPEND'} BUSINESS`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true
        },
        acceptProps: {
            label: newStatus === 'active' ? 'Activate' : 'Suspend',
            severity: newStatus === 'active' ? 'success' : 'danger'
        },
        accept: async () => {
            try {
                const response = await axios.patch(`/internals/companies/${business.key}/status`, { status: newStatus });
                business.status = newStatus;
                toast.add({ severity: 'success', summary: 'Success', detail: `Business ${newStatus === 'active' ? 'activated' : 'suspended'} successfully.`, life: 2000 });
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update business status.', life: 3000 });
            }
        },
        reject: () => {}
    });
}

function openAccountStatusDialog() {
    selectedAccountStatus.value = accountDetails.value?.status || 'active';
    showAccountStatusDialog.value = true;
}

async function changeAccountStatus() {
    if (!props.account || !selectedAccountStatus.value) return;
    try {
        const response = await axios.patch(`/internals/accounts/${props.account.key}/status`, { status: selectedAccountStatus.value });
        if (accountDetails.value) {
            accountDetails.value.status = selectedAccountStatus.value;
        }
        // Update all businesses in the UI to match the selected status
        businesses.value.forEach(b => {
            b.status = selectedAccountStatus.value;
        });
        toast.add({ severity: 'success', summary: 'Status Changed', detail: 'Account and businesses status updated.', life: 2000 });
        showAccountStatusDialog.value = false;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to change status.', life: 3000 });
    }
}

const accountLogoUploadRef = ref<HTMLInputElement | null>(null);
const accountLogoPreview = ref<string | null>(null);

function triggerAccountLogoUpload() {
    if (accountLogoUploadRef.value) {
        accountLogoUploadRef.value.click();
    }
}

async function handleAccountLogoUpload(event: Event) {
    const fileInput = event.target as HTMLInputElement;
    const file = fileInput.files && fileInput.files[0];
    if (!file || !props.account) return;
    const formData = new FormData();
    formData.append('logo', file);
    formData.append('account_key', props.account.key);
    try {
        const response = await axios.post('/internals/accounts/upload-logo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        if (response.data.logo_path) {
            if (accountDetails.value) accountDetails.value.logo_path = response.data.logo_path;
            accountLogoPreview.value = response.data.logo_path;
            toast.add({ severity: 'success', summary: 'Logo Uploaded', detail: 'Account logo updated.', life: 2000 });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to upload account logo.', life: 3000 });
    }
}

function handleAccountLogoError(event: Event) {
    if (accountDetails.value) {
        accountDetails.value.logo_path = '';
    }
}

function handleBusinessLogoError(slot: any) {
    if (slot.data) {
        slot.data.logo_path = '/logos/orgs/building.png';
    }
}

function editBusinessData(business: any) {
    editingBusiness.key = business.key;
    editingBusiness.bns_name = business.bns_name || '';
    editingBusiness.description = business.description || '';
    editingBusiness.status = business.status || 'active';
    showEditBusinessDialog.value = true;
}

function validateEditBusinessForm() {
    editBusinessErrors.bns_name = editingBusiness.bns_name.trim() ? '' : 'Business name is required.';
    editBusinessErrors.description = '';
    isEditBusinessFormValid.value = !editBusinessErrors.bns_name;
}

watch(editingBusiness, validateEditBusinessForm, { deep: true });

async function submitEditBusiness() {
    validateEditBusinessForm();
    if (!isEditBusinessFormValid.value) return;

    try {
        const response = await axios.patch(`/internals/companies/${editingBusiness.key}`, {
            bns_name: editingBusiness.bns_name,
            description: editingBusiness.description,
            status: editingBusiness.status
        });

        // Update the business in the local array
        const businessIndex = businesses.value.findIndex(b => b.key === editingBusiness.key);
        if (businessIndex !== -1) {
            businesses.value[businessIndex].bns_name = editingBusiness.bns_name;
            businesses.value[businessIndex].description = editingBusiness.description;
            businesses.value[businessIndex].status = editingBusiness.status;
        }

        showEditBusinessDialog.value = false;
        toast.add({ 
            severity: 'success', 
            summary: 'Business Updated', 
            detail: 'Business details updated successfully.', 
            life: 2000 
        });
    } catch (error) {
        toast.add({ 
            severity: 'error', 
            summary: 'Error', 
            detail: 'Failed to update business details.', 
            life: 3000 
        });
    }
}
</script>

<template>
    <Drawer :visible="visible" position="right" header="ACCOUNT DETAILS" :style="{ width: '60vw' }" :modal="true"
        @update:visible="closeDrawer">
        <Card class="h-full border border-gray-200">
            <template #content>
                <div v-if="loading" class="flex justify-center items-center h-64">
                    <ProgressSpinner />
                    <span class="ml-3" hidden>Loading account details...</span>
                </div>

                <div v-else-if="accountDetails" class="space-y-4">
                    <!-- Account Header -->
                    <Card class="mb-4 h-full border border-gray-200">
                        <template #content>
                            <div class="flex items-center gap-4">
                                <div class="relative inline-flex items-center justify-center w-20 h-20 border border-gray-200 cursor-pointer" style="border-radius: 6px; overflow: hidden;">
                                    <Avatar
                                        v-if="accountDetails.logo_path && accountDetails.logo_path !== ''"
                                        :image="accountDetails.logo_path"
                                        size="xlarge"
                                        shape="circle"
                                        class="bg-blue-500 text-white text-3xl cursor-pointer"
                                        @click="triggerAccountLogoUpload"
                                        @error="handleAccountLogoError"
                                    />
                                    <Avatar
                                        v-else
                                        :label="accountDetails.acc_name?.charAt(0)?.toUpperCase()"
                                        size="xlarge"
                                        shape="circle"
                                        class="bg-blue-500 text-white text-3xl cursor-pointer"
                                        @click="triggerAccountLogoUpload"
                                    />
                                    <input type="file" accept="image/*" ref="accountLogoUploadRef" style="display:none" @change="handleAccountLogoUpload" />
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start w-full">
                                        <div class="flex flex-col">
                                            <h2 class="text-2xl font-bold mb-1">
                                                {{ accountDetails.acc_name }} 
                                            </h2>
                                            <p class="text-gray-600 mb-2">
                                                {{ accountDetails.description || '' }}
                                            </p>
                                            <small>WITH US SINCE: {{ formatDate(accountDetails.start_date ?? '') }}</small>
                                        </div>

                                        <div class="flex gap-2">
                                            <Button
                                                type="button"
                                                :label="accountDetails.status"
                                                :severity="accountDetails.status?.toLowerCase() === 'active' ? 'success' : 'danger'"
                                                size="small"
                                                variant="outlined"
                                                class="capitalize"
                                                @click="openAccountStatusDialog"
                                            />

                                            <Button type="button" :label="accountDetails.industry_name || 'Unspecified'"
                                                size="small" severity="info" variant="outlined" disabled/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <Tabs v-model:value="activeTab" class="w-full">
                        <TabList>
                            <Tab value="0">ACCOUNT</Tab>
                            <Tab value="1">BUSINESSES ({{ businesses.length }})</Tab>
                            <Tab value="2">MODULES ({{ modules.length }})</Tab>
                            <Tab value="3">SUBSCRIPTIONS ({{ 0 }} invoices)</Tab>
                        </TabList>

                        <TabPanels class="mt-4">
                            <!-- Account Information Tab -->
                            <TabPanel value="0">
                                <Card class="mb-4 h-full border border-gray-200">
                                    <template #content>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="col-span-2 grid grid-cols-3 gap-4">
                                                <div class="col-span-3">
                                                    <DataTable :value="[
                                                        { label: 'Account', value: accountDetails.acc_name },
                                                        { label: 'Description', value: accountDetails.description || 'No description available' },
                                                        // { label: 'Status', value: accountDetails.status, isTag: true },
                                                        { label: 'Industry', value: accountDetails.industry_name || 'No Industry' },
                                                        { label: 'Effective date', value: formatDate(accountDetails.start_date ?? '') },
                                                        { label: 'Max Businesses', value: accountDetails.max_bnses || 1 },
                                                        { label: 'Max Employees', value: accountDetails.max_staff || 10 },
                                                        { label: 'Sender Name', value: accountDetails.sender_name || 'N/A' },
                                                        { label: 'Sender Email', value: accountDetails.sender_email || 'N/A' }
                                                    ]" class="w-full">
                                                        <Column field="label" style="width: 40%;" />
                                                        <Column style="width: 60%">
                                                            <template #body="slot">
                                                                <template v-if="slot.data.isTag">
                                                                    <Tag :value="slot.data.value"
                                                                        :severity="getStatusSeverity(slot.data.value)" />
                                                                </template>
                                                                <template v-else>
                                                                    {{ slot.data.value }}
                                                                </template>
                                                            </template>
                                                        </Column>
                                                        <Column>
                                                            <template #body="slot"> 
                                                                <Button label="Edit" severity="info" size="small"
                                                                    icon="pi pi-pencil" variant="outlined"
                                                                    @click="editFieldData(slot.data.label, slot.data.value)" />
                                                            </template>
                                                        </Column>
                                                    </DataTable>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </Card>
                            </TabPanel>

                            <!-- Businesses Tab -->
                            <TabPanel value="1">
                                <div class="flex justify-between items-center mb-4">
                                    <span
                                        class="text-lg font-bold bg-gradient-to-r from-pink-400 to-blue-600 bg-clip-text text-transparent">
                                        {{ accountDetails.acc_name }}
                                    </span>
 
                                    <Button label="ADD BUSINESS" icon="pi pi-plus" severity="info" size="small"
                                        @click="openAddBusinessDialog" 
                                        :disabled="businesses.length >= (accountDetails.max_bnses || 1)" />
                                </div>

                                <div v-if="businesses.length === 0" class="text-center py-8 text-gray-500">
                                    <div class="mb-4">
                                        <i class="pi pi-building text-4xl text-gray-300"></i>
                                    </div>
                                    <p class="text-lg mb-2">No businesses found for this account</p>
                                    <p class="text-sm">Click "ADD BUSINESS" to get started</p>
                                </div>

                                <div v-else>
                                    <div class="space-y-6">

                                        <Fieldset class="w-full">
                                            <template #legend>
                                                <div class="flex items-center pl-2">
                                                    <span class="">
                                                        <strong class="text-gray-700">BUSINESSES</strong>
                                                    </span>
                                                </div>
                                            </template>

                                            <div>
                                                <DataTable :value="businesses ?? []" class="w-full">

                                                    <Column style="width: 5%">
                                                        <template #body="slot">
                                                            <div v-if="slot.data.logo_path && slot.data.logo_path !== ''"
                                                                class="inline-flex items-center justify-center w-12 h-12 rounded-lg border border-gray-200 cursor-pointer"
                                                                style="border-radius: 6px;"
                                                                @click="triggerLogoUpload(slot.data.key)">
                                                                <Avatar :image="slot.data.logo_path" class="h-8 w-8" @error="() => handleBusinessLogoError(slot)" />
                                                                <input type="file" accept="image/*"
                                                                    :ref="el => setLogoUploadRef(el as HTMLInputElement | null, slot.data.key)"
                                                                    style="display:none"
                                                                    @change="event => handleLogoUpload(event, slot.data)" />
                                                            </div>
                                                            <div v-else class="relative inline-flex items-center justify-center w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-green-500"
                                                            style="border-radius: 6px;" @click="triggerLogoUpload(slot.data.key)">
                                                                <Avatar :image="'/logos/orgs/building.png'" class="h-8 w-8" />
                                                                <input type="file" accept="image/*"
                                                                    :ref="el => setLogoUploadRef(el as HTMLInputElement | null, slot.data.key)"
                                                                    style="display:none"
                                                                    @change="(event: Event) => handleLogoUpload(event, slot.data as Business)" />
                                                            </div>
                                                        </template>
                                                    </Column>

                                                    <Column style="width: 85%" field="bns_name"></Column>

                                                    <!-- <Column style="width: 50%" field="description"></Column> -->
                                                    <Column style="width: 10%" field="status" hidden>
                                                        <template #body="slot">
                                                            <Button 
                                                                v-if="slot.data.status?.toLowerCase() === 'active'"
                                                                type="button" 
                                                                :label="slot.data.status" 
                                                                icon="pi pi-check" 
                                                                badgeSeverity="success" 
                                                                variant="outlined" 
                                                                size="small"
                                                                severity="success"
                                                                class="uppercase"
                                                            />
                                                            <Button 
                                                                v-else
                                                                type="button" 
                                                                :label="slot.data.status" 
                                                                badgeSeverity="danger" 
                                                                variant="outlined" 
                                                                size="small"
                                                                severity="danger"
                                                                class="uppercase"
                                                            />
                                                        </template>
                                                    </Column>

                                                    <Column style="width: 10%" >
                                                        <template #body="slotActions" #end>
                                                            <div class="flex justify-content-end">
                                                                <Button v-if="slotActions.data.status === 'active'"
                                                                    label="ACTIVE" variant="outlined" severity="success"
                                                                    type="button" size="small" icon="pi pi-check-circle"
                                                                    @click="confirmBusinessStatusAction(slotActions.data)"/>
                                                                <Button
                                                                    v-else
                                                                    label="SUSPENDED"
                                                                    variant="outlined"
                                                                    severity="danger"
                                                                    type="button"
                                                                    size="small"
                                                                    :disabled="accountDetails.status !== 'active'"
                                                                    @click="confirmBusinessStatusAction(slotActions.data)"
                                                                />
                                                            </div>
                                                        </template>
                                                    </Column>

                                                    <Column> 
                                                        <template #body="slot">
                                                            <Button label="Edit" severity="info" size="small"
                                                                icon="pi pi-pencil" variant="outlined"
                                                                @click="editBusinessData(slot.data)" />
                                                        </template>
                                                    </Column>
                                                </DataTable>
                                            </div>
                                            
                                        </Fieldset>
                                    </div>
                                </div>
                            </TabPanel>

                            <!-- Modules Tab -->
                            <TabPanel value="2">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold">Account Modules</h3>
                                    <Button label="ADD MODULE" icon="pi pi-plus" severity="success" size="small"
                                        @click="openAddModuleDialog" />
                                </div>

                                <div v-if="modules.length === 0" class="text-center py-8 text-gray-500">
                                    <div class="mb-4">
                                        <i class="pi pi-box text-4xl text-gray-300"></i>
                                    </div>
                                    <p class="text-lg mb-2">No modules assigned to this account</p>
                                    <p class="text-sm">Click "ADD MODULE" to get started</p>
                                </div>

                                <div v-else class="grid grid-cols-1 gap-4" style="padding-top: -10px;">
                                    <Fieldset class="w-full">
                                        <template #legend>
                                            <div class="flex items-center pl-2">
                                                <span class="">
                                                    SUBSCRIBED MODULES
                                                </span>
                                            </div>
                                        </template>

                                        <div>
                                            <DataTable :value="modules ?? []" class="w-full">

                                                <Column style="width: 5%">
                                                    <template #body="slot">
                                                        <div class="relative inline-flex items-center justify-center w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-green-500"
                                                            style="border-radius: 6px;">
                                                            <Avatar
                                                                :image="slot.data.logo_path || '/logos/apps/layout-grid.png'"
                                                                class="h-8 w-8 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
                                                        </div>
                                                    </template>
                                                </Column>

                                                <Column style="width: 20%" class="uppercase" field="app_name">
                                                </Column>

                                                <Column style="width: 65%" field="description"></Column>

                                                <Column style="width: 10%">
                                                    <template #body="slotActions" #end>
                                                        <div class="flex justify-content-end">
                                                            <Button label="REMOVE" variant="outlined" severity="danger"
                                                                type="button" size="small" icon="pi pi-times-circle"
                                                                @click="confirmRemoveModule(slotActions.data)"
                                                                :loading="removingModuleKey === slotActions.data.app_key" />
                                                        </div>
                                                    </template>
                                                </Column>
                                            </DataTable>
                                        </div>
                                    </Fieldset>
                                </div>


                            </TabPanel>
                        </TabPanels>
                    </Tabs>
                </div>

                <div v-else-if="!loading" class="text-center py-8 text-gray-500">
                    No account data available
                </div>
            </template>



        </Card>

        <!-- Confirmation Dialog -->
        <ConfirmDialog />

        <!-- Add Module Dialog -->
        <AddModuleDialog v-model:visible="showAddModuleDialog" :account="account" @moduleAdded="onModuleAdded" />
    </Drawer>

    <!-- Edit Field Modal -->
    <Dialog v-model:visible="showEditFieldModal" header="EDIT" :modal="true" :closeOnEscape="true"
        :dismissableMask="true" :style="{ width: '450px', minHeight: '260px' }">
        <hr>
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ editField }}</label>
                <!-- <br /> -->
                <InputText v-model="editValue" class="w-full" />
            </div>
            <div class="flex justify-end gap-2">
                <Button label="Cancel" icon="pi pi-times" outlined severity="secondary" @click="cancelEditField" />
                <Button label="Save" icon="pi pi-check" severity="primary" @click="saveEditField" />
            </div>
        </div>
    </Dialog>

    <!-- Add Business Dialog -->
    <Dialog v-model:visible="showAddBusinessDialog" header="Add New Business" :modal="true" :closeOnEscape="true"
        :dismissableMask="true" :style="{ width: '600px' }">
        <div class="p-4">
            <FloatLabel variant="on">
                <InputText id="bns_name" v-model="newBusiness.bns_name" class="w-full"
                    :class="{ 'p-invalid border-red-500': businessErrors.bns_name }" autocomplete="off" />
                <label for="bns_name">Business Name</label>
            </FloatLabel>
            <small v-if="businessErrors.bns_name" class="text-red-500">{{ businessErrors.bns_name }}</small>
            <br />
            <FloatLabel variant="on">
                <InputText id="description" v-model="newBusiness.description" class="w-full"
                    :class="{ 'p-invalid border-red-500': businessErrors.description }" autocomplete="off" />
                <label for="description">Description</label>
            </FloatLabel>
            <br />
            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-4">
                    <FloatLabel variant="on">
                        <InputMask id="tin_num" v-model="newBusiness.tin_num" class="w-full" mask="999-999-999"
                            :class="{ 'p-invalid border-red-500': businessErrors.tin_num }" autocomplete="off" />
                        <label for="tin_num">TIN Number</label>
                    </FloatLabel>
                    <small v-if="businessErrors.tin_num" class="text-red-500">{{ businessErrors.tin_num }}</small>

                    <FloatLabel variant="on">
                        <InputMask id="bns_phone" v-model="newBusiness.bns_phone" class="w-full"
                            autocomplete="off"  mask="9999-999-999"/>
                        <label for="bns_phone">Phone</label>
                    </FloatLabel>
                </div>
                <div class="flex flex-col gap-4">
                    <FloatLabel variant="on">
                        <InputText id="vat_num" v-model="newBusiness.vat_num" class="w-full" autocomplete="off" />
                        <label for="vat_num">VAT Number</label>
                    </FloatLabel>
                    <FloatLabel variant="on">
                        <InputText id="bns_email" v-model="newBusiness.bns_email" class="w-full"
                            :class="{ 'p-invalid border-red-500': businessErrors.bns_email }" autocomplete="off" />
                        <label for="bns_email">Email address</label>
                    </FloatLabel>
                    <small v-if="businessErrors.bns_email" class="text-red-500">{{ businessErrors.bns_email }}</small>
                </div>
            </div>
            <br>
            <FloatLabel variant="on">
                <InputText id="contact_person" v-model="newBusiness.contact_person" class="w-full" autocomplete="off" />
                <label for="contact_person">Contact Person</label>
            </FloatLabel>
            <br> 
            <FloatLabel variant="on">
                <Textarea id="phys_address" v-model="newBusiness.phys_address" class="w-full" :rows="2" autocomplete="off" />
                <label for="phys_address">Address</label>
            </FloatLabel>
            <br /> 
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" icon="pi pi-times" outlined severity="secondary"
                    @click="() => showAddBusinessDialog = false" />
                <Button label="Add business" icon="pi pi-check" severity="primary" @click="submitNewBusiness"
                    :disabled="!isBusinessFormValid" />
            </div>
        </div>
    </Dialog>

    <!-- Change Account Status Dialog -->
    <Dialog v-model:visible="showAccountStatusDialog" header="Change Account Status" :modal="true" :closeOnEscape="true"
        :dismissableMask="true" :style="{ width: '400px' }">
        <div class="p-4">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <Select v-model="selectedAccountStatus" :options="accountStatusOptions" optionLabel="label"
                    optionValue="value" class="w-full" />
            </div>
            <div class="flex justify-end gap-2">
                <Button label="Cancel" icon="pi pi-times" outlined severity="secondary"
                    @click="() => showAccountStatusDialog = false"  size="small"/>
                <Button label="Confirm" icon="pi pi-check" severity="primary" @click="changeAccountStatus" size="small"/>
            </div>
        </div>
    </Dialog>

    <!-- Edit Business Dialog -->
    <Dialog v-model:visible="showEditBusinessDialog" header="Edit Business" :modal="true" :closeOnEscape="true"
        :dismissableMask="true" :style="{ width: '500px' }">
        <div class="p-4">
            <div class="mb-4">
                <FloatLabel variant="on">
                    <InputText id="edit_bns_name" v-model="editingBusiness.bns_name" class="w-full"
                        :class="{ 'p-invalid border-red-500': editBusinessErrors.bns_name }" autocomplete="off" />
                    <label for="edit_bns_name">Business Name</label>
                </FloatLabel>
                <small v-if="editBusinessErrors.bns_name" class="text-red-500">{{ editBusinessErrors.bns_name }}</small>
            </div>
            
            <div class="mb-4">
                <FloatLabel variant="on">
                    <Textarea id="edit_description" v-model="editingBusiness.description" class="w-full" :rows="3"
                        :class="{ 'p-invalid border-red-500': editBusinessErrors.description }" autocomplete="off" />
                    <label for="edit_description">Description</label>
                </FloatLabel>
                <small v-if="editBusinessErrors.description" class="text-red-500">{{ editBusinessErrors.description }}</small>
            </div>

            <div class="mb-4">
                <FloatLabel variant="on">
                    <Select id="edit_status" v-model="editingBusiness.status" :options="businessStatusOptions" 
                        optionLabel="label" optionValue="value" class="w-full" />
                    <label for="edit_status">Status</label>
                </FloatLabel>
            </div>
            
            <div class="flex justify-end gap-2">
                <Button label="Cancel" icon="pi pi-times" outlined severity="secondary" size="small"
                    @click="() => showEditBusinessDialog = false" />
                <Button label="Update" icon="pi pi-check" severity="primary" size="small" @click="submitEditBusiness"
                    :disabled="!isEditBusinessFormValid" />
            </div>
        </div>
    </Dialog>

    <!-- Account Logo Upload (Hidden) -->
    <input type="file" accept="image/*" ref="accountLogoUploadRef" style="display:none" @change="handleAccountLogoUpload" />
</template>