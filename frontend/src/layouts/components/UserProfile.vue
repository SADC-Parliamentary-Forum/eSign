<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import avatar1 from '@images/avatars/avatar-1.png'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import NotificationMenu from '@/components/common/NotificationMenu.vue'

const authStore = useAuthStore()
const router = useRouter()

// Debug: Log user data to check for name/role issues
watch(() => authStore.user, (val) => {
  console.log('UserProfile - User Data:', val)
  console.log('UserProfile - Role:', val?.role)
}, { immediate: true })

const userProfileList = [
  { type: 'divider' },
  {
    type: 'navItem',
    icon: 'ri-user-line',
    title: 'My Profile',
    to: '/profile',
  },
  {
    type: 'navItem',
    icon: 'ri-file-list-line',
    title: 'My Documents',
    to: '/documents',
  },
  { type: 'divider' },
]

function handleLogout() {
  authStore.clearAuth()
  router.push('/login')
}
</script>

<template>
  <NotificationMenu class="me-2" />

  <VBadge
    dot
    bordered
    location="bottom right"
    offset-x="2"
    offset-y="2"
    color="success"
    class="user-profile-badge"
  >
    <VAvatar
      class="cursor-pointer"
      size="38"
    >
      <VImg :src="authStore.user?.avatar_url || avatar1" />

      <!-- SECTION Menu -->
      <VMenu
        activator="parent"
        width="230"
        location="bottom end"
        offset="15px"
      >
        <VList>
          <VListItem class="px-4">
            <div class="d-flex gap-x-2 align-center">
              <VAvatar>
                <VImg :src="authStore.user?.avatar_url || avatar1" />
              </VAvatar>

              <div>
                <div class="text-body-2 font-weight-medium text-high-emphasis">
                  {{ authStore.user?.name || 'User' }}
                </div>
                <div class="text-capitalize text-caption text-disabled">
                  {{ authStore.user?.role?.name || authStore.role || 'Member' }}
                </div>
              </div>
            </div>
          </VListItem>

          <PerfectScrollbar :options="{ wheelPropagation: false }">
            <template
              v-for="item in userProfileList"
              :key="item.title"
            >
              <VListItem
                v-if="item.type === 'navItem'"
                :to="item.to"
                class="px-4"
              >
                <template #prepend>
                  <VIcon
                    :icon="item.icon"
                    size="22"
                  />
                </template>

                <VListItemTitle>{{ item.title }}</VListItemTitle>
              </VListItem>

              <VDivider
                v-else
                class="my-1"
              />
            </template>

            <VListItem class="px-4">
              <VBtn
                block
                color="error"
                size="small"
                append-icon="ri-logout-box-r-line"
                @click="handleLogout"
              >
                Logout
              </VBtn>
            </VListItem>
          </PerfectScrollbar>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>

<style lang="scss">
.user-profile-badge {
  &.v-badge--bordered.v-badge--dot .v-badge__badge::after {
    color: rgb(var(--v-theme-background));
  }
}
</style>

<style lang="scss">
.user-profile-badge {
  &.v-badge--bordered.v-badge--dot .v-badge__badge::after {
    color: rgb(var(--v-theme-background));
  }
}
</style>
