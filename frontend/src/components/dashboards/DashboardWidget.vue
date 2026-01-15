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
  <v-card>
    <v-card-title class="d-flex align-center">
      <v-icon
        v-if="icon"
        :icon="icon"
        :color="color"
        class="mr-2"
      />
      {{ title }}
      
      <v-spacer />
      
      <v-btn
        v-if="action"
        variant="text"
        size="small"
        :to="action.to"
        @click="action.onClick"
      >
        {{ action.label }}
      </v-btn>
    </v-card-title>

    <v-divider />

    <v-card-text>
      <v-progress-linear
        v-if="loading"
        indeterminate
        color="primary"
      />
      
      <slot v-else />
    </v-card-text>
  </v-card>
</template>
