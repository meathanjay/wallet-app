<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { transactions as transactionsRoute } from '@/routes';
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { useTransactions, type Transaction } from '@/composables/useTransactions';
import { useEcho } from '@/composables/useEcho';
import type { BreadcrumbItem } from '@/types';
import { ArrowLeft, ArrowRight, History } from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Transactions',
        href: transactionsRoute().url,
    },
];

const {
    transactions,
    balance,
    loading,
    error,
    currentPage,
    lastPage,
    total,
    fetchTransactions,
    addTransaction,
    updateBalance,
} = useTransactions();

const perPage = 50; // Show 50 transactions per page

const formatCurrency = (value: string | number): string => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(num);
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const isSender = (transaction: Transaction): boolean => {
    return transaction.sender_id === user.value?.id;
};

const loadPage = (pageNum: number): void => {
    if (pageNum >= 1 && pageNum <= lastPage.value) {
        fetchTransactions(pageNum, perPage);
    }
};

// Set up real-time event listener
let echoChannel: any = null;

onMounted(async () => {
    // Fetch initial transactions with 50 per page
    await fetchTransactions(1, perPage);

    // Set up Pusher listener if Echo is available
    try {
        const echo = useEcho();
        if (echo && user.value?.id) {
            echoChannel = echo.private(`user.${user.value.id}`);

            echoChannel.listen('.transaction.created', (data: any) => {
                // Update balance
                if (data.sender_balance !== undefined && isSender(data.transaction)) {
                    updateBalance(data.sender_balance.toString());
                } else if (
                    data.receiver_balance !== undefined &&
                    !isSender(data.transaction)
                ) {
                    updateBalance(data.receiver_balance.toString());
                }

                // Add transaction to list if it's for the current user
                if (
                    data.transaction.sender_id === user.value?.id ||
                    data.transaction.receiver_id === user.value?.id
                ) {
                    // Only add if we're on the first page, otherwise refresh
                    if (currentPage.value === 1) {
                        addTransaction(data.transaction);
                        // Keep only 50 transactions on first page
                        if (transactions.value.length > perPage) {
                            transactions.value = transactions.value.slice(0, perPage);
                        }
                    } else {
                        // If on another page, refresh first page to show new transaction
                        fetchTransactions(1, perPage);
                    }
                }
            });
        }
    } catch (error) {
        console.warn('Pusher/Echo not configured:', error);
    }
});

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('.transaction.created');
    }
});
</script>

<template>
    <Head title="Transactions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <!-- Header -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <History class="h-6 w-6 text-sidebar-foreground" />
                        <div>
                            <h1 class="text-2xl font-bold text-sidebar-foreground">
                                Transaction History
                            </h1>
                            <p class="mt-1 text-sm text-sidebar-foreground/70">
                                View all your transactions
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-sidebar-foreground/70">Current Balance</p>
                        <p class="text-2xl font-bold text-sidebar-foreground">
                            {{ formatCurrency(balance) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div
                v-if="!loading && total > 0"
                class="rounded-xl border border-sidebar-border/70 bg-sidebar p-4 dark:border-sidebar-border"
            >
                <div class="flex items-center justify-between text-sm">
                    <span class="text-sidebar-foreground/70">
                        Showing {{ (currentPage - 1) * perPage + 1 }} to
                        {{ Math.min(currentPage * perPage, total) }} of
                        {{ total }} transactions
                    </span>
                    <span class="text-sidebar-foreground/70">
                        Page {{ currentPage }} of {{ lastPage }}
                    </span>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading && transactions.length === 0" class="py-12 text-center">
                <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-current border-r-transparent"></div>
                <p class="mt-4 text-sidebar-foreground/70">Loading transactions...</p>
            </div>

            <!-- Error State -->
            <div
                v-else-if="error"
                class="rounded-xl border border-destructive/50 bg-destructive/10 p-6"
            >
                <p class="text-destructive">{{ error }}</p>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="transactions.length === 0"
                class="py-12 text-center"
            >
                <History class="mx-auto h-12 w-12 text-sidebar-foreground/30" />
                <p class="mt-4 text-sidebar-foreground/70">No transactions yet</p>
            </div>

            <!-- Transactions List -->
            <div v-else class="space-y-3">
                <div
                    v-for="transaction in transactions"
                    :key="transaction.id"
                    class="rounded-lg border border-sidebar-border/50 bg-background p-4 transition-all hover:border-sidebar-border hover:shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="
                                        isSender(transaction)
                                            ? 'bg-destructive/20 text-destructive'
                                            : 'bg-green-500/20 text-green-600 dark:text-green-400'
                                    "
                                >
                                    {{ isSender(transaction) ? 'Sent' : 'Received' }}
                                </span>
                                <span class="text-sm font-medium text-sidebar-foreground">
                                    {{
                                        isSender(transaction)
                                            ? `To: ${transaction.receiver.name}`
                                            : `From: ${transaction.sender.name}`
                                    }}
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-sidebar-foreground/60">
                                <span>{{ formatDate(transaction.created_at) }}</span>
                                <span v-if="isSender(transaction)" class="flex items-center gap-1">
                                    <span>Commission:</span>
                                    <span class="font-medium">{{ formatCurrency(transaction.commission_fee) }}</span>
                                </span>
                                <span class="flex items-center gap-1">
                                    <span>Transaction ID:</span>
                                    <span class="font-mono font-medium">#{{ transaction.id }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p
                                class="text-xl font-semibold"
                                :class="
                                    isSender(transaction)
                                        ? 'text-destructive'
                                        : 'text-green-600 dark:text-green-400'
                                "
                            >
                                {{
                                    isSender(transaction) ? '-' : '+'
                                }}{{ formatCurrency(transaction.amount) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="lastPage > 1"
                class="flex items-center justify-center gap-2 rounded-xl border border-sidebar-border/70 bg-sidebar p-4 dark:border-sidebar-border"
            >
                <button
                    :disabled="currentPage === 1 || loading"
                    @click="loadPage(currentPage - 1)"
                    class="flex items-center gap-2 rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm font-medium text-sidebar-foreground transition-colors disabled:opacity-50 disabled:cursor-not-allowed hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Previous
                </button>

                <div class="flex items-center gap-1">
                    <button
                        v-for="pageNum in Math.min(5, lastPage)"
                        :key="pageNum"
                        @click="loadPage(pageNum)"
                        class="h-10 w-10 rounded-lg border border-sidebar-border bg-background text-sm font-medium text-sidebar-foreground transition-colors hover:bg-accent"
                        :class="
                            pageNum === currentPage
                                ? 'border-primary bg-primary text-primary-foreground'
                                : ''
                        "
                    >
                        {{ pageNum }}
                    </button>
                    <span
                        v-if="lastPage > 5"
                        class="px-2 text-sm text-sidebar-foreground/70"
                    >
                        ...
                    </span>
                    <button
                        v-if="lastPage > 5 && currentPage > 5"
                        @click="loadPage(currentPage)"
                        class="h-10 w-10 rounded-lg border border-primary bg-primary text-sm font-medium text-primary-foreground"
                    >
                        {{ currentPage }}
                    </button>
                </div>

                <button
                    :disabled="currentPage === lastPage || loading"
                    @click="loadPage(currentPage + 1)"
                    class="flex items-center gap-2 rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm font-medium text-sidebar-foreground transition-colors disabled:opacity-50 disabled:cursor-not-allowed hover:bg-accent"
                >
                    Next
                    <ArrowRight class="h-4 w-4" />
                </button>
            </div>
        </div>
    </AppLayout>
</template>

