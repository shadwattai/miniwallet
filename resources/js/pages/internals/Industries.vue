<script setup lang="ts">
import { dashboard } from '@/routes';
import { Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Toolbar from 'primevue/toolbar';
import Dialog from 'primevue/dialog';
import Fieldset from 'primevue/fieldset';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import MultiSelect from 'primevue/multiselect';
import Avatar from 'primevue/avatar';
import Skeleton from 'primevue/skeleton';
import IconField from 'primevue/iconfield';

import { FilterMatchMode } from '@primevue/core/api';
import { router } from '@inertiajs/vue3';

import { Form, Field } from 'vee-validate';
import { ref, computed } from 'vue';

import { useConfirm } from "primevue/useconfirm";
import ConfirmPopup from 'primevue/confirmpopup';
import { useToast } from 'primevue/usetoast'
import AddModuleToIndustry from '@/components/AddModuleToIndustry.vue';

const toast = useToast();
const confirm = useConfirm();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Home',
    href: dashboard().url,
  },
  {
    title: 'Industries',
    href: ''
  }
];

const props = defineProps({
  industries: Array,
  maccess: Object,
  modules: Array
});


const visible = ref(false);
const dataForm = ref(false);
const selectedModules = ref<any[]>([]);

interface Industry {
  key: string;
  name: string;
  description: string;
  nick: string;
  modules: any[];
  mods?: any[];
}

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  verified: { value: null, matchMode: FilterMatchMode.EQUALS },

  apps: { value: null, matchMode: FilterMatchMode.IN },
  code: { value: null, matchMode: FilterMatchMode.EQUALS },
  name: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
  description: { value: null, matchMode: FilterMatchMode.STARTS_WITH },
});

function showDataForm(event?: MouseEvent, data: any = null) {
  dataForm.value = true;
  row.value = data;
  selectedModules.value = props.modules ?? [];
}


const row = ref<Industry | null>(null);
function showRowData(data: Industry) {
  visible.value = true;
  row.value = data;
}


const loading = ref(false);
function closeForm() {
  dataForm.value = false;
  row.value = null;
}

function confirmDelete(data: Industry) {
  console.log('Deleting industry:', data);
}


const initialValues = ref({
  module: [],
  name: '',
  description: '',
  nick: ''
});

interface ValidationError {
  type: string;
  message: string;
}

const resolver = (values: any) => {
  const errors: Record<string, string> = {};
  if (!values.name) {
    errors.name = 'Name is required';
  }
  if (!values.description) {
    errors.description = 'Description is required';
  }
  if (!values.nick) {
    errors.nick = 'Industry code is required';
  }
  return { values, errors };
};

function onFormSubmit(values: any, { resetForm }: any) {
  loading.value = true;
  console.log('Form submission values:', values);
  const isEdit = !!row.value?.key;

  if (isEdit) {
    const url = `/internals/industries/${row.value!.key}`;
    router.put(url, values, {
      onSuccess: () => {
        toast.add({ severity: 'success', summary: 'Success', detail: 'Industry updated.', life: 3000 });
        resetForm();
        closeForm();
      },
      onError: (errors: any) => {
        console.log('Validation errors:', errors);
        const errorMessage = errors.error || 'Failed to save industry.';
        toast.add({ severity: 'error', summary: 'Error', detail: errorMessage, life: 5000 });
      },
      onFinish: () => {
        loading.value = false;
      }
    });
  } else {
    const url = '/internals/industries';
    console.log('Posting to URL:', url);
    console.log('Posting data:', values);
    router.post(url, values, {
      onSuccess: () => {
        toast.add({ severity: 'success', summary: 'Success', detail: 'Industry created.', life: 3000 });
        resetForm();
        closeForm();
      },
      onError: (errors: any) => {
        console.log('Validation errors:', errors);
        const errorMessage = errors.error || 'Failed to save industry.';
        toast.add({ severity: 'error', summary: 'Error', detail: errorMessage, life: 5000 });
      },
      onFinish: () => {
        loading.value = false;
      }
    });
  }
}

declare module 'vee-validate' {
  interface FieldError extends ValidationError { }
}

const formattedRowData = computed(() => {
  if (!row.value) {
    return [];
  }
  // Exclude fields you don't want to display
  const ignoredKeys = ['id', 'key', 'created_by', 'created_at', 'updated_at', 'mods'];
  return Object.entries(row.value)
    .filter(([key]) => !ignoredKeys.includes(key))
    .map(([key, value]) => ({
      field: key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
      value: value
    }));
});

const showConfirmationTemplate = (event: MouseEvent): void => {
  confirm.require({
    target: event.currentTarget as HTMLElement,
    group: 'templating',
    message: 'CONFIRM TO PROCEED',
    icon: 'pi pi-exclamation-circle',
    rejectProps: {
      icon: 'pi pi-times',
      label: 'Cancel',
      outlined: true
    },
    acceptProps: {
      icon: 'pi pi-check',
      label: 'Confirm'
    },
    accept: () => {
      toast.add({ severity: 'info', summary: 'Confirmed', detail: 'Action accepted', life: 3000 });
    },
    reject: () => {
      toast.add({ severity: 'error', summary: 'Rejected', detail: 'Action rejected', life: 3000 });
    }
  });
}

function removeIndustryModule(rowKey: string, moduleName: string) {
  loading.value = true;
  router.delete(`/internals/industries/${rowKey}/modules/${moduleName}`, {
    onSuccess: () => {
      toast.add({ severity: 'success', summary: 'Success', detail: 'Module removed from industry.', life: 3000 });
      // Remove the module from the row.value.mods array
      if (row.value?.mods) {
        const idx = row.value.mods.findIndex((m: any) => m.app_name.toLowerCase() === moduleName.toLowerCase());
        if (idx !== -1) row.value.mods.splice(idx, 1);
      }
    },
    onError: (errors: any) => {
      toast.add({ severity: 'error', summary: 'Error', detail: errors.error || 'Failed to remove module.', life: 5000 });
    },
    onFinish: () => {
      loading.value = false;
    }
  });
}

function confirmRemoveIndustryModule(event: MouseEvent, rowKey: string, moduleKey: string, moduleName: string) {
  confirm.require({
    target: event.currentTarget as HTMLElement,
    group: 'templating',
    message: `Are you sure you want to remove "${moduleName}" from this industry?`,
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Remove',
    rejectLabel: 'Cancel',
    acceptClass: 'p-button-danger',
    accept: () => removeIndustryModule(rowKey, moduleName),
    reject: () => { }
  });
}

const showAddModuleDialog = ref(false);
const selectedModuleKey = ref('');
const addModuleLoading = ref(false);
const addModuleDescription = ref('');
const searchQuery = ref('');

function addModuleToIndustry() {
  if (!row.value || !selectedModuleKey.value) return;
  addModuleLoading.value = true;
  router.post(`/internals/industries/${row.value.key}/modules`, {
    module_key: selectedModuleKey.value,
    description: addModuleDescription.value,
  }, {
    onSuccess: () => {
      toast.add({ severity: 'success', summary: 'Success', detail: 'Module added to industry.', life: 3000 });
      // Add the new module to the row.value.mods array
      const newModule = (props.modules ?? []).find(m => m.key === selectedModuleKey.value);
      if (newModule && row.value.mods) {
        row.value.mods.push({
          ...newModule,
          app_key: newModule.key,
          description: addModuleDescription.value,
          status: 'active',
        });
      }
      showAddModuleDialog.value = false;
      selectedModuleKey.value = '';
      addModuleDescription.value = '';
    },
    onError: (errors) => {
      toast.add({ severity: 'error', summary: 'Error', detail: errors.error || 'Failed to add module.', life: 5000 });
    },
    onFinish: () => {
      addModuleLoading.value = false;
    }
  });
}

function onIndustryModuleAdded(module) {
  if (row.value && module && module.key) {
    const newModule = props.modules.find(m => m.key === module.key);
    if (newModule) {
      if (!row.value.mods) row.value.mods = [];
      row.value.mods.push({
        ...newModule,
        app_key: newModule.key,
        description: module.description,
        status: 'active',
      });
    }
    toast.add({ severity: 'success', summary: 'Success', detail: 'Module added to industry.', life: 3000 });
    showAddModuleDialog.value = false;
  }
}

</script>


<template>

  <Head title="INDUSTRIES" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div style="padding-top: -10px;">

      <Toolbar class="mb-1" :style="{ justifyContent: 'space-between', borderRadius: '0px' }">
        <template #start>
          <h1 class="m-0">
            <span class="font-bold p-2 bg-clip-text">
              INDUSTRIES SUPPORTED
            </span>
          </h1>
        </template>
      </Toolbar>


      <div class="flex" v-for="i in [1, 2, 3, 4, 5]" v-if="!industries || industries.length === 0" :key="i"
        style="margin-bottom: 1em;">
        <Skeleton shape="circle" size="4rem" class="mr-2"></Skeleton>
        <div class="self-center" style="flex: 1" :style="{ paddingBottom: '20px' }">
          <Skeleton width="100%" class="mb-2"></Skeleton>
          <Skeleton width="75%"></Skeleton>
        </div>
      </div>


      <DataTable v-model:filters="filters" :value="industries" paginator :rows="10" dataKey="id"
        :totalRecords="industries?.length ?? 0" :rowsPerPageOptions="[10, 20, 30, industries?.length ?? 10]"
        filterDisplay="menu" :loading="loading" :globalFilterFields="['name', 'description', 'code', 'apps']"
        removableSort resizableColumns columnResizeMode="fit" sortField="id" tableStyle="min-width: 50rem"
        selectionMode="single" scrollable scrollHeight="800px">

        <template #header>
          <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
            <div style="flex:1; margin-right:1rem;">
              <IconField style="width:100%;">
                <InputText v-model="filters['global'].value" placeholder="Search ..." style="width:40%;" />
              </IconField>
            </div>
            <div>
              <Button label="REGISTER" @click="(e) => showDataForm(e)" icon="pi pi-plus-circle" severity="info" />
            </div>
          </div>
        </template>

        <template #empty> No data found. </template>
        <template #loading> Loading data. Please wait. </template>

        <!-- <Column style="width: 5%" header="SN">
          <template #body="slot">
            {{ slot.index + 1 }}.
          </template>
        </Column>  -->

        <Column style="width: 5%" header="CODE">
          <template #body="slot">
            <div
              class="flex items-center justify-center w-9 h-9 rounded-full bg-gradient-to-r from-blue-500 to-green-500 text-white">
              <small>{{ slot.data.code }}</small>
            </div>
          </template>
        </Column>

        <Column sortable style="width: 20%" field="name" header="NAME"></Column>
        <Column sortable style="width: 35%" field="description" header="DESCRIPTION"></Column>
        <Column sortable style="width: 30%" field="apps" header="MODULES">
          <template #body="slot">
            <div class="flex align-items-center justify-content-center w-full" style="height:100%;">
              <div class="text-center">
                {{ slot.data.apps }}
              </div>
            </div>
          </template>
        </Column>
        <Column style="width: 10%" header="ACTIONS">
          <template #body="slotActions" #end>
            <div class="flex justify-content-end">
              <Button variant="outlined" severity="secondary" @click="showRowData(slotActions.data)" type="button"
                icon="pi pi-eye" raised size="small" />
              &nbsp;
              <Button variant="outlined" severity="info" @click="showDataForm($event, slotActions.data)" type="button"
                icon="pi pi-pencil" raised size="small" />
              &nbsp;
              <Button variant="outlined" severity="danger" @click="showConfirmationTemplate($event)" label=""
                type="button" icon="pi pi-trash" raised size="small" />
            </div>
          </template>
        </Column>
      </DataTable>
      <!-- </div> -->

    </div>
  </AppLayout>


  <div class="card flex justify-center">
    <template>
      <Dialog v-model:visible="visible" :modal="true" :closable="true" :style="{ width: '50vw' }"
        :breakpoints="{ '960px': '75vw', '640px': '100vw' }">
        <template #header>
          <div class="flex justify-content-end w-full">
            <Button icon="pi pi-times" @click="visible = false" text rounded />
          </div>
        </template>
        <div style="margin-top: -6px;"></div>

        <div class="flex flex-col gap-4">
          <div>
            <Fieldset class="w-full">
              <template #legend>
                <div class="flex items-center pl-2">
                  <div
                    class="relative inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-green-500 rounded-full">
                    <Avatar image="/logos/apps/radius.png"
                      class="h-8 w-8 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
                  </div>

                  <span
                    class="font-bold p-2 uppercase bg-gradient-to-r from-indigo-500 to-violet-500 text-transparent bg-clip-text">
                    {{ row?.name }}
                  </span>
                </div>
              </template>

              <table class="w-full">
                <tbody>
                  <tr v-for="(item, index) in formattedRowData" :key="index">
                    <td class="font-bold capitalize p-2 w-1/3">{{ item.field }}</td>
                    <td class="p-2 w-2/3">{{ item.value }}</td>
                    <td class="p-2 w-auto">
                      <Button variant="outlined" severity="info" type="button" icon="pi pi-pencil" size="small" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </Fieldset>
          </div>

          <div>
            <Fieldset class="w-full">
              <template #legend>
                <div class="flex items-center pl-2">
                  <div
                    class="relative inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-green-500"
                    style="border-radius: 6px;">
                    <Avatar image="/logos/apps/layout-grid.png"
                      class="h-8 w-8 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
                  </div>
                  <span
                    class="font-bold p-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-transparent bg-clip-text">
                    LINKED MODULES
                  </span>
                </div>
              </template>

              <div>
                <DataTable :value="row?.mods ?? []" removableSort resizableColumns columnResizeMode="fit" sortField="id"
                  class="w-full" tableStyle="min-width: 0">

                  <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div style="flex:1; margin-right:1rem;">
                      <IconField style="width:100%;">
                        <InputText v-model="searchQuery" placeholder="Search ..." style="width:50%;"  disabled/>
                      </IconField>
                    </div>
                    <div>
                      <Button label="ADD MODULE" icon="pi pi-plus" severity="info" size="small" class="ml-4"
                        @click="showAddModuleDialog = true" />
                    </div>
                  </div>

                  <Column style="width: 5%">
                    <template #body="slot">
                      <div
                        class="relative inline-flex items-center justify-center w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-green-500"
                        style="border-radius: 6px;">
                        <Avatar :image="slot.data.logo_path"
                          class="h-8 w-8 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
                      </div>
                    </template>
                  </Column>

                  <Column style="width: 20%" class="uppercase" field="app_name"></Column>

                  <Column style="width: 65%" field="description"></Column>

                  <Column style="width: 10%">
                    <template #body="slotActions" #end>
                      <div class="flex justify-content-end">
                        <Button label="REMOVE" variant="outlined" severity="danger" type="button" size="small"
                          icon="pi pi-trash"
                          @click="(e) => row && confirmRemoveIndustryModule(e, row.key, slotActions.data.app_key, slotActions.data.app_name)" />
                      </div>
                    </template>
                  </Column>
                </DataTable>
              </div>
            </Fieldset>
          </div>
        </div>

      </Dialog>
    </template>
  </div>




  <div class="card flex justify-center">
    <template>
      <Dialog v-model:visible="dataForm" :modal="true" :closable="true" :style="{ width: '40vw' }"
        :breakpoints="{ '1199px': '75vw', '575px': '90vw' }">
        <template #header>
          <div class="flex justify-content-end w-full">
            <Button icon="pi pi-times" @click="dataForm = false" text rounded />
          </div>
        </template>
        <div style="margin-top: -12px;"></div>


        <Form :key="row ? `edit-${row.key}` : 'new'" v-slot="{ values, errors, resetForm, meta }" :resolver="resolver"
          :initial-values="{
            key: row?.key ?? null,
            module: row?.mods ?? row?.modules ?? [],
            name: row?.name ?? '',
            description: row?.description ?? '',
            nick: row?.nick ?? ''
          }" @submit="onFormSubmit" class="flex justify-center flex-col gap-4">

          <Fieldset>
            <template #legend>
              <div class="flex items-center pl-2">
                <Avatar image="/logos/apps/radius.png" shape="circle"
                  class="rounded-full bg-gradient-to-r from-blue-500 to-green-500" />
                <span
                  class="font-bold p-2 bg-gradient-to-r from-blue-500 to-violet-500 text-transparent bg-clip-text">{{
                    row ? 'EDIT INDUSTRY' : 'NEW INDUSTRY' }}</span>
              </div>
            </template>

            <div class="flex flex-col gap-2" style="margin-bottom: 1em;">
              <label for="name" :style="{ color: (errors.name) ? '#dc2626' : '' }">Name</label>
              <Field name="name" v-slot="{ field, errorMessage }">
                <InputText id="name" v-bind="field" :style="{
                  borderColor: (errorMessage || errors.name) ? '#dc2626 !important' : '',
                  borderWidth: (errorMessage || errors.name) ? '2px !important' : ''
                }" placeholder="Enter industry name" autocomplete="off" required />
                <Message v-if="errorMessage || errors.name" severity="error" size="small">{{ errorMessage || errors.name
                  }}
                </Message>
              </Field>
            </div>

            <div class="flex flex-col gap-2" style="margin-bottom: 1em;">
              <label for="description" :style="{ color: (errors.description) ? '#dc2626' : '' }">Description</label>
              <Field name="description" v-slot="{ field, errorMessage }">
                <InputText id="description" v-bind="field" :style="{
                  borderColor: (errorMessage || errors.description) ? '#dc2626 !important' : '',
                  borderWidth: (errorMessage || errors.description) ? '2px !important' : ''
                }" placeholder="Enter industry description" autocomplete="off" />
                <Message v-if="errorMessage || errors.description" severity="error" size="small">{{ errorMessage ||
                  errors.description }}</Message>
              </Field>
            </div>

            <div class="flex flex-col gap-1" style="margin-bottom: 1em;">
              <label for="nick" :style="{ color: (errors.nick) ? '#dc2626' : '' }">Industry code</label>
              <Field name="nick" v-slot="{ field, errorMessage }">
                <InputText required id="nick" v-bind="field" :style="{
                  borderColor: (errorMessage || errors.nick) ? '#dc2626 !important' : '',
                  borderWidth: (errorMessage || errors.nick) ? '2px !important' : ''
                }" placeholder="Enter unique industry code" autocomplete="off" />
                <Message v-if="errorMessage || errors.nick" severity="error" size="small">{{ errorMessage || errors.nick
                  }}
                </Message>
              </Field>
            </div>

            <div class="flex flex-col gap-1" style="margin-bottom: 3em;">
              <label for="modules">Linked modules</label>
              <Field name="module" v-slot="{ field }">
                <MultiSelect id="modules" v-bind="field" :options="selectedModules" display="chip"
                  optionLabel="mod_name" filter showClear placeholder="Select Modules" :maxSelectedLabels="3"
                  :virtualScrollerOptions="{ itemSize: 30 }" style="font-size: medium;" />
              </Field>
            </div>

            <hr class="w-full">
            <div class="flex justify-end gap-2 mt-2">
              <Button type="button" outlined severity="secondary" label="Cancel"
                @click="() => { resetForm(); closeForm(); }" />
              <Button type="submit" severity="info" :loading="loading" :label="row ? 'Update' : 'Submit'"
                :disabled="!meta.valid || loading || !values.name || !values.description || !values.nick" />
            </div>
          </Fieldset>
        </Form>

      </Dialog>
    </template>
  </div>



  <ConfirmPopup group="templating">
    <template #message="slotProps">
      <div
        class="flex flex-col items-center w-full gap-4 border-b border-surface-200 dark:border-surface-700 p-4 mb-4 pb-0">
        <i :class="slotProps.message.icon" class="!text-6xl text-danger-500"></i>
        <p>{{ slotProps.message.message }}</p>
      </div>
    </template>
  </ConfirmPopup>


  <AddModuleToIndustry v-model:visible="showAddModuleDialog" :industry="row" :modules="props.modules"
    @moduleAdded="onIndustryModuleAdded" />

</template>
