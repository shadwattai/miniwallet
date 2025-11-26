<script setup lang="ts"> 
import Card from 'primevue/card';
import Button from 'primevue/button';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputText from 'primevue/inputtext'; 
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import Toast from 'primevue/toast';
import ConfirmPopup from 'primevue/confirmpopup';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import InputMask from 'primevue/inputmask';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import Divider from 'primevue/divider';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import TabPanels from 'primevue/tabpanels';
import Tab from 'primevue/tab';
import TabPanel from 'primevue/tabpanel';
import { FilterMatchMode } from '@primevue/core/api';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

interface User {
    id: number;
    key: string;
    name: string;
    email: string;
    phone: string;
    status: string;
    creator: string;
    role: string;
    created_at: string;
    avatar?: string;
}

const props = defineProps<{
    users?: User[];
}>();

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const debouncedSearchTerm = ref('');
const visibleRight = ref(false);
const selectedUser = ref<User | null>(null);
const showRegisterDialog = ref(false);
const isSubmitting = ref(false);
const updatingStatus = ref(false);
const toast = useToast();
const confirm = useConfirm();

// Form data for new user registration
const newUserForm = ref({
    name: '',
    email: '',
    phone: '',
});

// Form errors
const formErrors = ref<Record<string, string>>({});

// Helper function to get avatar URL
const getAvatarUrl = (user: User) => {
    if (!user.avatar) {
        return getAvatarInitials(user);
    } else {
        return `/avatars/${user.avatar}`;
    }
};

const getAvatarInitials = (user: User) => {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=random`;
};

// Debounce search input for better performance
let searchTimeout: number;
watch(() => filters.value.global.value, (newValue) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        debouncedSearchTerm.value = newValue || '';
    }, 300);
});

// Optimized computed property to filter users based on debounced search
const filteredUsers = computed(() => {
    if (!props.users) return [];

    const searchTerm = debouncedSearchTerm.value;
    if (!searchTerm) return props.users;

    const lowerSearchTerm = searchTerm.toLowerCase();

    return props.users.filter(user =>
        user.key.toLowerCase().includes(lowerSearchTerm) ||
        user.name.toLowerCase().includes(lowerSearchTerm) ||
        user.email.toLowerCase().includes(lowerSearchTerm) ||
        user.status.toLowerCase().includes(lowerSearchTerm) ||
        (user.phone && user.phone.toLowerCase().includes(lowerSearchTerm))
    );
});

// Event handlers
const openUserProfile = (user: User) => {
    console.log('Opening profile for:', user.name); // Debug log
    selectedUser.value = user;
    visibleRight.value = true;
    console.log('Drawer should be visible:', visibleRight.value); // Debug log
};

const openRegistrationDialog = () => {
    newUserForm.value = {
        name: '',
        email: '',
        phone: '',
    };
    formErrors.value = {};
    showRegisterDialog.value = true;
};

// Function to validate form
const validateForm = () => {
    const errors: Record<string, string> = {};

    if (!newUserForm.value.name.trim()) {
        errors.name = 'Name is required';
    }

    if (!newUserForm.value.email.trim()) {
        errors.email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newUserForm.value.email)) {
        errors.email = 'Please enter a valid email address';
    }

    if (!newUserForm.value.phone.trim()) {
        errors.phone = 'Phone number is required';
    }

    formErrors.value = errors;
    return Object.keys(errors).length === 0;
};

// Function to submit registration form
const submitRegistration = async () => {
    if (!validateForm()) {
        return;
    }

    isSubmitting.value = true;

    try {
        router.post('/miniwallet/settings/users', newUserForm.value, {
            onSuccess: (page) => {
                showRegisterDialog.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'User Registered',
                    detail: `${newUserForm.value.name} has been registered successfully!`,
                    life: 4000
                });

                // Reset form
                newUserForm.value = {
                    name: '',
                    email: '',
                    phone: '',
                };
            },
            onError: (errors) => {
                console.error('Registration failed:', errors);
                formErrors.value = errors;

                toast.add({
                    severity: 'error',
                    summary: 'Registration Failed',
                    detail: 'Please check the form for errors and try again.',
                    life: 5000
                });
            },
            onFinish: () => {
                isSubmitting.value = false;
            }
        });
    } catch (error) {
        console.error('Registration error:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'An unexpected error occurred. Please try again.',
            life: 5000
        });
        isSubmitting.value = false;
    }
};

// Function to toggle user status (activate/deactivate)
const toggleUserStatus = async (user: User, event: Event) => {
    const action = user.status === 'active' ? 'deactivate' : 'activate';
    const actionTitle = user.status === 'active' ? 'Deactivate User' : 'Activate User';
    const confirmMessage = user.status === 'active'
        ? `Are you sure you want to deactivate ${user.name}?`
        : `Are you sure you want to activate ${user.name}?`;

    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: confirmMessage,
        header: actionTitle,
        icon: user.status === 'active' ? 'pi pi-ban' : 'pi pi-check',
        rejectClass: 'p-button-secondary p-button-outlined',
        acceptClass: user.status === 'active' ? 'p-button-danger' : 'p-button-success',
        rejectLabel: 'Cancel',
        acceptLabel: user.status === 'active' ? 'Deactivate' : 'Activate',
        accept: () => {
            performStatusUpdate(user, action);
        },
    });
};

// Perform status update
const performStatusUpdate = async (user: User, action: string) => {
    updatingStatus.value = true;
    const originalStatus = user.status;
    const newStatus = user.status === 'active' ? 'inactive' : 'active';

    // Update UI immediately
    user.status = newStatus;
    if (selectedUser.value && selectedUser.value.key === user.key) {
        selectedUser.value.status = newStatus;
    }

    try {
        router.patch(`/miniwallet/users/${user.key}`, {
            status: newStatus
        }, {
            onSuccess: () => {
                toast.add({
                    severity: action === 'activate' ? 'success' : 'warn',
                    summary: `User ${action === 'activate' ? 'Activated' : 'Deactivated'}`,
                    detail: `${user.name} has been ${action}d successfully!`,
                    life: 4000
                });
            },
            onError: () => {
                // Rollback
                user.status = originalStatus;
                if (selectedUser.value && selectedUser.value.key === user.key) {
                    selectedUser.value.status = originalStatus;
                }
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: `Failed to ${action} ${user.name}. Please try again.`,
                    life: 5000
                });
            },
            onFinish: () => {
                updatingStatus.value = false;
            }
        });
    } catch (error) {
        // Rollback
        user.status = originalStatus;
        if (selectedUser.value && selectedUser.value.key === user.key) {
            selectedUser.value.status = originalStatus;
        }
        updatingStatus.value = false;
    }
};

</script>

<template>
    <Toast />
    <ConfirmPopup />
    
    <div style="padding-top: -10px;">
        <Toolbar class="mb-4" :style="{ justifyContent: 'space-between', borderRadius: '0px', marginTop: '-5px' }">
            <template #start>
                <IconField>
                    <InputText v-model="filters.global.value" placeholder="Search ..." class="w-120" />
                </IconField>
            </template>
            <template #end>
                <Button label="REGISTER" icon="pi pi-plus-circle" severity="info" @click="openRegistrationDialog" />
            </template>
        </Toolbar>

        <div class="text-center py-12 text-gray-500"
            v-if="!props.users || props.users.length === 0">
            <i class="pi pi-users text-6xl mb-4 text-gray-300"></i>
            <h3 class="text-xl mb-2">No Users Found</h3>
            <p class="mb-4">There are currently no users in the system.</p>
            <p class="text-sm">Click the "REGISTER" button to add your first user.</p>
        </div>

        <div class="grid grid-cols-5 gap-4" style="margin-top: 12px; margin: 12px;" v-if="props.users && props.users.length > 0">
            <Card v-for="usr in filteredUsers" :key="usr.key"
                :style="{ 'box-shadow': '0 2px 12px 0 rgba(0, 0, 0, 0.1)', 'border-radius': '6px', 'overflow': 'hidden' }">
                <template #header>
                    <img :src="getAvatarUrl(usr)" :alt="usr.name" class="w-full h-64 object-cover" />
                </template>
                <template #title>
                    <p class="capitalize">{{ usr.name.substring(0, 16) }}</p>
                </template>
                <template #subtitle>{{ usr.email }}</template>

                <template #footer>
                    <div class="flex gap-4 mt-auto">
                        <Button label="PROFILE" :severity="usr.status === 'active' ? 'success' : 'secondary'" outlined
                            variant="outlined" class="w-full" @click="openUserProfile(usr)" size="small" />
                    </div>
                </template>
            </Card>

            <!-- Show message when no results found -->
            <div v-if="filteredUsers.length === 0 && filters.global.value"
                class="col-span-5 text-center py-8 text-gray-500">
                No users found matching "{{ filters.global.value }}"
            </div>
        </div>
    </div>

    <!-- User Profile Drawer -->
    <Drawer v-model:visible="visibleRight" position="right" class="!w-[60vw]">
        <template #header>
            <h3></h3>
        </template>
        
        <div v-if="selectedUser" class="space-y-4">
            <div class="text-center">
                <img 
                    :src="getAvatarUrl(selectedUser)" 
                    :alt="selectedUser.name"
                    class="w-24 h-24 rounded-full mx-auto mb-4 object-cover border-4 border-gray-200"
                />
                <span class="font-bold">{{ selectedUser.name }}</span>
            </div>

            <Divider type="solid" />

            <div class="card">
                <Tabs value="0">
                    <TabList>
                        <Tab value="0">BASIC</Tab>
                        <Tab value="1">WALLETS</Tab>
                        <!-- <Tab value="2">PERMISSIONS</Tab> -->
                    </TabList>
                    <TabPanels>
                        <TabPanel value="0">
                            <br>
                            <div class="border-b pb-4">
                                <div class="space-y-2">
                                    <div class="flex items-center" style="padding-bottom: 10px;">
                                        <i class="pi pi-id-card text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">PHONE</p>
                                            <p class="font-medium">#{{ selectedUser.phone }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="pi pi-envelope text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">E - MAIL</p>
                                            <p class="font-medium">{{ selectedUser.email }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border-b pb-4">
                                <div class="space-y-2">
                                    <div class="flex items-center" style="padding-bottom: 10px; padding-top: 10px;">
                                        <i class="pi pi-sparkles text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">USER KEY</p>
                                            <p class="font-medium text-sm uppercase">
                                                {{ selectedUser.key.split('-').pop() }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="pi pi-calendar text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">REGISTERED</p>
                                            <p class="font-small">
                                                {{ new Date(selectedUser.created_at).toLocaleDateString() }}
                                                BY {{ selectedUser.creator?.toLocaleUpperCase() || 'SYSTEM' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

               
                        </TabPanel>
                        <TabPanel value="1">
                            <p class="m-0">User WALLETS will be displayed here.</p>
                        </TabPanel>
                        <TabPanel value="2">
                            <p class="m-0">User permissions and access control settings will be displayed here.</p>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>
        </div>
    </Drawer>

    <!-- Registration Dialog -->
    <Dialog v-model:visible="showRegisterDialog" header="REGISTER USER" :modal="true" :closable="false"
        class="p-4 max-w-lg mx-auto" :style="{ width: '90vw', maxWidth: '35rem' }">
        <div>
            <!-- Registration form fields -->
            <div class="field mb-4">
                <label for="reg-name" class="block text-sm font-medium mb-2">Name</label>
                <InputText id="reg-name" v-model="newUserForm.name" :class="{ 'p-invalid': formErrors.name }"
                    placeholder="Enter user name" class="w-full" autocomplete="off" />
                <small v-if="formErrors.name" class="p-error">{{ formErrors.name }}</small>
            </div>

            <div class="field mb-4">
                <label for="reg-email" class="block text-sm font-medium mb-2">Email</label>
                <InputText id="reg-email" v-model="newUserForm.email" :class="{ 'p-invalid': formErrors.email }"
                    placeholder="Enter accessible email" class="w-full" autocomplete="off" />
                <small v-if="formErrors.email" class="p-error">{{ formErrors.email }}</small>
            </div>

            <div class="field mb-4">
                <label for="reg-phone" class="block text-sm font-medium mb-2">Phone</label>
                <InputGroup>
                    <InputGroupAddon>+255</InputGroupAddon>
                    <InputMask id="reg-phone" v-model="newUserForm.phone" :class="{ 'p-invalid': formErrors.phone }"
                        placeholder="Accessible phone number" class="w-full" mask="(999) 999-999" autocomplete="off" />
                </InputGroup>
                <small v-if="formErrors.phone" class="p-error">{{ formErrors.phone }}</small>
            </div>

            <hr class="my-4" />
            <div class="flex justify-end gap-2">
                <Button label="Cancel" icon="pi pi-times" severity="secondary" outlined @click="showRegisterDialog = false" />
                <Button label="Register" icon="pi pi-check" severity="success" :loading="isSubmitting"
                    @click="submitRegistration" />
            </div>
        </div>
    </Dialog>
</template>