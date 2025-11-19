import { ref, type Ref } from 'vue';

export interface Transaction {
    id: number;
    sender_id: number;
    receiver_id: number;
    amount: string;
    commission_fee: string;
    status: string;
    created_at: string;
    sender: {
        id: number;
        name: string;
        email: string;
    };
    receiver: {
        id: number;
        name: string;
        email: string;
    };
}

export interface TransactionResponse {
    balance: string;
    transactions: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface TransferRequest {
    receiver_id: number;
    amount: number;
}

export interface TransferResponse {
    message: string;
    transaction: Transaction;
    balance: string;
}

const getCsrfToken = (): string => {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    return token || '';
};

const fetchJson = async <T>(
    url: string,
    options?: RequestInit,
): Promise<T> => {
    const csrfToken = getCsrfToken();
    const headers: HeadersInit = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...options?.headers,
    };

    if (options?.method && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method.toUpperCase())) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    const response = await fetch(url, {
        ...options,
        headers,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const error = await response.json().catch(() => ({
            message: `Failed to fetch: ${response.status}`,
        }));
        throw new Error(error.message || `HTTP error! status: ${response.status}`);
    }

    return response.json();
};

export function useTransactions() {
    const transactions: Ref<Transaction[]> = ref([]);
    const balance: Ref<string> = ref('0.00');
    const loading = ref(false);
    const error = ref<string | null>(null);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const total = ref(0);

    const fetchTransactions = async (page = 1, perPage = 15): Promise<void> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetchJson<TransactionResponse>(
                `/api/transactions?page=${page}&per_page=${perPage}`,
            );

            transactions.value = response.transactions.data;
            balance.value = response.balance;
            currentPage.value = response.transactions.current_page;
            lastPage.value = response.transactions.last_page;
            total.value = response.transactions.total;
        } catch (err) {
            error.value =
                err instanceof Error ? err.message : 'Failed to fetch transactions';
            console.error('Error fetching transactions:', err);
        } finally {
            loading.value = false;
        }
    };

    const transfer = async (
        receiverId: number,
        amount: number,
    ): Promise<TransferResponse> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetchJson<TransferResponse>(
                '/api/transactions',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        receiver_id: receiverId,
                        amount,
                    }),
                },
            );

            await fetchTransactions(currentPage.value);

            return response;
        } catch (err) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to transfer money';
            error.value = errorMessage;
            throw new Error(errorMessage);
        } finally {
            loading.value = false;
        }
    };

    const addTransaction = (transaction: Transaction): void => {
        if (!transactions.value.some((t) => t.id === transaction.id)) {
            transactions.value.unshift(transaction);
        }
    };

    const updateBalance = (newBalance: string): void => {
        balance.value = newBalance;
    };

    return {
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
    };
}

