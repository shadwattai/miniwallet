<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from 'vue';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Avatar from 'primevue/avatar';
import ProgressSpinner from 'primevue/progressspinner';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Badge from 'primevue/badge';
import Tag from 'primevue/tag';

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

const emit = defineEmits(['update:visible', 'moduleAdded']);

const toast = useToast();
const loading = ref(false);
const submitting = ref(false);
const availableModules = ref([]);
const selectedModule = ref(null);
const searchQuery = ref('');

// Form data for module configuration
const moduleConfig = reactive({
//   start_date: new Date(),
//   expire_date: null,
  access: 'yes',
  description: '',
  pri: 0
});

const priorityString = ref(moduleConfig.pri.toString());

watch(priorityString, (val) => {
  const num = parseInt(val, 10);
  moduleConfig.pri = isNaN(num) ? 0 : num;
});

watch(() => moduleConfig.pri, (val) => {
  priorityString.value = val.toString();
});

// Watch for visibility changes
watch(() => props.visible, async (isVisible) => {
  if (isVisible && props.account) {
    await fetchAvailableModules();
    resetForm();
  }
});

const fetchAvailableModules = async () => {
  try {
    loading.value = true;
    const response = await axios.get(`/internals/accounts/${props.account.key}/available-modules`);
    availableModules.value = response.data.modules || [];
  } catch (error) {
    console.error('Error fetching available modules:', error);
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: 'Failed to load available modules',
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

const filteredModules = computed(() => {
  if (!searchQuery.value) return availableModules.value;
  
  const query = searchQuery.value.toLowerCase();
  return availableModules.value.filter(module => 
    module.app_name?.toLowerCase().includes(query) ||
    module.brand_name?.toLowerCase().includes(query) ||
    module.brand_label?.toLowerCase().includes(query) ||
    module.description?.toLowerCase().includes(query)
  );
});

const selectModule = (module) => {
  selectedModule.value = module;
  moduleConfig.description = module.description || '';
  moduleConfig.pri = module.pri || 0;
};

const resetForm = () => {
  selectedModule.value = null;
  searchQuery.value = '';
  Object.assign(moduleConfig, {
    // start_date: new Date(),
    // expire_date: null,
    access: 'yes',
    description: '',
    pri: 0
  });
};

const closeDialog = () => {
  emit('update:visible', false);
  resetForm();
};

const addModule = async () => {
  if (!selectedModule.value) {
    toast.add({
      severity: 'warn',
      summary: 'No Module Selected',
      detail: 'Please select a module to add',
      life: 3000
    });
    return;
  }

  try {
    submitting.value = true;

    const formData = {
      module_key: selectedModule.value.key,
    //   start_date: moduleConfig.start_date,
    //   expire_date: moduleConfig.expire_date,
      access: moduleConfig.access,
      description: moduleConfig.description,
      pri: moduleConfig.pri
    };

    await axios.post(`/internals/accounts/${props.account.key}/modules`, formData);

    toast.add({
      severity: 'success',
      summary: 'Success',
      detail: 'Module added successfully',
      life: 3000
    });

    emit('moduleAdded');
    closeDialog();

  } catch (error) {
    console.error('Error adding module:', error);
    const errorMessage = error.response?.data?.error || 'Failed to add module';
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: errorMessage,
      life: 3000
    });
  } finally {
    submitting.value = false;
  }
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString();
};
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    header="ADD MODULE TO ACCOUNT"
    :style="{ width: '60rem' }"
    :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
    @update:visible="closeDialog"
  >
    <Card class="border-2 border-gray-300 shadow-lg">
      <template #content>
        <div v-if="loading" class="flex justify-center items-center h-64">
          <ProgressSpinner />
          <span class="ml-3">Loading available modules...</span>
        </div>

        <div v-else class="space-y-4">
          <!-- Search and Filter -->
          <div class="mb-4">
            <IconField>
              <InputIcon class="pi pi-search" />
              <InputText
                v-model="searchQuery"
                placeholder="Search modules..."
                class="w-full"
              />
            </IconField>
          </div>

          <div class="grid grid-cols-2 gap-6">
            <!-- Available Modules List -->
            <div class="space-y-2">
              <h3 class="text-lg font-semibold mb-3">Available Modules ({{ filteredModules.length }})</h3>
              <hr class="mb-2" />

              <div v-if="filteredModules.length === 0" class="text-center py-8 text-gray-500">
                <div v-if="availableModules.length === 0">
                  No modules available to add
                </div>
                <div v-else>
                  No modules match your search
                </div>
              </div>

              <div v-else class="space-y-2 max-h-96 overflow-y-auto">
                <Card
                  v-for="module in filteredModules"
                  :key="module.key"
                  class="cursor-pointer border-2 border-gray-200 transition-all hover:shadow-md hover:border-gray-300"
                  :class="{ 'border-blue-500 bg-blue-50': selectedModule?.key === module.key }"
                  @click="selectModule(module)"
                >
                  <template #content>
                    <div class="flex items-center gap-3 p-2">
                      <div class="relative inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-green-500">
                        <Avatar
                          :image="module.logo_path || '/logos/apps/layout-grid.png'"
                          class="h-6 w-6"
                        />
                      </div>
                      
                      <div class="flex-1">
                        <h4 class="font-semibold text-sm uppercase">
                          {{ module.brand_label || module.brand_name || module.app_name }}
                        </h4>
                        <p class="text-xs text-gray-600 mt-1">
                          {{ module.description || 'No description available' }}
                        </p>
                        <div class="flex gap-2 mt-2">
                          <Badge :value="`Priority: ${module.pri || 0}`" severity="info" />
                        </div>
                      </div>
                    </div>
                  </template>
                </Card>
              </div>
            </div>

            <!-- Module Configuration -->
            <div class="space-y-4">
              <h3 class="text-lg font-semibold mb-3">Module Configuration</h3>
              <hr class="mb-2" />
              <div v-if="!selectedModule" class="text-center py-8 text-gray-500">
                Select a module to configure
              </div>

              <div v-else class="space-y-4">
                <!-- Selected Module Preview -->
                <Card class="border-2 border-blue-300 bg-blue-50">
                  <template #content>
                    <div class="flex items-center gap-3">
                      <div class="relative inline-flex items-center justify-center w-12 h-12 rounded-lg bg-gradient-to-br from-blue-500 to-green-500">
                        <Avatar
                          :image="selectedModule.logo_path || '/logos/apps/layout-grid.png'"
                          class="h-8 w-8"
                        />
                      </div>
                      <div>
                        <h4 class="font-semibold uppercase">
                          {{ selectedModule.brand_label || selectedModule.brand_name || selectedModule.app_name }}
                        </h4>
                        <p class="text-sm text-gray-600">
                          {{ selectedModule.description || 'No description available' }}
                        </p>
                      </div>
                    </div>
                  </template>
                </Card>

                <!-- Configuration Form -->
                <div class="space-y-4">
                  <!-- <div class="field">
                    <label for="start_date" class="block text-sm font-medium mb-2">Start Date *</label>
                    <DatePicker
                      id="start_date"
                      v-model="moduleConfig.start_date"
                      class="w-full"
                      date-format="yy-mm-dd"
                    />
                  </div> -->

                  <!-- <div class="field">
                    <label for="expire_date" class="block text-sm font-medium mb-2">Expire Date</label>
                    <DatePicker
                      id="expire_date"
                      v-model="priorityString"
                      type="text"
                      class="w-full"
                      placeholder="Module priority (0 = highest)"
                    />
                  </div> -->

                  <div class="field">
                    <label for="priority" class="block text-sm font-medium mb-2">Priority</label>
                    <InputText
                      id="priority"
                      v-model="moduleConfig.pri"
                      type="number"
                      class="w-full"
                      placeholder="Module priority (0 = highest)"
                    />
                  </div>

                  <div class="field">
                    <label for="module_description" class="block text-sm font-medium mb-2">Custom Description</label>
                    <Textarea
                      id="module_description"
                      v-model="moduleConfig.description"
                      class="w-full"
                      rows="3"
                      placeholder="Custom description for this module assignment"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Cancel"
          severity="secondary"
          @click="closeDialog"
          outlined
        />
        <Button
          label="Add Module"
          icon="pi pi-plus"
          severity="success"
          :disabled="!selectedModule"
          :loading="submitting"
          @click="addModule"
        />
      </div>
    </template>
  </Dialog>
</template>