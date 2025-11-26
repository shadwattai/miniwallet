<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';

import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Wallet, ListCheck, Settings, Home, FolderKanban, UserPen, FolderTree, Users as UsersIcon, Wallet2 } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

import { dashboard } from '@/routes';


const props = defineProps<{
    User?: any;
}>();



const userNavItems: NavItem[] = [
    {
        title: 'Home',
        href: dashboard(),
        icon: Home,
    },
    {
        title: 'My Wallets',
        href: "/miniwallet/mywallets",
        icon: Wallet,
    },
    {
        title: 'Transactions',
        href: "/miniwallet/transactions",
        icon: ListCheck,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Home',
        href: "/miniwallet/dashboard",
        icon: Home,
    },
    {
        title: 'Wallets',
        href: "/miniwallet/wallets",
        icon: Wallet2,
    },
    {
        title: 'My Wallets',
        href: "/miniwallet/mywallets",
        icon: Wallet,
    },
    {
        title: 'Transactions',
        href: "/miniwallet/transactions",
        icon: ListCheck,
    },
    {
        title: 'System settings',
        href: "/miniwallet/settings",
        icon: Settings,
    },
];

// Combined navigation for admin users
const adminCombinedNavItems: NavItem[] = [
    ...userNavItems,
    ...adminNavItems
];


</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                        <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>

            <NavMain v-if="props.User?.role === 'user'" :items="userNavItems" />
            <NavMain v-if="props.User?.role === 'admin'" :items="adminNavItems" />
            <!-- <NavMain v-if="props.User?.role === 'admin'" :items="adminCombinedNavItems" /> -->

        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
