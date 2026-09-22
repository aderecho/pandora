import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/buckets',
      name: 'buckets.index',
      component: () => import('@/views/buckets/BucketsIndexView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/buckets/:name',
      name: 'buckets.show',
      component: () => import('@/views/buckets/BucketShowView.vue'),
      meta: { requiresAuth: true },
      props: true,
    },
    {
      path: '/tokens',
      name: 'tokens.index',
      component: () => import('@/views/tokens/TokensIndexView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/users',
      name: 'users.index',
      component: () => import('@/views/users/UsersIndexView.vue'),
      meta: { requiresAuth: true, requiresUserManagement: true },
    },
    {
      path: '/audit-logs',
      name: 'audit-logs.index',
      component: () => import('@/views/audit/AuditLogsView.vue'),
      meta: { requiresAuth: true, requiresAuditRead: true },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.restore()
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresUserManagement && !auth.canManageUsers) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAuditRead && !auth.canReadAudit) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
