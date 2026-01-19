import { useDisplay } from 'vuetify'

export function useResponsive() {
  const { xs, sm, md, lg, xl, mobile, name } = useDisplay()

  const isMobile = computed(() => mobile.value)
  const isTablet = computed(() => sm.value && !mobile.value)
  const isDesktop = computed(() => md.value || lg.value || xl.value)

  // Grid columns based on screen size
  const gridCols = computed(() => {
    if (xs.value) return 1
    if (sm.value) return 2
    if (md.value) return 3
    
    return 4
  })

  // Card layout
  const cardClass = computed(() => {
    if (isMobile.value) return 'mobile-card'
    if (isTablet.value) return 'tablet-card'
    
    return 'desktop-card'
  })

  // Spacing
  const spacing = computed(() => {
    if (isMobile.value) return 2
    if (isTablet.value) return 3
    
    return 4
  })

  // Dialog width
  const dialogWidth = computed(() => {
    if (xs.value) return '95vw'
    if (sm.value) return '600px'
    if (md.value) return '800px'
    
    return '1000px'
  })

  // List density
  const listDensity = computed(() => {
    return isMobile.value ? 'compact' : 'default'
  })

  // Button size
  const buttonSize = computed(() => {
    return isMobile.value ? 'small' : 'default'
  })

  // Show bottom sheet instead of dialog on mobile
  const useBottomSheet = computed(() => isMobile.value)

  return {
    // Breakpoint checks
    xs,
    sm,
    md,
    lg,
    xl,
    isMobile,
    isTablet,
    isDesktop,
    name,

    // Computed values
    gridCols,
    cardClass,
    spacing,
    dialogWidth,
    listDensity,
    buttonSize,
    useBottomSheet,
  }
}
