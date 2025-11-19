import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: typeof Echo;
    }
}

let echoInstance: Echo | null = null;

export function initializeEcho(): Echo {
    if (echoInstance) {
        return echoInstance;
    }

    // Get Pusher credentials from environment or window
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || '';
    const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';
    const pusherHost = import.meta.env.VITE_PUSHER_HOST;
    const pusherPort = import.meta.env.VITE_PUSHER_PORT || '443';
    const pusherScheme = import.meta.env.VITE_PUSHER_SCHEME || 'https';

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        wsHost: pusherHost,
        wsPort: pusherPort,
        wssPort: pusherPort,
        forceTLS: pusherScheme === 'https',
        encrypted: true,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        },
    });

    return echoInstance;
}

function getCsrfToken(): string {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    return token || '';
}

export function useEcho() {
    if (!echoInstance) {
        initializeEcho();
    }

    return echoInstance!;
}

export function getEcho(): Echo | null {
    return echoInstance;
}

