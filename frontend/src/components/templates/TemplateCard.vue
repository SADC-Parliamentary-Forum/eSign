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

const formatDate = date => {
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

<template>
  <VCard
    variant="outlined"
    class="template-card"
  >
    <VCardItem>
      <template #prepend>
        <VAvatar
          :color="getStatusColor(template.status)"
          size="48"
        >
          <VIcon :icon="getStatusIcon(template.status)" />
        </VAvatar>
      </template>

      <VCardTitle>{{ template.name }}</VCardTitle>
      
      <VCardSubtitle v-if="template.description">
        {{ template.description }}
      </VCardSubtitle>

      <template #append>
        <VChip
          :color="getStatusColor(template.status)"
          size="small"
        >
          {{ template.status }}
        </VChip>
      </template>
    </VCardItem>

    <VCardText>
      <div class="d-flex flex-wrap ga-2 mb-2">
        <VChip
          v-if="template.workflow_type"
          size="small"
          prepend-icon="mdi-workflow"
          variant="tonal"
        >
          {{ template.workflow_type }}
        </VChip>
        
        <VChip
          v-if="template.amount_required"
          size="small"
          prepend-icon="mdi-currency-usd"
          variant="tonal"
          color="warning"
        >
          Financial Rules
        </VChip>
        
        <VChip
          v-if="template.roles_count"
          size="small"
          prepend-icon="mdi-account-group"
          variant="tonal"
        >
          {{ template.roles_count }} roles
        </VChip>
      </div>

      <div class="text-caption text-medium-emphasis">
        <VIcon
          icon="mdi-update"
          size="x-small"
          class="mr-1"
        />
        Updated {{ formatDate(template.updated_at) }}
        <span
          v-if="template.version"
          class="ml-2"
        >
          • v{{ template.version }}
        </span>
      </div>
    </VCardText>

    <VDivider />

    <VCardActions>
      <VBtn
        size="small"
        variant="text"
        :to="`/templates/${template.id}`"
      >
        View
      </VBtn>

      <VBtn
        v-if="template.status === 'ACTIVE'"
        size="small"
        color="primary"
        variant="flat"
        :to="`/upload?templateId=${template.id}`"
      >
        Use
      </VBtn>

      <VBtn
        v-if="template.status === 'DRAFT'"
        size="small"
        variant="text"
        @click="$emit('edit', template)"
      >
        Edit
      </VBtn>

      <VBtn
        v-if="template.status === 'APPROVED'"
        size="small"
        color="primary"
        variant="text"
        @click="$emit('activate', template)"
      >
        Activate
      </VBtn>

      <VSpacer />

      <VBtn
        v-if="template.usage_count"
        size="small"
        variant="text"
        color="info"
      >
        {{ template.usage_count }} uses
      </VBtn>

      <VMenu>
        <template #activator="{ props: menuProps }">
          <VBtn
            icon="mdi-dots-vertical"
            size="small"
            variant="text"
            v-bind="menuProps"
          />
        </template>

        <VList>
          <VListItem @click="$emit('edit', template)">
            <template #prepend>
              <VIcon>mdi-pencil</VIcon>
            </template>
            <VListItemTitle>Edit</VListItemTitle>
          </VListItem>

          <VListItem @click="$emit('delete', template)">
            <template #prepend>
              <VIcon color="error">
                mdi-delete
              </VIcon>
            </template>
            <VListItemTitle class="text-error">
              Delete
            </VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
    </VCardActions>
  </VCard>
</template>



<style scoped>
.template-card {
  transition: all 0.2s ease-in-out;
}

.template-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}
</style>
