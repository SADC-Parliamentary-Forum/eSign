<script setup>
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    default: null,
  },
  color: {
    type: String,
    default: 'primary',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  action: {
    type: Object,
    default: null,
  },
})
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center">
      <VIcon
        v-if="icon"
        :icon="icon"
        :color="color"
        class="mr-2"
      />
      {{ title }}
      
      <VSpacer />
      
      <VBtn
        v-if="action"
        variant="text"
        size="small"
        :to="action.to"
        @click="action.onClick"
      >
        {{ action.label }}
      </VBtn>
    </VCardTitle>

    <VDivider />

    <VCardText>
      <VProgressLinear
        v-if="loading"
        indeterminate
        color="primary"
      />
      
      <slot v-else />
    </VCardText>
  </VCard>
</template>
