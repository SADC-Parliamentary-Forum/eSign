import { setupLayouts } from 'virtual:meta-layouts'
import { createRouter, createWebHistory } from 'vue-router/auto'

function recursiveLayouts(route) {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])

    return route
  }

  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
  extendRoutes: pages => [
    ...[...pages].map(route => recursiveLayouts(route)),
  ],
})

// Authentication guard
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  const isPublic = to.meta?.public

  if (!isPublic && !token) {
    // Redirect to login if not authenticated and route is not public
    next('/login')
  } else if (isPublic && token && to.path === '/login') {
    // Redirect to home if already logged in and trying to access login
    next('/')
  } else {
    next()
  }
})

export { router }
export default function (app) {
  app.use(router)
}
