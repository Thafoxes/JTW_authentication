import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
    },
    {
      path: '/signup',
      name: 'signup',
      component: () => import('../views/SignupView.vue'),
    },
    {
      path: '/jwt-explorer',
      name: 'jwt-explorer',
      component: () => import('../views/JwtExplorerView.vue'),
    },
    {
      path: '/dashboard',
      redirect: () => {
        const userJson = localStorage.getItem('user')
        if (!userJson) {
          return '/login'
        }
        try {
          const user = JSON.parse(userJson)
          return user.role === 'admin' ? '/dashboard/admin' : '/dashboard/member'
        } catch {
          return '/login'
        }
      }
    },
    {
      path: '/dashboard/member',
      name: 'member-dashboard',
      component: () => import('../views/MemberDashboardView.vue'),
      beforeEnter: (to, from, next) => {
        const token = localStorage.getItem('jwt_token')
        const userJson = localStorage.getItem('user')
        if (!token || !userJson) {
          return next('/login')
        }
        try {
          const user = JSON.parse(userJson)
          if (user.role !== 'member') {
            return next('/dashboard')
          }
          return next()
        } catch {
          return next('/login')
        }
      }
    },
    {
      path: '/dashboard/admin',
      name: 'admin-dashboard',
      component: () => import('../views/AdminDashboardView.vue'),
      beforeEnter: (to, from, next) => {
        const token = localStorage.getItem('jwt_token')
        const userJson = localStorage.getItem('user')
        if (!token || !userJson) {
          return next('/login')
        }
        try {
          const user = JSON.parse(userJson)
          if (user.role !== 'admin') {
            return next('/dashboard')
          }
          return next()
        } catch {
          return next('/login')
        }
      }
    }
  ],
})

export default router
