<script setup>
import { useKeyboardNavigation } from '@/composables/useKeyboardNavigation'

const { skipToContent } = useKeyboardNavigation()
</script>

<template>
  <!-- Skip to main content link for screen readers -->
  <a
    href="#main-content"
    class="skip-link"
    @click.prevent="skipToContent"
  >
    Skip to main content
  </a>
</template>

<style scoped>
.skip-link {
  position: absolute;
  top: -40px;
  left: 0;
  z-index: 9999;
  padding: 8px 16px;
  background: rgb(var(--v-theme-primary));
  color: white;
  text-decoration: none;
  font-weight: 500;
  border-radius: 0 0 4px 0;
}

.skip-link:focus {
  top: 0;
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}
</style>
