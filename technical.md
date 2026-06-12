# Secure Gym Check-In and Checkout System

We have implemented a complete Secure Gym Check-In system with a frontend simulation dashboard in Vue.js and a secure, OOP-based PHP backend using PDO and JWT.

---

## 1. Architecture Flow Diagram

```mermaid
sequenceDiagram
    autonumber
    actor Member
    participant Frontend as Vue.js App
    participant GenAPI as API: generate.php
    participant DB as MySQL DB
    participant ScanAPI as API: verify.php (Scanner)
    participant OutAPI as API: checkout.php

    Note over Member, GenAPI: CHECK-IN PASS GENERATION
    Member->>Frontend: Click "Generate Check-In Pass"
    Frontend->>GenAPI: POST /api/checkin/generate.php (Bearer token)
    GenAPI->>DB: Fetch user (Ensure gym_status != 'INSIDE')
    GenAPI->>GenAPI: Generate JTI & sign JWT
    GenAPI->>DB: UPDATE users SET active_jti = [jti]
    GenAPI->>Frontend: Return JWT Pass

    Note over Member, ScanAPI: SCANNING TURNSTILE
    Member->>Frontend: Click "Simulate Turnstile Scan"
    Frontend->>ScanAPI: POST /api/checkin/verify.php (payload: token)
    ScanAPI->>ScanAPI: Decode JWT & Verify signature
    ScanAPI->>DB: Fetch user by ID (sub)
    Note over ScanAPI: Check status != 'INSIDE' & active_jti == jti
    ScanAPI->>DB: UPDATE users SET gym_status = 'INSIDE', active_jti = NULL
    ScanAPI->>DB: INSERT INTO check_ins (user_id, check_in_time)
    ScanAPI->>Frontend: Return Success
    Frontend->>Member: Switch layout to INSIDE GYM (Pulsing badge)

    Note over Member, OutAPI: CHECKING OUT
    Member->>Frontend: Click "Check Out of Gym"
    Frontend->>OutAPI: POST /api/checkout.php (Bearer token)
    OutAPI->>DB: Fetch active check_ins where check_out_time IS NULL
    OutAPI->>DB: UPDATE check_ins SET check_out_time = NOW()
    OutAPI->>DB: UPDATE users SET gym_status = 'OUTSIDE', active_jti = NULL
    OutAPI->>Frontend: Return Success
    Frontend->>Member: Reset layout to OUTSIDE GYM
```

---

## 2. File Directory and Created Components

### Backend Endpoints
- **Auth Helper** ([auth.php](file:///c:/laragon/www/JTW_authentication/backend/helpers/auth.php)): Reusable helper that extracts the Bearer token from headers, verifies it with `JWT::decode`, and returns the payload.
- **Generate Pass** ([generate.php](file:///c:/laragon/www/JTW_authentication/backend/api/checkin/generate.php)): Validates session, checks that the user's `gym_status` is `OUTSIDE`, generates a unique `jti`, creates the JWT pass, updates the user's `active_jti` in MySQL, and returns the JWT.
- **Verify Pass** ([verify.php](file:///c:/laragon/www/JTW_authentication/backend/api/checkin/verify.php)): Simulates the scanner. Verifies the token signature, checks that the user's `gym_status` is not already `INSIDE`, checks that `active_jti` matches the token's `jti`, logs the check-in time in `check_ins`, updates the status to `INSIDE`, and invalidates the pass (`active_jti = NULL`).
- **Checkout** ([checkout.php](file:///c:/laragon/www/JTW_authentication/backend/api/checkout.php)): Validates session, updates the active `check_ins` log with the `check_out_time`, and sets `gym_status = 'OUTSIDE'`.

### Frontend Component
- **Check-In Simulator** ([GymCheckInSimulator.vue](file:///c:/laragon/www/JTW_authentication/jwt_project/src/components/GymCheckInSimulator.vue)): A premium card component mimicking a mobile screen presenting a QR code with scanning animation and turnstile trigger.
- **Dashboard Integrations** ([MemberDashboardView.vue](file:///c:/laragon/www/JTW_authentication/jwt_project/src/views/MemberDashboardView.vue) and [AdminDashboardView.vue](file:///c:/laragon/www/JTW_authentication/jwt_project/src/views/AdminDashboardView.vue)): Renders the simulator and displays the real-time `Gym Location Status` in the user profiles.
