# Falcon Delivery - Full API Reference for Frontend & QA Teams

**Base API URL:** `http://127.0.0.1:8000/api` (replace with staging/production URL)
**Global Headers:**
- `Accept: application/json`
- `Content-Type: application/json`
- `Authorization: Bearer <your_access_token>` (for protected routes)

---

## 1. Authentication (Public Routes)

### `POST /api/auth/request-otp`
**Role:** Public (Customer Flow)
**Description:** Requests an OTP to the user's phone number. Phone-first authentication (lazy signup).
**Request Body:**
```json
{
  "phone": "+212600123456"
}
```
**Response (200 OK):**
```json
{
  "message": "OTP sent successfully."
}
```

### `POST /api/auth/verify-otp`
**Role:** Public (Customer Flow)
**Description:** Verifies the OTP. If this is a new phone number, the account is created (Terms of Service acceptance is implied here). Returns the access token.
**Request Body:**
```json
{
  "phone": "+212600123456",
  "otp": "123456",
  "tos_accepted": true
}
```
**Response (200 OK):**
```json
{
  "access_token": "eyJhbG...",
  "user": {
    "id": 1,
    "phone": "+212600123456",
    "role": "customer",
    "is_activated": true
  }
}
```

### `POST /api/auth/login`
**Role:** Public (Admin / Store Owner Flow)
**Description:** Traditional email/password login for backend operators.
**Request Body:**
```json
{
  "email": "owner@store.com",
  "password": "securepassword"
}
```
**Response (200 OK):**
```json
{
  "access_token": "eyJhbG...",
  "user": {
    "id": 2,
    "email": "owner@store.com",
    "role": "restaurant_owner"
  }
}
```

---

## 2. Profile & Account Management (Requires Token)

### `GET /api/profile`
**Description:** Fetch the logged-in user's profile info.
**Response (200 OK):** `{"id": 1, "name": "John", "phone": "+212600...", "role": "customer"}`

### `PUT /api/profile`
**Description:** Update profile details.
**Request Body:** `{"name": "John Doe", "email": "john@email.com"}`

### `GET /api/profile/addresses`
**Description:** Get customer's saved addresses.
**Response (200 OK):** `[{"id": 1, "label": "Home", "address": "123 Main St", "lat": 33.5, "lng": -7.5}]`

### `POST /api/profile/addresses`
**Description:** Add a new address.
**Request Body:** `{"label": "Work", "address": "456 Office Rd", "lat": 33.6, "lng": -7.6}`

---

## 3. Customer Store & Browsing Endpoints

### `GET /api/categories`
**Role:** Public (No token required)
**Description:** Fetch all top-level store categories (Food, Pharmacy, Market, etc.).

### `GET /api/stores/nearby`
**Role:** Customer
**Description:** Get stores sorted by proximity.
**Query Params:** `?lat=33.5&lng=-7.5&radius=5` (radius in km)
**Response (200 OK):** Array of Store objects with calculated `distance` fields.

### `GET /api/stores/{id}`
**Role:** Customer
**Description:** Get Single Store details along with its menu/products.
**Response (200 OK):** `{"id": 1, "name": "Pizza House", "products": [...]}`

---

## 4. Customer Ordering Endpoints

### `POST /api/orders`
**Role:** Customer
**Description:** Place a new order. For age-restricted items, `age_confirmation` must be true.
**Request Body:**
```json
{
  "store_id": 1,
  "address_id": 4,
  "items": [
    {"product_id": 12, "quantity": 1, "options": []}
  ],
  "age_confirmation": true,
  "promo_code": "FALCO2026"
}
```
**Response (201 Created):** `{"message": "Order placed successfully", "order_id": 99}`

### `GET /api/orders/{id}/tracking`
**Role:** Customer
**Description:** Get real-time courier coordinates and status.
**Response (200 OK):** `{"status": "picked_up", "courier_lat": 33.51, "courier_lng": -7.51}`

### `POST /api/orders/{id}/reorder`
**Role:** Customer
**Description:** Initiates a new cart/checkout with items from a past order.

---

## 5. Courier Endpoints (Role: `courier`)

### `POST /api/courier/activate`
**Description:** Initial activation using SMS code provided by Admin.
**Request Body:** `{"activation_code": "ABC-123"}`

### `PUT /api/courier/status`
**Description:** Toggle online/offline status to receive orders.
**Request Body:** `{"is_online": true}`

### `PUT /api/courier/location`
**Description:** Constantly update GPS location (Throttle: 12 requests / min).
**Request Body:** `{"lat": 33.5, "lng": -7.5}`

### `GET /api/courier/orders/available`
**Description:** Get incoming cascade orders assigned to this courier.

### `POST /api/courier/orders/{id}/accept` (or `/reject`)
**Description:** Accept or reject a delivery assignment within the 20-second window.

### `POST /api/courier/orders/{id}/pickup`
**Description:** Mark the food as picked up from the restaurant.

### `POST /api/courier/orders/{id}/deliver`
**Description:** Mark the order as handed to the customer.

---

## 6. Store Dashboard Endpoints (Role: `restaurant_owner`)

### `GET /api/store/dashboard`
**Description:** Master dashboard stats for the store app (active orders, daily revenue).

### `PUT /api/store/hours`
**Description:** Set standard weekly opening/closing hours.

### `POST /api/store/menu`
**Description:** Propose a new menu item. (Triggers admin-approval workflow; does not instantly publish).

### `GET /api/store/orders`
**Description:** Poll or fetch active pending orders waiting for restaurant acceptance.

### `PUT /api/store/orders/{id}/accept`
**Description:** Accept the incoming order so the kitchen can start preparing.

### `PUT /api/store/orders/{id}/ready`
**Description:** Mark the order as prepared and ready for courier pickup.

---

## 7. Admin Endpoints (Role: `admin`)

Admin routes generally retrieve paginated lists or force mutations. 
- `GET /api/admin/users`: List all users.
- `GET /api/admin/couriers`: List all couriers.
- `GET /api/admin/stores/pending`: Review newly registered stores.
- `POST /api/admin/stores/{id}/approve`: Approve a store layout.
- `GET /api/admin/menu-changes`: Review menu item additions requested by store owners.
- `POST /api/admin/menu-changes/{id}/approve`: Approve menu modifications.

---

## 8. Global Services & Notifications

### `GET /api/notifications`
**Description:** Get paginated user notifications (order updates, promos).

### `POST /api/notifications/device-token`
**Description:** Send Expo/FCM push token to backend for push notifications.
**Request Body:** `{"token": "ExponentPushToken[xxxxxx]"}`

### `POST /api/promotions/apply`
**Description:** Validate promo code before checkout.
**Request Body:** `{"code": "WELCOME10", "store_id": 1, "cart_total": 150.00}`

