<script setup lang="ts">
import { withDefaults, defineProps, ref } from 'vue';
import { Bell, Settings, User, LogOutIcon } from 'lucide-vue-next';
import { MessagesSquare, LayoutGrid, HeartHandshake, Users, HeartPulse, FolderGit2 } from 'lucide-vue-next';

import OverlayBadge from 'primevue/overlaybadge';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

const props = withDefaults(defineProps<{
    userName?: string;
}>(), {
    userName: 'Guest User'
});

defineEmits<{
    (e: 'update:selected', value: string): void;
}>();


const selected = ref('dashboard');

const pathSegments = window.location.pathname.split('/').filter(Boolean);
const bnsKey = pathSegments.length > 0 ? pathSegments[0] : 'default-key';
const app_name = pathSegments[1];




function useActiveNav() {
    const pathSegments = window.location.pathname.split('/').filter(Boolean);
    const currentPage = pathSegments.length > 2 ? pathSegments[2] : 'dashboard';
    function isActive(key: string) {
        return currentPage === key;
    }


    function getLinkClass(key: string) {
        return [
            'flex flex-col items-center gap-1 px-3 py-2 rounded hover:bg-gray-100 transition text-xs',
            isActive(key) ? 'text-blue-600 font-bold bg-blue-50 border border-blue-200' : 'text-gray-700'
        ];
    }

    return { isActive, getLinkClass };
}

const { isActive, getLinkClass } = useActiveNav();

const logout = () => {
    router.post('/logout');
};
</script>


<template>
    <div class="ml-auto flex gap-2">
        <Link :href="`/${bnsKey}/${app_name}/notifications`" :class="getLinkClass('notifications')" hidden>
        <OverlayBadge value="4" severity="danger" class="inline-flex">
            <span class="text-lg">
                <component :is="Bell" />
            </span>
        </OverlayBadge>
        <span>Notifications</span>
        </Link>

        <Link :href="`/${bnsKey}/${app_name}/settings/home`" :class="getLinkClass('settings')">
        <span class="text-lg">
            <component :is="Settings" />
        </span>
        <span>Settings</span>
        </Link>

        <Link :href="`/settings/profile`" :class="getLinkClass('beneficiaries')">
        <span class="text-lg">
            <component :is="User" />
        </span>
        <span>{{ props.userName }}</span>
        </Link>

        <Link @click.prevent="logout" :class="getLinkClass('health')">
        <span class="text-lg">
            <component :is="LogOutIcon" />
        </span>
        <span>Log Out</span>
        </Link>
    </div>


    <!-- <a href="#" @click.prevent="selected = 'notifications'" :class="['flex flex-col items-center text-xs hover:text-blue-600', selected === 'notifications' ? 'text-blue-600 font-bold' : 'text-gray-700']">
            <OverlayBadge value="4" severity="danger" class="inline-flex">
                <span class="text-sm">
                    <component :is="Bell" />
                </span>
            </OverlayBadge>
            <span>Notifications</span>
        </a> -->

</template>