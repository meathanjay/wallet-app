<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { wallet } from '@/routes';
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, computed, watch } from 'vue';
import { useTransactions, type Transaction } from '@/composables/useTransactions';
import { useEcho } from '@/composables/useEcho';
import type { BreadcrumbItem } from '@/types';
import { useDebounceFn } from '@vueuse/core';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Wallet',
        href: wallet().url,
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
    transfer,
    addTransaction,
    updateBalance,
} = useTransactions();

const urlParams = new URLSearchParams(window.location.search);
const initialReceiverId = urlParams.get('receiver_id');
const receiverIdInput = ref<string>(initialReceiverId || '');
const receiverId = ref<number | null>(initialReceiverId ? parseInt(initialReceiverId) : null);
const amount = ref<string>('');
const transferLoading = ref(false);
const transferError = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const userIdError = ref<string | null>(null);
const userIdValidating = ref(false);
const userIdValid = ref(false);
const validatedUser = ref<{ id: number; name: string; email: string } | null>(null);

const amountError = ref<string | null>(null);
const amountValidating = ref(false);
const amountValid = ref(false);
const commissionInfo = ref<{ fee: number; total: number } | null>(null);

const getCsrfToken = (): string => {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    return token || '';
};

const validateUserId = async (userId: string | number): Promise<void> => {
    const userIdStr = String(userId || '').trim();
    
    if (!userIdStr || userIdStr === '') {
        userIdError.value = null;
        userIdValid.value = false;
        validatedUser.value = null;
        receiverId.value = null;
        return;
    }

    const userIdNum = parseInt(userIdStr);
    if (isNaN(userIdNum) || userIdNum <= 0) {
        userIdError.value = 'Please enter a valid user ID';
        userIdValid.value = false;
        validatedUser.value = null;
        receiverId.value = null;
        return;
    }

    userIdValidating.value = true;
    userIdError.value = null;
    userIdValid.value = false;

    try {
        const response = await fetch('/api/users/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ user_id: userIdNum }),
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({
                message: `Validation failed: ${response.status}`,
            }));
            userIdValid.value = false;
            // Handle Laravel validation errors
            if (errorData.errors && errorData.errors.user_id) {
                userIdError.value = Array.isArray(errorData.errors.user_id) 
                    ? errorData.errors.user_id[0] 
                    : errorData.errors.user_id;
            } else {
                userIdError.value = errorData.message || 'Failed to validate user ID';
            }
            validatedUser.value = null;
            receiverId.value = null;
            userIdValidating.value = false;
            return;
        }

        const data = await response.json();

        if (data.valid) {
            userIdValid.value = true;
            userIdError.value = null;
            validatedUser.value = data.user;
            receiverId.value = userIdNum;
        } else {
            userIdValid.value = false;
            userIdError.value = data.message || 'Invalid user ID';
            validatedUser.value = null;
            receiverId.value = null;
        }
    } catch (err) {
        userIdValid.value = false;
        userIdError.value = err instanceof Error ? err.message : 'Failed to validate user ID';
        validatedUser.value = null;
        receiverId.value = null;
    } finally {
        userIdValidating.value = false;
    }
};

// Validate amount
const validateAmountValue = async (amountValue: string | number): Promise<void> => {
    const amountStr = String(amountValue || '').trim();
    
    if (!amountStr || amountStr === '') {
        amountError.value = null;
        amountValid.value = false;
        commissionInfo.value = null;
        return;
    }

    const amountNum = parseFloat(amountStr);
    if (isNaN(amountNum) || amountNum <= 0) {
        amountError.value = 'Please enter a valid amount (minimum 0.01)';
        amountValid.value = false;
        commissionInfo.value = null;
        return;
    }

    amountValidating.value = true;
    amountError.value = null;
    amountValid.value = false;

    try {
        const response = await fetch('/api/users/validate-amount', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ amount: amountNum }),
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({
                message: `Validation failed: ${response.status}`,
            }));
            amountValid.value = false;
            // Handle Laravel validation errors
            if (errorData.errors && errorData.errors.amount) {
                amountError.value = Array.isArray(errorData.errors.amount) 
                    ? errorData.errors.amount[0] 
                    : errorData.errors.amount;
            } else {
                amountError.value = errorData.message || 'Failed to validate amount';
            }
            commissionInfo.value = null;
            amountValidating.value = false;
            return;
        }

        const data = await response.json();

        if (data.valid) {
            amountValid.value = true;
            amountError.value = null;
            commissionInfo.value = {
                fee: data.commission_fee,
                total: data.total_required,
            };
        } else {
            amountValid.value = false;
            amountError.value = data.message || 'Invalid amount';
            commissionInfo.value = null;
        }
    } catch (err) {
        amountValid.value = false;
        amountError.value = err instanceof Error ? err.message : 'Failed to validate amount';
        commissionInfo.value = null;
    } finally {
        amountValidating.value = false;
    }
};

const debouncedValidateUserId = useDebounceFn(validateUserId, 500);
const debouncedValidateAmount = useDebounceFn(validateAmountValue, 500);

watch(receiverIdInput, (newValue) => {
    const value = String(newValue || '').trim();
    if (value) {
        debouncedValidateUserId(value);
    } else {
        // Clear validation when input is cleared
        userIdValid.value = false;
        userIdError.value = null;
        validatedUser.value = null;
        receiverId.value = null;
    }
});

watch(amount, (newValue) => {
    const value = String(newValue || '').trim();
    if (value && value !== '') {
        debouncedValidateAmount(value);
    } else {
        // Clear validation when input is cleared
        amountValid.value = false;
        amountError.value = null;
        commissionInfo.value = null;
    }
});

watch(balance, () => {
    if (amount.value) {
        debouncedValidateAmount(amount.value);
    }
});

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

const handleTransfer = async (): Promise<void> => {
    if (!receiverId.value || !amount.value) {
        transferError.value = 'Please fill in all fields';
        return;
    }

    const amountNum = parseFloat(amount.value);
    if (isNaN(amountNum) || amountNum <= 0) {
        transferError.value = 'Please enter a valid amount';
        return;
    }

    transferLoading.value = true;
    transferError.value = null;
    successMessage.value = null;

    try {
        await transfer(receiverId.value, amountNum);
        successMessage.value = 'Transfer completed successfully!';
        
        // Clear all form fields and validation states
        receiverIdInput.value = '';
        receiverId.value = null;
        amount.value = '';
        
        // Reset all validation states
        userIdValid.value = false;
        userIdValidating.value = false;
        userIdError.value = null;
        validatedUser.value = null;
        
        amountValid.value = false;
        amountValidating.value = false;
        amountError.value = null;
        commissionInfo.value = null;

        // Clear success message after 3 seconds
        setTimeout(() => {
            successMessage.value = null;
        }, 3000);
    } catch (err) {
        transferError.value =
            err instanceof Error ? err.message : 'Failed to transfer money';
    } finally {
        transferLoading.value = false;
    }
};

const loadPage = (page: number): void => {
    fetchTransactions(page);
};

let echoChannel: any = null;

onMounted(async () => {
    await fetchTransactions(1);

    if (receiverIdInput.value) {
        await validateUserId(receiverIdInput.value);
    }

    try {
        const echo = useEcho();
        if (echo && user.value?.id) {
            echoChannel = echo.private(`user.${user.value.id}`);

        echoChannel.listen('.transaction.created', (data: any) => {
            console.log('Transaction created:', data);
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
                addTransaction(data.transaction);
            }

            // Refresh transactions to get updated list
            fetchTransactions(currentPage.value);
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
    <Head title="Wallet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <!-- Balance Card -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
            >
                <h2 class="mb-2 text-sm font-medium text-sidebar-foreground/70">
                    Current Balance
                </h2>
                <p class="text-3xl font-bold text-sidebar-foreground">
                    {{ formatCurrency(balance) }}
                </p>
            </div>

            <!-- Transfer Form -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
            >
                <h2 class="mb-4 text-lg font-semibold text-sidebar-foreground">
                    Send Money
                </h2>

                <form @submit.prevent="handleTransfer" class="space-y-4">
                    <div>
                        <label
                            for="receiver_id"
                            class="mb-2 block text-sm font-medium text-sidebar-foreground"
                        >
                            Recipient User ID
                        </label>
                        <div class="relative">
                            <input
                                id="receiver_id"
                                v-model="receiverIdInput"
                                type="number"
                                min="1"
                                required
                                @input="() => debouncedValidateUserId(String(receiverIdInput || ''))"
                                class="w-full rounded-lg border bg-background px-3 py-2 pr-10 text-foreground transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="
                                    userIdError
                                        ? 'border-destructive focus:border-destructive'
                                        : userIdValid
                                          ? 'border-green-500 focus:border-green-500'
                                          : 'border-sidebar-border focus:border-primary'
                                "
                                placeholder="Enter recipient user ID"
                                :disabled="transferLoading || userIdValidating"
                            />
                            <div
                                v-if="userIdValidating"
                                class="absolute right-3 top-1/2 -translate-y-1/2"
                            >
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-sidebar-border border-t-primary"></div>
                            </div>
                            <div
                                v-else-if="userIdValid"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-green-500"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>
                        </div>
                        <div v-if="validatedUser" class="mt-2 rounded-lg bg-green-500/10 p-2">
                            <p class="text-sm font-medium text-green-600 dark:text-green-400">
                                {{ validatedUser.name }} ({{ validatedUser.email }})
                            </p>
                        </div>
                        <p
                            v-if="userIdError"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ userIdError }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="amount"
                            class="mb-2 block text-sm font-medium text-sidebar-foreground"
                        >
                            Amount
                        </label>
                        <div class="relative">
                            <input
                                id="amount"
                                v-model="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                @input="() => debouncedValidateAmount(String(amount || ''))"
                                class="w-full rounded-lg border bg-background px-3 py-2 pr-10 text-foreground transition-colors focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="
                                    amountError
                                        ? 'border-destructive focus:border-destructive'
                                        : amountValid && amount != ''
                                          ? 'border-green-500 focus:border-green-500'
                                          : 'border-sidebar-border focus:border-primary'
                                "
                                placeholder="0.00"
                                :disabled="transferLoading || amountValidating"
                            />
                            <div
                                v-if="amountValidating"
                                class="absolute right-3 top-1/2 -translate-y-1/2"
                            >
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-sidebar-border border-t-primary"></div>
                            </div>
                            <div
                                v-else-if="amountValid && amount != ''"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-green-500"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>
                        </div>
                        <div v-if="commissionInfo && amountValid && amount != ''" class="mt-2 space-y-1">
                            <p class="text-xs text-sidebar-foreground/70">
                                Commission fee (1.5%): {{ formatCurrency(commissionInfo.fee) }}
                            </p>
                            <p class="text-sm font-medium text-sidebar-foreground">
                                Total to debit: {{ formatCurrency(commissionInfo.total) }}
                            </p>
                        </div>
                        <p
                            v-if="amountError"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ amountError }}
                        </p>
                        <p
                            v-else-if="!amountValidating && !amount"
                            class="mt-1 text-xs text-sidebar-foreground/70"
                        >
                            Commission fee: 1.5% (charged to sender)
                        </p>
                    </div>

                    <div v-if="transferError" class="rounded-lg bg-destructive/10 p-3">
                        <p class="text-sm text-destructive">{{ transferError }}</p>
                    </div>

                    <div v-if="successMessage" class="rounded-lg bg-green-500/10 p-3">
                        <p class="text-sm text-green-600 dark:text-green-400">
                            {{ successMessage }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="transferLoading || loading || !userIdValid || !amountValid || userIdValidating || amountValidating"
                        class="w-full rounded-lg bg-primary px-4 py-2 font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="transferLoading">Processing...</span>
                        <span v-else>Send Money</span>
                    </button>
                </form>
            </div>

            <!-- Transaction History -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-sidebar-foreground">
                        Transaction History
                    </h2>
                    <span
                        v-if="total > 0"
                        class="text-sm text-sidebar-foreground/70"
                    >
                        Total: {{ total }}
                    </span>
                </div>

                <div v-if="loading && transactions.length === 0" class="py-8 text-center">
                    <p class="text-sidebar-foreground/70">Loading transactions...</p>
                </div>

                <div v-else-if="error" class="rounded-lg bg-destructive/10 p-3">
                    <p class="text-sm text-destructive">{{ error }}</p>
                </div>

                <div v-else-if="transactions.length === 0" class="py-8 text-center">
                    <p class="text-sidebar-foreground/70">No transactions yet</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="transaction in transactions"
                        :key="transaction.id"
                        class="rounded-lg border border-sidebar-border/50 bg-background p-4"
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
                                    <span class="text-sm text-sidebar-foreground/70">
                                        {{
                                            isSender(transaction)
                                                ? `To: ${transaction.receiver.name}`
                                                : `From: ${transaction.sender.name}`
                                        }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-sidebar-foreground/60">
                                    {{ formatDate(transaction.created_at) }}
                                </p>
                                <p
                                    v-if="isSender(transaction)"
                                    class="mt-1 text-xs text-sidebar-foreground/60"
                                >
                                    Commission: {{ formatCurrency(transaction.commission_fee) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p
                                    class="text-lg font-semibold"
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
                    class="mt-6 flex items-center justify-center gap-2"
                >
                    <button
                        :disabled="currentPage === 1"
                        @click="loadPage(currentPage - 1)"
                        class="rounded-lg border border-sidebar-border bg-background px-3 py-1 text-sm text-sidebar-foreground disabled:opacity-50"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-sidebar-foreground/70">
                        Page {{ currentPage }} of {{ lastPage }}
                    </span>
                    <button
                        :disabled="currentPage === lastPage"
                        @click="loadPage(currentPage + 1)"
                        class="rounded-lg border border-sidebar-border bg-background px-3 py-1 text-sm text-sidebar-foreground disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

