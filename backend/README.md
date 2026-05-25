# PHP JWT Authentication Backend

This is a self-contained, lightweight PHP backend for JWT (JSON Web Token) authentication, designed to run in Laragon and serve a Vue.js frontend.

## Features
- **Pure PHP JWT Helper**: Signs, encodes, and validates JWT payloads with HMAC-SHA256 and expiration support.
- **Security Protocols**: Safe password storing with BCRYPT hashing, input sanitization, dynamic CORS handling, and token authentication.

---

## Directory Structure
- [database.php](file:///c:/laragon/www/JTW_authentication/backend/config/database.php) - Manages PDO database connections.
- [cors.php](file:///c:/laragon/www/JTW_authentication/backend/helpers/cors.php) - Manages CORS policies.
- [jwt.php](file:///c:/laragon/www/JTW_authentication/backend/helpers/jwt.php) - Handles JSON Web Token encode/decode logic.
- [register.php](file:///c:/laragon/www/JTW_authentication/backend/register.php) - Registration route.
- [login.php](file:///c:/laragon/www/JTW_authentication/backend/login.php) - Login route.
- [me.php](file:///c:/laragon/www/JTW_authentication/backend/me.php) - Protected user profile route.

---

## Endpoints

### 1. Register Account
* **URL**: `/backend/register.php`
* **Method**: `POST`
* **Content-Type**: `application/json`
* **Payload**:
```json
{
  "username": "johndoe",
  "email": "johndoe@example.com",
  "password": "securepassword"
}
```
* **Success Response (201 Created)**:
```json
{
  "success": true,
  "message": "Account created successfully! You can now log in."
}
```

### 2. Login
* **URL**: `/backend/login.php`
* **Method**: `POST`
* **Content-Type**: `application/json`
* **Payload**:
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

### 3. Fetch Profile (Protected)
* **URL**: `/backend/me.php`
* **Method**: `GET`
* **Headers**: `Authorization: Bearer <your_jwt_token>`
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

---

## Database Configuration
Configure credentials in [database.php](file:///c:/laragon/www/JTW_authentication/backend/config/database.php):
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jwt_dbms');
define('JWT_SECRET', 'your_super_secret_jwt_key_change_me_in_production_1234567890');
```
