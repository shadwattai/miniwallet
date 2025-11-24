<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Avatar from 'primevue/avatar';
import ProgressSpinner from 'primevue/progressspinner';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import MultiSelect from 'primevue/multiselect';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  visible: Boolean,
  industry: Object,
  modules: Array
});
const emit = defineEmits(['update:visible', 'moduleAdded']);

const toast = useToast();
const submitting = ref(false);
const selectedModuleKey = ref('');
const addModuleDescription = ref('');

const availableModules = computed(() => {
  if (!props.industry || !props.modules) return [];
  const linkedKeys = (props.industry.mods ?? []).map((m: any) => m.app_key);
  return props.modules.filter((m: any) => !linkedKeys.includes(m.key));
});

const resetForm = () => {
  selectedModuleKey.value = '';
  addModuleDescription.value = '';
};

watch(() => props.visible, (isVisible) => {
  if (isVisible) resetForm();
});

const closeDialog = () => {
  emit('update:visible', false);
  resetForm();
};

const addModule = async () => {
  if (!props.industry || !selectedModuleKey.value) {
    toast.add({ severity: 'warn', summary: 'No Module Selected', detail: 'Please select a module to add', life: 3000 });
    return;
  }
  submitting.value = true;
  router.post(`/internals/industries/${props.industry.key}/modules`, {
    module_key: selectedModuleKey.value,
    description: addModuleDescription.value,
  }, {
    preserveState: true,
    onSuccess: () => {
      toast.add({ severity: 'success', summary: 'Success', detail: 'Module added to industry.', life: 3000 });
      emit('moduleAdded', { key: selectedModuleKey.value, description: addModuleDescription.value });
      closeDialog();
    },
    onError: (errors) => {
      const errorMessage = errors.error || 'Failed to add module.';
      toast.add({ severity: 'error', summary: 'Error', detail: errorMessage, life: 5000 });
    },
    onFinish: () => {
      submitting.value = false;
    }
  });
};
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    header="ADD MODULE TO INDUSTRY"
    :style="{ width: '30vw' }"
    @update:visible="closeDialog"
  >
    <Card class="border-1 border-gray-100">
      <template #content>
        <div class="space-y-4">
          <div>
            <label for="moduleSelect">Select Module</label>
            <MultiSelect filter
              id="moduleSelect"
              v-model="selectedModuleKey"
              :options="availableModules"
              optionLabel="mod_name"
              optionValue="key"
              placeholder="Choose module"
              display="chip"
              :maxSelectedLabels="3"
              class="w-full"
            />
          </div>
          <div>
            <label for="moduleDesc">Description (optional)</label>
            <Textarea id="moduleDesc" v-model="addModuleDescription" class="w-full" rows="2" placeholder="Module description" />
          </div>
        </div>
      </template>
    </Card>
    <template #footer>
      <div class="flex justify-end gap-2">
        <Button label="Cancel" severity="secondary" outlined @click="closeDialog" />
        <Button label="Add Module" icon="pi pi-plus" severity="success" :disabled="!selectedModuleKey" :loading="submitting" @click="addModule" />
      </div>
    </template>
  </Dialog>
</template>
