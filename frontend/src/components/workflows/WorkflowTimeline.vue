<script setup>
const props = defineProps({
  steps: {
    type: Array,
    required: true,
  },
})

const getStepColor = status => {
  switch (status) {
    case 'SIGNED':
      return 'success'
    case 'DECLINED':
      return 'error'
    case 'PENDING':
      return 'grey'
    default:
      return 'grey'
  }
}

const getStepIcon = status => {
  switch (status) {
    case 'SIGNED':
      return 'mdi-check-circle'
    case 'DECLINED':
      return 'mdi-close-circle'
    case 'PENDING':
      return 'mdi-clock-outline'
    default:
      return 'mdi-circle-outline'
  }
}

const formatDate = date => {
  if (!date) return ''
  return new Date(date).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
   minute: '2-digit',
  })
}

const isCurrentStep = step => {
  return step.status === 'PENDING' && !props.steps.find((s, index) => {
    const stepIndex = props.steps.indexOf(step)
    return index < stepIndex && s.status === 'PENDING'
  })
}
</script>

<template>
  <v-timeline align="start" density="compact">
    <v-timeline-item
      v-for="(step, index) in steps"
      :key="step.id"
      :dot-color="getStepColor(step.status)"
      :icon="getStepIcon(step.status)"
      size="small"
      :class="{ 'current-step': isCurrentStep(step) }"
    >
      <template #opposite>
        <div class="text-caption text-medium-emphasis">
          {{ formatDate(step.signed_at || step.declined_at || step.created_at) }}
        </div>
      </template>

      <v-card
        variant="outlined"
        :class="{ 'current-step-card': isCurrentStep(step) }"
      >
        <v-card-title class="text-subtitle-1 d-flex align-center">
          {{ step.role }}
          
          <v-spacer />
          
          <v-chip
            :color="getStepColor(step.status)"
            size="small"
            variant="flat"
          >
            {{ step.status }}
          </v-chip>
        </v-card-title>

        <v-card-subtitle v-if="step.assignedUser">
          <v-icon
            icon="mdi-account"
            size="x-small"
            class="mr-1"
          />
          {{ step.assignedUser.name }}
          <span v-if="step.assignedUser.email" class="text-caption">
            ({{ step.assignedUser.email }})
          </span>
        </v-card-subtitle>

        <v-card-text v-if="step.status === 'DECLINED' && step.decline_reason">
          <v-alert
            type="error"
            variant="tonal"
            density="compact"
          >
            <div class="text-caption">
              <strong>Decline Reason:</strong>
              {{ step.decline_reason }}
            </div>
          </v-alert>
        </v-card-text>

        <v-card-text v-if="isCurrentStep(step)">
          <v-alert
            type="info"
            variant="tonal"
            density="compact"
          >
            <div class="text-caption">
              <v-icon icon="mdi-information" size="x-small" class="mr-1" />
              Waiting for signature
            </div>
          </v-alert>
        </v-card-text>
      </v-card>
    </v-timeline-item>
  </v-timeline>
</template>

<style scoped>
.current-step {
  animation: pulse 2s infinite;
}

.current-step-card {
  border-color: rgb(var(--v-theme-primary));
  border-width: 2px;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}
</style>
