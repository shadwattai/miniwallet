<script setup lang="ts">
import { dashboard } from '@/routes';
import { Head, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Toolbar from 'primevue/toolbar';
import Skeleton from 'primevue/skeleton';
import IconField from 'primevue/iconfield';
import InputText from 'primevue/inputtext';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import { FilterMatchMode } from '@primevue/core/api';
import { ref, computed, watch, onMounted } from 'vue';
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
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';


const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Home',
    href: dashboard().url,
  },
  {
    title: 'Internals',
    href: '',
  },
  {
    title: 'Users',
    href: '',
  },
];

interface User {
  id: number;
  key: string;
  name: string;
  email: string;
  phone: string;
  gender: string;
  status: string;
  creator: string;
  is_root: boolean;
  created_at: string;
  avatar?: string;
}
const props = defineProps<{ users: User[] }>();

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  verified: { value: null, matchMode: FilterMatchMode.EQUALS },

  key: { value: null, matchMode: FilterMatchMode.CONTAINS },
  email: { value: null, matchMode: FilterMatchMode.CONTAINS },
  phone: { value: null, matchMode: FilterMatchMode.CONTAINS },
  name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
  status: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
});

const loading = ref(true);
const visibleRight = ref(false);
const selectedUser = ref<User | null>(null);
const debouncedSearchTerm = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const updatingStatus = ref(false);
const killingSession = ref(false);
const showRegisterDialog = ref(false);
const isSubmitting = ref(false);
const toast = useToast();
const confirm = useConfirm();

// Form data for new user registration
const newUserForm = ref({
  name: '',
  email: '',
  phone: '',
  gender: '',
  launchpad: false,
  demo: false
});

// Form errors
const formErrors = ref<Record<string, string>>({});

// Gender options for dropdown
const genderOptions = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' }
];

// Function to open drawer with selected user
const openUserProfile = (user: User) => {
  selectedUser.value = user;
  visibleRight.value = true;
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
    reject: () => {
      // User cancelled - do nothing
    }
  });
};

// Separate function to perform the actual status update
const performStatusUpdate = async (user: User, action: string) => {
  updatingStatus.value = true;

  // Store original status for potential rollback
  const originalStatus = user.status;
  const newStatus = user.status === 'active' ? 'inactive' : 'active';

  // Update UI immediately for instant feedback
  user.status = newStatus;
  if (selectedUser.value && selectedUser.value.key === user.key) {
    selectedUser.value.status = newStatus;
  }

  // Update in the users array immediately
  const userIndex = props.users.findIndex(u => u.key === user.key);
  if (userIndex !== -1) {
    props.users[userIndex].status = newStatus;
  }

  try {
    router.patch(`/internals/users/${user.key}/status`, {
      status: newStatus
    }, {
      onSuccess: (page) => {
        // Confirm the status update from server
        const updatedUser = page.props.updatedUser as User;
        if (updatedUser) {
          // Ensure consistency with server response
          const finalStatus = updatedUser.status;
          user.status = finalStatus;
          if (selectedUser.value && selectedUser.value.key === user.key) {
            selectedUser.value.status = finalStatus;
          }
          if (userIndex !== -1) {
            props.users[userIndex].status = finalStatus;
          }
        }

        // Show success toast
        toast.add({
          severity: action === 'activate' ? 'success' : 'warn',
          summary: `User ${action === 'activate' ? 'Activated' : 'Deactivated'}`,
          detail: `${user.name} has been ${action}d successfully! 🎉`,
          life: 4000
        });

        // console.log(`User ${action}d successfully`);
      },
      onError: (errors) => {
        // Rollback to original status on error
        user.status = originalStatus;
        if (selectedUser.value && selectedUser.value.key === user.key) {
          selectedUser.value.status = originalStatus;
        }
        if (userIndex !== -1) {
          props.users[userIndex].status = originalStatus;
        }

        console.error(`Failed to ${action} user:`, errors);
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: `Failed to ${action} ${user.name}. Please try again. ❌`,
          life: 5000
        });
      },
      onFinish: () => {
        updatingStatus.value = false;
      }
    });
  } catch (error) {
    // Rollback to original status on error
    user.status = originalStatus;
    if (selectedUser.value && selectedUser.value.key === user.key) {
      selectedUser.value.status = originalStatus;
    }
    if (userIndex !== -1) {
      props.users[userIndex].status = originalStatus;
    }

    console.error(`Error ${action}ing user:`, error);
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: `An error occurred while ${action}ing the user. ❌`,
      life: 5000
    });
    updatingStatus.value = false;
  }
};

// Function to kill user session
const killUserSession = async (user: User, event: Event) => {
  // Check if user is root
  if (user.is_root) {
    toast.add({
      severity: 'warn',
      summary: 'Action Not Allowed',
      detail: 'Cannot terminate sessions of root users for security reasons. 🛡️',
      life: 4000
    });
    return;
  }

  confirm.require({
    target: event.currentTarget as HTMLElement,
    message: `Are you sure you want to terminate ${user.name}'s active session? They will be logged out immediately.`,
    header: 'Kill User Session',
    icon: 'pi pi-ban',
    rejectClass: 'p-button-secondary p-button-outlined',
    acceptClass: 'p-button-danger',
    rejectLabel: 'Cancel',
    acceptLabel: 'Kill Session',
    accept: () => {
      performKillSession(user);
    },
    reject: () => {
      // User cancelled - do nothing
    }
  });
};

// Separate function to perform the actual session kill
const performKillSession = async (user: User) => {
  killingSession.value = true;

  try {
    router.patch(`/internals/users/${user.key}/kill-session`, {}, {
      onSuccess: (page) => {
        // Check if the operation was successful
        if (page.props.success) {
          toast.add({
            severity: 'warn',
            summary: 'Session Terminated',
            detail: `${user.name}'s session has been terminated successfully! 🚫`,
            life: 4000
          });
        }
        
        console.log('User session killed successfully');
      },
      onError: (errors) => {
        console.error('Failed to kill user session:', errors);
        
        // Handle different types of errors
        const errorMessage = errors.message || `Failed to terminate ${user.name}'s session. Please try again. ❌`;
        
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: errorMessage,
          life: 5000
        });
      },
      onFinish: () => {
        killingSession.value = false;
      }
    });
  } catch (error) {
    console.error('Error killing user session:', error);
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'An error occurred while terminating the session. ❌',
      life: 5000
    });
    killingSession.value = false;
  }
};

// File upload functions
const triggerFileUpload = () => {
  fileInput.value?.click();
};

const handleFileUpload = async (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (file && selectedUser.value) {
    if (!file.type.startsWith('image/')) {
      toast.add({
        severity: 'error',
        summary: 'Invalid File',
        detail: 'Please select an image file',
        life: 3000
      });
      return;
    }

    // Check file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      toast.add({
        severity: 'error',
        summary: 'File Too Large',
        detail: 'File size must be less than 5MB',
        life: 3000
      });
      return;
    }

    uploading.value = true;

    try {
      // Create FormData for file upload
      const formData = new FormData();
      formData.append('avatar', file);
      formData.append('_method', 'PATCH'); // Laravel method spoofing

      // Upload using Inertia router
      router.post(`/internals/users/${selectedUser.value.key}/avatar`, formData, {
        forceFormData: true,
        onSuccess: (page) => {
          // Update the selected user's avatar path
          const updatedUser = page.props.updatedUser as User;
          if (selectedUser.value && updatedUser) {
            selectedUser.value.avatar = updatedUser.avatar;

            // Also update in the main users array
            const userIndex = props.users.findIndex(u => u.key === selectedUser.value!.key);
            if (userIndex !== -1) {
              props.users[userIndex].avatar = updatedUser.avatar;
            }
          }

          // Show success toast
          toast.add({
            severity: 'success',
            summary: 'Avatar Updated',
            detail: `Profile picture updated successfully! 📸`,
            life: 3000
          });

          console.log('Avatar uploaded successfully');
        },
        onError: (errors) => {
          console.error('Upload failed:', errors);
          toast.add({
            severity: 'error',
            summary: 'Upload Failed',
            detail: 'Failed to upload avatar. Please try again.',
            life: 5000
          });
        },
        onFinish: () => {
          uploading.value = false;
          // Clear the file input
          if (fileInput.value) {
            fileInput.value.value = '';
          }
        }
      });
    } catch (error) {
      console.error('Upload error:', error);
      toast.add({
        severity: 'error',
        summary: 'Upload Error',
        detail: 'An error occurred while uploading',
        life: 5000
      });
      uploading.value = false;
    }
  }
};

// Helper function to get avatar URL
const getAvatarUrl = (user: User) => {
  if (!user.avatar) {
    return getAvatarInitials(user);
  } else {
    return `/photos/profile/${user.avatar}`;
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
  }, 300); // 300ms delay
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
    (user.phone && user.phone.toLowerCase().includes(lowerSearchTerm)) ||
    (user.gender && user.gender.toLowerCase().includes(lowerSearchTerm))
  );
});

// Simulate initial loading
onMounted(() => {
  // Set loading to false after component is mounted and data is available
  setTimeout(() => {
    loading.value = false;
  }, 500); // Small delay to show skeleton briefly
});

// Function to open registration dialog
const openRegistrationDialog = () => {
  // Reset form
  newUserForm.value = {
    name: '',
    email: '',
    phone: '',
    gender: '',
    launchpad: false,
    demo: false
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

  if (!newUserForm.value.gender) {
    errors.gender = 'Gender is required';
  }

  formErrors.value = errors;
  return Object.keys(errors).length === 0;
};

// Function to submit registration form
const submitRegistration = async () => {
  console.log('Submit registration called');
  console.log('Form data:', newUserForm.value);

  if (!validateForm()) {
    console.log('Form validation failed:', formErrors.value);
    return;
  }

  console.log('Form validation passed, submitting...');
  isSubmitting.value = true;

  try {
    router.post('//internals/users', newUserForm.value, {
      onSuccess: (page) => {
        showRegisterDialog.value = false;
        toast.add({
          severity: 'success',
          summary: 'User Registered',
          detail: `${newUserForm.value.name} has been registered successfully! 🎉`,
          life: 4000
        });

        // Reset form
        newUserForm.value = {
          name: '',
          email: '',
          phone: '',
          gender: '',
          launchpad: false,
          demo: false
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
</script>


<template>

  <Head title="USERS" />
  <Toast />
  <ConfirmPopup />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div style="padding-top: -10px;">
      <Toolbar class="mb-1" :style="{ justifyContent: 'space-between', borderRadius: '0px' }">
        <template #start>
          <h1 class="m-0">
            <span class="font-bold p-2 bg-clip-text">
              SYSTEM USERS
            </span>
          </h1>
        </template>
      </Toolbar>

      <Toolbar class="mb-4" :style="{ justifyContent: 'space-between', borderRadius: '0px', marginTop: '-5px' }">
        <template #start>
          <IconField>
            <InputText v-model="filters.global.value" placeholder="Search ..." class="w-120" />
          </IconField> 
        </template>
        <template #end>
          <Button label="REGISTER" disabled icon="pi pi-plus-circle" severity="info" @click="openRegistrationDialog" />
        </template>
      </Toolbar>


      <div class="rounded border border-surface-200 dark:border-surface-700 p-6 bg-surface-0 dark:bg-surface-900"
        v-if="loading || !props.users || props.users.length === 0">
        <div v-for="i in [1, 2, 3]">
          <div class="flex mb-4">
            <Skeleton shape="circle" size="4rem" class="mr-2"></Skeleton>
            <div>
              <Skeleton width="10rem" class="mb-2"></Skeleton>
              <Skeleton width="5rem" class="mb-2"></Skeleton>
              <Skeleton height=".5rem"></Skeleton>
            </div>
          </div>
          <Skeleton width="100%" height="150px"></Skeleton>
          <div class="flex justify-between mt-4">
            <Skeleton width="4rem" height="2rem"></Skeleton>
            <Skeleton width="4rem" height="2rem"></Skeleton>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-5 gap-4" style="margin-top: 12px; margin: 12px;" v-else>
        <Card v-for="usr in filteredUsers" :key="usr.key"
          :style="{ 'box-shadow': '0 2px 12px 0 rgba(0, 0, 0, 0.1)', 'border-radius': '6px', 'overflow': 'hidden' }"
          :loading="loading">
          <template #header>
            <img v-if="!usr.avatar" alt="user header" :src="getAvatarUrl(usr)" class="h-28 object-cover" />
            <img v-if="usr.avatar" alt="user header" :src="getAvatarUrl(usr)" class="w-full h-64 object-cover" />
          </template>
          <template #title size="small">
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
  </AppLayout>

  <Drawer v-model:visible="visibleRight" :style="{ width: '60vw' }" position="right" :closeOnEscape="true">
    <template #header v-if="selectedUser">
      <div class="flex items-center gap-2">
        <!-- <span class="font-bold uppercase">{{ selectedUser.name }}</span> -->
      </div>
    </template>
    <Card class="border border-surface-100 dark:border-surface-300" style="min-height: 700px; padding-top: -10px;">


      <template #content>
        <div v-if="selectedUser" class="space-y-4">

          <div class="text-center">
            <div class="relative inline-block cursor-pointer" @click="triggerFileUpload">
              <img :src="getAvatarUrl(selectedUser)" :alt="selectedUser.name"
                class="w-24 h-24 rounded-full mx-auto mb-4 hover:opacity-75 transition-opacity object-cover" />

              <span class="font-bold ">{{ selectedUser.name }}</span>
              <!-- <br />
              <Button type="button" label="USER" icon="pi pi-user" :badge="selectedUser.status.toUpperCase()"
                severity="help" badgeSeverity="contrast" variant="outlined" size="small" disabled />
              &nbsp; -->



              <!-- Upload overlay -->
              <div
                class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 opacity-0 hover:opacity-100 transition-opacity"
                style="border-radius: 180%;">
                <i v-if="!uploading" class="pi pi-camera text-white text-xl"></i>
                <i v-else class="pi pi-spin pi-spinner text-white text-xl"></i>
              </div>

              <!-- Loading overlay -->
              <div v-if="uploading"
                class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-75 rounded-full">
                <i class="pi pi-spin pi-spinner text-white text-xl"></i>
              </div>

              <input ref="fileInput" type="file" accept="image/*" @change="handleFileUpload" class="hidden"
                :disabled="uploading" />
            </div> 
          </div>

          <Divider type="solid" />


          <div class="card">
            <Tabs value="0">
              <TabList>
                <Tab value="0">BASIC</Tab>
                <Tab value="1">STATUSES</Tab>
                <Tab value="2">PERMISSIONS</Tab>
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
                      <div class="flex items-center" style="padding-bottom: 10px;  padding-top: 10px;">
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
                            BY {{ selectedUser.creator.toLocaleUpperCase() }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="border-b pb-4">
                    <div class="space-y-2">
                      <div class="flex items-center" style="padding-bottom: 10px; padding-top: 10px;">
                        <i class="pi pi-history text-gray-500 mr-3"></i>
                        <div class="flex justify-between items-center">
                          <div class="">
                            <p class="text-sm text-gray-500">USER LAST LOGIN</p>
                            <p class="font-small">HAS NEVER LOGGED TO THE SYSTEM</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>


                  <div class="border-b pb-4">
                    <div class="space-y-2">
                      <div class="flex items-center" style="padding-bottom: 10px; padding-top: 10px;">
                        <i class="pi pi-list text-gray-500 mr-3"></i>

                        <div class="flex justify-between">
                          <div class="">
                            <p class="text-sm text-gray-500"></p>
                          </div>
                        </div>
                        <hr>
                        <div class="flex gap-2 mt-2 w-full">
                          <Button v-if="selectedUser.status === 'active'" label="Deactivate" icon="pi pi-ban"
                            severity="danger" outlined class="flex-1" :loading="updatingStatus"
                            :disabled="updatingStatus || selectedUser.is_root"
                            @click="toggleUserStatus(selectedUser, $event)" size="small"
                            :badge="selectedUser.status.toUpperCase()" badgeSeverity="contrast" />
                          &nbsp;
                          <Button v-else label="Activate user" icon="pi pi-check" severity="success" outlined
                            class="flex-1" :loading="updatingStatus" :disabled="updatingStatus || selectedUser.is_root"
                            @click="toggleUserStatus(selectedUser, $event)" size="small"
                            :badge="selectedUser.status.toUpperCase()" badgeSeverity="contrast" />
                          &nbsp;

                          <Button label="Reset" icon="pi pi-key" severity="info" class="flex-1" outlined size="small" />
                          &nbsp;
                          <Button label="Kill session" icon="pi pi-ban" severity="secondary" class="flex-1" outlined
                            size="small" :loading="killingSession" :disabled="killingSession || selectedUser.is_root"
                            @click="killUserSession(selectedUser, $event)" />
                          &nbsp;
                          <Button label="Profile" icon="pi pi-user" severity="success" class="flex-1" outlined
                            size="small" />

                        </div>
                      </div>
                    </div>
                  </div>

                </TabPanel>
                <TabPanel value="1">
                  <p class="m-0">
                    Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium,
                    totam
                    rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt
                    explicabo. Nemo enim
                    ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni
                    dolores
                    eos
                    qui ratione voluptatem sequi nesciunt. Consectetur, adipisci velit, sed quia non numquam eius modi.
                  </p>
                </TabPanel>
                <TabPanel value="2">
                  <p class="m-0">
                    At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum
                    deleniti
                    atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident,
                    similique
                    sunt in culpa
                    qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis
                    est
                    et
                    expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit
                    quo
                    minus.
                  </p>
                </TabPanel>
              </TabPanels>
            </Tabs>
          </div>

        </div>
      </template>
    </Card>
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

      <div class="field mb-4">
        <label for="reg-gender" class="block text-sm font-medium mb-2">Gender</label>
        <Select id="reg-gender" v-model="newUserForm.gender" :options="genderOptions" optionLabel="label"
          optionValue="value" :class="{ 'p-invalid': formErrors.gender }" placeholder="Select gender" class="w-full"
          autocomplete="off" />
        <small v-if="formErrors.gender" class="p-error">{{ formErrors.gender }}</small>
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
