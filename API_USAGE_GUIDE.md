# Falcon Delivery - API Implementation Guide

This guide explains how to communicate with the Falcon Delivery API: sending data (POST/PUT) and fetching data (GET).

## 1. Base URL & Headers
All API requests must be made to:
`http://127.0.0.1:8000/api`

### Required Headers
```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <your_access_token>
```

---

## 2. Sending Data (POST/PUT Objects)
When you send data to the server, it must be a valid JSON object.

### Example: User Login
**Endpoint:** `POST /auth/login`

**Request Body:**
```json
{
  "email": "contact.falcodelivery@gmail.com",
  "password": "@falcodelivery@"
}
```

### Example: Placing an Order
**Endpoint:** `POST /orders/create`

**Request Body:**
```json
{
  "restaurant_id": 1,
  "address_id": 5,
  "items": [
    { "id": 101, "qty": 2 },
    { "id": 105, "qty": 1 }
  ]
}
```

---

## 3. Fetching Data (GET Requests)
When you fetch data, the server returns a JSON object or an array of objects.

### Example: Fetching User Profile
**Endpoint:** `GET /profile`

**Success Response (200 OK):**
```json
{
  "id": 1,
  "name": "Falcon Admin",
  "email": "contact.falcodelivery@gmail.com",
  "role": "admin",
  "is_activated": true,
  "created_at": "2026-02-24T18:05:00.000000Z"
}
```

### Example: Fetching Restaurants
**Endpoint:** `GET /restaurants`

**Success Response (200 OK):**
```json
[
  {
    "id": 1,
    "name": "Pizza Palace",
    "cuisine": "Italian",
    "rating": 4.5
  },
  {
    "id": 2,
    "name": "Burger Barn",
    "cuisine": "American",
    "rating": 4.2
  }
]
```

---

## 4. Handling Authentication
After logging in, you will receive an `access_token`. You **MUST** store this token and include it in the header of every subsequent request.

**Step 1: Login**
`POST /auth/login` -> returns `{"access_token": "eyJhbG...", ...}`

**Step 2: Use Token**
Include it in your headers:
`Authorization: Bearer eyJhbG...`

---

## 5. Typical Workflow for Frontend
1. **POST** `/auth/register` (Register User)
2. **POST** `/auth/login` (Get Token)
3. **GET** `/restaurants` (Fetch data to show on screen)
4. **POST** `/profile/addresses` (Send object to save address)
5. **POST** `/orders/create` (Send complex object to place order)
6. **GET** `/notifications` (Fetch updates)

---

## 6. Error Handling
If you send incorrect data, the API will return a **400 Bad Request** or **422 Unprocessable Entity** with details:

**Example Error Response:**
```json
{
  "email": ["The email has already been taken."],
  "password": ["The password field must be at least 6 characters."]
}
```
