<script setup>
const tabs = ref('rules')
const rules = ref([])
const legalHolds = ref([])
const loading = ref(false)

// Rule Dialog
const showRuleDialog = ref(false)

const newRule = ref({
  name: '',
  conditions: {},
  actions: { retention_days: 3650 },
})

onMounted(() => {
  fetchComplianceData()
})

async function fetchComplianceData() {
  loading.value = true
  try {
    // Mock Data for now until API endpoints exist in Controller
    // rules.value = await $api('/admin/compliance/rules')
    // legalHolds.value = await $api('/admin/compliance/holds')
        
    rules.value = [
      { id: 1, name: 'Standard Retention', conditions: 'All Documents', actions: '10 Years', active: true },
      { id: 2, name: 'Financial Records', conditions: 'Amount > 0', actions: '7 Years', active: true },
    ]
        
    legalHolds.value = [] // Empty for now
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function createRule() {
  // API call mock
  rules.value.push({
    id: Date.now(),
    name: newRule.value.name,
    conditions: JSON.stringify(newRule.value.conditions),
    actions: JSON.stringify(newRule.value.actions),
    active: true,
  })
  showRuleDialog.value = false
  newRule.value = { name: '', conditions: {}, actions: { retention_days: 3650 } }
}
</script>

<template>
  <div class="h-100 d-flex flex-column">
    <VToolbar
      color="surface"
      elevation="1"
    >
      <VAppBarNavIcon @click="$router.push('/')" />
      <VToolbarTitle>Compliance & Governance</VToolbarTitle>
      <VSpacer />
      <VTabs v-model="tabs">
        <VTab value="rules">
          Rules Engine
        </VTab>
        <VTab value="holds">
          Legal Holds
        </VTab>
      </VTabs>
    </VToolbar>

    <div class="flex-grow-1 bg-grey-lighten-4 pa-6">
      <VWindow v-model="tabs">
        <!-- Rules Management -->
        <VWindowItem value="rules">
          <VCard>
            <VToolbar
              flat
              density="compact"
            >
              <VToolbarTitle>Active Retention Rules</VToolbarTitle>
              <VSpacer />
              <VBtn
                color="primary"
                prepend-icon="mdi-plus"
                @click="showRuleDialog = true"
              >
                Add Rule
              </VBtn>
            </VToolbar>
            <VDataTable
              :headers="[
                { title: 'Rule Name', key: 'name' },
                { title: 'Conditions', key: 'conditions' },
                { title: 'Actions', key: 'actions' },
                { title: 'Status', key: 'active' },
              ]"
              :items="rules"
            >
              <template #item.active="{ item }">
                <VChip
                  :color="item.active ? 'success' : 'grey'"
                  size="small"
                >
                  {{ item.active ? 'Active' : 'Inactive' }}
                </VChip>
              </template>
            </VDataTable>
          </VCard>
        </VWindowItem>

        <!-- Legal Holds -->
        <VWindowItem value="holds">
          <VCard>
            <VAlert
              type="info"
              variant="tonal"
              class="ma-4"
            >
              Documents under legal hold are exempt from automated retention policies.
            </VAlert>
                   
            <VDataTable
              :headers="[
                { title: 'Document', key: 'title' },
                { title: 'Hold Reason', key: 'legal_hold_reason' },
                { title: 'Placed By', key: 'user.name' },
                { title: 'Date', key: 'updated_at' },
                { title: 'Actions', key: 'actions' },
              ]"
              :items="legalHolds"
            >
              <template #no-data>
                <div class="pa-4 text-center text-medium-emphasis">
                  No documents under legal hold
                </div>
              </template>
            </VDataTable>
          </VCard>
        </VWindowItem>
      </VWindow>
    </div>

    <!-- New Rule Dialog -->
    <VDialog
      v-model="showRuleDialog"
      max-width="500"
    >
      <VCard title="Create Compliance Rule">
        <VCardText>
          <VTextField
            v-model="newRule.name"
            label="Rule Name"
          />
          <VTextField
            v-model="newRule.actions.retention_days"
            type="number"
            label="Retention (Days)"
          />
          <div class="text-caption text-medium-emphasis">
            Conditions builder coming soon...
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            text
            @click="showRuleDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="createRule"
          >
            Save Rule
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
