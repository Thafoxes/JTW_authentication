<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { backendBaseUrl } from '../utils/api.js'

// router helper to navigate after signup success
const router = useRouter()

// local form state managed with v-model in the template
const username = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const isLoading = ref(false)

// object used to show inline validation errors and general server errors
const errors = ref({
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
  general: ''
})

// submit handler for the signup form
const handleSignUp = async () => {
  errors.value.username = ''
  errors.value.email = ''
  errors.value.password = ''
  errors.value.confirmPassword = ''
  errors.value.general = ''

  let hasError = false

  if (!username.value) {
    errors.value.username = 'Username is required'
    hasError = true
  }

  if (!email.value) {
    errors.value.email = 'Email is required'
    hasError = true
  } else if (!/\S+@\S+\.\S+/.test(email.value)) {
    errors.value.email = 'Please enter a valid email address'
    hasError = true
  }

  // password length validation ensures basic security before submission
  if (!password.value) {
    errors.value.password = 'Password is required'
    hasError = true
  } else if (password.value.length < 6) {
    errors.value.password = 'Password must be at least 6 characters'
    hasError = true
  }

  if (confirmPassword.value !== password.value) {
    errors.value.confirmPassword = 'Passwords do not match'
    hasError = true
  }

  // stop form submit if any validation error exists
  if (hasError) return

  isLoading.value = true

  try {
    // send the signup payload as JSON to the backend register endpoint
    const response = await fetch(`${backendBaseUrl}/register.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        username: username.value,
        email: email.value,
        password: password.value
      })
    })

    const data = await response.json()

    if (response.ok && data.success) {
      router.push('/login')
    } else {
      errors.value.general = data.message || 'Registration failed. Please try again.'
    }
  } catch (err) {
    errors.value.general = 'Cannot connect to server.'
    console.error(err)
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="login-container">
    <div class="login-card">
      <div class="card-header">
        <span class="card-icon">67</span>
        <h2>MEMBER SIGN UP</h2>
        <p>Create your gym account to access the dashboard</p>
      </div>

      <form @submit.prevent="handleSignUp" class="login-form">
        <div v-if="errors.general" class="alert alert-error">
          {{ errors.general }}
        </div>

        <div class="form-group">
          <label for="username">USERNAME</label>
          <input
            id="username"
            v-model="username"
            placeholder="Your username"
            :class="{ 'input-error': errors.username }"
          />
          <span v-if="errors.username" class="error-text">{{ errors.username }}</span>
        </div>

        <div class="form-group">
          <label for="email">EMAIL ADDRESS</label>
          <input
            type="email"
            id="email"
            v-model="email"
            placeholder="name@example.com"
            :class="{ 'input-error': errors.email }"
          />
          <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
        </div>

        <div class="form-group">
          <label for="password">PASSWORD</label>
          <input
            type="password"
            id="password"
            v-model="password"
            placeholder="••••••••"
            :class="{ 'input-error': errors.password }"
          />
          <span v-if="errors.password" class="error-text">{{ errors.password }}</span>
        </div>

        <div class="form-group">
          <label for="confirmPassword">CONFIRM PASSWORD</label>
          <input
            type="password"
            id="confirmPassword"
            v-model="confirmPassword"
            placeholder="••••••••"
            :class="{ 'input-error': errors.confirmPassword }"
          />
          <span v-if="errors.confirmPassword" class="error-text">{{ errors.confirmPassword }}</span>
        </div>

        <button type="submit" class="btn-submit" :disabled="isLoading">
          <span v-if="isLoading">CREATING ACCOUNT...</span>
          <span v-else>CREATE ACCOUNT</span>
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');

.login-container {
  font-family: 'Outfit', sans-serif;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 70vh;
}

.login-card {
  background-color: #111113;
  border: 1px solid #222225;
  border-radius: 8px;
  padding: 2.5rem;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.card-header {
  text-align: center;
  margin-bottom: 2rem;
}

.card-icon {
  font-size: 2rem;
  color: #d2fc00;
  display: inline-block;
  margin-bottom: 0.5rem;
  filter: drop-shadow(0 0 10px rgba(210, 252, 0, 0.4));
}

.card-header h2 {
  font-size: 1.75rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  color: #ffffff;
  margin: 0 0 0.25rem 0;
  text-transform: uppercase;
}

.card-header p {
  color: #707075;
  font-size: 0.9rem;
  margin: 0;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: #a0a0a5;
}

.form-group input {
  background-color: #1a1a1e;
  border: 1px solid #2d2d32;
  color: #ffffff;
  padding: 0.85rem 1rem;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.95rem;
  transition: all 0.2s ease;
}

.form-group input:focus {
  outline: none;
  border-color: #d2fc00;
  box-shadow: 0 0 0 1px #d2fc00;
}

.form-group input.input-error {
  border-color: #ff3b30;
}

.alert {
  padding: 1rem;
  border-radius: 6px;
  font-size: 0.9rem;
}

.alert-error {
  background-color: #281111;
  border: 1px solid #ff3b30;
  color: #ff7a78;
}

.error-text {
  color: #ff3b30;
  font-size: 0.85rem;
}

.btn-submit {
  background-color: #d2fc00;
  border: none;
  color: #0b0b0c;
  font-weight: 900;
  letter-spacing: 0.08em;
  cursor: pointer;
  padding: 1rem 1.25rem;
  border-radius: 6px;
  text-transform: uppercase;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.btn-submit:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 10px 30px rgba(210, 252, 0, 0.25);
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
