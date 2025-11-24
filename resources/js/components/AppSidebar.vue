<script setup lang="ts"> 
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';

import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Wallet, ListCheck, Settings, Home, FolderKanban, UserPen, FolderTree, Users as UsersIcon } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

import { dashboard } from '@/routes';
import Divider from 'primevue/divider';


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
        title: 'Wallets',
        href: "/miniwallet/wallets",
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
            <!-- User Info Section -->

            <Divider  />
            <div v-if="props.User" class="px-3 py-2 mb-4 bg-muted/50 rounded-lg mx-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-primary text-primary-foreground rounded-full flex items-center justify-center">
                        <UsersIcon class="w-4 h-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">
                            {{ props.User.name || props.User.username || 'User' }}
                        </p>
                        <p class="text-xs text-muted-foreground truncate">
                            {{ props.User.email || props.User.role || '' }}
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Navigation based on user role -->
            <NavMain v-if="props.User?.role === 'user'" :items="userNavItems" />
            <NavMain v-if="props.User?.role === 'admin'" :items="adminCombinedNavItems" />
            
             
        </SidebarContent>

        <SidebarFooter> 
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
