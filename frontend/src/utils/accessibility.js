/**
 * Accessibility utilities for ARIA labels and announcements
 */

/**
 * Create live region for screen reader announcements
 */
export function createLiveRegion(politeness = 'polite') {
    const liveRegion = document.createElement('div')
    liveRegion.setAttribute('aria-live', politeness)
    liveRegion.setAttribute('aria-atomic', 'true')
    liveRegion.setAttribute('class', 'sr-only')
    liveRegion.style.position = 'absolute'
    liveRegion.style.left = '-10000px'
    liveRegion.style.width = '1px'
    liveRegion.style.height = '1px'
    liveRegion.style.overflow = 'hidden'

    document.body.appendChild(liveRegion)
    return liveRegion
}

let liveRegion = null

/**
 * Announce message to screen readers
 */
export function announce(message, politeness = 'polite') {
    if (!liveRegion) {
        liveRegion = createLiveRegion(politeness)
    }

    // Clear and set new message
    liveRegion.textContent = ''
    setTimeout(() => {
        liveRegion.textContent = message
    }, 100)
}

/**
 * Generate accessible label from string
 */
export function generateAriaLabel(text, context = {}) {
    const parts = [text]

    if (context.count !== undefined) {
        parts.push(`${context.count} items`)
    }

    if (context.status) {
        parts.push(`Status: ${context.status}`)
    }

    if (context.required) {
        parts.push('Required')
    }

    return parts.filter(Boolean).join(', ')
}

/**
 * Get readable date for screen readers
 */
export function getReadableDate(date) {
    if (!date) return ''

    const d = new Date(date)
    const now = new Date()
    const diff = now - d
    const hours = Math.floor(diff / 3600000)
    const days = Math.floor(hours / 24)

    if (days === 0) {
        if (hours === 0) return 'Today'
        if (hours === 1) return '1 hour ago'
        return `${hours} hours ago`
    }
    if (days === 1) return 'Yesterday'
    if (days < 7) return `${days} days ago`

    return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    })
}

/**
 * Get status description for screen readers
 */
export function getStatusDescription(status) {
    const descriptions = {
        DRAFT: 'Draft, not yet sent',
        IN_PROGRESS: 'In progress, awaiting signatures',
        COMPLETED: 'Completed, all signatures received',
        DECLINED: 'Declined by a signer',
        CANCELLED: 'Cancelled by initiator',
        PENDING: 'Pending, awaiting action',
        SIGNED: 'Signed successfully',
    }

    return descriptions[status] || status
}

/**
 * Generate unique ID for aria-labelledby
 */
let idCounter = 0
export function generateId(prefix = 'a11y') {
    return `${prefix}-${++idCounter}`
}
