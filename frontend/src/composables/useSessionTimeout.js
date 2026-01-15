import { useRouter } from 'vue-router'

export function useSessionTimeout(options = {}) {
    const {
        timeoutMinutes = 30,
        warningMinutes = 5,
        onTimeout = null,
        onWarning = null,
    } = options

    const router = useRouter()

    const lastActivity = ref(Date.now())
    const showWarning = ref(false)
    const timeRemaining = ref(timeoutMinutes * 60)

    let activityTimer = null
    let warningTimer = null
    let countdownInterval = null

    const timeoutMs = timeoutMinutes * 60 * 1000
    const warningMs = warningMinutes * 60 * 1000

    function updateActivity() {
        lastActivity.value = Date.now()
        showWarning.value = false
        resetTimers()
    }

    function resetTimers() {
        // Clear existing timers
        if (activityTimer) clearTimeout(activityTimer)
        if (warningTimer) clearTimeout(warningTimer)
        if (countdownInterval) clearInterval(countdownInterval)

        // Set warning timer
        warningTimer = setTimeout(() => {
            showWarning.value = true
            if (onWarning) onWarning()
            startCountdown()
        }, timeoutMs - warningMs)

        // Set timeout timer
        activityTimer = setTimeout(() => {
            handleTimeout()
        }, timeoutMs)
    }

    function startCountdown() {
        timeRemaining.value = warningMinutes * 60
        countdownInterval = setInterval(() => {
            timeRemaining.value--
            if (timeRemaining.value <= 0) {
                clearInterval(countdownInterval)
            }
        }, 1000)
    }

    function handleTimeout() {
        // Clear session
        localStorage.removeItem('token')
        localStorage.removeItem('user_id')
        localStorage.removeItem('user_name')
        localStorage.removeItem('user_email')
        localStorage.removeItem('user_role')

        if (onTimeout) {
            onTimeout()
        }
        else {
            // Default: redirect to login
            router.push('/login?reason=timeout')
        }
    }

    function extendSession() {
        updateActivity()
    }

    // Track user activity
    const activityEvents = ['mousedown', 'keydown', 'scroll', 'touchstart']

    onMounted(() => {
        // Add activity listeners
        activityEvents.forEach(event => {
            window.addEventListener(event, updateActivity, { passive: true })
        })

        // Start timers
        resetTimers()
    })

    onUnmounted(() => {
        // Remove listeners
        activityEvents.forEach(event => {
            window.removeEventListener(event, updateActivity)
        })

        // Clear timers
        if (activityTimer) clearTimeout(activityTimer)
        if (warningTimer) clearTimeout(warningTimer)
        if (countdownInterval) clearInterval(countdownInterval)
    })

    return {
        showWarning,
        timeRemaining,
        extendSession,
        formatTimeRemaining: computed(() => {
            const minutes = Math.floor(timeRemaining.value / 60)
            const seconds = timeRemaining.value % 60
            return `${minutes}:${seconds.toString().padStart(2, '0')}`
        }),
    }
}
