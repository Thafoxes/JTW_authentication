<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { authFetch } from '../utils/api.js'

const router = useRouter()
const user = ref(null)
const loading = ref(true)
const error = ref('')

const loadProfile = async () => {
  loading.value = true
  error.value = ''

  try {
    const data = await authFetch('/me.php', { method: 'GET' })
    user.value = data.user
  } catch (err) {
    console.error('Admin profile fetch failed:', err)
    if (err.response?.status === 401 || err.response?.status === 403) {
      localStorage.removeItem('jwt_token')
      localStorage.removeItem('user')
      router.push('/login')
      return
    }
    error.value = err.message || 'Unable to load admin profile.'
  } finally {
    loading.value = false
  }
}

onMounted(loadProfile)
</script>

<template>
  <section class="dashboard-page">
    <div v-if="loading" class="loader-shell">
      <div class="loader"></div>
      <p>Loading admin dashboard...</p>
    </div>

    <div v-else>
      <h1>Admin Dashboard</h1>
      <p class="dashboard-intro">Welcome, <strong>{{ user.username }}</strong>. Use this space to manage member access and view protected admin content.</p>

      <div class="card info-card">
        <h2>Admin Profile</h2>
        <ul>
          <li><strong>Email:</strong> {{ user.email }}</li>
          <li><strong>Role:</strong> {{ user.role }}</li>
          <li><strong>Joined:</strong> {{ user.date_joined }}</li>
          <li><strong>Member valid:</strong> {{ user.member_valid ? 'Yes' : 'No' }}</li>
        </ul>
      </div>

      <div class="card admin-card">
        <h2>Protected Admin Area</h2>
        <p>Only authenticated users with an admin role can see this dashboard. The token is validated on every request before loading protected content.</p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.dashboard-page {
  max-width: 900px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.loader-shell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 3rem 0;
}

.loader {
  width: 50px;
  height: 50px;
  border: 6px solid rgba(210, 252, 0, 0.4);
  border-top-color: #d2fc00;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.dashboard-intro {
  color: #cbd0d8;
}

.card {
  background: #111113;
  border: 1px solid #222225;
  border-radius: 16px;
  padding: 1.75rem;
}

.info-card ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.75rem;
}

.info-card li {
  color: #cbd0d8;
}

.admin-card p {
  color: #cbd0d8;
}

@media (max-width: 720px) {
  .dashboard-page {
    padding: 0 1rem;
  }
}
</style>
