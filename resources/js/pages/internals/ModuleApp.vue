<script setup lang="ts">
import SidebarNavbarLayout from '@/pages/company/app/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{ bns_key: string; app_name: string }>();
</script>

<template>
  <SidebarNavbarLayout>
    <Head :title="`Module: ${props.app_name}`" />
    <div class="flex flex-col items-center justify-center h-[90vh] mx-auto max-w-3xl w-full">
      <div class="bg-white rounded-lg shadow p-8 w-full max-w-md border border-gray-200">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-red-600 mb-4">Page Not Found!</h1>
            <p class="text-gray-700 mb-2">
                Sorry, but the page you were looking for could not be found <span class="font-semibold text-red-500">[Error 404]</span>.
            </p>
            <p class="text-sm text-gray-500 mb-6">
                <span class="break-all">
                    <!-- /business/{{ props.bns_key }}/module/{{ props.app_name }} -->
                </span>
            </p>
            <p class="mb-6 text-gray-600">
                Return to the homepage if you cannot find what you are looking for.
            </p>
            <a
                href="/dashboard"
                class="inline-block px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition"
            >
                Go to Homepage
            </a>
        </div>
      </div>
    </div>
  </SidebarNavbarLayout>
</template>
