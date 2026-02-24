# Falcon Delivery API Documentation

**Base API URL:** `/api/`
**Header Requirements:**
- Accept: application/json
- Authorization: Bearer {token} *(for protected routes)*

---

## 1. Authentication (Public Routes)
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth/register` | Register a new user |
| `POST` | `/api/auth/login` | Login and receive JWT token |
| `POST` | `/api/auth/refresh` | Refresh standard JWT token |

---

## 2. Protected User Account & Profile (Requires Auth)
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth/logout` | Invalidate current JWT token |
| `GET` | `/api/profile` | View authenticated user profile details |
| `PUT` | `/api/profile` | Update profile information |
| `POST` | `/api/profile/upload-avatar` | Upload or change avatar image |
| `GET` | `/api/profile/addresses` | Get user saved addresses |
| `POST` | `/api/profile/addresses` | Add a new delivery address |
| `DELETE`| `/api/profile/addresses/{id}`| Delete a saved string address |
| `GET` | `/api/profile/favorites` | View list of favorited restaurants |
| `POST` | `/api/profile/favorites/{id}`| Toggle restaurant favorite status |

---

## 3. Customer Endpoints (Role: `customer`)

### Restaurants & Browsing
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/restaurants` | List all available restaurants |
| `GET` | `/api/restaurants/nearby` | List restaurants proximity sorted (Lat/Lng) |
| `GET` | `/api/restaurants/{id}` | Get details of a single restaurant + menu |
| `GET` | `/api/restaurants/{id}/reviews`| View reviews for a specific restaurant |

### Orders & Tracking
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/orders` | Get current customer's order history |
| `POST` | `/api/orders/create` | Place a standard food order |
| `POST` | `/api/orders/pharmacy` | Place a pharmacy order (triggers WhatsApp redirect) |
| `PUT` | `/api/orders/{id}/cancel` | Cancel a pending order |
| `GET` | `/api/orders/{id}/track` | Track live location of courier for an order |

---

## 4. Courier Operations (Role: `courier`)
| Method | Endpoint | Description |
|---|---|---|
| `PUT` | `/api/courier/status` | Update online/offline status |
| `PUT` | `/api/courier/location` | Update live GPS coordinates (Lat/Lng) |
| `GET` | `/api/courier/orders/history` | View historically completed deliveries |
| `POST` | `/api/courier/orders/{id}/accept`| Accept an incoming delivery request |
| `POST` | `/api/courier/orders/{id}/pickup`| Mark order as picked up from restaurant |
| `POST` | `/api/courier/orders/{id}/deliver`| Mark order as successfully delivered |
| `GET` | `/api/courier/earnings` | View total courier earnings summary |

---

## 5. Restaurant Dashboard (Role: `restaurant_owner`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/restaurant/dashboard` | Main dashboard insights |
| `PUT` | `/api/restaurant/profile` | Update restaurant basic details |
| `PUT` | `/api/restaurant/status` | Open or close the restaurant |
| `GET` | `/api/restaurant/orders` | View incoming active orders |
| `PUT` | `/api/restaurant/orders/{id}/accept`| Accept/Confirm an incoming order |
| `PUT` | `/api/restaurant/orders/{id}/ready` | Mark food as prepared & ready for courier |
| `POST` | `/api/restaurant/menu` | Add a new menu item / dish |
| `PUT` | `/api/restaurant/menu/{id}`| Update menu item details |
| `DELETE`| `/api/restaurant/menu/{id}`| Delete a menu item entirely |
| `GET` | `/api/restaurant/analytics`| View localized restaurant sales analytics |

---

## 6. Admin Panel Operations (Role: `admin`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/admin/users` | List all system users |
| `PUT` | `/api/admin/users/{id}/block`| Block or unblock a specific user |
| `POST` | `/api/admin/couriers` | Directly create a verified courier account |
| `GET` | `/api/admin/restaurants/pending`| View newly registered restaurants awaiting approval|
| `POST` | `/api/admin/restaurants/{id}/approve`| Approve a pending restaurant |
| `GET` | `/api/admin/orders` | System-wide order view |
| `GET` | `/api/admin/analytics` | High-level global system analytics |

---

## 7. Global Services (Wallet, Promotions, Notifications)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/wallet` | View current user wallet balance |
| `POST` | `/api/wallet/top-up` | Add funds to user wallet |
| `GET` | `/api/wallet/history` | View wallet transaction ledger |
| `GET` | `/api/promotions` | List available promotions & coupons |
| `POST` | `/api/promotions/apply` | Validate & apply promo code to cart |
| `GET` | `/api/notifications` | List user in-app notifications |
| `PUT` | `/api/notifications/{id}/read` | Mark specific notification as read |
