<script setup>
import { computed } from 'vue'

const props = defineProps({
  score: {
    type: Number,
    required: true,
    validator: (value) => value >= 0 && value <= 100,
  },
  breakdown: {
    type: Object,
    default: () => ({}),
  },
  size: {
    type: Number,
    default: 120,
  },
  showDetails: {
    type: Boolean,
    default: false,
  },
})

const scoreColor = computed(() => {
  if (props.score >= 80) return 'success'
  if (props.score >= 50) return 'warning'
  return 'error'
})

const scoreLabel = computed(() => {
  if (props.score >= 80) return 'Excellent'
  if (props.score >= 60) return 'Good'
  if (props.score >= 40) return 'Fair'
  return 'Low'
})

const breakdownItems = computed(() => {
  if (!props.breakdown) return []
  
  return [
    {
      label: 'Signature Level',
      value: props.breakdown.signature_level || 0,
      weight: '40%',
      icon: 'mdi-shield-check',
    },
    {
      label: 'Identity Verification',
      value: props.breakdown.identity_verification || 0,
      weight: '30%',
      icon: 'mdi-account-check',
    },
    {
      label: 'Timestamps',
      value: props.breakdown.timestamps || 0,
      weight: '20%',
      icon: 'mdi-clock-check',
    },
    {
      label: 'Certificates',
      value: props.breakdown.certificates || 0,
      weight: '10%',
      icon: 'mdi-certificate',
    },
  ]
})
</script>

<template>
  <div class="trust-score-indicator">
    <v-card :variant="showDetails ? 'elevated' : 'flat'">
      <v-card-text class="text-center">
        <!-- Circular Progress -->
        <v-progress-circular
          :model-value="score"
          :size="size"
          :width="size / 10"
          :color="scoreColor"
        >
          <div class="d-flex flex-column align-center">
            <div class="text-h4 font-weight-bold">
              {{ Math.round(score) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ scoreLabel }}
            </div>
          </div>
        </v-progress-circular>

        <!-- Score Label -->
        <div class="mt-2 text-subtitle-2 font-weight-medium">
          Trust Score
        </div>

        <!-- Trust Badge -->
        <v-chip
          :color="scoreColor"
          size="small"
          class="mt-2"
        >
          <v-icon start icon="mdi-shield-check" />
          {{ scoreLabel }} Security
        </v-chip>

        <!-- Breakdown Details -->
        <v-expand-transition>
          <div v-if="showDetails && breakdown" class="mt-4">
            <v-divider class="mb-3" />
            
            <div class="text-subtitle-2 mb-2">Score Breakdown</div>
            
            <v-list density="compact">
              <v-list-item
                v-for="item in breakdownItems"
                :key="item.label"
              >
                <template #prepend>
                  <v-icon :icon="item.icon" size="small" />
                </template>

                <v-list-item-title class="text-caption">
                  {{ item.label }}
                </v-list-item-title>

                <v-list-item-subtitle class="text-caption">
                  {{ item.value }}/100 ({{ item.weight }} weight)
                </v-list-item-subtitle>

                <template #append>
                  <v-progress-linear
                    :model-value="item.value"
                    :color="item.value >= 80 ? 'success' : item.value >= 50 ? 'warning' : 'error'"
                    height="6"
                    rounded
                    style="width: 80px;"
                  />
                </template>
              </v-list-item>
            </v-list>

            <!-- Formula Explanation -->
            <v-alert type="info" variant="tonal" density="compact" class="mt-3">
              <div class="text-caption">
                <strong>Formula:</strong> (Level × 40%) + (Verification × 30%) + (Timestamps × 20%) + (Certificates × 10%)
              </div>
            </v-alert>
          </div>
        </v-expand-transition>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.trust-score-indicator {
  display: inline-block;
}
</style>
