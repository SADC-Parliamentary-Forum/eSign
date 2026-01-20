<script setup>
/**
 * AISuggestionPanel - Inline AI Template Suggestions
 * Shows AI analysis results and template recommendations
 */
import { useAIStore } from '@/stores/ai'
import { useTemplateStore } from '@/stores/templates'

const props = defineProps({
  documentId: {
    type: [String, Number],
    default: null,
  },
  file: {
    type: [File, Object],
    default: null,
  },
  collapsed: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['apply-template', 'skip', 'collapse'])

const aiStore = useAIStore()
const templateStore = useTemplateStore()

const expanded = ref(!props.collapsed)
const analyzing = ref(false)
const error = ref(null)

// Watch for file changes to trigger analysis
watch(() => props.file, async (newFile) => {
  if (newFile) {
    await analyzeDocument()
  }
}, { immediate: true })

const analyzeDocument = async () => {
  if (!props.file) return
  
  analyzing.value = true
  error.value = null
  
  try {
    await aiStore.suggestTemplates(props.file)
  } catch (e) {
    error.value = e.message || 'Failed to analyze document'
    console.error('AI analysis error:', e)
  } finally {
    analyzing.value = false
  }
}

const applyTemplate = async (suggestion) => {
  try {
    await templateStore.fetchTemplate(suggestion.template.id)
    emit('apply-template', suggestion.template)
  } catch (e) {
    error.value = 'Failed to load template'
  }
}

const skipSuggestions = () => {
  aiStore.clearSuggestions()
  emit('skip')
}

const toggleExpanded = () => {
  expanded.value = !expanded.value
  emit('collapse', !expanded.value)
}

const getConfidenceColor = (confidence) => {
  if (confidence >= 90) return 'success'
  if (confidence >= 70) return 'info'
  return 'warning'
}

const getConfidenceLabel = (strength) => {
  switch (strength) {
    case 'STRONG': return 'High Match'
    case 'MODERATE': return 'Good Match'
    default: return 'Possible Match'
  }
}

const getConfidenceDescription = (strength) => {
  switch (strength) {
    case 'STRONG': 
      return 'This template closely matches your document structure. Fields will be auto-positioned accurately.'
    case 'MODERATE': 
      return 'This template partially matches your document. Some field adjustments may be needed.'
    default: 
      return 'This template might work but may require significant modifications.'
  }
}
</script>

<template>
  <VCard 
    class="ai-suggestion-panel"
    :class="{ 'panel-collapsed': !expanded }"
    variant="outlined"
    color="purple-lighten-4"
  >
    <!-- Header -->
    <VCardTitle 
      class="d-flex align-center py-2 px-4 cursor-pointer"
      :class="expanded ? 'bg-purple text-white' : 'bg-purple-lighten-5'"
      @click="toggleExpanded"
    >
      <VAvatar color="purple-lighten-3" size="32" class="mr-3">
        <VIcon icon="mdi-robot" size="18" />
      </VAvatar>
      
      <div class="flex-grow-1">
        <div class="text-body-1 font-weight-bold">
          AI Template Suggestions
        </div>
        <div v-if="!expanded && aiStore.hasSuggestions" class="text-caption">
          {{ aiStore.suggestions.length }} template(s) found
        </div>
      </div>
      
      <VChip 
        v-if="aiStore.hasSuggestions && !expanded" 
        color="success" 
        size="x-small"
        class="mr-2"
      >
        {{ aiStore.suggestions.length }}
      </VChip>
      
      <VBtn 
        :icon="expanded ? 'mdi-chevron-up' : 'mdi-chevron-down'" 
        variant="text"
        size="small"
        :color="expanded ? 'white' : 'default'"
      />
    </VCardTitle>

    <VExpandTransition>
      <div v-show="expanded">
        <VDivider />
        
        <VCardText class="pa-4">
          <!-- Loading State -->
          <div v-if="analyzing" class="text-center py-6">
            <VProgressCircular indeterminate color="purple" size="48" />
            <div class="mt-3 text-body-2 text-medium-emphasis">
              Analyzing your document...
            </div>
            <div class="text-caption text-medium-emphasis">
              Looking for matching templates
            </div>
          </div>

          <!-- Error State -->
          <VAlert v-else-if="error" type="error" variant="tonal" class="mb-0">
            <div class="d-flex align-center justify-space-between">
              <span>{{ error }}</span>
              <VBtn variant="text" size="small" @click="analyzeDocument">
                Retry
              </VBtn>
            </div>
          </VAlert>

          <!-- Suggestions Found -->
          <template v-else-if="aiStore.hasSuggestions">
            <VAlert type="info" variant="tonal" class="mb-4" density="compact">
              <VIcon icon="mdi-lightbulb" class="mr-2" />
              We found templates that match your document. Apply one to auto-configure signature fields.
            </VAlert>

            <div class="suggestions-list">
              <VCard
                v-for="suggestion in aiStore.suggestions.slice(0, 3)"
                :key="suggestion.template.id"
                variant="outlined"
                class="suggestion-card mb-3"
                :class="{ 'strong-match': suggestion.strength === 'STRONG' }"
              >
                <VCardText class="pa-3">
                  <div class="d-flex align-start">
                    <!-- Confidence Indicator -->
                    <div class="confidence-indicator mr-3">
                      <VProgressCircular
                        :model-value="suggestion.confidence"
                        :color="getConfidenceColor(suggestion.confidence)"
                        :size="56"
                        :width="5"
                      >
                        <span class="text-body-2 font-weight-bold">
                          {{ Math.round(suggestion.confidence) }}%
                        </span>
                      </VProgressCircular>
                    </div>

                    <!-- Template Info -->
                    <div class="flex-grow-1">
                      <div class="d-flex align-center mb-1">
                        <span class="text-body-1 font-weight-bold mr-2">
                          {{ suggestion.template.name }}
                        </span>
                        <VChip 
                          :color="getConfidenceColor(suggestion.confidence)" 
                          size="x-small"
                          variant="tonal"
                        >
                          {{ getConfidenceLabel(suggestion.strength) }}
                        </VChip>
                      </div>
                      
                      <div class="text-body-2 text-medium-emphasis mb-2">
                        {{ suggestion.template.description || 'No description' }}
                      </div>
                      
                      <div class="text-caption text-medium-emphasis">
                        <VIcon icon="mdi-information" size="14" class="mr-1" />
                        {{ getConfidenceDescription(suggestion.strength) }}
                      </div>
                    </div>
                  </div>
                </VCardText>
                
                <VDivider />
                
                <VCardActions class="pa-2">
                  <VSpacer />
                  <VBtn
                    :color="suggestion.strength === 'STRONG' ? 'primary' : 'default'"
                    :variant="suggestion.strength === 'STRONG' ? 'flat' : 'outlined'"
                    size="small"
                    @click="applyTemplate(suggestion)"
                  >
                    <VIcon start size="18">mdi-check</VIcon>
                    Apply Template
                  </VBtn>
                </VCardActions>
              </VCard>
            </div>

            <VBtn
              block
              variant="text"
              color="secondary"
              @click="skipSuggestions"
            >
              Skip - Configure Manually
            </VBtn>
          </template>

          <!-- No Suggestions -->
          <div v-else class="text-center py-4">
            <VIcon icon="mdi-file-search" size="48" color="grey-lighten-1" class="mb-3" />
            <div class="text-body-1 font-weight-medium mb-1">
              No matching templates found
            </div>
            <div class="text-body-2 text-medium-emphasis">
              You can manually configure signature fields for this document.
            </div>
          </div>
        </VCardText>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.ai-suggestion-panel {
  border-color: rgb(var(--v-theme-purple)) !important;
  border-width: 2px;
}

.panel-collapsed {
  border-width: 1px;
}

.cursor-pointer {
  cursor: pointer;
}

.suggestion-card {
  transition: all 0.2s ease;
}

.suggestion-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.suggestion-card.strong-match {
  border-color: rgb(var(--v-theme-success));
  border-width: 2px;
}

.confidence-indicator {
  flex-shrink: 0;
}
</style>
