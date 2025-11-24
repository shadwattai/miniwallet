<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import Toast from 'primevue/toast'

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    User?: any;
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :User="props.User"> 
        <Toast />
        <slot />
    </AppLayout>
</template>
