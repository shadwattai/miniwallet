<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { User as UserIcon } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const props = defineProps<{
    User?: any;
}>();

</script>

<template>

    <AppLayout :breadcrumbs="breadcrumbs" :User="props.User">

        <Head title="HOME" />

        <div class="flex h-full flex-1 gap-4 overflow-x-auto rounded-xl p-4">
            <div class="w-[70%] flex flex-col">
                ABC

                <div v-if="props.User">
                    User data: {{ props.User }}
                </div>

            </div>
            <div class="flex-1 flex flex-col">
                EFG
            </div>
        </div>
    </AppLayout>
</template>

<style>
.tooltip-sm .p-tooltip-text {
    font-size: 0.75rem !important;
}
</style>
