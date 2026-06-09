# PHP JWT Authentication Backend

A PHP backend for JSON Web Token (JWT) authentication, designed to integrate seamlessly with modern frontends (e.g., Vue.js/Vite) and local dev suites like Laragon.

This backend manages user authentication and role-based permissions strictly through JSON Web Tokens, without using session cookies.

---

## System Architecture & Auth Flow

The backend handles request validation, security checks, and token generation completely statelessly. Below is a flowchart of the authentication and request cycle:

![alt text](<readme_media/Project Interim Progress.png>)

---

##  Backend Directory Structure

-  [backend/](source/JTW_authentication/backend) — The core backend root directory.
  -  [config/](source/JTW_authentication/backend/config)
    -  [database.php](source/JTW_authentication/backend/config/database.php) — Configuration constants and PDO database wrapper.
  -  [helpers/](source/JTW_authentication/backend/helpers)
    -   [cors.php](source/JTW_authentication/backend/helpers/cors.php) — Dynamic CORS configuration & preflight `OPTIONS` responder.
    -   [jwt.php](source/JTW_authentication/backend/helpers/jwt.php) — Custom engine for signing, encoding, decoding, and verifying JSON Web Tokens.
  -   [global_var.php](source/JTW_authentication/backend/global_var.php) — Backend-wide type definitions and enums (e.g. `Membership`).
  -   [register.php](source/JTW_authentication/backend/register.php) — API endpoint to register new user accounts.
  -   [login.php](source/JTW_authentication/backend/login.php) — API endpoint to verify credentials and issue JWTs.
  -   [me.php](source/JTW_authentication/backend/me.php) — API endpoint returning validated profile data for the active user.
  -   [user_update.php](source/JTW_authentication/backend/user_update.php) — API endpoint to modify user details (with role-based access control).

---

## Core Modules & Mechanics

### 1. The Custom JWT Engine ([jwt.php](source/JTW_authentication/backend/helpers/jwt.php))
Rather than relying on third-party libraries, the backend utilizes a native `JWT` helper class implementing **HS256 (HMAC-SHA256)**:
- **Encoding**: Converts headers and payloads into JSON, applies `Base64URL` encoding, hashes the result with `hash_hmac`, and creates the standard three-part token (`header.payload.signature`).
- **Decoding & Validation**: Split-validates incoming tokens. It verifies:
  1. The cryptographic integrity of the signature matching the local server secret.
  2. The token expiration parameter (`exp`) against the current Unix server time.
  3. The optional activation window (`nbf` - Not Before).

### 2. Database Schema & Connection ([database.php](source/JTW_authentication/backend/config/database.php))
- **Singleton PDO**: Utilizes a single, reusable database connection instance throughout each script's execution lifecycle.
- **SQL PreparedStatement**: Prevents SQL injection attacks by strictly decoupling query structure from input values.
- **Schema & Procedures**:
  - The `user` table holds account status, created dates, hashed passwords, and membership details.
  - A deterministic MySQL stored procedure (`update_user`) is utilized to securely update users. See the schema at [schema_sql.sql](source/JTW_authentication/database/schema_sql.sql).

### 3. Role-Based Permissions & Enums ([global_var.php](source/JTW_authentication/backend/global_var.php))
The backend implements dynamic role verification backed by a PHP string-backed Enum:
```php
enum Membership: string {
    case member = "member";
    case admin = "admin";
    case VIP = "VIP";
    case blacklisted = "blacklisted";
}
```
- **Self-Service Restrictions**: Regular users can only update details matching their own authenticated `user_id` inside [user_update.php](source/JTW_authentication/backend/user_update.php).
- **Administrative Privileges**: Only users registered with the `admin` role are permitted to update other users' accounts, elevate roles (e.g. promoting a user to `admin` or `VIP`), or toggling the `member_valid` parameter.

---

## API Endpoint Catalog

### 1. Register Account
* **URL**: `/backend/register.php`
* **Method**: `POST`
* **Request Body**:
  ```json
  {
    "username": "johndoe",
    "email": "johndoe@example.com",
    "password": "securepassword"
  }
  ```
* **Status Codes**: 
  - `201 Created`: Account successfully generated.
  - `400 Bad Request`: Validation failure (empty inputs, password too short, invalid email structure).
  - `409 Conflict`: Username or Email already in use.

### 2. Login & Token Generation
* **URL**: `/backend/login.php`
* **Method**: `POST`
* **Request Body**:
  ```json
  {
    "email": "johndoe@example.com",
    "password": "securepassword"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Login successful!",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "user_id": 1,
      "username": "johndoe",
      "email": "johndoe@example.com",
      "role": "member"
    }
  }
  ```
* **Status Codes**:
  - `200 OK`: Successful authorization.
  - `401 Unauthorized`: Invalid credentials.
  - `403 Forbidden`: Account is blacklisted.

### 3. Fetch Active Profile (Protected)
* **URL**: `/backend/me.php`
* **Method**: `GET`
* **Header**: `Authorization: Bearer <your_jwt_token>`
* **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "user": {
      "user_id": 1,
      "username": "johndoe",
      "email": "johndoe@example.com",
      "role": "member",
      "date_joined": "2026-05-25 12:00:00",
      "member_valid": 1
    }
  }
  ```
* **Status Codes**:
  - `200 OK`: Successful query.
  - `401 Unauthorized`: Missing, expired, or malformed authorization header.
  - `403 Forbidden`: Account has been blacklisted.

### 4. Update Profile (Protected)
* **URL**: `/backend/user_update.php`
* **Method**: `POST`
* **Header**: `Authorization: Bearer <your_jwt_token>`
* **Request Body**:
  ```json
  {
    "userId": "1",
    "username": "johndoe_updated",
    "email": "john.updated@example.com",
    "password": "newpassword123",
    "isMemberValid": "1",
    "role": "member"
  }
  ```
* **Status Codes**:
  - `200 OK`: Profile successfully modified.
  - `400 Bad Request`: Invalid roles, input validation error, or malformed values.
  - `401 Unauthorized`: Invalid token payload.
  - `403 Forbidden`: Attempted modification of another user's details without admin privileges, or self-elevation of privileges (e.g. promoting oneself to `admin`).

---

## Security Summary

| Feature | Implementation | Purpose |
| :--- | :--- | :--- |
| **Password Security** | `bcrypt` (via PHP's `password_hash`) | Ensures secure password hashing on register/update. |
| **Cross-Origin Security** | Dynamic reflection header validation in [cors.php](source/JTW_authentication/backend/helpers/cors.php) | Restricts non-whitelisted cross-site scripting attacks, responds to `OPTIONS` preflight requests. |
| **Database Protection** | Parameterized bindings (Prepared Statements) | Eradicates SQL injection possibilities entirely. |
| **Privilege Escalation Guard** | Strict role assertions before running `CALL update_user()` | Prevents users from upgrading their roles or statuses without authorization. |

---

## Frontend Design System

![alt text](<readme_media/Screenshot 2026-05-26 013627.png>)
### 1. Typography and Font
The application integrates the **Outfit** font family from Google Fonts. It is configured globally to establish a premium and clean aesthetic suited for a fitness brand. Weight specifications include:
- Regular (400): Standard labels, descriptions, and regular text inputs.
- Bold (700): Navigation links, button actions, and input titles.
- Black (900): Core headings, page headers, logo branding, and badge details.

### 2. Color Palette and Theme
The layout employs a high-contrast dark theme centered around fitness and gym aesthetics:
- **Base Background**: Deep dark solid carbon tone (#0B0B0C) and card backgrounds (#111113).
- **Secondary Accents**: Charcoal tones (#1A1A1E and #2D2D32) used for borders, inputs, and structure elements.
- **Neon Volt Accent**: High-intensity volt accent color (#D2FC00) designed for interactive states, call-to-actions, active links, and brand highlights.
- **Alert Tones**: Crimson red (#FF3B30) for warning validations, input validation errors, and invalid border styling.

### 3. Layout Architecture
- **Responsive Flex Containers**: Layout elements align vertically using flex models with clean padding and margin standards.
- **Micro-interactions**: Links and buttons utilize transition transforms and shadows to deliver modern feed-back behaviors when clicked or hovered over.

## Test login with the database
email : developer@example.com
password: developer

normal user
email: user@example.com
password: user