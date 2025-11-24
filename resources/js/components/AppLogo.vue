<script setup lang="ts">
import { withDefaults, defineProps } from 'vue';
import Avatar from 'primevue/avatar';

const props = defineProps({
    const: Object,
    app_name: String,
});
const appConst = props.const ?? {};

</script>

<template>
    <a href="/dashboard">
        <div style="width: 10.05rem;">
            <div class="flex items-center">
                <!-- <span class="text-sm font-semibold uppercase truncate leading-tight"
                    style="background: linear-gradient(90deg, #3b82f6 0%, #000000 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    mini &nbsp;
                </span> -->
                <div class="relative inline-flex items-center justify-center w-6 h-6 rounded-full bg-white"
                    style="border-radius: 6px;">
                    <Avatar :image="appConst.logo_path || '/logos/apps/workforce.png'" size="small"
                        class="h-6 w-6 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
                </div>

                <span class="ml-2 text-sm font-semibold uppercase truncate leading-tight"
                    style="background: linear-gradient(90deg, #3b82f6 0%, #000000 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                    {{ app_name || 'wallet' }}
                </span>

            </div>
        </div>
    </a>
</template>
