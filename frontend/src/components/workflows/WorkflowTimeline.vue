<script setup>
const props = defineProps({
  steps: {
    type: Array,
    required: true,
  },
})

const getStatusConfig = status => {
  const map = {
    'SIGNED': { color: 'success', icon: 'mdi-check', text: 'Signed' },
    'DECLINED': { color: 'error', icon: 'mdi-close', text: 'Declined' },
    'PENDING': { color: 'grey-lighten-1', icon: 'mdi-clock-outline', text: 'Pending' },
    'NOTIFIED': { color: 'info', icon: 'mdi-email-check-outline', text: 'Notified' },
    'VIEWED': { color: 'warning', icon: 'mdi-eye-outline', text: 'Viewed' },
    'IN_PROGRESS': { color: 'primary', icon: 'mdi-pencil', text: 'Signing...' }
  }
  return map[status] || map['PENDING']
}

const formatDate = date => {
  if (!date) return ''
  return new Date(date).toLocaleString('en-US', {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
  })
}

const getInitials = name => {
    return name ? name.split(' ').map(n => n[0]).join('').substring(0,2).toUpperCase() : '?'
}
</script>

<template>
  <div class="timeline-container pl-2">
      <div 
        v-for="(step, index) in steps" 
        :key="step.id"
        class="timeline-item position-relative d-flex pb-6"
      >
          <!-- Line -->
          <div 
            v-if="index !== steps.length - 1" 
            class="timeline-line position-absolute bg-grey-lighten-2"
            style="left: 15px; top: 32px; bottom: 0; width: 2px;"
          ></div>

          <!-- Avatar/Dot -->
          <div class="me-3 position-relative z-index-1">
             <v-avatar 
                :color="getStatusConfig(step.status).color" 
                size="32" 
                variant="tonal"
                class="border"
                :class="step.status === 'IN_PROGRESS' ? 'ring-pulse' : ''"
             >
                <span v-if="step.status === 'SIGNED'" class="text-caption font-weight-bold">
                    <v-icon icon="mdi-check" size="16" />
                </span>
                <span v-else class="text-caption font-weight-bold">
                    {{ getInitials(step.assignedUser?.name) }}
                </span>
             </v-avatar>
             
             <!-- Status Icon Badge -->
             <div 
                class="status-badge position-absolute bg-surface rounded-circle border d-flex align-center justify-center"
                style="bottom: -4px; right: -4px; width: 16px; height: 16px;"
                :class="`text-${getStatusConfig(step.status).color}`"
             >
               <v-icon :icon="getStatusConfig(step.status).icon" size="10" />
             </div>
          </div>

          <!-- Content -->
          <div class="d-flex flex-column pt-1 flex-grow-1" style="min-width: 0;">
             <div class="d-flex justify-space-between align-start">
                 <span class="text-subtitle-2 font-weight-bold text-truncate" :title="step.assignedUser?.name">
                     {{ step.assignedUser?.name || 'Unknown' }}
                 </span>
                 <span v-if="step.signed_at" class="text-[10px] text-medium-emphasis mt-1 text-no-wrap ms-2">
                     {{ formatDate(step.signed_at) }}
                 </span>
             </div>
             
             <div class="d-flex align-center justify-space-between mt-1">
                 <span class="text-caption text-medium-emphasis">{{ step.role }}</span>
                 <span 
                    class="text-[10px] text-uppercase font-weight-bold"
                    :class="`text-${getStatusConfig(step.status).color}`"
                 >
                    {{ getStatusConfig(step.status).text }}
                 </span>
             </div>

             <!-- Decline Reason -->
             <div v-if="step.status === 'DECLINED' && step.decline_reason" class="mt-2 text-caption text-error bg-error-lighten-5 pa-2 rounded">
                 "{{ step.decline_reason }}"
             </div>
          </div>
      </div>
  </div>
</template>

<style scoped>
.ring-pulse {
  box-shadow: 0 0 0 0 rgba(var(--v-theme-primary), 0.7);
  animation: pulse-primary 1.5s infinite cubic-bezier(0.66, 0, 0, 1);
}

@keyframes pulse-primary {
  to {
    box-shadow: 0 0 0 10px rgba(var(--v-theme-primary), 0);
  }
}
</style>
