<script setup>
const props = defineProps({
  template: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['edit', 'delete', 'activate'])

const getStatusColor = status => {
  const colors = {
    DRAFT: 'grey',
    REVIEW: 'info',
    APPROVED: 'success',
    ACTIVE: 'primary',
    ARCHIVED: 'error',
  }
  return colors[status] || 'grey'
}

const getStatusIcon = status => {
  const icons = {
    DRAFT: 'mdi-file-edit',
    REVIEW: 'mdi-eye',
    APPROVED: 'mdi-check',
    ACTIVE: 'mdi-check-circle',
    ARCHIVED: 'mdi-archive',
  }
  return icons[status] || 'mdi-file'
}
</script>

<template>
  <v-card variant="outlined" class="template-card">
    <v-card-item>
      <template #prepend>
        <v-avatar
          :color="getStatusColor(template.status)"
          size="48"
        >
          <v-icon :icon="getStatusIcon(template.status)" />
        </v-avatar>
      </template>

      <v-card-title>{{ template.name }}</v-card-title>
      
      <v-card-subtitle v-if="template.description">
        {{ template.description }}
      </v-card-subtitle>

      <template #append>
        <v-chip
          :color="getStatusColor(template.status)"
          size="small"
        >
          {{ template.status }}
        </v-chip>
      </template>
    </v-card-item>

    <v-card-text>
      <div class="d-flex flex-wrap ga-2 mb-2">
        <v-chip
          v-if="template.workflow_type"
          size="small"
          prepend-icon="mdi-workflow"
          variant="tonal"
        >
          {{ template.workflow_type }}
        </v-chip>
        
        <v-chip
          v-if="template.amount_required"
          size="small"
          prepend-icon="mdi-currency-usd"
          variant="tonal"
          color="warning"
        >
          Financial Rules
        </v-chip>
        
        <v-chip
          v-if="template.roles_count"
          size="small"
          prepend-icon="mdi-account-group"
          variant="tonal"
        >
          {{ template.roles_count }} roles
        </v-chip>
      </div>

      <div class="text-caption text-medium-emphasis">
        <v-icon icon="mdi-update" size="x-small" class="mr-1" />
        Updated {{ formatDate(template.updated_at) }}
        <span v-if="template.version" class="ml-2">
          • v{{ template.version }}
        </span>
      </div>
    </v-card-text>

    <v-divider />

    <v-card-actions>
      <v-btn
        size="small"
        variant="text"
        :to="`/templates/${template.id}`"
      >
        View
      </v-btn>

      <v-btn
        v-if="template.status === 'DRAFT'"
        size="small"
        variant="text"
        @click="$emit('edit', template)"
      >
        Edit
      </v-btn>

      <v-btn
        v-if="template.status === 'APPROVED'"
        size="small"
        color="primary"
        variant="text"
        @click="$emit('activate', template)"
      >
        Activate
      </v-btn>

      <v-spacer />

      <v-btn
        v-if="template.usage_count"
        size="small"
        variant="text"
        color="info"
      >
        {{ template.usage_count }} uses
      </v-btn>

      <v-menu>
        <template #activator="{ props: menuProps }">
          <v-btn
            icon="mdi-dots-vertical"
            size="small"
            variant="text"
            v-bind="menuProps"
          />
        </template>

        <v-list>
          <v-list-item @click="$emit('edit', template)">
            <template #prepend>
              <v-icon>mdi-pencil</v-icon>
            </template>
            <v-list-item-title>Edit</v-list-item-title>
          </v-list-item>

          <v-list-item @click="$emit('delete', template)">
            <template #prepend>
              <v-icon color="error">mdi-delete</v-icon>
            </template>
            <v-list-item-title class="text-error">Delete</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-menu>
    </v-card-actions>
  </v-card>
</template>

<script>
function formatDate(date) {
  if (!date) return ''
  const d = new Date(date)
  const now = new Date()
  const diff = now - d
  const days = Math.floor(diff / 86400000)
  
  if (days === 0) return 'Today'
  if (days === 1) return 'Yesterday'
  if (days < 7) return `${days} days ago`
  return d.toLocaleDateString()
}
</script>

<style scoped>
.template-card {
  transition: all 0.2s ease-in-out;
}

.template-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}
</style>
