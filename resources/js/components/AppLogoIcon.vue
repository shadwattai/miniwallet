<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import Avatar from 'primevue/avatar';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

 
const props = defineProps({ 
    app_icon: {
        type: String,
        required: false,
    },
    const: {
        type: Object,
        required: false,
    },
});
const appConst = props.const ?? {};

const app_name = 'personal'; // Add your app name here
</script>

<template>
    {{ app_icon }}
    <div class="flex items-center">
        <Avatar :src="appConst.app_icon || 'W'" alt="App Logo" class="size-5" />
    </div>
</template>
