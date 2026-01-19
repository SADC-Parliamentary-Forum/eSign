<script setup>
import  { useAIStore } from '@/stores/ai'

const props = defineProps({
  suggestion: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['apply'])

const aiStore = useAIStore()

const apply = () => {
  emit('apply', props.suggestion.template)
}

const getConfidenceColor = confidence => {
  if (confidence >= 90) return 'success'
  if (confidence >= 70) return 'info'
  
  return 'warning'
}

const getConfidenceIcon = strength => {
  switch (strength) {
  case 'STRONG':
    return 'mdi-star'
  case 'MODERATE':
    return 'mdi-star-half-full'
  default:
    return 'mdi-star-outline'
  }
}
</script>

<template>
  <VCard
    variant="outlined"
    class="ai-suggestion-card"
    :class="{ 'strong-match': suggestion.strength === 'STRONG' }"
  >
    <VCardTitle class="d-flex align-center">
      <VIcon
        icon="mdi-lightbulb"
        :color="getConfidenceColor(suggestion.confidence)"
        class="mr-2"
      />
      {{ suggestion.template.name }}
      
      <VSpacer />
      
      <VChip
        :color="getConfidenceColor(suggestion.confidence)"
        :prepend-icon="getConfidenceIcon(suggestion.strength)"
        size="small"
      >
        {{ Math.round(suggestion.confidence) }}%
      </VChip>
    </VCardTitle>

    <VCardSubtitle v-if="suggestion.template.description">
      {{ suggestion.template.description }}
    </VCardSubtitle>

    <VCardText>
      <div class="confidence-bar mb-2">
        <VProgressLinear
          :model-value="suggestion.confidence"
          :color="getConfidenceColor(suggestion.confidence)"
          height="8"
          rounded
        />
      </div>

      <div class="text-caption text-medium-emphasis">
        Match Strength: <strong>{{ suggestion.strength }}</strong>
        <VTooltip location="top">
          <template #activator="{ props: tooltipProps }">
            <VIcon
              v-bind="tooltipProps"
              icon="mdi-information"
              size="x-small"
              class="ml-1"
            />
          </template>
          <div class="text-caption">
            <div v-if="suggestion.strength === 'STRONG'">
              <strong>Strong Match:</strong> Template structure closely matches your document.
              Automatic field mapping recommended.
            </div>
            <div v-else-if="suggestion.strength === 'MODERATE'">
              <strong>Moderate Match:</strong> Template partially matches your document.
              Manual review of field mappings recommended.
            </div>
            <div v-else>
              <strong>Weak Match:</strong> Template may not be suitable for this document.
            </div>
          </div>
        </VTooltip>
      </div>
    </VCardText>

    <VCardActions>
      <VBtn
        variant="outlined"
        @click="$emit('preview', suggestion.template)"
      >
        Preview
      </VBtn>
      
      <VSpacer />
      
      <VBtn
        :color="suggestion.strength === 'STRONG' ? 'primary' : 'default'"
        :variant="suggestion.strength === 'STRONG' ? 'flat' : 'outlined'"
        @click="apply"
      >
        <VIcon start>
          mdi-check
        </VIcon>
        Apply Template
      </VBtn>
    </VCardActions>
  </VCard>
</template>

<style scoped>
.ai-suggestion-card {
  transition: all 0.2s ease-in-out;
}

.ai-suggestion-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.ai-suggestion-card.strong-match {
  border-color: rgb(var(--v-theme-success));
  border-width: 2px;
}
</style>
