export function useNetworkErrorRecovery() {
    const isOnline = ref(navigator.onLine)
    const showOfflineNotice = ref(!navigator.onLine)
    const retryQueue = ref([])

    // Track online/offline status
    function handleOnline() {
        isOnline.value = true
        showOfflineNotice.value = false

        // Retry queued requests
        processRetryQueue()
    }

    function handleOffline() {
        isOnline.value = false
        showOfflineNotice.value = true
    }

    function queueRequest(requestFn, context = {}) {
        retryQueue.value.push({ requestFn, context, timestamp: Date.now() })
    }

    async function processRetryQueue() {
        if (retryQueue.value.length === 0) return

        const requests = [...retryQueue.value]
        retryQueue.value = []

        for (const { requestFn, context } of requests) {
            try {
                await requestFn()
            }
            catch (error) {
                console.error('Retry failed:', error)
                // Re-queue if still offline
                if (!navigator.onLine) {
                    retryQueue.value.push({ requestFn, context, timestamp: Date.now() })
                }
            }
        }
    }

    // Intercept failed requests
    function handleRequestError(error, retryFn) {
        if (!navigator.onLine || error.message?.includes('Network')) {
            queueRequest(retryFn, { error: error.message })
            return {
                queued: true,
                message: 'Request queued - will retry when online',
            }
        }
        return { queued: false }
    }

    onMounted(() => {
        window.addEventListener('online', handleOnline)
        window.addEventListener('offline', handleOffline)
    })

    onUnmounted(() => {
        window.removeEventListener('online', handleOnline)
        window.removeEventListener('offline', handleOffline)
    })

    return {
        isOnline,
        showOfflineNotice,
        retryQueue: computed(() => retryQueue.value.length),
        queueRequest,
        handleRequestError,
    }
}
