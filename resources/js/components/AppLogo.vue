<script setup lang="ts">
import { Wallet as WalletIcon } from 'lucide-vue-next';
</script>

<template>
    <a href="/dashboard" class="block">
        <div class="flex items-center">
            <div class="">
                <div class="flex items-center gap-2 px-2 py-1">
                    <div
                        class="flex h-6 w-6 items-center justify-center rounded-md bg-gradient-to-r from-primary to-primary/80 text-primary-foreground"
                    >
                        <WalletIcon class="h-4 w-4" />
                    </div>
                    <!-- <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-foreground truncate">
                            WALLET
                        </p> 
                    </div> -->
                </div>
            </div>
        </div>
    </a>
</template>
