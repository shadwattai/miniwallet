<script setup lang="ts">

import { dashboard } from '@/routes';
import { Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Toolbar from 'primevue/toolbar';
import Button from 'primevue/button';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
import Divider from 'primevue/divider';
import Avatar from 'primevue/avatar';
import Badge from 'primevue/badge';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import AccountRegistrationDialog from '@/components/AccountRegistrationDialog.vue';
import AccountDetailsDrawer from '@/components/AccountDetailsDrawer.vue';
import { ref } from 'vue';

interface Account {
  key: string | number;
  acc_name: string;
  avatar?: string;
  max_bnses?: number;
  description?: string;
  bns_count?: number;
  status?: string;
  logo_path?: string;
}

interface Industry {
  key: string;
  name: string;
  // Add other properties if needed to match AccountRegistrationDialog.vue
}

interface Module {
  // Define module properties as needed
}

const filters = {
  global: {
    value: ref(null)
  }
};

const showRegistrationDialog = ref(false);
const showAccountDrawer = ref(false);
const selectedAccount = ref<Account | undefined>(undefined);

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Home',
    href: dashboard().url,
  },
  {
    title: 'Accounts',
    href: ''
  }
];

defineProps<{
  accounts: Account[];
  industries: Industry[];
  modules: Module[];
}>();

const openRegistrationDialog = () => {
  showRegistrationDialog.value = true;
};

const onAccountRegistered = () => {
  // Refresh the page or update the accounts list
  window.location.reload();
};

const openAccountDrawer = (account: Account) => {
  selectedAccount.value = account;
  showAccountDrawer.value = true;
};
</script>


<template>

  <Head title="ACCOUNTS" />
  <Toast />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div style="padding-top: -10px;">
      <Toolbar class="mb-1" :style="{ justifyContent: 'space-between', borderRadius: '0px' }">
        <template #start>
          <h1 class="m-0">
            <span class="font-bold p-2 bg-clip-text">
              ACCOUNTS
            </span>
          </h1>
        </template>
      </Toolbar>

      <Toolbar class="mb-4" :style="{ justifyContent: 'space-between', borderRadius: '0px', marginTop: '-5px' }">
        <template #start>
          <IconField>
            <InputText placeholder="Search ..." class="w-120" />
          </IconField>

        </template>
        <template #end>
          <Button label="REGISTER" icon="pi pi-plus-circle" severity="info" @click="openRegistrationDialog" />
        </template>
      </Toolbar>

      <div class="card" style="margin-top: -16px;">
        <Accordion value="0">
          <AccordionPanel v-for="value in accounts" :key="value.key" :value="value.key" :toggleable="false">
            <AccordionHeader class="cursor-pointer" @click="openAccountDrawer(value)">
              <span class="flex items-center gap-2 w-full">
                <div class="relative inline-flex items-center justify-center w-12 h-12"
                  style="border-radius: 6px; overflow: hidden;">
                  <Avatar v-if="value.logo_path && value.logo_path !== ''" :image="value.logo_path" size="large"
                    shape="circle" class="bg-blue-500 text-white cursor-pointer"
                    @error="event => value.logo_path = ''" />
                  <Avatar v-else :label="value.acc_name?.charAt(0)?.toUpperCase()" size="large" shape="circle"
                    class="bg-blue-500 text-white cursor-pointer" />
                </div>
                <span class="capitalize">{{ value.acc_name }}</span>
                <Button :label="value.status" class="ml-auto mr-2 uppercase"
                  :icon="value.status === 'active' ? 'pi pi-check-circle' : undefined"
                  :severity="value.status === 'inactive' ? 'danger' : 'success'" outlined size="small" />
              </span>
            </AccordionHeader>
          </AccordionPanel>
        </Accordion>
      </div>

      <!-- Registration Dialog -->
      <AccountRegistrationDialog v-model:visible="showRegistrationDialog" :industries="industries" :modules="modules"
        @registered="onAccountRegistered" />

      <!-- Account Details Drawer -->
      <AccountDetailsDrawer v-model:visible="showAccountDrawer" :account="selectedAccount ?? undefined" />


    </div>
  </AppLayout>
</template>