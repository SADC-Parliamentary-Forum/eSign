<script setup>
/**
 * MobileBottomSheet - Swipeable bottom sheet for mobile
 * Replaces dialogs on mobile for better UX
 */
import { useResponsive } from '@/composables/useResponsive'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  persistent: {
    type: Boolean,
    default: false,
  },
  height: {
    type: String,
    default: '80vh', // Default height
  },
  fullscreen: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'close'])

const { isMobile } = useResponsive()

const isOpen = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const sheetRef = ref(null)
const startY = ref(0)
const currentY = ref(0)
const isDragging = ref(false)

const handleTouchStart = (e) => {
  if (props.persistent) return
  startY.value = e.touches[0].clientY
  isDragging.value = true
}

const handleTouchMove = (e) => {
  if (!isDragging.value || props.persistent) return
  currentY.value = e.touches[0].clientY - startY.value
  if (currentY.value < 0) currentY.value = 0
}

const handleTouchEnd = () => {
  if (!isDragging.value || props.persistent) return
  isDragging.value = false
  
  // If dragged down more than 100px, close
  if (currentY.value > 100) {
    close()
  }
  currentY.value = 0
}

const close = () => {
  if (!props.persistent) {
    isOpen.value = false
    emit('close')
  }
}

const sheetStyle = computed(() => ({
  transform: isDragging.value && currentY.value > 0 
    ? `translateY(${currentY.value}px)` 
    : 'translateY(0)',
  transition: isDragging.value ? 'none' : 'transform 0.3s ease',
  height: props.fullscreen ? '100vh' : props.height,
}))
</script>

<template>
  <!-- Use VDialog for desktop, custom bottom sheet for mobile -->
  <VDialog
    v-if="!isMobile"
    v-model="isOpen"
    :persistent="persistent"
    max-width="600"
  >
    <VCard>
      <VCardTitle v-if="title" class="d-flex align-center py-3">
        {{ title }}
        <VSpacer />
        <VBtn 
          v-if="!persistent"
          icon="mdi-close" 
          variant="text" 
          size="small"
          @click="close"
        />
      </VCardTitle>
      <VDivider v-if="title" />
      <slot />
    </VCard>
  </VDialog>

  <!-- Mobile Bottom Sheet -->
  <Teleport v-else to="body">
    <Transition name="bottom-sheet">
      <div
        v-if="isOpen"
        class="bottom-sheet-overlay"
        @click.self="close"
      >
        <div
          ref="sheetRef"
          class="bottom-sheet"
          :style="sheetStyle"
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd"
        >
          <!-- Drag Handle -->
          <div class="drag-handle">
            <div class="drag-indicator" />
          </div>

          <!-- Header -->
          <div v-if="title" class="sheet-header">
            <div class="text-h6 font-weight-bold">{{ title }}</div>
            <VBtn 
              v-if="!persistent"
              icon="mdi-close" 
              variant="text" 
              size="small"
              @click="close"
            />
          </div>

          <!-- Content -->
          <div class="sheet-content">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.bottom-sheet-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 9999;
  display: flex;
  align-items: flex-end;
}

.bottom-sheet {
  width: 100%;
  background: white;
  border-radius: 16px 16px 0 0;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
}

.drag-handle {
  padding: 12px;
  display: flex;
  justify-content: center;
  cursor: grab;
}

.drag-indicator {
  width: 40px;
  height: 4px;
  background: #ccc;
  border-radius: 2px;
}

.sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px 12px;
  border-bottom: 1px solid #eee;
}

.sheet-content {
  flex: 1;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

/* Transitions */
.bottom-sheet-enter-active,
.bottom-sheet-leave-active {
  transition: opacity 0.3s ease;
}

.bottom-sheet-enter-active .bottom-sheet,
.bottom-sheet-leave-active .bottom-sheet {
  transition: transform 0.3s ease;
}

.bottom-sheet-enter-from,
.bottom-sheet-leave-to {
  opacity: 0;
}

.bottom-sheet-enter-from .bottom-sheet,
.bottom-sheet-leave-to .bottom-sheet {
  transform: translateY(100%);
}
</style>
