<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { backendBaseUrl } from '../utils/api.js'
import {
  parseJwt,
  signToken,
  verifyTokenLocally,
  base64UrlEncode
} from '../utils/jwtHelper.js'

// Core state
const encodedToken = ref('')
const secretKey = ref('JTW_test')
const headerJsonString = ref('')
const payloadJsonString = ref('')

// Validation state
const validationStatus = ref(null) // 'valid', 'invalid'
const validationLogs = ref([])
const loading = ref(false)
const secretVisible = ref(false)

// JSON edit indicators
const headerJsonError = ref(false)
const payloadJsonError = ref(false)

// Token segment parsing helper for styling
const tokenSegments = computed(() => {
  const parts = encodedToken.value.split('.')
  return {
    header: parts[0] || '',
    payload: parts[1] || '',
    signature: parts[2] || ''
  }
})

// Syncing flags to avoid infinite updates
let isSyncing = false

// Synchronize changes from raw Encoded token input to Decoded JSON fields
const handleEncodedChanged = () => {
  if (isSyncing) return
  isSyncing = true
  try {
    const trimmed = encodedToken.value.trim()
    if (!trimmed) {
      headerJsonString.value = ''
      payloadJsonString.value = ''
      validationStatus.value = null
      validationLogs.value = []
      isSyncing = false
      return
    }
    const { header, payload } = parseJwt(trimmed)
    headerJsonString.value = JSON.stringify(header, null, 2)
    payloadJsonString.value = JSON.stringify(payload, null, 2)
    validationStatus.value = null
    headerJsonError.value = false
    payloadJsonError.value = false
  } catch (err) {
    validationStatus.value = 'invalid'
    validationLogs.value = [
      { name: 'Format & Structure Check', status: 'fail', msg: err.message }
    ]
  } finally {
    isSyncing = false
  }
}

// Synchronize changes from Decoded JSON editors to Encoded token string
const handleDecodedChanged = async () => {
  if (isSyncing || headerJsonError.value || payloadJsonError.value) return
  isSyncing = true
  try {
    const header = JSON.parse(headerJsonString.value)
    const payload = JSON.parse(payloadJsonString.value)
    const signed = await signToken(header, payload, secretKey.value)
    encodedToken.value = signed
    validationStatus.value = null
  } catch (err) {
    console.error('Signing failed:', err)
  } finally {
    isSyncing = false
  }
}

// Watchers for Decoded fields
watch(headerJsonString, (newVal) => {
  try {
    JSON.parse(newVal)
    headerJsonError.value = false
    handleDecodedChanged()
  } catch {
    headerJsonError.value = true
  }
}, { flush: 'sync' })

watch(payloadJsonString, (newVal) => {
  try {
    JSON.parse(newVal)
    payloadJsonError.value = false
    handleDecodedChanged()
  } catch {
    payloadJsonError.value = true
  }
}, { flush: 'sync' })

// Watcher for Secret key: re-sign token
watch(secretKey, () => {
  handleDecodedChanged()
})

// Action: Load token from local storage if available
const loadSessionToken = () => {
  const token = localStorage.getItem('jwt_token')
  if (token) {
    encodedToken.value = token
    handleEncodedChanged()
    validationLogs.value = [
      { name: 'Session', status: 'pass', msg: 'Active login token successfully imported.' }
    ]
  } else {
    validationLogs.value = [
      { name: 'Session', status: 'warning', msg: 'No active login session. Initialized with dummy token.' }
    ]
    loadDefaultToken()
  }
}

// Action: Generate default sandbox token
const loadDefaultToken = async () => {
  const defaultHeader = { alg: 'HS256', typ: 'JWT' }
  const defaultPayload = {
    user_id: 42,
    username: 'gym_champion',
    email: 'champion@anytimegym.com',
    role: 'member',
    member_valid: 1,
    exp: Math.floor(Date.now() / 1000) + 3600, // 1 hour expiration
    iat: Math.floor(Date.now() / 1000)
  }
  isSyncing = true
  try {
    headerJsonString.value = JSON.stringify(defaultHeader, null, 2)
    payloadJsonString.value = JSON.stringify(defaultPayload, null, 2)
    encodedToken.value = await signToken(defaultHeader, defaultPayload, secretKey.value)
    validationStatus.value = null
    validationLogs.value = []
  } catch (err) {
    console.error(err)
  } finally {
    isSyncing = false
  }
}

// Action: Verify token locally
const runLocalValidation = async () => {
  loading.value = true
  validationLogs.value = []
  try {
    const payload = await verifyTokenLocally(encodedToken.value.trim(), secretKey.value)
    validationStatus.value = 'valid'
    validationLogs.value = [
      { name: 'Structure Verification', status: 'pass', msg: 'Token contains 3 valid Base64 segments.' },
      { name: 'Cryptographic Signature Check', status: 'pass', msg: 'HMAC-SHA256 signature is authentic and verified with the input secret key.' },
      { name: 'Time Integrity (exp/nbf)', status: 'pass', msg: `Token is active and expires at ${new Date(payload.exp * 1000).toLocaleString()}.` }
    ]
  } catch (err) {
    validationStatus.value = 'invalid'
    const msg = err.message
    if (msg.includes('segments') || msg.includes('dots') || msg.includes('decode')) {
      validationLogs.value.push({ name: 'Structure Verification', status: 'fail', msg })
    } else if (msg.includes('signature') || msg.includes('cryptographic') || msg.includes('Cryptographic')) {
      validationLogs.value.push({ name: 'Structure Verification', status: 'pass', msg: 'Segments format correct.' })
      validationLogs.value.push({ name: 'Cryptographic Signature Check', status: 'fail', msg: 'Signature mismatch! Secret key is incorrect or payload was altered.' })
    } else if (msg.includes('expired')) {
      validationLogs.value.push({ name: 'Structure Verification', status: 'pass', msg: 'Segments format correct.' })
      validationLogs.value.push({ name: 'Cryptographic Signature Check', status: 'pass', msg: 'Signature is valid.' })
      validationLogs.value.push({ name: 'Time Integrity (exp/nbf)', status: 'fail', msg: 'Token expired! The exp timestamp resides in the past.' })
    } else {
      validationLogs.value.push({ name: 'Local Verification', status: 'fail', msg })
    }
  } finally {
    loading.value = false
  }
}

// Action: Verify token on server
const runServerValidation = async () => {
  loading.value = true
  validationLogs.value = []
  try {
    const response = await fetch(`${backendBaseUrl}/verify_token.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        token: encodedToken.value.trim(),
        secret: secretKey.value
      })
    })
    
    const data = await response.json()
    
    if (!data.success) {
      throw new Error(data.message || 'Server check rejected.')
    }
    
    if (data.valid) {
      validationStatus.value = 'valid'
      validationLogs.value = [
        { name: 'Server Integrity Verification', status: 'pass', msg: 'Server decoded token parts successfully.' },
        { name: 'Cryptographic Check', status: 'pass', msg: `Signature validated successfully using ${data.verified_with_secret === 'DEFAULT_SERVER_SECRET' ? 'Server Default Secret' : 'Custom Secret'}.` }
      ]
      if (data.payload && data.payload.exp) {
        validationLogs.value.push({ name: 'Server Expiration Check', status: 'pass', msg: `Server verified token exp (${new Date(data.payload.exp * 1000).toLocaleString()}) is valid.` })
      }
    } else {
      validationStatus.value = 'invalid'
      validationLogs.value = [
        { name: 'Server Security Assertion', status: 'fail', msg: data.reason || 'Server-side validation failed.' }
      ]
    }
  } catch (err) {
    validationStatus.value = 'invalid'
    validationLogs.value = [
      { name: 'Server Communication Error', status: 'fail', msg: `Unable to request backend validation: ${err.message}` }
    ]
  } finally {
    loading.value = false
  }
}

// Attack Simulation: Change payload without signing
const simulateTamperAttack = () => {
  try {
    isSyncing = true
    const payload = JSON.parse(payloadJsonString.value)
    payload.role = 'admin' // Elevate privileges
    payloadJsonString.value = JSON.stringify(payload, null, 2)
    
    const base64Payload = base64UrlEncode(JSON.stringify(payload))
    const parts = encodedToken.value.split('.')
    if (parts.length === 3) {
      // Modify payload segment but keep OLD signature
      encodedToken.value = `${parts[0]}.${base64Payload}.${parts[2]}`
      
      validationStatus.value = null
      validationLogs.value = [
        {
          name: 'SECURITY TRIGGER',
          status: 'warning',
          msg: 'ATTACK MODE: Payload upgraded role to "admin" but kept the old member signature. Validate now to check detection.'
        }
      ]
    }
  } catch {
    alert('Please ensure payload is valid JSON before tampering.')
  } finally {
    isSyncing = false
  }
}

// Attack Simulation: Corrupt signature segment
const corruptSignaturePart = () => {
  const parts = encodedToken.value.split('.')
  if (parts.length === 3) {
    let sig = parts[2]
    if (sig.length > 0) {
      // Toggle last character
      const lastChar = sig[sig.length - 1]
      const newChar = lastChar === 'x' ? 'y' : 'x'
      sig = sig.slice(0, -1) + newChar
    }
    encodedToken.value = `${parts[0]}.${parts[1]}.${sig}`
    validationStatus.value = null
    validationLogs.value = [
      {
        name: 'SECURITY TRIGGER',
        status: 'warning',
        msg: 'ATTACK MODE: Token signature modified/corrupted directly. Validate to verify signature check failures.'
      }
    ]
  }
}

onMounted(() => {
  loadDefaultToken()
})
</script>

<template>
  <div class="jwt-explorer-view">
    <div class="view-header">
      <h1 class="view-title">JWT <span class="accent-text">EXPLORER & SANDBOX</span></h1>
      <p class="view-subtitle">Interactive playground to visualize, tamper with, and validate JSON Web Tokens.</p>
    </div>

    <div class="jwt-explorer-layout">
      <!-- Left Navigation Column / Inspector -->
      <aside class="inspector-sidebar">
        <h3 class="panel-section-title">TOKEN INSPECTION</h3>
        
        <!-- Live Color-coded segments -->
        <div class="color-coded-container">
          <label class="label-info">COLOR-CODED ENCODED JWT</label>
          <div class="token-colored">
            <span class="token-part part-header" v-if="tokenSegments.header">{{ tokenSegments.header }}</span>
            <span class="token-dot" v-if="tokenSegments.header">.</span>
            <span class="token-part part-payload" v-if="tokenSegments.payload">{{ tokenSegments.payload }}</span>
            <span class="token-dot" v-if="tokenSegments.payload">.</span>
            <span class="token-part part-signature" v-if="tokenSegments.signature">{{ tokenSegments.signature }}</span>
            <span class="token-placeholder" v-if="!encodedToken">Empty Token</span>
          </div>
          <div class="color-legends">
            <span class="legend header-legend">Header</span>
            <span class="legend payload-legend">Payload</span>
            <span class="legend signature-legend">Signature</span>
          </div>
        </div>

        <!-- Decoded Header Editor -->
        <div class="editor-container header-editor-border">
          <div class="editor-header">
            <span class="editor-title header-text-color">HEADER: ALGORITHM & TOKEN TYPE</span>
            <span v-if="headerJsonError" class="json-error-badge">Invalid JSON</span>
          </div>
          <textarea 
            v-model="headerJsonString" 
            class="editor-textarea" 
            placeholder="Edit header JSON..."
            rows="5"
          ></textarea>
        </div>

        <!-- Decoded Payload Editor -->
        <div class="editor-container payload-editor-border">
          <div class="editor-header">
            <span class="editor-title payload-text-color">PAYLOAD: DATA & TIMESTAMPS</span>
            <span v-if="payloadJsonError" class="json-error-badge">Invalid JSON</span>
          </div>
          <textarea 
            v-model="payloadJsonString" 
            class="editor-textarea" 
            placeholder="Edit payload JSON..."
            rows="10"
          ></textarea>
        </div>
      </aside>

      <!-- Main Controls and Playground -->
      <main class="playground-main">
        <div class="main-card">
          <h2 class="card-title">JWT CONTROLS & TESTER</h2>

          <!-- Raw encoded token text editor -->
          <div class="input-group">
            <div class="input-header">
              <label for="encoded-jwt-input">EDIT ENCODED TOKEN TEXT DIRECTLY</label>
              <button class="btn-text" @click="loadSessionToken">Use Active Login Token</button>
            </div>
            <textarea
              id="encoded-jwt-input"
              v-model="encodedToken"
              @input="handleEncodedChanged"
              class="encoded-textarea"
              placeholder="Paste token here or modify string segments..."
              rows="6"
            ></textarea>
          </div>

          <!-- Secret key selection -->
          <div class="input-group">
            <label for="secret-key-input">VERIFICATION SIGNATURE KEY (SECRET)</label>
            <div class="secret-input-wrapper">
              <input
                id="secret-key-input"
                :type="secretVisible ? 'text' : 'password'"
                v-model="secretKey"
                placeholder="Enter HMAC-SHA256 signature secret key..."
                class="input-text"
              />
              <button @click="secretVisible = !secretVisible" class="btn-toggle-secret" type="button">
                {{ secretVisible ? 'HIDE' : 'SHOW' }}
              </button>
            </div>
            <p class="input-hint">
              Matches default backend key <code>JTW_test</code>. Modifying this recalculates signature unless tampered.
            </p>
          </div>

          <!-- Simulation Triggers (Security Attack) -->
          <div class="simulation-triggers">
            <div class="section-divider">
              <span>SIMULATE SECURITY ATTACKS</span>
            </div>
            <p class="hint-text">
              Try to bypass token authentication structures. Modifying segments manually mimics user-side data alterations.
            </p>
            <div class="action-buttons-grid">
              <button @click="simulateTamperAttack" class="btn-warning btn-tamper">
                🕵️ PRIVILEGE ESCALATION ATTACK
              </button>
              <button @click="corruptSignaturePart" class="btn-warning btn-corrupt">
                💥 CORRUPT SIGNATURE
              </button>
              <button @click="loadDefaultToken" class="btn-secondary">
                🔄 RESET PLAYGROUND
              </button>
            </div>
          </div>

          <!-- Verification buttons -->
          <div class="verification-actions">
            <button @click="runLocalValidation" :disabled="loading" class="btn-primary btn-volt">
              VERIFY CLIENT-SIDE
            </button>
            <button @click="runServerValidation" :disabled="loading" class="btn-primary btn-server">
              VERIFY ON SERVER
            </button>
          </div>

          <!-- Validation Log results -->
          <div class="validation-results-panel" v-if="validationStatus || validationLogs.length">
            <h3 class="panel-section-title">VALIDATION RESULTS</h3>
            
            <div :class="['status-banner', validationStatus]">
              <span class="status-label">STATUS:</span>
              <span class="status-value">{{ validationStatus ? validationStatus.toUpperCase() : 'PENDING' }}</span>
            </div>

            <ul class="logs-list">
              <li v-for="(log, idx) in validationLogs" :key="idx" class="log-item">
                <span :class="['log-icon', log.status]">
                  {{ log.status === 'pass' ? '✔' : log.status === 'fail' ? '❌' : '⚠' }}
                </span>
                <div class="log-details">
                  <strong class="log-name">{{ log.name }}</strong>
                  <p class="log-msg">{{ log.msg }}</p>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <!-- Educational Section -->
        <div class="edu-card">
          <h3>How does JWT protection work?</h3>
          <p>
            A JSON Web Token consists of three base64url-encoded parts separated by dots:
            <span class="header-text-color">Header</span>, 
            <span class="payload-text-color">Payload</span>, and 
            <span class="signature-text-color">Signature</span>.
          </p>
          <ul>
            <li>
              <strong>Integrity</strong>: If you click <em>Privilege Escalation Attack</em>, you modify the role in the payload. However, because you don't know the server's private secret, you cannot sign the token again correctly.
            </li>
            <li>
              <strong>Verification</strong>: When checking validation, the verification engine hashes the header and payload with the secret key. If the result matches the signature block, it proves the payload has not been tampered with.
            </li>
          </ul>
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');

.jwt-explorer-view {
  font-family: 'Outfit', sans-serif;
  color: #f3f3f3;
  margin-bottom: 3rem;
}

.view-header {
  margin-bottom: 2rem;
  text-align: left;
}

.view-title {
  font-size: 2.2rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  margin: 0;
  text-transform: uppercase;
}

.accent-text {
  color: #d2fc00;
  text-shadow: 0 0 15px rgba(210, 252, 0, 0.2);
}

.view-subtitle {
  color: #a0a0a5;
  font-size: 1.05rem;
  margin-top: 0.25rem;
}

.jwt-explorer-layout {
  display: flex;
  gap: 2rem;
  align-items: flex-start;
}

@media (max-width: 1024px) {
  .jwt-explorer-layout {
    flex-direction: column;
  }
  .inspector-sidebar, .playground-main {
    width: 100% !important;
  }
}

/* Sidebar Inspector */
.inspector-sidebar {
  width: 42%;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.panel-section-title {
  font-size: 1.1rem;
  font-weight: 900;
  letter-spacing: 0.05em;
  color: #ffffff;
  margin-bottom: 0.5rem;
  border-left: 3px solid #d2fc00;
  padding-left: 0.75rem;
  text-transform: uppercase;
}

.color-coded-container {
  background-color: #111113;
  border: 1px solid #222225;
  border-radius: 6px;
  padding: 1rem;
}

.label-info {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: #a0a0a5;
  margin-bottom: 0.5rem;
}

.token-colored {
  font-family: 'Courier New', Courier, monospace;
  background-color: #0b0b0c;
  border: 1px solid #1a1a1e;
  border-radius: 4px;
  padding: 1rem;
  font-size: 0.9rem;
  word-break: break-all;
  line-height: 1.5;
  min-height: 100px;
}

.token-part {
  font-weight: 700;
}

.part-header {
  color: #ff6b6b;
}

.part-payload {
  color: #e599f7;
}

.part-signature {
  color: #d2fc00;
}

.token-dot {
  color: #ffffff;
  font-weight: 900;
}

.token-placeholder {
  color: #55555a;
  font-style: italic;
}

.color-legends {
  display: flex;
  gap: 1rem;
  margin-top: 0.75rem;
  font-size: 0.8rem;
  font-weight: 700;
}

.legend {
  padding-left: 0.75rem;
  position: relative;
}

.legend::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.header-legend::before { background-color: #ff6b6b; }
.payload-legend::before { background-color: #e599f7; }
.signature-legend::before { background-color: #d2fc00; }

.header-text-color { color: #ff6b6b; }
.payload-text-color { color: #e599f7; }
.signature-text-color { color: #d2fc00; }

/* Editors */
.editor-container {
  background-color: #111113;
  border: 1px solid #222225;
  border-radius: 6px;
  overflow: hidden;
}

.header-editor-border {
  border-top: 3px solid #ff6b6b;
}

.payload-editor-border {
  border-top: 3px solid #e599f7;
}

.editor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #161619;
  padding: 0.6rem 1rem;
  border-bottom: 1px solid #222225;
}

.editor-title {
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.05em;
}

.json-error-badge {
  background-color: #ff3b30;
  color: #ffffff;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.15rem 0.4rem;
  border-radius: 3px;
}

.editor-textarea {
  width: 100%;
  background-color: #111113;
  color: #f3f3f3;
  border: none;
  font-family: 'Courier New', Courier, monospace;
  font-size: 0.85rem;
  padding: 0.85rem;
  resize: vertical;
  outline: none;
  line-height: 1.4;
}

/* Main Playground */
.playground-main {
  width: 58%;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.main-card {
  background-color: #111113;
  border: 2px solid #222225;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 4px 25px rgba(0,0,0,0.4);
}

.card-title {
  font-size: 1.4rem;
  font-weight: 900;
  letter-spacing: -0.01em;
  margin-top: 0;
  margin-bottom: 1.25rem;
  text-transform: uppercase;
}

.input-group {
  margin-bottom: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.input-group label {
  font-size: 0.85rem;
  font-weight: 700;
  color: #a0a0a5;
  letter-spacing: 0.05em;
}

.input-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.input-header label {
  margin: 0;
}

.btn-text {
  background: none;
  border: none;
  color: #d2fc00;
  font-weight: 700;
  font-size: 0.8rem;
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
  font-family: inherit;
}

.btn-text:hover {
  color: #ffffff;
}

.encoded-textarea {
  width: 100%;
  background-color: #0b0b0c;
  color: #f3f3f3;
  border: 1px solid #2d2d32;
  border-radius: 4px;
  padding: 0.75rem;
  font-family: 'Courier New', Courier, monospace;
  font-size: 0.9rem;
  resize: vertical;
  outline: none;
  line-height: 1.5;
}

.encoded-textarea:focus {
  border-color: #d2fc00;
}

.secret-input-wrapper {
  display: flex;
  gap: 0.5rem;
}

.input-text {
  flex: 1;
  background-color: #0b0b0c;
  color: #f3f3f3;
  border: 1px solid #2d2d32;
  border-radius: 4px;
  padding: 0.65rem 0.75rem;
  font-family: 'Courier New', Courier, monospace;
  font-size: 0.9rem;
  outline: none;
}

.input-text:focus {
  border-color: #d2fc00;
}

.btn-toggle-secret {
  background-color: #1a1a1e;
  border: 1px solid #2d2d32;
  color: #a0a0a5;
  font-weight: 700;
  font-size: 0.75rem;
  padding: 0 1rem;
  border-radius: 4px;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
}

.btn-toggle-secret:hover {
  background-color: #2d2d32;
  color: #ffffff;
}

.input-hint {
  font-size: 0.78rem;
  color: #707075;
  margin: 0;
}

.section-divider {
  display: flex;
  align-items: center;
  text-align: center;
  color: #55555a;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  margin: 1.5rem 0 0.5rem 0;
}

.section-divider::before,
.section-divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px dashed #222225;
}

.section-divider:not(:empty)::before {
  margin-right: .5em;
}

.section-divider:not(:empty)::after {
  margin-left: .5em;
}

.hint-text {
  font-size: 0.82rem;
  color: #a0a0a5;
  margin-top: 0;
  margin-bottom: 0.75rem;
}

.action-buttons-grid {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}

.btn-warning {
  padding: 0.6rem 1rem;
  font-size: 0.8rem;
  font-weight: 700;
  border-radius: 4px;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
}

.btn-tamper {
  background-color: rgba(255, 152, 0, 0.15);
  color: #ff9800;
  border: 1px solid rgba(255, 152, 0, 0.4);
}

.btn-tamper:hover {
  background-color: #ff9800;
  color: #111113;
}

.btn-corrupt {
  background-color: rgba(255, 59, 48, 0.15);
  color: #ff3b30;
  border: 1px solid rgba(255, 59, 48, 0.4);
}

.btn-corrupt:hover {
  background-color: #ff3b30;
  color: #ffffff;
}

.btn-secondary {
  background-color: #1a1a1e;
  border: 1px solid #2d2d32;
  color: #ffffff;
  padding: 0.6rem 1rem;
  font-size: 0.8rem;
  font-weight: 700;
  border-radius: 4px;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background-color: #2d2d32;
}

.verification-actions {
  display: flex;
  gap: 1rem;
  margin-top: 1rem;
}

.btn-primary {
  flex: 1;
  padding: 0.8rem 1.5rem;
  font-size: 0.95rem;
  font-weight: 700;
  border-radius: 4px;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
  text-transform: uppercase;
}

.btn-volt {
  background-color: #d2fc00;
  color: #111113;
  border: 1px solid #d2fc00;
  box-shadow: 0 4px 15px rgba(210, 252, 0, 0.25);
}

.btn-volt:hover:not(:disabled) {
  background-color: #ffffff;
  border-color: #ffffff;
  box-shadow: 0 4px 20px rgba(255,255,255,0.3);
  transform: translateY(-1px);
}

.btn-server {
  background-color: #1a1a1e;
  color: #ffffff;
  border: 1px solid #2d2d32;
}

.btn-server:hover:not(:disabled) {
  background-color: #2d2d32;
  border-color: #d2fc00;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Results panel */
.validation-results-panel {
  margin-top: 1.5rem;
  border-top: 1px solid #222225;
  padding-top: 1.25rem;
}

.status-banner {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  padding: 0.75rem 1rem;
  border-radius: 4px;
  font-weight: 700;
  font-size: 1.05rem;
  margin-bottom: 1rem;
}

.status-banner.valid {
  background-color: rgba(210, 252, 0, 0.15);
  color: #d2fc00;
  border: 1px solid rgba(210, 252, 0, 0.4);
}

.status-banner.invalid {
  background-color: rgba(255, 59, 48, 0.15);
  color: #ff3b30;
  border: 1px solid rgba(255, 59, 48, 0.4);
}

.logs-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.log-item {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  background-color: #161619;
  border: 1px solid #222225;
  border-radius: 4px;
  padding: 0.75rem;
}

.log-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  font-size: 0.65rem;
  font-weight: 700;
  flex-shrink: 0;
  margin-top: 0.15rem;
}

.log-icon.pass {
  background-color: rgba(210, 252, 0, 0.2);
  color: #d2fc00;
}

.log-icon.fail {
  background-color: rgba(255, 59, 48, 0.2);
  color: #ff3b30;
}

.log-icon.warning {
  background-color: rgba(255, 152, 0, 0.2);
  color: #ff9800;
}

.log-details {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.log-name {
  font-size: 0.85rem;
  color: #ffffff;
}

.log-msg {
  font-size: 0.8rem;
  color: #a0a0a5;
  margin: 0;
  line-height: 1.4;
}

/* Educational Panel */
.edu-card {
  background-color: #111113;
  border: 1px dashed #2d2d32;
  border-radius: 6px;
  padding: 1.25rem;
}

.edu-card h3 {
  font-size: 1.1rem;
  font-weight: 700;
  margin-top: 0;
  margin-bottom: 0.5rem;
  color: #d2fc00;
}

.edu-card p {
  font-size: 0.88rem;
  color: #a0a0a5;
  line-height: 1.5;
  margin-bottom: 0.75rem;
}

.edu-card ul {
  padding-left: 1.25rem;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.edu-card li {
  font-size: 0.85rem;
  color: #a0a0a5;
  line-height: 1.4;
}
</style>
