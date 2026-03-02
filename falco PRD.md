========================================================
FALCO DELIVERY
Product Requirements Document — Version 2.0
March 2026 — Laâyoune, Maroc
========================================================
Stack: Laravel + PostgreSQL + PostGIS + Redis + WebSockets + FCM + Flutter
========================================================


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CHANGES SUMMARY — v1.0 → v2.0
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section   Change                                              Type
--------  --------------------------------------------------  -------
§3.4      ToS enforcement — two layers, verify step           Updated
§5.1      Order lifecycle — store verification added          Updated
§5.6      Order notes — 3-level (customer/store/courier)      New
§5.7      Store verification flow before courier dispatch     New
§8.5      Courier assignment — Push + REST fallback           New
§8.6      Delivery confirmation + prohibited methods          New
§9.2      Store creation — admin only, no approval flow       Updated
§9.3      Admin analytics — 4 endpoints + period filter       New
§10.2     Refund endpoints — FCM + SMS on resolution          Updated
§10.3     Promo codes — category eligibility                  New
§11.3     Flutter + FCM — mobile framework decision           New
§18       Reviews — product at delivery + store anytime       New
§19       Issue reporting — 7 categories, admin resolution    New


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. VISION & OBJECTIVES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Falco Delivery is a Moroccan on-demand multi-service delivery platform
inspired by Glovo, designed specifically for the Moroccan market and
user behavior. The core philosophy minimizes friction, avoids forced
account creation at startup, and uses phone number as the primary identity.

1.1 Key Objectives
------------------
  - Phone-first authentication — no email required
  - Lazy account creation — registration only at checkout
  - Multi-category marketplace: Food, Pharmacy, Market, Smoking
  - Age-restricted product compliance (Glovo model)
  - Moroccan legal compliance — SMS, ToS, age confirmation logging
  - Scalable monolith architecture for Phase 1
  - Store verification before courier dispatch — reduces fake orders

1.2 Supported Categories
-------------------------
  Category    Description                    Age Restricted
  ----------  -----------------------------  --------------
  Food        Restaurants and food delivery  No
  Pharmacy    Medicines and health products  Some items
  Market      Groceries and daily goods      No
  Smoking     Tobacco and related products   Yes — all items


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
2. USER ROLES & PERMISSIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Role          How Created                        Auth Method
  ------------  ---------------------------------  -------------------
  Customer      Lazy signup — OTP at first order   OTP only
  Store Owner   Admin creates account              Email + password
  Courier       Admin creates + activation code    Activation code
  Admin         Pre-existing                       Email + password


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3. AUTHENTICATION SYSTEM — PHONE-FIRST / LAZY SIGNUP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

3.1 Core Principles
-------------------
  - No registration screen on app launch
  - User browses freely as anonymous
  - Registration triggered only at first checkout
  - Phone number is the primary identity key
  - Email is optional (nullable)
  - No password system for customers — OTP only

3.2 OTP Registration Flow
--------------------------
  1. User reaches checkout for the first time
  2. Flutter shows phone input + ToS checkbox
  3. User checks ToS box → "Send OTP" button becomes active
  4. POST /api/auth/request-otp — OTP stored in Redis (TTL 5min)
  5. SMS sent to user phone
  6. User enters 6-digit code → POST /api/auth/verify-otp
  7. Backend validates, creates user if new, returns Sanctum token
  8. ToS acceptance logged to tos_acceptances table
  9. Checkout resumes automatically

3.3 OTP Security Parameters
-----------------------------
  Parameter         Value
  ----------------  -------------------------------------
  OTP length        6 digits
  TTL               5 minutes (Redis)
  Max attempts      3 per 10 minutes per phone
  Rate limit key    throttle:otp:{phone} (Redis)
  Storage           Redis only — not in DB until verified
  Invalidation      Immediately after successful use

  ❌ DO NOT store OTP in the database before verification
  ❌ DO NOT allow more than 3 attempts per 10 minutes
  ❌ DO NOT reuse OTP after successful verification

3.4 ToS Acceptance — Enforcement Rules
----------------------------------------
ToS acceptance required only on first registration (new phone number).
Returning users not prompted again unless ToS version changes.

Two-Layer Enforcement:

  Layer 1 — Flutter (UI):
    "Send OTP" button disabled until ToS checkbox is checked.
    Cannot be bypassed in the UI.

  Layer 2 — Backend (Laravel):
    Validates tos_accepted: true at verify-otp step only.
    This is the legally binding moment tied to a verified phone.

  Scenario              tos_accepted Required?   Action
  --------------------  -----------------------  ------------------------
  New phone number      Yes — must be true        Create account + log ToS
  Existing phone        No — field ignored        Return token only

  ❌ DO NOT validate ToS at request-otp step — phone not yet verified
  ❌ DO NOT skip ToS logging for new users — required for Moroccan compliance
  ❌ DO NOT allow new account creation without tos_accepted: true

  tos_acceptances table logs: user_id, tos_version, ip_address, accepted_at


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
4. DATABASE SCHEMA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

4.1 Core Tables
----------------
  users             id, phone (UNIQUE), email (nullable), name, role, is_blocked
  addresses         id, user_id, label, address, lat, lng, is_default
  tos_acceptances   id, user_id, tos_version, ip_address, accepted_at
  phone_otps        id, phone, otp_hash, expires_at, used_at, attempts

4.2 Categories & Stores
------------------------
  categories      id, name, slug, icon, is_active
  stores          id, owner_id, category_id, name, coords (geography Point), is_active
  store_hours     id, store_id, day_of_week, open_time, close_time
  store_closures  id, store_id, closure_date, reason

4.3 Products
-------------
  products              id, store_id, name, price, is_available, age_restricted, deleted_at
  menu_change_requests  id, store_id, product_id, type, requested_data (jsonb), status, admin_note

4.4 Orders
-----------
  orders
    id, user_id, store_id, courier_id, status (enum), total, delivery_fee,
    delivery_distance_km, age_confirmation, age_confirmation_at,
    promo_code_id, customer_note, store_note, courier_note

  order_items
    id, order_id, product_id, quantity, unit_price, subtotal

  order_assignment_attempts
    id, order_id, courier_id, status (sent/accepted/rejected/timeout),
    sent_at, responded_at

  ❌ DO NOT use wallet or balance fields — no wallet system in Phase 1
  ❌ DO NOT remove note fields — required by PRD §5.6

4.5 Couriers
-------------
  couriers               id, name, phone (UNIQUE), is_activated, is_online,
                         current_location (geography Point), activation_code
  courier_monthly_stats  id, courier_id, month, deliveries_count, total_distance_km
  courier_earnings       id, courier_id, order_id, amount, earned_at

4.6 Payments & Promo Codes
---------------------------
  payments        id, order_id, amount, method, status, paid_at
  promo_codes     id, code (UNIQUE), discount_type, discount_value,
                  eligible_categories (jsonb), min_order_amount,
                  max_uses, expires_at, is_active
  promo_code_uses id, promo_code_id, user_id, order_id, used_at

4.7 Refunds, Reviews, Reports & Favorites
------------------------------------------
  refund_requests  id, order_id, user_id, reason, status, refund_method,
                   admin_note, resolved_at
  reviews          id, user_id, store_id (nullable), order_item_id (nullable),
                   order_id, type (store/product), rating (1-5), comment, deleted_at
  user_favorites   id, user_id, store_id
  order_reports    id, order_id, user_id, type (enum), description,
                   status (open/resolved), admin_response, action_taken, resolved_at

4.8 System & Logging Tables
-----------------------------
  settings      id, key (UNIQUE), value, type
  sms_logs      id, phone, type, provider, status, provider_message_id, sent_at
  audit_logs    id, user_id, action, model, model_id, changes (jsonb), created_at
  device_tokens id, user_id, token, platform (android/ios), updated_at

  ❌ DO NOT delete sms_logs — required for ANRT compliance
  ❌ DO NOT delete audit_logs — required for dispute handling


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
5. ORDER FLOW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

5.1 Complete Order Status Sequence
------------------------------------
  Status              Triggered By   Description
  ------------------  -------------  ------------------------------------
  pending             Customer       Order placed, payment confirmed
  store_notified      System         SMS + dashboard sent to store
  store_confirmed     Store Owner    Store called customer, verified, accepted
  courier_searching   System         Courier search begins after confirmation
  courier_assigned    System         Courier accepted within 20s window
  preparing           Store Owner    Kitchen starts preparing
  ready               Store Owner    Food ready — courier notified
  picked_up           Courier        Courier collected from store
  delivered           Courier        Order handed to customer
  rejected            Store Owner    Store rejected after verification call
  cancelled           System/Admin   Cancelled at any stage

  ❌ DO NOT start courier search before store_confirmed status
  ❌ DO NOT use old statuses: assigned, on_the_way
  ❌ DO NOT skip store_notified step — it is legally required

5.2 Cascading Courier Assignment
----------------------------------
  - Based on store coordinates (NOT customer address)
  - PostGIS: ORDER BY ST_Distance(courier.location, store.coords)
             WHERE is_online AND is_activated
  - 20-second acceptance window per courier
  - Timeout/reject → logged to order_assignment_attempts → next courier
  - No courier available → status: no_courier_found → admin + store notified

  ❌ DO NOT use customer address for courier search radius
  ❌ DO NOT skip logging to order_assignment_attempts

5.3 Delivery Fee Calculation
------------------------------
  Delivery Fee = cost_per_km (settings table)
               × ST_Distance(store_coords → customer_coords)

  Result stored as delivery_distance_km on the order record.

  ❌ DO NOT hardcode cost_per_km — must come from settings table
  ❌ DO NOT use Haversine manually — use PostGIS ST_Distance with SRID 4326

5.4 Reorder Shortcut
----------------------
  POST /orders/{id}/reorder pre-fills cart from past order.
  If any product unavailable → flag to customer, do NOT silently drop.

  ❌ DO NOT silently drop unavailable products on reorder

5.5 Order Lifecycle Summary
-----------------------------
  See Section 5.1 for complete status table.
  Courier search begins ONLY after store_confirmed — not at placement.

5.6 Order Notes System
------------------------
  Three note fields on orders table:

  Field           Written By    Visible To       Editable Until
  --------------  ------------  ---------------  --------------------------
  customer_note   Customer      Store + Courier  Order placement only
  store_note      Store Owner   Courier          Between preparing → ready
  courier_note    Courier       Store Owner      Between assigned → picked_up

  Endpoints:
    PUT /api/store/orders/{id}/note    — store_note
    PUT /api/courier/orders/{id}/note  — courier_note
    PUT /api/store/orders/{id}/ready   — mark ready

  ❌ DO NOT allow customer to edit note after order placement
  ❌ DO NOT allow store to edit note after ready status
  ❌ DO NOT allow courier to edit note after picked_up status

5.7 Store Verification Flow [NEW v2.0]
----------------------------------------
PURPOSE: Removes platform liability for fake and prank orders.
Store must manually verify the order by calling the customer BEFORE
any courier is dispatched.

  Flow:
  -----
    Customer places order
          ↓
    System sends SMS + dashboard notification to store:
      "[FALCO DELIVERY] طلب جديد
       الزبون: Youssef — +212600123456
       المبلغ: 150.00 درهم
       الرجاء التحقق والرد على لوحة التحكم"
          ↓
    Store calls customer to verify order is legitimate
          ↓
         / \
        /   \
    Accept   Reject
       ↓        ↓
  courier    status: rejected
  search     customer notified
  begins     FCM + SMS

  Customer's full phone number is visible to store owner — required
  for the verification call.

  Store Endpoints:
    PUT /api/store/orders/{id}/accept  → store_confirmed → courier search starts
    PUT /api/store/orders/{id}/reject  → rejected → customer notified

  Why This Change:
    - Platform not liable for unverified orders
    - Prevents prank orders (especially from children)
    - Store has full authority to reject suspicious orders
    - No courier dispatched until order confirmed legitimate

  ❌ DO NOT start courier search before store accepts
  ❌ DO NOT hide customer phone from store — required for verification call
  ❌ DO NOT allow system to auto-accept on store's behalf
  ❌ DO NOT remove the store_notified status step


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
6. AGE-RESTRICTED PRODUCTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Applicable to: All Smoking category + age_restricted = true products.

  Flow:
    1. User adds age-restricted product to cart
    2. Modal displayed — cannot be bypassed
    3. User must tap "I Agree" to continue
    4. age_confirmation = true + age_confirmation_at = NOW() on order

  ❌ DO NOT allow bypass of age confirmation modal
  ❌ DO NOT store birthdate — self-declaration only (Glovo compliance model)
  ❌ DO NOT require ID verification in Phase 1


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
7. STORE FEATURES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

7.1 Store Hours & Calendar
---------------------------
  - store_hours: weekly schedule per day_of_week
  - store_closures: exceptional dates (holidays, Eid, maintenance)
  - System auto-determines open/closed — no manual toggle
  - Response includes: is_open, closes_at, next_open_at, closure_reason

  ❌ DO NOT add manual open/close toggle — auto-computed only
  ❌ DO NOT show store as open during a store_closures date

7.2 Menu Governance
--------------------
  Store owners CANNOT directly create, edit, or delete menu items.
  All changes go through admin-approval workflow.

  Flow:
    Store Owner → POST /api/store/menu (creates pending request)
    Admin → GET /api/admin/menu-changes (reviews)
    Admin → POST /api/admin/menu-changes/{id}/approve or /reject
    System → applies to products table only after approval

  ❌ DO NOT allow store owners to directly modify products table
  ❌ DO NOT auto-approve menu changes — admin must review

7.3 Store Owner Analytics
--------------------------
  GET /api/store/analytics/revenue       — daily revenue last 30 days
  GET /api/store/analytics/top-products  — top 10 best-selling products
  GET /api/store/analytics/volume        — order volume by day/week/month


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
8. COURIER FEATURES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

8.1 Account Lifecycle
----------------------
  - Courier accounts created exclusively by admin
  - Admin generates one-time activation code
  - Courier activates: POST /api/courier/activate → is_activated = true
  - EnsureCourierIsActivated middleware protects all courier endpoints

  ❌ DO NOT allow couriers to self-register
  ❌ DO NOT allow unactivated couriers to receive orders

8.2 Order Handling
-------------------
  - Receives FCM push with order details + delivery distance
  - 20-second window to accept or reject
  - Location updated every 5 seconds max (rate-limited in Redis)
  - Customer receives real-time tracking via WebSocket (order.{id})

8.3 Courier Earnings
---------------------
  Cash paid directly by customer at delivery.
  No wallet system exists.
  Logged in courier_earnings table for transparency only.

  ❌ DO NOT implement wallet or balance system in Phase 1

8.4 Admin Monthly Stats
------------------------
  Stored in courier_monthly_stats:
  month, deliveries_count, total_distance_km per courier.

8.5 Courier Order Assignment — Notification Strategy
------------------------------------------------------
  Primary:  FCM push notification — instant, no persistent connection
  Fallback: GET /api/courier/orders/available — called on app reopen
            or FCM failure. Returns active assignment still in 20s window.

  Why NOT WebSocket for assignment:
    Persistent background connection drains battery all day.
    WebSocket reserved for customer tracking only.

  Why NOT Polling:
    20s window = only 4-6 polls — unreliable.
    Wastes battery + server resources.

  ❌ DO NOT use WebSocket for courier order assignment
  ❌ DO NOT implement polling for courier assignment
  ❌ DO NOT use customer address for courier search

8.6 Delivery Confirmation — Courier Side [NEW v2.0]
-----------------------------------------------------
  When courier hands order to customer, they tap ONE clearly labeled button:

    +-------------------------------+
    |  Arrived at destination?      |
    |                               |
    |   [ Confirm Delivery ]        |
    |                               |
    |  Tap after customer receives  |
    +-------------------------------+

  Endpoint: POST /api/courier/orders/{id}/deliver
  Result:   status → delivered, customer notified, stats updated

  PROHIBITED CONFIRMATION METHODS
  ================================
  The following are explicitly PROHIBITED. Do NOT implement under
  any circumstances to avoid interaction ambiguity, accidental
  triggers, and courier fraud:

    ❌ Long press
       Reason: Easily abused — courier can trigger without delivering.
               Unclear gesture — no standard duration known to users.

    ❌ Swipe to confirm
       Reason: Can be triggered accidentally while holding phone.

    ❌ Auto-confirm on GPS proximity
       Reason: GPS inaccurate inside buildings.
               Easily spoofed — high fraud risk.

    ❌ Timer-based auto-confirm
       Reason: No human action required — highest fraud risk.

    ❌ Double tap
       Reason: Too easily triggered accidentally.

    ❌ Shake gesture
       Reason: Triggered accidentally during normal movement on bike/car.

  These are prohibited to avoid:
    1. Mixing interaction meanings across the app
    2. Accidental delivery confirmation
    3. Courier fraud (marking delivered without actual delivery)
    4. Inconsistent user experience
    5. Legal disputes with no proof of delivery action

  PHASE 2 — Customer Confirmation Code (Future)
  ===============================================
  In Phase 2, a 4-digit code system will be introduced:

    Step 1: Customer sees code (e.g. 4821) in Flutter app
    Step 2: Courier asks customer for the code
    Step 3: Courier enters code in courier app
    Step 4: System confirms delivery automatically

  Benefits:
    - Guarantees customer was physically present
    - Eliminates fraud possibility entirely
    - Provides legal proof of delivery

  Deferred to Phase 2 based on fraud reports data from Phase 1.

  Summary:
    Phase 1:  Single tap button → POST /api/courier/orders/{id}/deliver
    Phase 2:  4-digit customer code → same endpoint + code validation


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
9. ADMIN PANEL
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

9.1 Admin Responsibilities
---------------------------
  - Create and manage store owners, stores, couriers
  - Approve or reject menu change requests
  - Review and resolve refund requests
  - Review and resolve order issue reports
  - Manage promo codes
  - Monitor platform analytics
  - Moderate reviews

9.2 Store & Store Owner Creation (Admin Only)
----------------------------------------------
  No self-registration. Admin creates everything directly.
  Stores are immediately live — no approval or pending state.

  Endpoints:
    POST   /api/admin/store-owners     — create owner account
    POST   /api/admin/stores           — create store (immediately live)
    PUT    /api/admin/stores/{id}      — edit store
    DELETE /api/admin/stores/{id}      — deactivate store
    GET    /api/admin/stores           — list all stores

  Credentials shared out-of-band by admin (call/message). No automated delivery.

  REMOVED ENDPOINTS — DO NOT IMPLEMENT:
    ❌ GET  /api/admin/stores/pending      — no pending state
    ❌ POST /api/admin/stores/{id}/approve — no approval workflow
    ❌ POST /api/auth/register-store       — no public registration

9.3 Admin Analytics Dashboard
-------------------------------
  Endpoint                           Description              Period Filter
  ---------------------------------  -----------------------  -------------
  GET /api/admin/analytics           KPI summary (all-time)   No
  GET /api/admin/analytics/revenue   Daily revenue chart      Yes
  GET /api/admin/analytics/orders    Orders by status         Yes
  GET /api/admin/analytics/stores    Top 10 stores            Yes
  GET /api/admin/analytics/couriers  Top 10 couriers          Yes

  Period filter: ?period=week (7d) | month (30d, default) | all

  ❌ DO NOT count cancelled/rejected orders in revenue
  ❌ DO NOT include non-delivered orders in revenue calculations


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
10. PAYMENT SYSTEM
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

10.1 Supported Methods
-----------------------
  - Cash on delivery — primary and only method in Phase 1
  - No wallet system
  - Refunds processed offline — no automated money movement

  ❌ DO NOT implement wallet, credits, or online payment in Phase 1
  ❌ DO NOT automate refund money transfers

10.2 Refund Endpoints & Flow
------------------------------
  Customer endpoints:
    POST /api/refunds   — submit request
    GET  /api/refunds   — list own requests

  Admin endpoints:
    GET /api/admin/refunds                    — list all (?status=pending)
    PUT /api/admin/refunds/{id}/approve       — approve (cash/bank_transfer)
    PUT /api/admin/refunds/{id}/reject        — reject with admin_note

  Guards on POST /api/refunds:
    - Order must belong to authenticated customer → 403
    - Order status must be delivered → 422
    - No existing pending refund for same order → 422

  On resolution: customer notified via FCM push + SMS (both channels).

  ❌ DO NOT allow refund request before delivered status
  ❌ DO NOT allow duplicate pending refunds for same order
  ❌ DO NOT process refund automatically — admin manual only

10.3 Promo Codes & Admin Management
-------------------------------------
  eligible_categories:
    null or empty array → applies to all categories
    [1, 2] → restricted to those category IDs only

  Validation at checkout (in order):
    1. Code exists and is_active = true
    2. Not expired
    3. Usage limit not exceeded
    4. Cart total >= min_order_amount
    5. Store category in eligible_categories (if set)

  Admin endpoints:
    POST   /api/admin/promo-codes      — create
    GET    /api/admin/promo-codes      — list with usage stats
    PUT    /api/admin/promo-codes/{id} — edit or deactivate
    DELETE /api/admin/promo-codes/{id} — delete

  ❌ DO NOT allow customers to create or modify promo codes
  ❌ DO NOT skip category eligibility check at checkout


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
11. NOTIFICATIONS & REAL-TIME TRACKING
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

11.1 Notification Events
-------------------------
  Event                          Recipient     Channel
  -----------------------------  ------------  ----------------
  New order placed               Store owner   SMS + FCM push
  Store verified & confirmed     Customer      FCM push
  Store rejected order           Customer      FCM push + SMS
  Courier assigned               Customer      FCM push
  Courier picked up order        Customer      FCM push
  Order delivered                Customer      FCM push
  New order assignment (20s)     Courier       FCM push
  Menu change approved/rejected  Store owner   FCM push
  Refund approved/rejected       Customer      FCM push + SMS
  Report received confirmation   Customer      FCM push
  OTP verification               Customer      SMS

  ❌ DO NOT use polling for real-time order status on customer side
  ❌ DO NOT use WebSocket for courier assignment notifications

11.2 SMS Provider Requirements
--------------------------------
  - Must support Moroccan numbers (+212)
  - Must provide Sender ID (ANRT compliance)
  - All SMS logged in sms_logs table

  Recommended:
    Development/MVP:  Twilio (sandbox mode, free trial)
    Production:       D7 Networks (direct Maroc Telecom/Orange/Inwi)

  ❌ DO NOT go to production without ANRT-compliant Sender ID

11.3 Mobile Framework & Push Notifications
-------------------------------------------
  Framework:  Flutter — iOS + Android single codebase
  Push:       Firebase Cloud Messaging (FCM) — no Expo dependency
  Tracking:   WebSocket channel order.{id} — event: CourierLocationUpdated

  Device token registered on app launch:
    POST /api/notifications/device-token
    { "token": "fcm_token", "platform": "android" }

  ❌ DO NOT use Expo push notifications — FCM directly only
  ❌ DO NOT use WebSocket for courier assignment (battery drain)
  ❌ DO NOT forget RTL configuration for Arabic from day one


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
12. SECURITY REQUIREMENTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  - All endpoints: auth:sanctum middleware (except public browse)
  - Role middleware: role:admin, role:restaurant_owner, role:courier
  - EnsureCourierIsActivated on all courier routes
  - OTP rate limited: 3 per 10min per phone (Redis)
  - Location update rate limited: 1 per 5s per courier (Redis)

  ❌ DO NOT expose admin endpoints without role:admin middleware
  ❌ DO NOT allow unactivated couriers to receive orders
  ❌ DO NOT skip rate limiting on OTP endpoint


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
13. LOCALIZATION & LANGUAGE SUPPORT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Language   Code  Direction
  ---------  ----  ---------
  Arabic     ar    RTL — configured in Flutter from day one
  French     fr    LTR
  Darija     dar   RTL
  English    en    LTR

  - All UI strings in 4 JSON translation files (Flutter frontend)
  - Default from device locale — user can switch manually
  - Backend returns raw data — no backend translation in Phase 1

  ❌ DO NOT implement backend translation in Phase 1
  ❌ DO NOT add RTL support as an afterthought — configure from day one


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
14. PERFORMANCE REQUIREMENTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Metric                    Target
  ------------------------  ---------------
  API response time (p95)   < 300ms
  OTP delivery              < 10 seconds
  Courier location update   Max 1 per 5s
  WebSocket latency         < 500ms
  Concurrent users Phase 1  500+


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
15. SCALABILITY ROADMAP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Phase 1  Scalable monolith — Laravel + PostgreSQL + Redis
  Phase 2  Extract courier tracking to separate microservice
  Phase 3  Add read replicas for analytics queries
  Phase 4  Message queue (Horizon) for heavy notification load


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
16. KPIs & SUCCESS METRICS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  KPI                              Target (Month 3)
  -------------------------------  ----------------
  Active customers                 500+
  Orders per day                   100+
  Average order value              120 MAD+
  Courier acceptance rate          > 85%
  Order completion rate            > 90%
  Store verification call rate     > 95%
  Average delivery time            < 45 min


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
17. LEGAL & COMPLIANCE (MOROCCO)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  - Age modal required — cannot be bypassed — age_confirmation_at logged
  - SMS logs retained for dispute handling and telecom compliance
  - ToS logged: user_id, tos_version, ip_address, accepted_at
  - SMS Sender ID must comply with ANRT regulations
  - No biometric or ID verification in Phase 1 — self-declaration only
  - Customer phone visible to store for verification — logged in sms_logs

  ❌ DO NOT go to production without ANRT-registered Sender ID
  ❌ DO NOT bypass age confirmation modal under any circumstances
  ❌ DO NOT delete ToS acceptance logs — legal requirement


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
18. REVIEWS & RATINGS SYSTEM [NEW v2.0]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

18.1 Product Review — Triggered at Delivery
---------------------------------------------
  When status changes to delivered, Flutter auto-shows rating popup
  for each product in the order.

  Rules:
    - Customer rates each product (1-5 stars)
    - Optional text comment (max 300 chars)
    - Skip button — never mandatory
    - One review per order_item_id — cannot submit twice

  Endpoint: POST /api/reviews/products
  Guards:
    - Order must belong to customer → 403
    - Status must be delivered → 422
    - No existing review for same order_item_id → 422

  ❌ DO NOT make product review mandatory
  ❌ DO NOT allow review before delivered status
  ❌ DO NOT allow duplicate reviews for same order_item_id

18.2 Store Review — Available Anytime
---------------------------------------
  Customer can rate a store anytime from the store page.

  Rules:
    - Only if customer has at least one delivered order from that store
    - 1-5 stars + optional comment (max 500 chars)
    - One review per customer per store — can update anytime (upsert)
    - Publicly visible on store page

  Endpoints:
    POST /api/reviews/stores       — create or update (upsert)
    GET  /api/stores/{id}/reviews  — paginated public reviews (no auth)

  ❌ DO NOT allow review from customer with no delivered order from that store
  ❌ DO NOT create duplicate store reviews — use upsert

18.3 Database Schema — reviews table
--------------------------------------
  id, user_id, store_id (nullable), order_item_id (nullable),
  order_id, type (store/product), rating (1-5), comment, deleted_at

  Unique constraints:
    (user_id, store_id)       — one store review per customer per store
    (user_id, order_item_id)  — one product review per order item

18.4 Average Ratings — Computed at Query Time
-----------------------------------------------
  No dedicated rating columns — computed via SQL and appended to responses.

  ❌ DO NOT add average_rating column to stores or products tables
  ❌ DO NOT cache ratings without invalidation on new review

18.5 Admin Moderation
-----------------------
  Admin can soft-delete reviews: DELETE /api/admin/reviews/{id}
  No edit capability — keep or remove only.

  ❌ DO NOT hard-delete reviews — soft delete only (deleted_at)
  ❌ DO NOT allow admin to edit review content


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
19. ORDER ISSUE REPORTING SYSTEM [NEW v2.0]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Separate from refunds — operational complaints handled by admin only.

19.1 When Can a Customer Report
---------------------------------
  Moment             Eligible Statuses
  -----------------  -------------------------------------------
  During order       courier_assigned, preparing, ready, picked_up
  After delivery     delivered — within 24 hours ONLY

  One report per order maximum.

  ❌ DO NOT allow report after 24h from delivered status
  ❌ DO NOT allow second report on same order

19.2 Report Categories & Priority
-----------------------------------
  Type                Priority   Description
  ------------------  ---------  ------------------------------------
  courier_no_show     HIGH       Courier accepted but never arrived
  courier_behavior    HIGH       Inappropriate courier behavior
  late_delivery       MEDIUM     Delivery taking too long
  missing_items       MEDIUM     Order arrived with missing products
  wrong_items         MEDIUM     Received wrong products
  store_issue         MEDIUM     Problem from the store side
  damaged_product     NORMAL     Product arrived damaged or expired
  other               NORMAL     Free-text for anything else

19.3 Customer Flow
-------------------
  Customer taps "Report an Issue"
          ↓
  Selects category
          ↓
  Optional description (max 500 chars)
          ↓
  POST /api/reports
          ↓
  Confirmation shown + FCM push sent:
  "بلاغك وصل، سنتصل بك قريباً"
  "Your report has been received. We will contact you shortly."

19.4 Endpoints
---------------
  POST /api/reports                      — customer submits
  GET  /api/reports                      — customer lists own reports
  GET  /api/admin/reports                — admin lists all (?status=open&type=)
  PUT  /api/admin/reports/{id}/resolve   — admin resolves

  Guards on POST /api/reports:
    - Order belongs to customer → 403
    - Status active or delivered within 24h → 422
    - No existing report for same order → 422

  ❌ DO NOT allow report on cancelled or rejected orders
  ❌ DO NOT allow store or courier to see reports — admin only

19.5 Database Schema — order_reports
--------------------------------------
  id, order_id, user_id, type (enum), description, status (open/resolved),
  admin_response, action_taken, resolved_at

  Unique constraint: (user_id, order_id)

19.6 Admin Notifications on New Report
----------------------------------------
  HIGH priority reports trigger immediate FCM push to admin.
  Medium and Normal appear in queue sorted by priority.

  ❌ DO NOT notify store or courier of reports — admin only


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
QUICK REFERENCE — ALL PROHIBITED ACTIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Authentication & OTP:
  ❌ Store OTP in DB before verification
  ❌ Allow more than 3 OTP attempts per 10 minutes
  ❌ Validate ToS at request-otp step
  ❌ Create account without tos_accepted: true (new phone)
  ❌ Delete ToS acceptance logs

Order Flow:
  ❌ Start courier search before store_confirmed
  ❌ Use old status literals: assigned, on_the_way
  ❌ Skip store_notified step
  ❌ Hide customer phone from store owner
  ❌ Auto-accept orders on store's behalf
  ❌ Silently drop unavailable products on reorder

Courier:
  ❌ Allow unactivated couriers to receive orders
  ❌ Use WebSocket for courier assignment
  ❌ Implement polling for courier assignment
  ❌ Use customer address for courier search radius
  ❌ Long press for delivery confirmation
  ❌ Swipe to confirm delivery
  ❌ Auto-confirm on GPS proximity
  ❌ Timer-based auto-confirm
  ❌ Double tap for delivery confirmation
  ❌ Shake gesture for delivery confirmation

Payment & Wallet:
  ❌ Implement wallet or balance system in Phase 1
  ❌ Automate refund money transfers
  ❌ Allow refund request before delivered status
  ❌ Allow duplicate pending refunds for same order

Admin & Stores:
  ❌ GET /api/admin/stores/pending
  ❌ POST /api/admin/stores/{id}/approve
  ❌ POST /api/auth/register-store
  ❌ Allow store owners to directly modify products table
  ❌ Auto-approve menu changes
  ❌ Count non-delivered orders in revenue analytics

Notifications:
  ❌ Use Expo push notifications — FCM directly only
  ❌ Use WebSocket for courier assignment
  ❌ Go to production without ANRT Sender ID

Reviews:
  ❌ Make product review mandatory
  ❌ Allow review before delivered status
  ❌ Duplicate store reviews — use upsert
  ❌ Hard-delete reviews — soft delete only
  ❌ Add average_rating column to stores/products tables

Reports:
  ❌ Allow report after 24h from delivery
  ❌ Allow second report on same order
  ❌ Show reports to store or courier — admin only

Legal:
  ❌ Bypass age confirmation modal
  ❌ Store birthdate — self-declaration only
  ❌ Delete sms_logs or audit_logs
  ❌ Go live without ANRT-compliant Sender ID

Database:
  ❌ Hardcode cost_per_km — must come from settings table
  ❌ Use Haversine manually — use PostGIS ST_Distance SRID 4326
  ❌ Add wallet/balance fields to users table


========================================================
END OF DOCUMENT
FALCO DELIVERY — PRD v2.0 — Laâyoune, Maroc — March 2026
========================================================