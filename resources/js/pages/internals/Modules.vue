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
import Avatar from 'primevue/avatar';
import Skeleton from 'primevue/skeleton';
import IconField from 'primevue/iconfield';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';

import { FilterMatchMode } from '@primevue/core/api';
import { ref, computed, getCurrentInstance } from 'vue';
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Divider from 'primevue/divider';

let toast = useToast();
let confirm = useConfirm();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Home',
    href: dashboard().url,
  },
  {
    title: 'Modules',
    href: ''
  }
];

const props = defineProps({
  maccess: Object,
  modules: Array,
});


const visible = ref(false);
const dataForm = ref(false);
const selectedModules = ref<any[]>([]);

interface Module {
  id: number;
  name: string;
  description: string;
  nick: string;
  modules: any[];

  // Optional fields used in the template/view
  app_name?: string;
  brand_label?: string;
  pri?: number | string;
  category?: string;
  logo_path?: string;
  // industries structure used in the dialog table
  industries?: { id: number; name: string; description?: string }[];
  // features structure used in the dialog table
  features?: Record<string, { key: string; uri_link: string; description: string; icon: string; pri: number; common: string }[]>;
}

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  verified: { value: null, matchMode: FilterMatchMode.EQUALS },

  icon: { value: null, matchMode: FilterMatchMode.IN },
  brand_label: { value: null, matchMode: FilterMatchMode.CONTAINS },
  app_name: { value: null, matchMode: FilterMatchMode.CONTAINS },
  description: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

function showDataForm(event?: MouseEvent, data: any = null) {
  dataForm.value = true;
  row.value = data;
  selectedModules.value = props.modules ?? [];
}


const row = ref<Module | null>(null);
function showRowData(data: Module) {
  visible.value = true;
  row.value = data;
}


const loading = ref(false);
function closeForm() {
  dataForm.value = false;
  row.value = null;
}

function confirmDelete(data: Module) {
  console.log('Deleting Module:', data);
}


function onFormSubmit(values: any) {
  loading.value = true;
  console.log(values);

  setTimeout(() => {
    row.value = null;
    loading.value = false;
    dataForm.value = false;
  }, 2000);
}

const formattedRowData = computed(() => {
  if (!row.value) {
    return [];
  }

  const ignoredKeys = [
    'id', 'key', 'created_by', 'created_at', 'updated_at',
    'deleted_at', 'deleted_by', 'mods', 'logo_path', 'status',
    'publish', 'progress', 'text_class', 'industries', 'updated_by', 'features',
    'linked', 'grouped_features', 'features_count'
  ];
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




</script>


<template>

  <Head title="MODULES" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div style="padding-top: -5px;">

      <Toolbar class="mb-1" :style="{ justifyContent: 'space-between', borderRadius: '0px' }">
        <template #start>
          <h1 class="m-0">
            <span class="font-bold p-2 bg-clip-text">
              SYSTEM MODULES
            </span>
          </h1>
        </template>
      </Toolbar>


      <div class="flex" v-for="i in [1, 2, 3, 4, 5]" v-if="!modules || modules.length === 0" :key="i"
        style="margin-bottom: 1em;">
        <Skeleton shape="circle" size="4rem" class="mr-2"></Skeleton>
        <div class="self-center" style="flex: 1" :style="{ paddingBottom: '20px' }">
          <Skeleton width="100%" class="mb-2"></Skeleton>
          <Skeleton width="75%"></Skeleton>
        </div>
      </div>


      <DataTable v-model:filters="filters" :value="modules" paginator :rows="10" dataKey="id"
        :totalRecords="modules?.length ?? 0" :rowsPerPageOptions="[10, 20, 30, modules?.length ?? 10]"
        filterDisplay="menu" :loading="loading"
        :globalFilterFields="['app_name', 'description', 'icon', 'brand_label', 'category', 'pri', 'brand_name', 'linked']"
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
              <Button disabled label="REGISTER" @click="(e) => showDataForm(e)" icon="pi pi-plus-circle"
                severity="info" />
            </div>
          </div>
        </template>

        <template #empty> No data found. </template>
        <template #loading> Loading data. Please wait. </template>

        <Column style="width: 5%" header="SN" hidden>
          <template #body="slot">
            {{ slot.index + 1 }}.
          </template>
        </Column>

        <Column style="width: 5%" header="ICON">
          <template #body="slot">
            <div
              class="relative inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-green-500"
              style="border-radius: 6px;">
              <Avatar :image="slot.data.logo_path"
                class="h-8 w-8 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
            </div>
          </template>
        </Column>


        <Column sortable style="width: 15%" field="app_name" class="uppercase" header="NAME"></Column>
        <Column sortable style="width: 10%" field="category" class="uppercase" header="CATEGORY"></Column>
        <Column sortable style="width: 15%" field="brand_label" class="uppercase" header="BRAND"></Column>
        <Column sortable style="width: 30%" field="description" header="DESCRIPTION"></Column>

        <Column sortable style="width: 10%" field="pri" header="PRIORITY">
          <template #body="slot">
            <div class="flex justify-center">
              <span class="font-medium">{{ slot.data.pri || 0 }}</span>
            </div>
          </template>
        </Column>


        <Column sortable style="width: 10%" field="linked" header="INDUSTRIES">
          <template #body="slot">
            <div class="flex justify-center">
              <span class="font-medium">{{ slot.data.linked || 0 }}</span>
            </div>
          </template>
        </Column>

        <Column sortable style="width: 10%" field="features_count" header="FEATURES">
          <template #body="slot">
            <div class="flex justify-center">
              <span class="font-medium">{{ slot.data.features_count || 0 }}</span>
            </div>
          </template>
        </Column>



        <Column style="width: 10%" header="ACTIONS">
          <template #body="slotActions" #end>
            <div class="flex justify-content-end">
              <Button variant="outlined" severity="info" @click="showRowData(slotActions.data)" type="button"
                icon="pi pi-eye" size="small" label="View" />
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

        <Fieldset legend="Module Details">
          <template #legend>
            <div class="flex items-center gap-4 mb-4">
              <div
                class="relative inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-green-500"
                style="border-radius: 6px;">
                <Avatar :image="row?.logo_path"
                  class="h-8 w-8 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
              </div>
              <div>
                <h3 class="font-bold text-lg uppercase text-gray-900">{{ row?.app_name }}</h3>
                <p class="text-sm text-gray-600">{{ row?.description }}</p>
              </div>
            </div>
          </template>
          <!-- <div style="margin-top: 8px;"></div> -->
          <Divider />

          <div class="flex flex-col gap-4">
            

            <TabView>
              <!-- Module Details Tab -->
              <TabPanel value="0" header="MODULE">
                <div class="grid grid-cols-2 gap-4">
                  <div v-for="(item, index) in formattedRowData" :key="index" class="border-b border-gray-200 pb-2">
                    <div class="font-bold text-gray-700 mb-1">{{ item.field }}</div>
                    <div class="text-gray-900">
                      {{
                        item.field === 'App Name'
                          ? String(item.value ?? '').toUpperCase()
                          : item.field === 'Category'
                            ? String(item.value ?? '').toUpperCase()
                            : item.value
                      }}
                    </div>
                  </div>
                </div>
              </TabPanel>

              <!-- Industries Tab -->
              <TabPanel value="1" header="INDUSTRIES">
                <div class="flex" v-for="i in [1, 2, 3]" v-if="!row?.industries || row?.industries.length === 0"
                  :key="i" style="margin-bottom: 1em;">
                  <Skeleton shape="circle" size="4rem" class="mr-2"></Skeleton>
                  <div class="self-center" style="flex: 1" :style="{ paddingBottom: '20px' }">
                    <Skeleton width="100%" class="mb-2"></Skeleton>
                    <Skeleton width="75%"></Skeleton>
                  </div>
                </div>

                <div v-if="row?.industries && row.industries.length > 0">
                  <DataTable :value="row.industries" removableSort resizableColumns columnResizeMode="fit"
                    sortField="id" class="w-full" tableStyle="min-width: 0">
                    <Column style="width: 5%" header="SN">
                      <template #body="slot">
                        {{ slot.index + 1 }}.
                      </template>
                    </Column>
                    <Column sortable style="width: 30%" class="uppercase" field="name" header="INDUSTRY"></Column>
                    <Column sortable style="width: 65%" field="description" header="DESCRIPTION"></Column>
                  </DataTable>
                </div>
                <div v-else-if="row?.industries && row.industries.length === 0" class="text-center py-8 text-gray-500">
                  <i class="pi pi-info-circle text-2xl"></i>
                  <p class="mt-2">No industries linked to this module</p>
                </div>
              </TabPanel>


              <TabPanel value="2" header="FEATURES">
                <div v-if="row?.features && typeof row.features === 'object'">
                  <Accordion :multiple="true" :activeIndex="[0]">
                    <AccordionPanel v-for="(links, featureName) in row.features" :key="featureName" :value="featureName">
                      <AccordionHeader>
                        <div class="flex items-center gap-3">
                          <i class="pi pi-bookmark text-blue-600"></i>
                          <span class="uppercase font-bold text-lg">{{ featureName }}</span>
                          <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded-full">
                            {{ links.length }} link{{ links.length !== 1 ? 's' : '' }}
                          </span>
                        </div>
                      </AccordionHeader>
                      <AccordionContent>
                        <DataTable :value="links" class="w-full" removableSort resizableColumns columnResizeMode="fit">
                          <Column style="width: 5%" header="SN">
                            <template #body="slot">
                              <div class="relative inline-flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-green-500">
                                <Avatar v-if="slot.data.icon" :icon="`pi pi-${slot.data.icon.toLowerCase()}`" class="text-white text-lg" />
                                <Avatar v-else icon="pi pi-bookmark" class="text-white text-lg" />
                              </div>
                            </template>
                          </Column>
                          <Column style="width: 35%" field="uri_link" header="LINK" sortable>
                            <template #body="slot">
                              <code class="bg-gray-100 px-2 py-1 rounded text-sm">{{ slot.data.uri_link }}</code>
                            </template>
                          </Column>
                          <Column style="width: 50%" field="description" header="DESCRIPTION" sortable></Column>
                          <Column style="width: 10%" field="pri" header="PRIORITY" sortable class="text-center">
                            <template #body="slot">
                              <div class="flex justify-center">
                                <span class="bg-gray-200 text-gray-800 text-xs font-medium px-2 py-1 rounded">
                                  {{ slot.data.pri || 0 }}
                                </span>
                              </div>
                            </template>
                          </Column>
                        </DataTable>
                      </AccordionContent>
                    </AccordionPanel>
                  </Accordion>
                </div>
                <div v-else class="text-center py-8 text-gray-500">
                  <i class="pi pi-info-circle text-2xl"></i>
                  <p class="mt-2">No features configured for this module</p>
                </div>
              </TabPanel>

            </TabView>
          </div>


        </Fieldset>
      </Dialog>
    </template>
  </div>








</template>
