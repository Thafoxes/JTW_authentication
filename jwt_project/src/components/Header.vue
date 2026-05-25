<script setup>
import { ref, watch } from 'vue'
import { RouterLink, useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()
const isLoggedIn = ref(false)

const checkLoginStatus = () => {
  isLoggedIn.value = !!localStorage.getItem('jwt_token')
}

// Re-check authentication status on every route navigation
watch(() => route.path, () => {
  checkLoginStatus()
}, { immediate: true })

const logout = () => {
  localStorage.removeItem('jwt_token')
  localStorage.removeItem('user')
  isLoggedIn.value = false
  router.push('/login')
}
</script>

<template>
  <header class="gym-header">
    <div class="header-container">
      <div class="logo-area">
        <span class="logo-icon">67</span>
        <h1 class="logo-text">ANYTIME<span class="accent-text">GYM</span></h1>
      </div>
      
      <nav class="navigation">
        <RouterLink to="/" class="nav-link" active-class="active">HOME</RouterLink>
        <RouterLink v-if="isLoggedIn" to="/dashboard" class="nav-link" active-class="active">DASHBOARD</RouterLink>
        <RouterLink v-if="!isLoggedIn" to="/login" class="nav-link" active-class="active">LOGIN</RouterLink>
        <RouterLink v-if="!isLoggedIn" to="/signup" class="nav-link" active-class="active">SIGN UP</RouterLink>
        <button v-if="isLoggedIn" @click="logout" class="nav-link btn-logout">LOGOUT</button>
      </nav>
    </div>
  </header>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');

.gym-header {
  font-family: 'Outfit', sans-serif;
  background-color: #111113;
  border-bottom: 2px solid #222225;
  padding: 0.75rem 1.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-container {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo-area {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.logo-icon {
  font-size: 1.5rem;
  color: #d2fc00; /* Neon Volt Gym Color */
  filter: drop-shadow(0 0 8px rgba(210, 252, 0, 0.4));
}

.logo-text {
  font-size: 1.6rem;
  font-weight: 900;
  letter-spacing: -0.05em;
  color: #ffffff;
  margin: 0;
  text-transform: uppercase;
}

.accent-text {
  color: #d2fc00;
}

.navigation {
  display: flex;
  gap: 1.25rem;
  align-items: center;
}

.nav-link {
  color: #a0a0a5;
  text-decoration: none;
  font-weight: 700;
  font-size: 0.875rem;
  letter-spacing: 0.05em;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid transparent;
}

.nav-link:hover {
  color: #ffffff;
  background-color: rgba(255, 255, 255, 0.05);
}

.nav-link.active {
  color: #111113;
  background-color: #d2fc00;
  border-color: #d2fc00;
  box-shadow: 0 0 12px rgba(210, 252, 0, 0.3);
}

.btn-logout {
  background: none;
  border: none;
  cursor: pointer;
  font-family: inherit;
}

@media (max-width: 640px) {
  .logo-text {
    font-size: 1.3rem;
  }
  .gym-header {
    padding: 0.75rem 1rem;
  }
}
</style>