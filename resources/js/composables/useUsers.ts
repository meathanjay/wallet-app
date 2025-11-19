import { ref, type Ref } from 'vue';

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface UsersResponse {
    users: User[];
}

const fetchJson = async <T>(
    url: string,
    options?: RequestInit,
): Promise<T> => {
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') || '';

    const headers: HeadersInit = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...options?.headers,
    };

    // Add CSRF token for POST/PUT/DELETE requests
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

export function useUsers() {
    const users: Ref<User[]> = ref([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const fetchUsers = async (): Promise<void> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetchJson<UsersResponse>('/api/users');
            users.value = response.users;
        } catch (err) {
            error.value =
                err instanceof Error ? err.message : 'Failed to fetch users';
            console.error('Error fetching users:', err);
        } finally {
            loading.value = false;
        }
    };

    return {
        users,
        loading,
        error,
        fetchUsers,
    };
}

