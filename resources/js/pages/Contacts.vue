<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { contacts, wallet } from '@/routes';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { useUsers } from '@/composables/useUsers';
import type { BreadcrumbItem } from '@/types';
import { Copy, Mail, User, Users } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Contacts',
        href: contacts().url,
    },
];

const { users, loading, error, fetchUsers } = useUsers();

const copyToClipboard = async (text: string): Promise<void> => {
    try {
        await navigator.clipboard.writeText(text);
        // You could add a toast notification here
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

onMounted(() => {
    fetchUsers();
});
</script>

<template>
    <Head title="Contacts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <!-- Header -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-3">
                    <Users class="h-6 w-6 text-sidebar-foreground" />
                    <h1 class="text-2xl font-bold text-sidebar-foreground">
                        Contacts
                    </h1>
                </div>
                <p class="mt-2 text-sm text-sidebar-foreground/70">
                    Select a user to send money. Click on the ID to copy it.
                </p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="py-12 text-center">
                <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-current border-r-transparent"></div>
                <p class="mt-4 text-sidebar-foreground/70">Loading contacts...</p>
            </div>

            <!-- Error State -->
            <div
                v-else-if="error"
                class="rounded-xl border border-destructive/50 bg-destructive/10 p-6"
            >
                <p class="text-destructive">{{ error }}</p>
            </div>

            <!-- Users List -->
            <div v-else-if="users.length === 0" class="py-12 text-center">
                <p class="text-sidebar-foreground/70">No contacts available</p>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="group relative rounded-xl border border-sidebar-border/70 bg-sidebar p-6 transition-all hover:border-primary hover:shadow-lg dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <User class="h-5 w-5 text-sidebar-foreground/70" />
                                <h3 class="text-lg font-semibold text-sidebar-foreground">
                                    {{ user.name }}
                                </h3>
                            </div>
                            <div class="mt-2 flex items-center gap-2 text-sm text-sidebar-foreground/70">
                                <Mail class="h-4 w-4" />
                                <span>{{ user.email }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- User ID Section -->
                    <div class="mt-4 rounded-lg bg-background p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-sidebar-foreground/70">
                                    User ID
                                </p>
                                <p
                                    class="mt-1 font-mono text-lg font-bold text-sidebar-foreground"
                                >
                                    {{ user.id }}
                                </p>
                            </div>
                            <button
                                @click="copyToClipboard(user.id.toString())"
                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-sidebar-border bg-background transition-colors hover:bg-accent"
                                title="Copy ID"
                            >
                                <Copy class="h-4 w-4 text-sidebar-foreground/70" />
                            </button>
                        </div>
                    </div>

                    <!-- Quick Action -->
                    <div class="mt-4">
                        <a
                            :href="wallet().url + '?receiver_id=' + user.id"
                            class="block w-full rounded-lg bg-primary px-4 py-2 text-center text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Send Money
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

