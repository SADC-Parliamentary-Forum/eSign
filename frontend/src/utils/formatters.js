import { format, parseISO } from 'date-fns'
import { useAppStore } from '@/stores/app'

/**
 * Normalizes a date input to a Date object in the system's preferred timezone.
 */
function getNormalizedDate(date, timezone) {
    if (!date) return null
    const d = typeof date === 'string' ? parseISO(date) : new Date(date)

    if (!timezone || timezone === 'UTC') return d

    // Use Intl to get the date in the target timezone
    try {
        const zonedDateString = d.toLocaleString('en-US', { timeZone: timezone })
        return new Date(zonedDateString)
    } catch (e) {
        return d
    }
}

/**
 * Standard professional date formatting
 * Respects system settings for format and timezone.
 */
export function formatDate(date) {
    if (!date) return '-'
    const appStore = useAppStore()
    const { timezone, date_format } = appStore.settings

    try {
        const d = getNormalizedDate(date, timezone)
        return format(d, date_format || 'MMM d, yyyy')
    } catch (e) {
        return '-'
    }
}

/**
 * Standard professional date time formatting
 * Respects system settings for format and timezone.
 */
export function formatDateTime(date) {
    if (!date) return '-'
    const appStore = useAppStore()
    const { timezone, date_format, time_format } = appStore.settings

    try {
        const d = getNormalizedDate(date, timezone)
        const pattern = `${date_format || 'MMM d, yyyy'}, ${time_format || 'h:mm a'}`
        return format(d, pattern)
    } catch (e) {
        return '-'
    }
}

/**
 * Standard professional relative date formatting
 * Example: 2 hours ago
 */
export function formatRelative(date) {
    if (!date) return '-'
    try {
        const d = typeof date === 'string' ? parseISO(date) : new Date(date)
        const now = new Date()
        const diffInSeconds = Math.floor((now - d) / 1000)

        if (diffInSeconds < 60) return 'just now'
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
        if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`

        return formatDate(date)
    } catch (e) {
        return '-'
    }
}
