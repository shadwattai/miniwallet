import { ref, Ref } from 'vue'
import Pusher, { Channel } from 'pusher-js'
import { useToast } from 'primevue/usetoast';
 

interface Notification {
    id?: number
    type: string
    title: string
    message: string
    amount?: string
}

interface WebSocketConfig {
    pusher_key: string
    cluster: string
    auth_endpoint: string
}

export function useWebSocket() {
    // Reactive state
    const isConnected: Ref<boolean> = ref(false);
    const pusher: Ref<Pusher | null> = ref(null);
    const channels: Ref<Record<string, Channel>> = ref({});
    const notifications: Ref<Notification[]> = ref([]);
    const balance: Ref<number> = ref(0);

    // Reactive wallets state for real-time updates
    const wallets: Ref<Record<string, any>> = ref({});

    // Initialize toast instance
    const toast = useToast();

    /**
     * Initialize the WebSocket connection
     * @param token - Authorization token
     * @param userId - User ID for channel subscription
     * @returns Promise<boolean>
     */
    const initialize = async (token: string, userId: string): Promise<boolean> => {
        try {
            const response = await fetch('/api/websocket/config', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                }
            })

            const config: WebSocketConfig = await response.json()

            // Initialize Pusher
            pusher.value = new Pusher(config.pusher_key, {
                cluster: config.cluster,
                authEndpoint: config.auth_endpoint,
                auth: {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    }
                }
            })

            // Bind connection events
            pusher.value.connection.bind('connected', () => {
                console.log('💰 Miniwallet: WebSocket connected')
                isConnected.value = true
            })

            return true
        } catch (error) {
            console.error('WebSocket initialization failed:', error)
            return false
        }
    }

    /**
     * Subscribe to user-specific notifications
     * @param userId - User ID for private channel
     * @returns Channel
     */
    const subscribeToUserNotifications = (userId: string): Channel | null => {
        if (!pusher.value) {
            console.error('Pusher is not initialized');
            return null;
        }

        const channel = pusher.value.subscribe(`private-user.${userId}`);

        // Bind to "money.received" event
        channel.bind('money.received', (data: any) => {
            balance.value = parseFloat(data.new_balance);
            addNotification({
                type: 'success',
                title: 'Money Received!',
                message: data.message,
                amount: data.formatted_amount
            });

            // Update wallet balance
            if (wallets.value[data.wallet_key]) {
                wallets.value[data.wallet_key].balance = parseFloat(data.new_balance);
            }

            // Show toast notification
            toast.add({
                severity: 'success',
                summary: 'Money Received!',
                detail: data.message,
                life: 5000 // Duration in milliseconds
            });
        });

        // Bind to "money.sent" event
        channel.bind('money.sent', (data: any) => {
            balance.value = parseFloat(data.new_balance);
            addNotification({
                type: 'info',
                title: 'Money Sent',
                message: data.message,
                amount: data.formatted_amount
            });

            // Update wallet balance
            if (wallets.value[data.wallet_key]) {
                wallets.value[data.wallet_key].balance = parseFloat(data.new_balance);
            }

            // Show toast notification
            toast.add({
                severity: 'info',
                summary: 'Money Sent',
                detail: data.message,
                life: 5000 // Duration in milliseconds
            });
        });

        // Store the channel reference
        channels.value[userId] = channel;

        return channel;
    };

    /**
     * Add a notification to the list
     * @param notification - Notification object
     */
    const addNotification = (notification: Notification): void => {
        notification.id = Date.now() + Math.random()
        notifications.value.unshift(notification)

        // Keep only the last 10 notifications
        if (notifications.value.length > 10) {
            notifications.value = notifications.value.slice(0, 10)
        }
    }

    /**
     * Show a browser notification
     * @param title - Notification title
     * @param message - Notification message
     */
    const showBrowserNotification = (title: string, message: string): void => {
        // if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, {
                body: message,
                icon: '/logos/logo.png'
            })
        // }
    }

    return {
        isConnected,
        notifications,
        balance,
        wallets,
        initialize,
        subscribeToUserNotifications,
        addNotification
    };
}