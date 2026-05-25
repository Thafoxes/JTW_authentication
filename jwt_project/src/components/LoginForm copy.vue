<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const email = ref('')
const password = ref('')
const isLoading = ref(false)

const errors = ref({
  email: '',
  password: '',
  general: ''
})

const handleLogin = async () => {
  // Reset errors
  errors.value.email = ''
  errors.value.password = ''
  errors.value.general = ''

  let hasError = false

  if (!email.value) {
    errors.value.email = 'Email is required'
    hasError = true
  } else if (!/\S+@\S+\.\S+/.test(email.value)) {
    errors.value.email = 'Please enter a valid email address'
    hasError = true
  }

  if (!password.value) {
    errors.value.password = 'Password is required'
    hasError = true
  }

  if (hasError) return

  isLoading.value = true

  try {
    // Dynamic URL detection supporting standard Laragon paths
    const hostname = window.location.hostname;
    const backendUrl = hostname === 'localhost' 
      ? 'http://localhost/JTW_authentication/backend/login.php'
      : '/backend/login.php';

    const response = await fetch(backendUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        email: email.value,
        password: password.value
      })
    })

    const data = await response.json()

    if (response.ok && data.success) {
      // Store token and user data in localStorage
      localStorage.setItem('jwt_token', data.token)
      localStorage.setItem('user', JSON.stringify(data.user))
      
      // Redirect to home page
      router.push('/')
    } else {
      errors.value.general = data.message || 'Login failed. Please try again.'
    }
  } catch (err) {
    errors.value.general = 'Cannot connect to backend server. Verify Laragon is running.'
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
        <h2>MEMBER SIGN IN</h2>
        <p>Enter your training credentials</p>
      </div>

      <form @submit.prevent="handleLogin" class="login-form">
        <!-- General Server Errors -->
        <div v-if="errors.general" class="alert alert-error">
          {{ errors.general }}
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

        <button type="submit" class="btn-submit" :disabled="isLoading">
          <span v-if="isLoading">AUTHENTICATING...</span>
          <span v-else>START WORKOUT (LOG IN)</span>
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

.error-text {
  color: #ff3b30;
  font-size: 0.75rem;
  font-weight: 700;
}

.alert {
  padding: 0.75rem 1rem;
  border-radius: 4px;
  font-size: 0.85rem;
  font-weight: 700;
  text-align: center;
}

.alert-error {
  background-color: rgba(255, 59, 48, 0.1);
  border: 1px solid rgba(255, 59, 48, 0.3);
  color: #ff3b30;
}

.btn-submit {
  background-color: #d2fc00;
  color: #111113;
  border: none;
  padding: 1rem;
  border-radius: 4px;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  letter-spacing: 0.05em;
  transition: all 0.2s ease;
  margin-top: 0.5rem;
  text-transform: uppercase;
}

.btn-submit:hover:not(:disabled) {
  background-color: #ffffff;
  box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
}

.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>