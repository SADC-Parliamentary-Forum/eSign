<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  user: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'submit',
])

const roles = ref([])
const formData = ref({
  name: '',
  email: '',
  role_id: null,
  mfa_enabled: false,
  status: 'ACTIVE',
})

const fetchRoles = async () => {
    try {
        const res = await $api('/admin/roles')
        roles.value = res.data || res
    } catch (e) {
        console.error("Failed to fetch roles", e)
    }
}

watch(() => props.user, (newUser) => {
  if (newUser) {
    formData.value = {
      name: newUser.name,
      email: newUser.email,
      role_id: newUser.role?.id || newUser.role_id,
      mfa_enabled: !!newUser.mfa_enabled,
      status: newUser.status || 'ACTIVE',
    }
  }
}, { immediate: true })

onMounted(() => {
    fetchRoles()
})

const onSubmit = () => {
  emit('submit', formData.value)
}

const onCancel = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard title="Edit User">
      <VCardText>
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model="formData.name"
              label="Name"
              readonly
              disabled
            />
          </VCol>
          <VCol cols="12">
            <VTextField
              v-model="formData.email"
              label="Email"
              readonly
              disabled
            />
          </VCol>
          <VCol cols="12">
            <VSelect
              v-model="formData.role_id"
              :items="roles"
              item-title="display_name"
              item-value="id"
              label="Role"
            />
          </VCol>
           <VCol cols="12">
             <VSelect
              v-model="formData.status"
              :items="['ACTIVE', 'INACTIVE']"
              label="Status"
            />
           </VCol>
          <VCol cols="12">
            <VSwitch
              v-model="formData.mfa_enabled"
              label="MFA Enabled"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="tonal"
          @click="onCancel"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          @click="onSubmit"
        >
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
