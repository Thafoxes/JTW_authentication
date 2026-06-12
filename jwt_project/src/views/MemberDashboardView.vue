<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { authFetch } from '../utils/api.js'
import GymCheckInSimulator from '../components/GymCheckInSimulator.vue'


const router = useRouter()
const user = ref(null)
const loading = ref(true)
const error = ref('')
const updateSuccess = ref('')
const updateLoading = ref(false)
const updateErrors = ref({ username: '', email: '', general: '' })
const form = ref({ username: '', email: '' })

const loadProfile = async () => {
  loading.value = true
  error.value = ''
  try {
    const data = await authFetch('/me.php', { method: 'GET' })
    user.value = data.user
    form.value.username = data.user.username
    form.value.email = data.user.email
  } catch (err) {
    console.error('Profile load failed:', err)
    if (err.response?.status === 401 || err.response?.status === 403) {
      localStorage.removeItem('jwt_token')
      localStorage.removeItem('user')
      router.push('/login')
      return
    }
    error.value = err.message || 'Unable to load profile information.'
  } finally {
    loading.value = false
  }
}

const submitUpdate = async () => {
  updateErrors.value.username = ''
  updateErrors.value.email = ''
  updateErrors.value.general = ''
  updateSuccess.value = ''

  if (!form.value.username) {
    updateErrors.value.username = 'Username is required.'
  }
  if (!form.value.email) {
    updateErrors.value.email = 'Email is required.'
  } else if (!/\S+@\S+\.\S+/.test(form.value.email)) {
    updateErrors.value.email = 'Please enter a valid email address.'
  }

  if (updateErrors.value.username || updateErrors.value.email) {
    return
  }

  updateLoading.value = true

  try {
    await authFetch('/user_update.php', {
      method: 'POST',
      body: JSON.stringify({
        userId: user.value.user_id,
        username: form.value.username,
        email: form.value.email,
        isMemberValid: user.value.member_valid,
        role: user.value.role
      })
    })

    updateSuccess.value = 'Profile updated successfully.'
    user.value.username = form.value.username
    user.value.email = form.value.email
    localStorage.setItem('user', JSON.stringify(user.value))
  } catch (err) {
    console.error('Profile update failed:', err)
    updateErrors.value.general = err.message || 'Failed to update profile.'
  } finally {
    updateLoading.value = false
  }
}

onMounted(loadProfile)
</script>

<template>
  <section class="dashboard-page">
    <div v-if="loading" class="loader-shell">
      <div class="loader"></div>
      <p>Loading your dashboard...</p>
    </div>

    <div v-else>
      <h1>Member Dashboard</h1>
      <p class="dashboard-intro">Welcome back, <strong>{{ user.username }}</strong>. This area is reserved for active members.</p>

      <div class="card info-card">
        <h2>Account Summary</h2>
        <ul>
          <li><strong>Email:</strong> {{ user.email }}</li>
          <li><strong>Role:</strong> {{ user.role }}</li>
          <li><strong>Member valid:</strong> {{ user.member_valid ? 'Yes' : 'No' }}</li>
          <li><strong>Joined:</strong> {{ user.date_joined }}</li>
          <li><strong>Gym Location Status:</strong> <span class="status-value">{{ user.gym_status || 'OUTSIDE' }}</span></li>
        </ul>
      </div>

      <GymCheckInSimulator :user="user" @status-updated="(status) => user.gym_status = status" />


      <div class="card update-card">
        <h2>Update Your Profile</h2>

        <div v-if="updateErrors.general" class="alert alert-error">
          {{ updateErrors.general }}
        </div>
        <div v-if="updateSuccess" class="alert alert-success">
          {{ updateSuccess }}
        </div>

        <form @submit.prevent="submitUpdate" class="form-grid">
          <label>
            Username
            <input v-model="form.username" type="text" />
            <span v-if="updateErrors.username" class="field-error">{{ updateErrors.username }}</span>
          </label>

          <label>
            Email
            <input v-model="form.email" type="email" />
            <span v-if="updateErrors.email" class="field-error">{{ updateErrors.email }}</span>
          </label>

          <button type="submit" class="btn-action" :disabled="updateLoading">
            <span v-if="updateLoading">Saving...</span>
            <span v-else>Save Profile</span>
          </button>
        </form>
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

.form-grid {
  display: grid;
  gap: 1rem;
}

.form-grid label {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  color: #cbd0d8;
}

.form-grid input {
  background: #121215;
  border: 1px solid #2d2d32;
  color: #fff;
  padding: 0.9rem 1rem;
  border-radius: 8px;
}

.field-error {
  color: #ff3b30;
  font-size: 0.85rem;
}

.btn-action {
  width: fit-content;
  background: #d2fc00;
  color: #111113;
  border: none;
  padding: 0.95rem 1.5rem;
  font-weight: 800;
  border-radius: 999px;
  cursor: pointer;
}

.alert {
  padding: 1rem;
  border-radius: 10px;
  margin-bottom: 1rem;
}

.alert-error {
  background: #2f1212;
  color: #ffb5b5;
  border: 1px solid #ff3b30;
}

.alert-success {
  background: #17280f;
  color: #dff7b8;
  border: 1px solid #8ade2b;
}

@media (max-width: 720px) {
  .dashboard-page {
    padding: 0 1rem;
  }
}
</style>
