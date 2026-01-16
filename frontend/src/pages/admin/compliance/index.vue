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
    actions: { retention_days: 3650 }
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
            { id: 2, name: 'Financial Records', conditions: 'Amount > 0', actions: '7 Years', active: true }
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
        active: true
    })
    showRuleDialog.value = false
    newRule.value = { name: '', conditions: {}, actions: { retention_days: 3650 } }
}
</script>

<template>
<div class="h-100 d-flex flex-column">
    <v-toolbar color="surface" elevation="1">
       <v-app-bar-nav-icon @click="$router.push('/')"></v-app-bar-nav-icon>
       <v-toolbar-title>Compliance & Governance</v-toolbar-title>
       <v-spacer></v-spacer>
       <v-tabs v-model="tabs">
          <v-tab value="rules">Rules Engine</v-tab>
          <v-tab value="holds">Legal Holds</v-tab>
       </v-tabs>
    </v-toolbar>

    <div class="flex-grow-1 bg-grey-lighten-4 pa-6">
        <v-window v-model="tabs">
            <!-- Rules Management -->
            <v-window-item value="rules">
                <v-card>
                   <v-toolbar flat density="compact">
                      <v-toolbar-title>Active Retention Rules</v-toolbar-title>
                      <v-spacer></v-spacer>
                      <v-btn color="primary" prepend-icon="mdi-plus" @click="showRuleDialog = true">Add Rule</v-btn>
                   </v-toolbar>
                   <v-data-table :headers="[
                      { title: 'Rule Name', key: 'name' },
                      { title: 'Conditions', key: 'conditions' },
                      { title: 'Actions', key: 'actions' },
                      { title: 'Status', key: 'active' },
                   ]" :items="rules">
                      <template #item.active="{ item }">
                         <v-chip :color="item.active ? 'success' : 'grey'" size="small">
                            {{ item.active ? 'Active' : 'Inactive' }}
                         </v-chip>
                      </template>
                   </v-data-table>
                </v-card>
            </v-window-item>

            <!-- Legal Holds -->
            <v-window-item value="holds">
                <v-card>
                   <v-alert type="info" variant="tonal" class="ma-4">
                      Documents under legal hold are exempt from automated retention policies.
                   </v-alert>
                   
                   <v-data-table :headers="[
                      { title: 'Document', key: 'title' },
                      { title: 'Hold Reason', key: 'legal_hold_reason' },
                      { title: 'Placed By', key: 'user.name' },
                      { title: 'Date', key: 'updated_at' },
                      { title: 'Actions', key: 'actions' },
                   ]" :items="legalHolds">
                      <template #no-data>
                         <div class="pa-4 text-center text-medium-emphasis">No documents under legal hold</div>
                      </template>
                   </v-data-table>
                </v-card>
            </v-window-item>
        </v-window>
    </div>

    <!-- New Rule Dialog -->
    <v-dialog v-model="showRuleDialog" max-width="500">
       <v-card title="Create Compliance Rule">
          <v-card-text>
             <v-text-field v-model="newRule.name" label="Rule Name" />
             <v-text-field v-model="newRule.actions.retention_days" type="number" label="Retention (Days)" />
             <div class="text-caption text-medium-emphasis">Conditions builder coming soon...</div>
          </v-card-text>
          <v-card-actions>
             <v-spacer></v-spacer>
             <v-btn text @click="showRuleDialog = false">Cancel</v-btn>
             <v-btn color="primary" @click="createRule">Save Rule</v-btn>
          </v-card-actions>
       </v-card>
    </v-dialog>
</div>
</template>
