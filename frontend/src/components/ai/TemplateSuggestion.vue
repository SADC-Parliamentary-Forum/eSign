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
  <v-card
    variant="outlined"
    class="ai-suggestion-card"
    :class="{ 'strong-match': suggestion.strength === 'STRONG' }"
  >
    <v-card-title class="d-flex align-center">
      <v-icon
        :icon="'mdi-lightbulb'"
        :color="getConfidenceColor(suggestion.confidence)"
        class="mr-2"
      />
      {{ suggestion.template.name }}
      
      <v-spacer />
      
      <v-chip
        :color="getConfidenceColor(suggestion.confidence)"
        :prepend-icon="getConfidenceIcon(suggestion.strength)"
        size="small"
      >
        {{ Math.round(suggestion.confidence) }}%
      </v-chip>
    </v-card-title>

    <v-card-subtitle v-if="suggestion.template.description">
      {{ suggestion.template.description }}
    </v-card-subtitle>

    <v-card-text>
      <div class="confidence-bar mb-2">
        <v-progress-linear
          :model-value="suggestion.confidence"
          :color="getConfidenceColor(suggestion.confidence)"
          height="8"
          rounded
        />
      </div>

      <div class="text-caption text-medium-emphasis">
        Match Strength: <strong>{{ suggestion.strength }}</strong>
        <v-tooltip location="top">
          <template #activator="{ props: tooltipProps }">
            <v-icon
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
        </v-tooltip>
      </div>
    </v-card-text>

    <v-card-actions>
      <v-btn
        variant="outlined"
        @click="$emit('preview', suggestion.template)"
      >
        Preview
      </v-btn>
      
      <v-spacer />
      
      <v-btn
        :color="suggestion.strength === 'STRONG' ? 'primary' : 'default'"
        :variant="suggestion.strength === 'STRONG' ? 'flat' : 'outlined'"
        @click="apply"
      >
        <v-icon start>
          mdi-check
        </v-icon>
        Apply Template
      </v-btn>
    </v-card-actions>
  </v-card>
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
