<script setup>
import { ref, computed } from 'vue'
import { authFetch } from '../utils/api.js'

const props = defineProps({
  user: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['status-updated'])

const qrToken = ref(null)
const lastTokenUsed = ref(null)
const errorMsg = ref('')
const successMsg = ref('')
const generateLoading = ref(false)
const scanLoading = ref(false)
const checkoutLoading = ref(false)

const isInside = computed(() => props.user.gym_status === 'INSIDE')

const generatePass = async () => {
  errorMsg.value = ''
  successMsg.value = ''
  generateLoading.value = true
  try {
    const data = await authFetch('/api/checkin/generate.php', { method: 'POST' })
    if (data.success && data.token) {
      qrToken.value = data.token
      lastTokenUsed.value = data.token
    } else {
      errorMsg.value = data.message || 'Failed to generate check-in pass.'
    }
  } catch (err) {
    console.error('Pass generation failed:', err)
    errorMsg.value = err.message || 'Error generating check-in pass.'
  } finally {
    generateLoading.value = false
  }
}

const debugJwt = (token) => {
  if (!token) return
  try {
    const parts = token.split('.')
    if (parts.length !== 3) {
      console.log('❌ Invalid JWT format. Expected 3 dot-separated segments.')
      return
    }

    const [headerB64, payloadB64, signatureB64] = parts

    const decodePart = (b64) => {
      const normalized = b64.replace(/-/g, '+').replace(/_/g, '/')
      return JSON.parse(decodeURIComponent(
        atob(normalized)
          .split('')
          .map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
          .join('')
      ))
    }

    const header = decodePart(headerB64)
    const payload = decodePart(payloadB64)

    console.log('--- 🔍 JWT Validation Simulator Debug ---')
    console.log('1. Raw Pass Token:', token)
    console.log('2. Decoded Header:', header)
    console.log('3. Decoded Payload:', payload)
    console.log('   - Gym Member (sub):', payload.sub)
    console.log('   - Unique Pass ID (jti):', payload.jti)
    console.log('   - Issued At (iat):', new Date(payload.iat * 1000).toLocaleString())
    console.log('   - Expiry Time (exp):', new Date(payload.exp * 1000).toLocaleString())
    
    const timeRemaining = payload.exp - Math.floor(Date.now() / 1000)
    if (timeRemaining > 0) {
      console.log(`✅ Token is active. Expires in ${timeRemaining} seconds.`)
    } else {
      console.log(`❌ Token is EXPIRED by ${Math.abs(timeRemaining)} seconds.`)
    }
    console.log('4. Signature Segment (Base64Url):', signatureB64)
    console.log('-----------------------------------------')
  } catch (e) {
    console.error('Failed to parse and debug JWT:', e)
  }
}

const simulateScan = async () => {
  if (!qrToken.value) return
  debugJwt(qrToken.value)
  errorMsg.value = ''
  successMsg.value = ''
  scanLoading.value = true
  try {
    const data = await authFetch('/api/checkin/verify.php', {
      method: 'POST',
      body: JSON.stringify({ token: qrToken.value })
    })
    if (data.success) {
      successMsg.value = data.message || 'Check-in successful! Turnstile unlocked.'
      qrToken.value = null // Clear token references
      emit('status-updated', 'INSIDE')
    } else {
      errorMsg.value = data.message || 'Check-in failed.'
    }
  } catch (err) {
    console.error('Turnstile scan failed:', err)
    errorMsg.value = err.message || 'Check-in failed.'
  } finally {
    scanLoading.value = false
  }
}

const simulateAbuseScan = async () => {
  if (!lastTokenUsed.value) return
  console.log('🚨 Attempting abuse check-in with previously used token...')
  debugJwt(lastTokenUsed.value)
  errorMsg.value = ''
  successMsg.value = ''
  scanLoading.value = true
  try {
    const data = await authFetch('/api/checkin/verify.php', {
      method: 'POST',
      body: JSON.stringify({ token: lastTokenUsed.value })
    })
    if (data.success) {
      successMsg.value = data.message || 'Abuse check-in unexpectedly succeeded!'
    } else {
      errorMsg.value = data.message || 'Check-in failed.'
    }
  } catch (err) {
    console.error('Abuse check-in failed:', err)
    errorMsg.value = err.message || 'Check-in failed.'
  } finally {
    scanLoading.value = false
  }
}


const checkOut = async () => {
  errorMsg.value = ''
  successMsg.value = ''
  checkoutLoading.value = true
  try {
    const data = await authFetch('/api/checkout.php', { method: 'POST' })
    if (data.success) {
      successMsg.value = data.message || 'Check-out successful. See you next time!'
      qrToken.value = null // Clear references
      lastTokenUsed.value = null
      emit('status-updated', 'OUTSIDE')
    } else {
      errorMsg.value = data.message || 'Check-out failed.'
    }
  } catch (err) {
    console.error('Check-out failed:', err)
    errorMsg.value = err.message || 'Error checking out.'
  } finally {
    checkoutLoading.value = false
  }
}
</script>

<template>
  <div class="card checkin-simulator-card">
    <div class="simulator-header">
      <div class="badge-status-container">
        <span class="pulse-indicator" :class="{ 'status-inside': isInside }"></span>
        <h2 class="simulator-title">Gym Access Simulator</h2>
      </div>
      <span class="status-badge" :class="isInside ? 'badge-inside' : 'badge-outside'">
        {{ isInside ? 'INSIDE GYM' : 'OUTSIDE GYM' }}
      </span>
    </div>

    <!-- Feedback Alerts -->
    <div v-if="errorMsg" class="alert alert-error">
      {{ errorMsg }}
    </div>
    <div v-if="successMsg" class="alert alert-success">
      {{ successMsg }}
    </div>

    <div class="simulator-layout">
      <!-- Left side: Status and actions -->
      <div class="control-panel">
        <p class="panel-desc">
          Generate a secure, single-use check-in pass to access the gym premises. 
          To leave the gym, use the checkout system below.
        </p>

        <div class="action-buttons-group">
          <!-- Generate Check-in pass button -->
          <button 
            @click="generatePass" 
            class="btn-primary" 
            :disabled="isInside || generateLoading"
          >
            <span v-if="generateLoading">GENERATING PASS...</span>
            <span v-else>GENERATE CHECK-IN PASS</span>
          </button>

          <!-- Check-out button -->
          <button 
            v-if="isInside" 
            @click="checkOut" 
            class="btn-action btn-checkout" 
            :disabled="checkoutLoading"
          >
            <span v-if="checkoutLoading">CHECKING OUT...</span>
            <span v-else>CHECK OUT OF GYM</span>
          </button>
        </div>
      </div>

      <!-- Right side: Phone/QR Simulator -->
      <div class="terminal-simulator" v-if="qrToken || isInside">
        <div class="device-frame">
          <div class="device-screen">
            <div class="screen-header">
              <span class="time-mock">09:41</span>
              <span class="sensor-mock"></span>
            </div>

            <!-- Inside gym view -->
            <div v-if="isInside" class="screen-inside">
              <span class="gym-icon">🏋️‍♂️</span>
              <h3>Welcome to the Gym</h3>
              <p class="gym-welcome-sub">Enjoy your training session!</p>
              <div class="session-timer">Active Session</div>
            </div>

            <!-- Pass view -->
            <div v-else-if="qrToken" class="screen-pass">
              <p class="pass-info">Scan pass at the Turnstile Scanner</p>
              <div class="qr-container">
                <img 
                  :src="'https://api.qrserver.com/v1/create-qr-code/?size=160x160&color=210-252-0&bgcolor=17-17-19&data=' + encodeURIComponent(qrToken)" 
                  class="qr-code-img" 
                  alt="Check-in Pass QR Code" 
                />
                <div class="scanner-laser"></div>
              </div>
              <div class="token-value-display" :title="qrToken">
                <code>{{ qrToken }}</code>
              </div>
              <span class="pass-validity">Pass valid for 5 min</span>
            </div>
          </div>
        </div>

        <!-- Simulator Scan Trigger -->
        <button 
          v-if="qrToken" 
          @click="simulateScan" 
          class="btn-scan" 
          :disabled="scanLoading"
        >
          <span v-if="scanLoading">SCANNING...</span>
          <span v-else>SIMULATE TURNSTILE SCAN 🔓</span>
        </button>

        <!-- Abuse Simulation Trigger -->
        <div v-if="isInside && lastTokenUsed" class="abuse-simulator">
          <button 
            @click="simulateAbuseScan" 
            class="btn-scan btn-abuse" 
            :disabled="scanLoading"
          >
            <span v-if="scanLoading">TESTING SCAN...</span>
            <span v-else>TRY CHECK IN AGAIN 🚨</span>
          </button>
          <p class="abuse-caption">Simulate someone trying to abuse this pass a second time</p>
        </div>
      </div>

      <!-- Helper info if idle -->
      <div class="terminal-placeholder" v-else>
        <div class="scanner-target">
          <div class="bracket top-left"></div>
          <div class="bracket top-right"></div>
          <div class="bracket bottom-left"></div>
          <div class="bracket bottom-right"></div>
          <span class="scan-hint">PASS GEN REQUIRED</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.checkin-simulator-card {
  margin-top: 1.5rem;
}

.simulator-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #222225;
  padding-bottom: 1rem;
  margin-bottom: 1.25rem;
}

.badge-status-container {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.simulator-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.pulse-indicator {
  width: 10px;
  height: 10px;
  background-color: #707075;
  border-radius: 50%;
  transition: all 0.3s ease;
}

.pulse-indicator.status-inside {
  background-color: #d2fc00;
  box-shadow: 0 0 10px #d2fc00;
  animation: pulse-glow 1.5s infinite alternate;
}

@keyframes pulse-glow {
  0% { opacity: 0.6; }
  100% { opacity: 1; transform: scale(1.15); }
}

.status-badge {
  font-size: 0.75rem;
  font-weight: 900;
  letter-spacing: 0.05em;
  padding: 0.25rem 0.65rem;
  border-radius: 4px;
  text-transform: uppercase;
}

.badge-inside {
  background-color: rgba(210, 252, 0, 0.15);
  color: #d2fc00;
  border: 1px solid rgba(210, 252, 0, 0.4);
}

.badge-outside {
  background-color: rgba(112, 112, 117, 0.15);
  color: #a0a0a5;
  border: 1px solid rgba(112, 112, 117, 0.3);
}

.simulator-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

@media (max-width: 768px) {
  .simulator-layout {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
}

.control-panel {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.panel-desc {
  color: #a0a0a5;
  font-size: 0.9rem;
  line-height: 1.5;
  margin: 0 0 1.5rem 0;
}

.action-buttons-group {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.btn-primary {
  width: 100%;
  background-color: #d2fc00;
  color: #111113;
  border: none;
  padding: 0.9rem 1.5rem;
  font-weight: 800;
  border-radius: 8px;
  cursor: pointer;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  transition: all 0.2s ease;
}

.btn-primary:hover:not(:disabled) {
  background-color: #ffffff;
}

.btn-primary:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.btn-checkout {
  width: 100%;
  background-color: #ff3b30;
  color: #ffffff;
  border: none;
  padding: 0.9rem 1.5rem;
  font-weight: 800;
  border-radius: 8px;
  cursor: pointer;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  transition: all 0.2s ease;
}

.btn-checkout:hover:not(:disabled) {
  background-color: #ff453a;
  box-shadow: 0 0 12px rgba(255, 59, 48, 0.3);
}

.terminal-simulator {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.device-frame {
  width: 200px;
  height: 320px;
  background-color: #000000;
  border: 4px solid #2d2d32;
  border-radius: 24px;
  padding: 6px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6);
  box-sizing: border-box;
}

.device-screen {
  width: 100%;
  height: 100%;
  background-color: #111113;
  border-radius: 18px;
  border: 1px solid #1a1a1e;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  position: relative;
  box-sizing: border-box;
}

.screen-header {
  height: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 12px;
  font-size: 0.65rem;
  color: #505055;
  font-weight: bold;
}

.sensor-mock {
  width: 40px;
  height: 10px;
  background-color: #000000;
  border-radius: 5px;
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  top: 4px;
}

.screen-pass {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 0.75rem;
  text-align: center;
}

.pass-info {
  font-size: 0.7rem;
  color: #a0a0a5;
  margin: 0 0 0.75rem 0;
  text-transform: uppercase;
  font-weight: 700;
}

.qr-container {
  position: relative;
  width: 130px;
  height: 130px;
  background-color: #171719;
  border: 1px solid #d2fc00;
  border-radius: 8px;
  padding: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  overflow: hidden;
}

.qr-code-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.scanner-laser {
  position: absolute;
  left: 0;
  right: 0;
  height: 2px;
  background-color: #ff3b30;
  box-shadow: 0 0 8px #ff3b30;
  animation: scan-loop 2s linear infinite;
}

@keyframes scan-loop {
  0% { top: 0%; }
  50% { top: 100%; }
  100% { top: 0%; }
}

.pass-validity {
  font-size: 0.65rem;
  color: #d2fc00;
  margin-top: 0.75rem;
  font-weight: bold;
}

.screen-inside {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  text-align: center;
}

.gym-icon {
  font-size: 2.25rem;
  margin-bottom: 0.5rem;
  filter: drop-shadow(0 0 10px rgba(210, 252, 0, 0.3));
}

.screen-inside h3 {
  font-size: 0.9rem;
  color: #ffffff;
  margin: 0 0 0.25rem 0;
  font-weight: 900;
  text-transform: uppercase;
}

.gym-welcome-sub {
  font-size: 0.7rem;
  color: #a0a0a5;
  margin: 0 0 1rem 0;
}

.session-timer {
  background-color: rgba(210, 252, 0, 0.15);
  border: 1px solid #d2fc00;
  color: #d2fc00;
  font-size: 0.65rem;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-weight: bold;
  animation: heartbeat 1.5s infinite ease-in-out;
}

@keyframes heartbeat {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

.btn-scan {
  width: 200px;
  background-color: #1a1a1e;
  border: 1px solid #2d2d32;
  color: #ffffff;
  padding: 0.65rem 1rem;
  font-size: 0.75rem;
  font-weight: 700;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.btn-scan:hover:not(:disabled) {
  background-color: #d2fc00;
  color: #111113;
  border-color: #d2fc00;
  box-shadow: 0 0 10px rgba(210, 252, 0, 0.3);
}

.btn-scan:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.terminal-placeholder {
  display: flex;
  justify-content: center;
  align-items: center;
  border: 2px dashed #222225;
  border-radius: 16px;
  min-height: 250px;
}

.scanner-target {
  position: relative;
  width: 140px;
  height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bracket {
  position: absolute;
  width: 20px;
  height: 20px;
  border-color: #3d3d42;
  border-style: solid;
}

.top-left {
  top: 0;
  left: 0;
  border-width: 3px 0 0 3px;
}

.top-right {
  top: 0;
  right: 0;
  border-width: 3px 3px 0 0;
}

.bottom-left {
  bottom: 0;
  left: 0;
  border-width: 0 0 3px 3px;
}

.bottom-right {
  bottom: 0;
  right: 0;
  border-width: 0 3px 3px 0;
}

.scan-hint {
  font-size: 0.7rem;
  font-weight: 700;
  color: #505055;
  letter-spacing: 0.05em;
}

.alert {
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: bold;
  margin-bottom: 1.25rem;
  text-align: center;
}

.alert-error {
  background-color: rgba(255, 59, 48, 0.1);
  border: 1px solid #ff3b30;
  color: #ffb5b5;
}

.alert-success {
  background-color: rgba(138, 222, 43, 0.1);
  border: 1px solid #8ade2b;
  color: #dff7b8;
}

.token-value-display {
  font-size: 0.52rem;
  color: #707075;
  background-color: #0c0c0e;
  padding: 0.35rem 0.5rem;
  border-radius: 4px;
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-top: 0.6rem;
  border: 1px solid #222225;
  font-family: monospace;
  box-sizing: border-box;
}

.abuse-simulator {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.5rem;
  width: 100%;
}

.btn-abuse {
  border-color: #ff3b30 !important;
  color: #ff3b30 !important;
}

.btn-abuse:hover:not(:disabled) {
  background-color: #ff3b30 !important;
  color: #ffffff !important;
  box-shadow: 0 0 10px rgba(255, 59, 48, 0.4) !important;
}

.abuse-caption {
  font-size: 0.7rem;
  color: #707075;
  text-align: center;
  margin: 0;
  max-width: 200px;
}
</style>
