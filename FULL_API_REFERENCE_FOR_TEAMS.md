# Falcon Delivery — Full API Reference (PRD-Aligned)

**Base URL:** `http://127.0.0.1:8000/api` *(replace for staging/production)*
**Required Headers (protected routes):**
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer <access_token>
```

---

## 1. Authentication (Public)

### `POST /api/auth/request-otp` — Customer Flow
```json
{ "phone_number": "+212600123456" }
```
**Response:** `{ "message": "OTP sent successfully. Valid for 3 minutes." }`

### `POST /api/auth/verify-otp` — Customer Login / Lazy Signup
```json
{ "phone_number": "+212600123456", "otp": "123456", "tos_accepted": true, "full_name": "Hassan" }
```
> `tos_accepted` is **required only for new numbers**. Ignored for returning users.

**Response:**
```json
{ "access_token": "eyJhbG...", "user": {...}, "is_new_user": true }
```

### `POST /api/auth/login` — Store Owners & Admins
> Store owners do **not** self-register. Admin creates their account (PRD §9.2). They log in here with credentials shared out-of-band.
```json
{ "email": "owner@store.ma", "password": "securepassword" }
```

---

## 2. Profile & Addresses

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/profile` | Get authenticated user profile |
| `PUT` | `/api/profile` | Update name, email |
| `POST` | `/api/profile/upload-avatar` | Upload avatar image |
| `GET` | `/api/profile/addresses` | List saved addresses |
| `POST` | `/api/profile/addresses` | Add `{ label, address, lat, lng }` |
| `DELETE` | `/api/profile/addresses/{id}` | Remove address |
| `GET` | `/api/profile/favorites` | List favorited stores |
| `POST` | `/api/profile/favorites/{storeId}` | Toggle favorite |

---

## 3. Store Browsing (Authenticated)

### `GET /api/categories` — Public, no token
Returns all active categories (Food, Pharmacy, Market, Smoking).

### `GET /api/stores?category_id=1&rating=4`
Paginated list of approved stores.

### `GET /api/stores/nearby?lat=33.5&lng=-7.5`
Proximity-sorted stores. Each object includes:
```json
{
  "id": 1,
  "name": "Pizza House",
  "is_currently_open": true,
  "hours": [...],
  "closures": [...]
}
```

### `GET /api/stores/{id}`
Full store details with products and `is_currently_open`.

---

## 4. Customer Orders

### `POST /api/orders`
Place an order. Courier search begins **immediately** upon placement (PRD §5.5).
```json
{
  "store_id": 1,
  "address_id": 4,
  "items": [{ "id": 12, "qty": 2 }],
  "age_confirmation": true,
  "promo_code": "FALCO2026",
  "customer_note": "Please ring bell 3."
}
```
**Response:** `{ "order_id": 99, "status": "courier_searching", ... }`

### Full Order Status Flow (PRD §5.5)
```
pending → courier_searching → courier_assigned → preparing → ready → picked_up → delivered
```
| Status | Triggered By |
|---|---|
| `courier_searching` | System — immediately after order placement |
| `courier_assigned` | System — courier accepted within 20s window |
| `preparing` | Store Owner — accepted the order |
| `ready` | Store Owner — food is ready for pickup |
| `picked_up` | Courier — collected from store |
| `delivered` | Courier — handed to customer |

### `GET /api/orders/{id}/tracking`
REST fallback. **Primary tracking is via WebSocket channel:** `order.{id}` event: `CourierLocationUpdated`

### `POST /api/orders/{id}/confirm-delivery`
Customer confirms receipt. Only valid when status = `picked_up`.

### `POST /api/orders/{id}/reorder`
Pre-fills cart from past order. Flags unavailable items.

---

## 5. Courier Operations

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/courier/activate` | `{ "activation_code": "ABC-123" }` |
| `PUT` | `/api/courier/status` | `{ "online": true }` |
| `PUT` | `/api/courier/location` | `{ "lat": 33.5, "lng": -7.5 }` — throttled: 12/min |
| `GET` | `/api/courier/orders/available` | REST fallback if FCM missed |
| `POST` | `/api/courier/orders/{id}/accept` | Accept within 20s window |
| `POST` | `/api/courier/orders/{id}/reject` | Reject assignment |
| `POST` | `/api/courier/orders/{id}/pickup` | → `picked_up` |
| `POST` | `/api/courier/orders/{id}/deliver` | → `delivered` |
| `PUT` | `/api/courier/orders/{id}/note` | Add courier note (visible to store) |
| `GET` | `/api/courier/earnings` | Earnings history |

### Courier Note Endpoint
```json
{ "note": "I'm stuck in traffic, arriving in 15 minutes." }
```
Allowed while status is `courier_assigned`, `preparing`, or `ready`.

---

## 6. Store Dashboard (Role: `restaurant_owner`)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/store/dashboard` | Dashboard summary |
| `PUT` | `/api/store/profile` | Update store details |
| `POST` | `/api/store/upload-image` | Upload store logo |
| `GET` | `/api/store/hours` | Get weekly hours |
| `PUT` | `/api/store/hours` | Set weekly schedule |
| `POST` | `/api/store/closures` | Add exceptional closure date |
| `DELETE` | `/api/store/closures/{id}` | Remove closure date |
| `GET` | `/api/store/menu` | List menu items |
| `POST` | `/api/store/menu` | Propose new item (pending admin approval) |
| `PUT` | `/api/store/menu/{id}` | Propose update (pending admin approval) |
| `DELETE` | `/api/store/menu/{id}` | Propose deletion (pending admin approval) |
| `GET` | `/api/store/orders` | Active incoming orders |
| `PUT` | `/api/store/orders/{id}/accept` | Accept → `preparing` |
| `PUT` | `/api/store/orders/{id}/ready` | Mark food ready → `ready` |
| `PUT` | `/api/store/orders/{id}/reject` | Cancel order |
| `PUT` | `/api/store/orders/{id}/note` | Add store note (visible to courier) |
| `GET` | `/api/store/analytics/revenue` | Daily revenue (last 30 days) |
| `GET` | `/api/store/analytics/top-products` | Top 10 best-sellers |
| `GET` | `/api/store/analytics/volume` | Order volume by day/week/month |

### Store Note Endpoint
```json
{ "note": "Order not started yet, please wait 10 minutes." }
```

---

## 7. Admin Panel (Role: `admin`)

### Users
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/admin/users` | List all users |
| `GET` | `/api/admin/users/{id}` | Show user details |
| `PATCH` | `/api/admin/users/{id}/status` | `{ "status": "active|suspended|banned" }` |

### Store Owners & Stores (PRD §9.2 — Admin-controlled creation)
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/admin/store-owners` | Create store owner account |
| `POST` | `/api/admin/stores` | Create store (immediately live, no approval) |
| `GET` | `/api/admin/stores` | List all stores |
| `PATCH` | `/api/admin/stores/{id}/status` | Enable/disable store |

**`POST /api/admin/store-owners`**
```json
{ "name": "Hassan Benali", "email": "hassan@store.ma", "password": "secure123", "phone": "+212600123456" }
```

**`POST /api/admin/stores`**
```json
{ "owner_id": 5, "name": "Pizza House", "category_id": 1, "address": "123 Blvd ...", "lat": 33.57, "lng": -7.58 }
```

### Couriers
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/admin/couriers` | Create courier + generate activation code |
| `GET` | `/api/admin/couriers` | List all couriers |
| `PATCH` | `/api/admin/couriers/{id}/status` | Change courier status |

### Analytics Dashboard (PRD §9.3)
> All endpoints support an optional `?period=` parameter (`week`, `month`, `all`). Default is `month`.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/admin/analytics` | Fixed KPI summary (no period filter) |
| `GET` | `/api/admin/analytics/revenue` | Daily revenue chart (delivered orders only) |
| `GET` | `/api/admin/analytics/orders` | Order volume breakdown by status |
| `GET` | `/api/admin/analytics/stores` | Top 10 stores ranked by revenue |
| `GET` | `/api/admin/analytics/couriers` | Top 10 couriers ranked by completed deliveries |

### Orders, Refunds, Menu Changes
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/admin/orders` | All orders |
| `PATCH` | `/api/admin/orders/{id}/force-status` | Force any order status |
| `GET` | `/api/admin/refunds` | All refund requests |
| `PUT` | `/api/admin/refunds/{id}/approve` | `{ "refund_method": "cash", "admin_note": "..." }` |
| `PUT` | `/api/admin/refunds/{id}/reject` | `{ "admin_note": "..." }` |
| `GET` | `/api/admin/menu-changes` | Pending menu change requests |
| `POST` | `/api/admin/menu-changes/{id}/approve` | Approve and apply change |
| `POST` | `/api/admin/menu-changes/{id}/reject` | `{ "reason": "..." }` |

---

## 8. Refunds (Customer)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/refunds` | List all customer's refund requests |
| `POST` | `/api/refunds` | Submit a refund request |

**`POST /api/refunds`**
```json
{ "order_id": 99, "reason": "Wrong items delivered." }
```
> Only valid when order status = `delivered`. No duplicate requests for same order.

---

## 9. Notifications (PRD §11)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/notifications` | User notifications |
| `PUT` | `/api/notifications/{id}/read` | Mark as read |
| `POST` | `/api/notifications/mark-all-read` | Mark all as read |
| `POST` | `/api/notifications/device-token` | Register FCM token |

**`POST /api/notifications/device-token`**
```json
{ "token": "fcm_device_token_here", "platform": "android" }
```

---

## 10. Promotions & Payments (PRD §10)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/promotions` | List active promo codes |
| `POST` | `/api/promotions/apply` | `{ "code": "WELCOME10", "store_id": 1, "cart_total": 150 }` |
| `POST` | `/api/payment/checkout` | `{ "order_id": 99, "payment_method": "card\|cash" }` |
| `GET` | `/api/payment/history` | Payment history |

---

---

## 11. Reviews & Ratings (PRD §18)

| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/reviews/products` | Review one or more products immediately after delivery | Yes (Customer) |
| `POST` | `/api/reviews/stores` | Create or update a store review (requires past delivery) | Yes (Customer) |
| `GET` | `/api/stores/{id}/reviews` | Paginated list of a store's public reviews | No (Public) |
| `DELETE` | `/api/admin/reviews/{id}` | Admin removes a review | Yes (Admin) |

**`POST /api/reviews/products`**
```json
{
  "order_id": 99,
  "reviews": [
    { "order_item_id": 12, "rating": 5, "comment": "Fresh and delicious!" }
  ]
}
```

**`POST /api/reviews/stores`**
```json
{
  "store_id": 3,
  "rating": 4,
  "comment": "Great food, slightly slow preparation."
}
```

---

## 12. Order Issue Reporting (PRD §19)

| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/reports` | Customer reports an issue with an active/recently delivered order | Yes (Customer) |
| `GET` | `/api/reports` | Customer views their own submitted reports | Yes (Customer) |
| `GET` | `/api/admin/reports` | Admin view of all reports (supports `?filter[status]=open`) | Yes (Admin) |
| `PUT` | `/api/admin/reports/{id}/resolve` | Admin resolves an issue | Yes (Admin) |

**`POST /api/reports`**
```json
{
  "order_id": 99,
  "type": "late_delivery",
  "description": "It has been 45 minutes and the courier has not arrived yet."
}
```
> Supported types: `late_delivery`, `courier_no_show`, `missing_items`, `wrong_items`, `courier_behavior`, `store_issue`, `damaged_product`, `other`.

**`PUT /api/admin/reports/{id}/resolve`**
```json
{
  "admin_response": "We have investigated and issued a warning to the courier.",
  "action_taken": "courier_warned"
}
```

---

## Error Responses

| Status | Meaning |
|---|---|
| `400` | Bad request / business rule violation |
| `401` | Missing or invalid token |
| `403` | Unauthorized for this resource |
| `409` | Conflict (e.g., order already paid) |
| `422` | Validation failed |
| `429` | Rate limited |

**Example 422:**
```json
{ "phone_number": ["The phone number field is required."] }
```
