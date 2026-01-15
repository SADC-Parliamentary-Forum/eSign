export function useKeyboardNavigation() {
    const trapFocus = (element) => {
        if (!element) return

        const focusableElements = element.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
        )

        const firstElement = focusableElements[0]
        const lastElement = focusableElements[focusableElements.length - 1]

        function handleTabKey(e) {
            if (e.key !== 'Tab') return

            if (e.shiftKey) {
                // Shift + Tab
                if (document.activeElement === firstElement) {
                    lastElement?.focus()
                    e.preventDefault()
                }
            }
            else {
                // Tab
                if (document.activeElement === lastElement) {
                    firstElement?.focus()
                    e.preventDefault()
                }
            }
        }

        element.addEventListener('keydown', handleTabKey)

        // Return cleanup function
        return () => element.removeEventListener('keydown', handleTabKey)
    }

    const handleEscape = (callback) => {
        function handleKeydown(e) {
            if (e.key === 'Escape') {
                callback(e)
            }
        }

        window.addEventListener('keydown', handleKeydown)
        return () => window.removeEventListener('keydown', handleKeydown)
    }

    const handleEnter = (callback) => {
        function handleKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                callback(e)
            }
        }

        window.addEventListener('keydown', handleKeydown)
        return () => window.removeEventListener('keydown', handleKeydown)
    }

    const skipToContent = () => {
        const mainContent = document.querySelector('main') || document.querySelector('[role="main"]')
        if (mainContent) {
            mainContent.setAttribute('tabindex', '-1')
            mainContent.focus()
            mainContent.removeAttribute('tabindex')
        }
    }

    // Arrow key navigation for lists
    const handleArrowNavigation = (element, orientation = 'vertical') => {
        if (!element) return

        const items = Array.from(element.querySelectorAll('[role="menuitem"], [role="option"], [role="tab"]'))
        if (items.length === 0) return

        function handleArrowKeys(e) {
            const currentIndex = items.indexOf(document.activeElement)
            if (currentIndex === -1) return

            let nextIndex = currentIndex

            if (orientation === 'vertical') {
                if (e.key === 'ArrowDown') {
                    nextIndex = (currentIndex + 1) % items.length
                    e.preventDefault()
                }
                else if (e.key === 'ArrowUp') {
                    nextIndex = (currentIndex - 1 + items.length) % items.length
                    e.preventDefault()
                }
            }
            else {
                // horizontal
                if (e.key === 'ArrowRight') {
                    nextIndex = (currentIndex + 1) % items.length
                    e.preventDefault()
                }
                else if (e.key === 'ArrowLeft') {
                    nextIndex = (currentIndex - 1 + items.length) % items.length
                    e.preventDefault()
                }
            }

            if (nextIndex !== currentIndex) {
                items[nextIndex]?.focus()
            }
        }

        element.addEventListener('keydown', handleArrowKeys)
        return () => element.removeEventListener('keydown', handleArrowKeys)
    }

    return {
        trapFocus,
        handleEscape,
        handleEnter,
        skipToContent,
        handleArrowNavigation,
    }
}
