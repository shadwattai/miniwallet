<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Checkbox from 'primevue/checkbox';
import Card from 'primevue/card';
import ProgressSpinner from 'primevue/progressspinner';
import InputMask from 'primevue/inputmask';

interface Industry {
  key: string;
  name: string;
  // add other properties if needed
}

const props = defineProps<{
  visible: boolean;
  industries: Industry[];
  modules: any[];
}>();

const emit = defineEmits(['update:visible', 'registered']);

const toast = useToast();
const loading = ref(false);
const activeTab = ref("0");
const loadingModules = ref(false);
interface IndustryModule {
  key: string;
  brand_label?: string;
  brand_name?: string;
  description?: string;
  pri?: string | number;
}

const industryModules = ref<IndustryModule[]>([]);

// Form data
const accountForm = reactive({
  industry_key: null,
  acc_name: '',
  description: '',
  start_date: new Date(),
  max_bnses: 1,
  max_staff: 10,
  sender_name: '',
  sender_email: '',
  sender_username: '',
  sender_password: ''
});

const companyForm = reactive({
  bns_name: '',
  description: '',
  reg_num: '',
  tin_num: '',
  vat_num: '',
  bns_phone: '',
  bns_email: '',
  phys_address: '',
  strt_address: '',
  postal_city: '',
  postal_code: '',
  postal_box: '',
  province: '',
  country: '',
  contact_person: '',
  contact_phone: '',
  contact_email: '',
  start_date: new Date(),
  expire_date: null
});

const selectedModules = ref<string[]>([]);

// Watch for industry changes to fetch industry-specific modules
watch(() => accountForm.industry_key, async (newIndustryKey) => {
  if (newIndustryKey) {
    await fetchIndustryModules(newIndustryKey);
  } else {
    industryModules.value = [];
    selectedModules.value = [];
  }
});

const fetchIndustryModules = async (industryKey: string) => {
  try {
    loadingModules.value = true;
    const response = await axios.get(`/internals/industries/${industryKey}/modules`);
    
    industryModules.value = response.data.modules;
    
    // Auto-select all industry modules by default
    selectedModules.value = industryModules.value.map((module: IndustryModule) => module.key);
    
    toast.add({
      severity: 'success',
      summary: 'Modules Loaded',
      detail: `${industryModules.value.length} modules loaded for this industry`,
      life: 2000
    });
    
  } catch (error) {
    console.error('Error fetching industry modules:', error);
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load modules for this industry',
      life: 3000
    });
    industryModules.value = [];
    selectedModules.value = [];
  } finally {
    loadingModules.value = false;
  }
};

const closeDialog = () => {
  emit('update:visible', false);
  resetForm();
};

const resetForm = () => {
  Object.assign(accountForm, {
    industry_key: null,
    acc_name: '',
    description: '',
    start_date: new Date(),
    max_bnses: 1,
    max_staff: 10,
    sender_name: '',
    sender_email: '',
    sender_username: '',
    sender_password: ''
  });
  
  Object.assign(companyForm, {
    bns_name: '',
    description: '',
    reg_num: '',
    tin_num: '',
    vat_num: '',
    bns_phone: '',
    bns_email: '',
    phys_address: '',
    strt_address: '',
    postal_city: '',
    postal_code: '',
    postal_box: '',
    province: '',
    country: '',
    contact_person: '',
    contact_phone: '',
    contact_email: '',
    start_date: new Date(),
    expire_date: null
  });
  
  selectedModules.value = [];
  industryModules.value = [];
  activeTab.value = "0";
};

const nextTab = () => {
  const currentTabNum = parseInt(activeTab.value);
  // Check if trying to go to modules tab without industry selected
  if (currentTabNum === 1 && !accountForm.industry_key) {
    toast.add({
      severity: 'warn',
      summary: 'Industry Required',
      detail: 'Please select an industry before proceeding to modules',
      life: 3000
    });
    return;
  }
  
  if (currentTabNum < 2) {
    activeTab.value = (currentTabNum + 1).toString();
  }
};

const prevTab = () => {
  const currentTabNum = parseInt(activeTab.value);
  if (currentTabNum > 0) {
    activeTab.value = (currentTabNum - 1).toString();
  }
};

const validateAccountForm = () => {
  if (!accountForm.acc_name || !accountForm.industry_key) {
    toast.add({
      severity: 'error',
      summary: 'Validation Error',
      detail: 'Account name and industry are required',
      life: 3000
    });
    return false;
  }
  return true;
};

const validateCompanyForm = () => {
  if (!companyForm.bns_name) {
    toast.add({
      severity: 'error',
      summary: 'Validation Error',
      detail: 'Business name is required',
      life: 3000
    });
    return false;
  }
  return true;
};

const submitRegistration = () => {
  if (!validateAccountForm() || !validateCompanyForm()) {
    return;
  }

  if (selectedModules.value.length === 0) {
    toast.add({
      severity: 'warn',
      summary: 'No Modules Selected',
      detail: 'Please select at least one module for this account',
      life: 3000
    });
    return;
  }

  loading.value = true;

  const formData = {
    account: accountForm,
    company: companyForm,
    modules: selectedModules.value
  };

  router.post('/internals/accounts/register', formData, {
    onSuccess: (response) => {
      console.log('Registration successful:', response);
      toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Account registered successfully',
        life: 3000
      });
      emit('registered');
      closeDialog();
    },
    onError: (errors) => {
      console.log('Registration errors:', errors);
      
      // Show specific validation errors if available
      if (errors && typeof errors === 'object') {
        Object.keys(errors).forEach(key => {
          if (Array.isArray(errors[key])) {
            errors[key].forEach(error => {
              toast.add({
                severity: 'error',
                summary: 'Validation Error',
                detail: error,
                life: 5000
              });
            });
          }
        });
      } else {
        toast.add({
          severity: 'error',
          summary: 'Registration Failed',
          detail: 'Please check the form and try again',
          life: 3000
        });
      }
    },
    onFinish: () => {
      loading.value = false;
    }
  });
};
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    header="NEW ACCOUNT"
    :style="{ width: '50rem' }"
    :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
    :focus-on-show="false"
    :auto-z-index="false"
    @update:visible="closeDialog"
  >
  <hr>
    <Tabs v-model:value="activeTab">
      <TabList>
        <Tab value="0">Account Info</Tab>
        <Tab value="1">Business Info</Tab>
        <Tab value="2" :disabled="!accountForm.industry_key">Industry Modules</Tab>
      </TabList>
      
      <TabPanels>
        <!-- Account Information Tab -->
        <TabPanel value="0">
        <div class="grid grid-cols-1 gap-4">
          <div class="field">
            <label for="acc_name" class="block text-sm font-medium mb-2">Account Name *</label>
            <InputText
              id="acc_name"
              v-model="accountForm.acc_name"
              class="w-full"
              placeholder="Enter account name"
              autocomplete="off"
            />
          </div>

          <div class="field">
            <label for="industry" class="block text-sm font-medium mb-2">Industry *</label>
            <Select
              id="industry"
              v-model="accountForm.industry_key"
              :options="industries"
              option-label="name"
              option-value="key"
              placeholder="Select industry"
              class="w-full"
              filter
              :auto-focus="false"
            />
            <small v-if="accountForm.industry_key" class="text-blue-600">
              Modules will be automatically loaded based on selected industry
            </small>
          </div>

          <div class="field">
            <label for="description" class="block text-sm font-medium mb-2">Description</label>
            <Textarea
              id="description"
              v-model="accountForm.description"
              class="w-full"
              rows="3"
              placeholder="Account description"
              autocomplete="off"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="field">
              <label for="max_bnses" class="block text-sm font-medium mb-2">Max Businesses</label>
              <InputNumber
                id="max_bnses"
                v-model="accountForm.max_bnses"
                class="w-full"
                :min="1"
              />
            </div>

            <div class="field">
              <label for="max_staff" class="block text-sm font-medium mb-2">Max Staff</label>
              <InputNumber
                id="max_staff"
                v-model="accountForm.max_staff"
                class="w-full"
                :min="1"
              />
            </div>
          </div>

          <div class="field">
            <label for="start_date" class="block text-sm font-medium mb-2">Start Date</label>
            <DatePicker
              id="start_date"
              v-model="accountForm.start_date"
              class="w-full"
              date-format="yy-mm-dd"
              :auto-focus="false"
            />
          </div>

          <!-- Sender Information -->
          <div class="grid grid-cols-2 gap-4">
            <div class="field">
              <label for="sender_name" class="block text-sm font-medium mb-2">Sender Name</label>
              <InputText
                id="sender_name"
                v-model="accountForm.sender_name"
                class="w-full"
                placeholder="Sender name"
                autocomplete="off"
              />
            </div>

            <div class="field">
              <label for="sender_email" class="block text-sm font-medium mb-2">Sender Email</label>
              <InputText
                id="sender_email"
                v-model="accountForm.sender_email"
                class="w-full"
                placeholder="sender@example.com"
                autocomplete="off"
              />
            </div>
          </div>
        </div>

        <div class="flex justify-end mt-4">
          <Button
            label="Next"
            icon="pi pi-arrow-right"
            @click="nextTab"
          />
        </div>
      </TabPanel>

      <!-- Business Information Tab -->
      <TabPanel value="1">
        <div class="grid grid-cols-1 gap-4">
          <div class="field">
            <label for="bns_name" class="block text-sm font-medium mb-2">Business Name *</label>
            <InputText
              id="bns_name"
              v-model="companyForm.bns_name"
              class="w-full"
              placeholder="Enter business name"
              autocomplete="off"
            />
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div class="field">
              <label for="reg_num" class="block text-sm font-medium mb-2">Registration Number</label>
              <InputText
                id="reg_num"
                v-model="companyForm.reg_num"
                class="w-full"
                placeholder="REG123456"
                autocomplete="off"
              />
            </div>

            <div class="field">
              <label for="tin_num" class="block text-sm font-medium mb-2">TIN Number</label>
              <InputMask
                id="tin_num"
                v-model="companyForm.tin_num"
                class="w-full"
                placeholder="TIN123456"
                autocomplete="off"
                mask="999-999-999"
              />
            </div>

            <div class="field">
              <label for="vat_num" class="block text-sm font-medium mb-2">VAT Number</label>
              <InputText
                id="vat_num"
                v-model="companyForm.vat_num"
                class="w-full"
                placeholder="VAT123456"
                autocomplete="off"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="field">
              <label for="bns_phone" class="block text-sm font-medium mb-2">Business Phone</label>
              <InputMask
                id="bns_phone"
                v-model="companyForm.bns_phone"
                class="w-full"
                placeholder="+1234567890"
                autocomplete="off"
                mask="9999-999-999"
              />
            </div>

            <div class="field">
              <label for="bns_email" class="block text-sm font-medium mb-2">Business Email</label>
              <InputText
                id="bns_email"
                v-model="companyForm.bns_email"
                class="w-full"
                placeholder="business@example.com"
                autocomplete="off"
              />
            </div>
          </div>

          <div class="field">
            <label for="phys_address" class="block text-sm font-medium mb-2">Physical Address</label>
            <InputText
              id="phys_address"
              v-model="companyForm.phys_address"
              class="w-full"
              placeholder="Physical address"
              autocomplete="off"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="field">
              <label for="contact_person" class="block text-sm font-medium mb-2">Contact Person</label>
              <InputText
                id="contact_person"
                v-model="companyForm.contact_person"
                class="w-full"
                placeholder="Contact person name"
                autocomplete="off"
              />
            </div>

            <div class="field">
              <label for="contact_phone" class="block text-sm font-medium mb-2">Contact Phone</label>
              <InputMask
                id="contact_phone"
                v-model="companyForm.contact_phone"
                class="w-full"
                placeholder="Contact phone"
                autocomplete="off"
                mask="9999-999-999"
              />
            </div>
          </div>
        </div>

        <div class="flex justify-between mt-4">
          <Button
            label="Previous"
            icon="pi pi-arrow-left"
            severity="secondary"
            @click="prevTab"
          />
          <Button
            label="Next"
            icon="pi pi-arrow-right"
            @click="nextTab"
          />
        </div>
      </TabPanel>

      <!-- Modules Selection Tab -->
      <TabPanel value="2">
        <div class="grid grid-cols-1 gap-4">
          <h5 v-if="accountForm.industry_key">
            Modules for {{ industries.find(i => i.key === accountForm.industry_key)?.name }}:
          </h5>
          <h5 v-else class="text-orange-600">
            Please select an industry in the first tab to see available modules
          </h5>
          
          <!-- Loading state -->
          <div v-if="loadingModules" class="text-center py-8">
            <ProgressSpinner />
            <p class="mt-2">Loading industry modules...</p>
          </div>
          
          <!-- Industry modules -->
          <div v-else-if="industryModules.length > 0" class="grid grid-cols-2 gap-4">
            <Card v-for="module in industryModules" :key="module.key" class="p-2">
              <template #content>
                <div class="flex items-center gap-3">
                  <Checkbox
                    :input-id="module.key"
                    v-model="selectedModules"
                    :value="module.key"
                  />
                  <label :for="module.key" class="cursor-pointer flex-1">
                    <div class="font-medium uppercase">
                        <small class="">{{ module.brand_label || module.brand_name }}</small></div>
                    <div class="text-sm text-gray-600">{{ module.description }}</div>
                    <div class="text-xs text-blue-500 mt-1">Priority: {{ module.pri }}</div>
                  </label>
                </div>
              </template>
            </Card>
          </div>

          <!-- No modules message -->
          <div v-else-if="accountForm.industry_key" class="text-center text-gray-500 py-8">
            No modules configured for this industry
          </div>

          <!-- No industry selected -->
          <div v-else class="text-center text-orange-500 py-8">
            Select an industry to view available modules
          </div>

          <!-- Selected modules summary -->
          <div v-if="selectedModules.length > 0" class="mt-4 p-3 bg-blue-50 rounded">
            <p class="text-sm text-blue-700">
              <strong>{{ selectedModules.length }}</strong> modules selected for this account
            </p>
          </div>
        </div>

        <div class="flex justify-between mt-4">
          <Button
            label="Previous"
            icon="pi pi-arrow-left"
            severity="secondary"
            @click="prevTab"
          />
          <Button
            label="Register Account"
            icon="pi pi-check"
            severity="success"
            @click="submitRegistration"
            :loading="loading"
            :disabled="!accountForm.industry_key || selectedModules.length === 0"
          />
        </div>
      </TabPanel>
      </TabPanels>
    </Tabs>
  </Dialog>
</template>