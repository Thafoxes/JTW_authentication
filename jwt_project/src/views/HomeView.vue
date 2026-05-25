<script setup>
// Home page content
</script>

<template>
  <div class="home-container">
    <section class="hero-section">
      <h2 class="sub-title">WELCOME TO THE ZONE</h2>
      <h1 class="main-heading">NO LIMITS. <span class="highlight">NO EXCUSES.</span></h1>
      <p class="promo-text">
        Get access to state-of-the-art equipment, personalized workout plans, and premium amenities 24/7. Log in to manage your membership.
      </p>
      <div class="action-buttons">
        <RouterLink to="/login" class="btn-primary">GET STARTED</RouterLink>
      </div>
    </section>
  </div>
</template>

<style scoped>
.home-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  text-align: center;
  padding: 2rem 1rem;
}

.hero-section {
  max-width: 800px;
}

.sub-title {
  color: #d2fc00;
  font-size: 1.1rem;
  letter-spacing: 0.2em;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.main-heading {
  font-size: 3.5rem;
  font-weight: 900;
  letter-spacing: -0.03em;
  margin: 0 0 1.5rem 0;
  line-height: 1.1;
  text-transform: uppercase;
}

.highlight {
  color: #d2fc00;
  text-shadow: 0 0 15px rgba(210, 252, 0, 0.2);
}

.promo-text {
  font-size: 1.2rem;
  color: #a0a0a5;
  line-height: 1.6;
  margin-bottom: 2rem;
}

.btn-primary {
  display: inline-block;
  background-color: #d2fc00;
  color: #111113;
  padding: 0.85rem 2rem;
  font-size: 1rem;
  font-weight: 700;
  border-radius: 4px;
  text-transform: uppercase;
  transition: all 0.2s ease;
  box-shadow: 0 4px 15px rgba(210, 252, 0, 0.3);
}

.btn-primary:hover {
  background-color: #ffffff;
  color: #111113;
  box-shadow: 0 4px 20px rgba(255, 255, 255, 0.4);
  transform: translateY(-2px);
}
</style>
