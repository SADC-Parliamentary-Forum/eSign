<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: 'SIMPLE',
    validator: value => ['SIMPLE', 'ADVANCED', 'QUALIFIED'].includes(value),
  },
})

const emit = defineEmits(['update:modelValue'])

const selectedLevel = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const levels = [
  {
    value: 'SIMPLE',
    name: 'Simple Electronic Signature',
    description: 'Email verification only',
    icon: 'mdi-email-check',
    color: 'grey',
    useCases: ['Internal documents', 'Low-risk agreements', 'Basic approvals'],
    verifications: ['Email confirmation'],
    compliance: 'ESIGN Act compliant',
  },
  {
    value: 'ADVANCED',
    name: 'Advanced Electronic Signature',
    description: 'Email + OTP verification',
    icon: 'mdi-shield-check',
    color: 'primary',
    useCases: ['Contracts', 'Business agreements', 'NDAs', 'Employment documents'],
    verifications: ['Email confirmation', 'One-time password (OTP)'],
    compliance: 'eIDAS Article 26 aligned',
  },
  {
    value: 'QUALIFIED',
    name: 'Qualified Electronic Signature',
    description: 'Email + OTP + Device verification',
    icon: 'mdi-shield-star',
    color: 'success',
    useCases: ['Legal documents', 'Financial agreements', 'Government forms', 'High-value contracts'],
    verifications: ['Email confirmation', 'One-time password (OTP)', 'Device fingerprinting', 'IP geolocation'],
    compliance: 'eIDAS Article 28 aligned (equivalent to handwritten)',
  },
]

const showComparison = ref(false)
</script>

<template>
  <div class="signature-level-selector">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon
          icon="mdi-shield-check"
          class="mr-2"
        />
        Signature Assurance Level
        <VSpacer />
        <VBtn
          variant="text"
          size="small"
          @click="showComparison = !showComparison"
        >
          {{ showComparison ? 'Hide' : 'Show' }} Comparison
        </VBtn>
      </VCardTitle>

      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          Higher signature levels provide stronger legal protection through additional identity verification steps.
        </VAlert>

        <VRadioGroup v-model="selectedLevel">
          <VCard
            v-for="level in levels"
            :key="level.value"
            :variant="selectedLevel === level.value ? 'elevated' : 'outlined'"
            :color="selectedLevel === level.value ? level.color : undefined"
            class="mb-3"
          >
            <VCardText>
              <VRadio :value="level.value">
                <template #label>
                  <div class="d-flex align-center w-100">
                    <VIcon
                      :icon="level.icon"
                      :color="level.color"
                      class="mr-2"
                    />
                    <div class="flex-grow-1">
                      <div class="text-subtitle-1 font-weight-bold">
                        {{ level.name }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ level.description }}
                      </div>
                    </div>
                  </div>
                </template>
              </VRadio>

              <VExpandTransition>
                <div
                  v-if="selectedLevel === level.value"
                  class="mt-3 ml-8"
                >
                  <VDivider class="mb-3" />
                  
                  <div class="mb-3">
                    <div class="text-caption text-medium-emphasis mb-1">
                      Required Verifications:
                    </div>
                    <VChip
                      v-for="verification in level.verifications"
                      :key="verification"
                      size="small"
                      class="mr-1 mb-1"
                      :color="level.color"
                      variant="tonal"
                    >
                      {{ verification }}
                    </VChip>
                  </div>

                  <div class="mb-3">
                    <div class="text-caption text-medium-emphasis mb-1">
                      Common Use Cases:
                    </div>
                    <ul class="text-caption">
                      <li
                        v-for="useCase in level.useCases"
                        :key="useCase"
                      >
                        {{ useCase }}
                      </li>
                    </ul>
                  </div>

                  <VAlert
                    type="success"
                    variant="tonal"
                    density="compact"
                  >
                    <div class="text-caption">
                      <VIcon
                        icon="mdi-check-circle"
                        size="small"
                        class="mr-1"
                      />
                      {{ level.compliance }}
                    </div>
                  </VAlert>
                </div>
              </VExpandTransition>
            </VCardText>
          </VCard>
        </VRadioGroup>

        <!-- Comparison Table -->
        <VExpandTransition>
          <VCard
            v-if="showComparison"
            variant="outlined"
            class="mt-4"
          >
            <VCardTitle class="text-subtitle-1">
              Level Comparison
            </VCardTitle>
            <VCardText>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Feature</th>
                    <th class="text-center">
                      SIMPLE
                    </th>
                    <th class="text-center">
                      ADVANCED
                    </th>
                    <th class="text-center">
                      QUALIFIED
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Email Verification</td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-check"
                        color="success"
                      />
                    </td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-check"
                        color="success"
                      />
                    </td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-check"
                        color="success"
                      />
                    </td>
                  </tr>
                  <tr>
                    <td>OTP Verification</td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-close"
                        color="error"
                      />
                    </td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-check"
                        color="success"
                      />
                    </td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-check"
                        color="success"
                      />
                    </td>
                  </tr>
                  <tr>
                    <td>Device Fingerprint</td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-close"
                        color="error"
                      />
                    </td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-close"
                        color="error"
                      />
                    </td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-check"
                        color="success"
                      />
                    </td>
                  </tr>
                  <tr>
                    <td>IP Geolocation</td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-close"
                        color="error"
                      />
                    </td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-close"
                        color="error"
                      />
                    </td>
                    <td class="text-center">
                      <VIcon
                        icon="mdi-check"
                        color="success"
                      />
                    </td>
                  </tr>
                  <tr>
                    <td>Legal Strength</td>
                    <td class="text-center">
                      Basic
                    </td>
                    <td class="text-center">
                      Strong
                    </td>
                    <td class="text-center">
                      Strongest
                    </td>
                  </tr>
                  <tr>
                    <td>Signing Time</td>
                    <td class="text-center">
                      ~1 min
                    </td>
                    <td class="text-center">
                      ~2 min
                    </td>
                    <td class="text-center">
                      ~3 min
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VExpandTransition>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.signature-level-selector {
  max-width: 800px;
}
</style>
