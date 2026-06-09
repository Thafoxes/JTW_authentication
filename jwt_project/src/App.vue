<script setup>
import { RouterView } from 'vue-router'
import HeaderNav from './components/Header.vue'
</script>

<template>
  <div class="app-layout">
    <HeaderNav />
    <main class="content-container">
      <RouterView />
    </main>
  </div>
</template>

<style>
/* Global reset and styling */
body {
  margin: 0;
  padding: 0;
  background-color: #0b0b0c;
  color: #f3f3f3;
  min-height: 100vh;
}

.app-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.content-container {
  flex: 1;
  max-width: 1200px;
  width: 100%;
  margin: 0 auto;
  padding: 2rem 1.5rem;
  box-sizing: border-box;
}
</style>
