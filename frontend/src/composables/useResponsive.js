import { useDisplay } from 'vuetify'
import { ref, onMounted, onUnmounted } from 'vue'

export function useResponsive() {
  const { xs, sm, md, lg, xl, mobile, name } = useDisplay()

  // Core breakpoint checks
  const isMobile = computed(() => mobile.value)
  const isTablet = computed(() => sm.value && !mobile.value)
  const isDesktop = computed(() => md.value || lg.value || xl.value)
  const isSmallScreen = computed(() => xs.value || sm.value)
  const isLargeScreen = computed(() => lg.value || xl.value)

  // Touch detection
  const isTouch = ref(false)

  onMounted(() => {
    isTouch.value = 'ontouchstart' in window || navigator.maxTouchPoints > 0
  })

  // Orientation
  const orientation = ref('portrait')

  const updateOrientation = () => {
    orientation.value = window.innerWidth > window.innerHeight ? 'landscape' : 'portrait'
  }

  onMounted(() => {
    updateOrientation()
    window.addEventListener('resize', updateOrientation)
  })

  onUnmounted(() => {
    window.removeEventListener('resize', updateOrientation)
  })

  const isLandscape = computed(() => orientation.value === 'landscape')
  const isPortrait = computed(() => orientation.value === 'portrait')

  // Grid columns based on screen size
  const gridCols = computed(() => {
    if (xs.value) return 1
    if (sm.value) return 2
    if (md.value) return 3
    return 4
  })

  // Responsive values helper
  const responsive = (mobileVal, tabletVal, desktopVal) => {
    if (isMobile.value) return mobileVal
    if (isTablet.value) return tabletVal ?? mobileVal
    return desktopVal ?? tabletVal ?? mobileVal
  }

  // Card layout
  const cardClass = computed(() => {
    if (isMobile.value) return 'mobile-card'
    if (isTablet.value) return 'tablet-card'
    return 'desktop-card'
  })

  // Spacing (for VRow gutters, padding, etc)
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

  // Icon size
  const iconSize = computed(() => {
    if (isMobile.value) return 20
    if (isTablet.value) return 22
    return 24
  })

  // Text size classes
  const titleClass = computed(() => {
    return isMobile.value ? 'text-h6' : 'text-h5'
  })

  const subtitleClass = computed(() => {
    return isMobile.value ? 'text-body-2' : 'text-subtitle-1'
  })

  // Show bottom sheet instead of dialog on mobile
  const useBottomSheet = computed(() => isMobile.value)

  // Avatar sizes
  const avatarSize = computed(() => {
    if (isMobile.value) return 36
    if (isTablet.value) return 40
    return 48
  })

  // Card sizes
  const cardPadding = computed(() => {
    return isMobile.value ? 'pa-3' : 'pa-4'
  })

  // Navigation drawer handling
  const drawerWidth = computed(() => {
    if (isMobile.value) return 280
    if (isTablet.value) return 300
    return 320
  })

  // Helper for responsive styling
  const responsiveStyle = (styles) => {
    const breakpoint = name.value
    return styles[breakpoint] || styles.default || {}
  }

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
    isSmallScreen,
    isLargeScreen,
    name,

    // Touch & orientation
    isTouch,
    orientation,
    isLandscape,
    isPortrait,

    // Computed values
    gridCols,
    cardClass,
    spacing,
    dialogWidth,
    listDensity,
    buttonSize,
    iconSize,
    titleClass,
    subtitleClass,
    useBottomSheet,
    avatarSize,
    cardPadding,
    drawerWidth,

    // Helper functions
    responsive,
    responsiveStyle,
  }
}
