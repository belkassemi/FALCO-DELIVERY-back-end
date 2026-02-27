FALCO DELIVERY
Product Requirements Document



1. Vision & Objectives
Falco Delivery is a Moroccan on-demand multi-service delivery platform inspired by Glovo, designed specifically for the Moroccan market and user behavior. The core philosophy minimizes friction, avoids forced account creation at startup, and uses phone number as the primary identity.

1.1 Key Objectives
Phone-first authentication — no email required
Lazy account creation — registration only at checkout
Multi-category marketplace: Food, Pharmacy, Market, Smoking
Age-restricted product compliance (Glovo model)
Moroccan legal compliance — SMS, ToS, age confirmation logging
Scalable monolith architecture for Phase 1

1.2 Supported Categories


2. User Roles & Permissions


3. Authentication System — Phone-First / Lazy Signup
3.1 Core Principles
No registration screen on app launch
User browses freely as anonymous
Registration triggered only at first checkout
Phone number is the primary identity key
Email is optional (nullable)
No password system — OTP only

3.2 OTP Registration Flow

3.3 OTP Security Parameters

3.4 ToS Acceptance — Enforcement Rules (Updated)
ToS acceptance is required only on first registration (new phone number). Returning users who have already accepted the ToS are not prompted again unless the ToS version changes in the future.
Enforcement Strategy — Two Layers
Layer 1 — Frontend (Flutter)
The "Send OTP" button is disabled until the user checks the consent box:
"I agree to the Terms of Service and Privacy Policy."
The button cannot be tapped until the checkbox is checked. This prevents the OTP request from being made without consent. This layer protects against accidental submission.
Layer 2 — Backend (Laravel)
The backend validates tos_accepted: true at the verify OTP step, not the request OTP step. This is the legally binding moment — when the user has confirmed their phone number and actively verified their identity via OTP.
If tos_accepted is missing or false and the phone number is new, the backend returns:
json{
  "message": "You must accept the Terms of Service to create an account.",
  "code": "TOS_NOT_ACCEPTED"
}
Updated POST /api/auth/verify-otp Logic
json{
  "phone": "+212600123456",
  "otp": "123456",
  "tos_accepted": true
}
Scenariotos_accepted required?ActionNew phone number✅ Yes — must be trueCreate account + log tos_acceptancesExisting phone number❌ No — field ignoredJust return token
tos_acceptances Table — What Gets Logged
FieldValueuser_idNewly created user IDtos_versionCurrent ToS version (e.g. "1.0") — hardcoded in configip_addressRequest IP addressaccepted_atCurrent timestamp
Why Validated at Verify Step Not Request Step
The request-otp step does not yet confirm the user owns the phone number. Logging ToS acceptance before OTP verification would mean recording consent from an unverified identity. Validating at verify-otp ensures the acceptance is tied to a confirmed, verified phone number — which is the legally correct moment to log consent under Moroccan compliance requirements.

Table: tos_acceptances

Table: phone_otps


4. Database Schema
4.1 Core Tables
Table: users

Table: addresses

4.2 Categories & Stores
Table: categories

Table: stores

Table: store_hours

Table: store_closures

4.3 Products
Table: products

Table: menu_change_requests


4.4 Orders
Table: orders

Table: order_items

Table: order_assignment_attempts

4.5 Couriers
Table: couriers

Table: courier_monthly_stats


4.6 Payments & Promo Codes
Table: payments

Table: promo_codes

Table: promo_code_uses

4.7 Refunds, Reviews & Favorites
Table: refund_requests

Table: reviews

Table: user_favorites


4.8 System & Logging Tables
Table: settings

Table: sms_logs

Table: audit_logs


5. Order Flow
5.1 Customer Order Lifecycle

5.2 Cascading Courier Assignment
Courier assignment is based on restaurant/store coordinates, not customer address. The system finds the nearest online and activated courier.


5.3 Delivery Fee Calculation
The admin configures a cost_per_km value in the settings table. At checkout, the system calculates:

Delivery Fee = cost_per_km × distance(store_coords → customer_coords)

Distance is calculated using the Haversine formula. The result is stored in delivery_distance_km on the order record and used for both fee calculation and courier stats.

5.4 Reorder Shortcut
A POST /orders/{id}/reorder endpoint pre-fills the cart from a past order. If any product is no longer available, the system flags those items to the customer rather than silently dropping them.
5.5 Revised Order Lifecycle & Status Flow
Order Status Sequence
The complete order lifecycle follows this sequence:
pending → courier_searching → courier_assigned → preparing → ready → picked_up → delivered
StatusTriggered ByDescriptionpendingCustomerOrder placed, payment method confirmedcourier_searchingSystemImmediately starts cascading courier search based on store coordinatescourier_assignedSystemCourier accepted the assignment within 20s windowpreparingStore OwnerStore confirmed order and started preparingreadyStore OwnerFood is ready for pickup, courier notifiedpicked_upCourierCourier collected the order from storedeliveredCourierOrder handed to customer
Key Logic Change — Courier Assignment Timing
Courier search begins immediately after the customer places the order, not after store acceptance. This ensures the courier is already at or near the store by the time the food is ready, minimizing wait time. The store is notified of the order simultaneously with the courier assignment confirmation.
Endpoints affected:

PUT /api/store/orders/{id}/accept → triggers preparing status
PUT /api/store/orders/{id}/ready → triggers ready status + pushes FCM notification to courier
POST /api/courier/orders/{id}/pickup → triggers picked_up status
POST /api/courier/orders/{id}/deliver → triggers delivered status


5.6 Order Notes System
Orders support a three-level notes system allowing communication between all parties involved in the delivery.
Notes Fields on orders Table
FieldTypeWritten ByVisible ToExamplecustomer_notetext NULLABLECustomerStore + Courier"I'm on the 5th floor, please ring bell 3"store_notetext NULLABLEStore OwnerCourier"Order not started yet, please wait 10 minutes"courier_notetext NULLABLECourierStore Owner"I'm stuck in traffic, arriving in 15 minutes"
When Notes Are Set

customer_note — submitted at checkout inside the order request body. Cannot be edited after placement.
store_note — can be added or updated any time between preparing and ready statuses via PUT /api/store/orders/{id}/note.
courier_note — can be added or updated any time between courier_assigned and picked_up statuses via PUT /api/courier/orders/{id}/note.

Updated Order Placement Request Body
json{
  "store_id": 1,
  "address_id": 4,
  "items": [
    {"product_id": 12, "quantity": 1, "options": []}
  ],
  "age_confirmation": true,
  "promo_code": "FALCO2026",
  "customer_note": "I'm on the 5th floor, please don't ring the bell after 10pm"
}
New Endpoints to Add to API Guide
EndpointRoleDescriptionPUT /api/store/orders/{id}/noteStore OwnerAdd or update store note visible to courierPUT /api/courier/orders/{id}/noteCourierAdd or update courier note visible to storePUT /api/store/orders/{id}/readyStore OwnerMark order as ready for pickup

6. Age-Restricted Products
6.1 Applicable Products

6.2 Confirmation Flow
User adds age-restricted product to cart
Modal displayed: 'I confirm that I am of legal age to purchase this product.'
User must click 'I Agree' to continue — cannot be bypassed
age_confirmation = true and age_confirmation_at timestamp recorded on order
No ID verification required — self-declaration model (Glovo compliance standard)
No birthdate stored


7. Store Features
7.1 Store Hours & Calendar
Admin sets opening hours at store creation. Store owners can customize hours and add one-off closure dates via calendar.

store_hours table defines weekly schedule (per day_of_week)
store_closures table handles exceptional dates (holidays, maintenance)
Moroccan public holidays (Eid, Throne Day, etc.) added manually by admin or store owner
System automatically shows store as closed based on schedule — no manual toggle needed

7.2 Menu Governance
Store owners cannot directly create, edit, or delete menu items. All changes go through an admin-approval workflow:


7.3 Store Owner Analytics
Computed from orders data via API endpoints — no dedicated analytics tables required:

GET /store/analytics/revenue — daily revenue for last 30 days
GET /store/analytics/top-products — top 10 best-selling products
GET /store/analytics/volume — order volume by day/week/month


8. Courier Features
8.1 Account Lifecycle
Courier accounts created exclusively by admin
Admin generates one-time activation code
Courier activates account using code → is_activated = true
EnsureCourierIsActivated middleware protects all courier endpoints

8.2 Order Handling
Receives push notification with order details
Sees delivery distance (store → customer) to decide acceptance
20-second window to accept or reject
Location updated periodically — rate-limited to prevent spam
Customer receives real-time tracking via WebSocket

8.3 Courier Earnings
Couriers are paid in cash directly by the customer at delivery. No wallet system exists. Earnings visibility is provided for transparency:

Table: courier_earnings

8.4 Admin Monthly Stats for Couriers

8.5 Courier Order Assignment — Notification Strategy
Courier order assignment uses a Push + REST Fallback strategy to ensure reliability within the 20-second acceptance window.
Primary Channel — FCM Push Notification
When the system assigns an order to a courier, it immediately sends a Firebase Cloud Messaging (FCM) push notification to the courier's registered device token. The notification contains:
FieldDescriptionorder_idThe assigned order IDstore_nameName of the store to pick up fromdelivery_distance_kmDistance from store to customerorder_totalTotal order valueexpires_atTimestamp when the 20s window closes
The courier must accept or reject via POST /api/courier/orders/{id}/accept or /reject within 20 seconds. If no response is received, the system automatically cascades to the next nearest available courier.
Fallback Channel — REST Endpoint
The GET /api/courier/orders/available endpoint exists as a fallback only, not the primary assignment mechanism. It is called in two scenarios:

The courier app reopens after being backgrounded or killed and may have missed a push notification
FCM delivery fails or is delayed due to poor network conditions

This endpoint returns any currently active assignment that is still within its 20-second window and waiting for a response.
Why Not WebSocket?
WebSocket was considered but rejected for the courier assignment channel because it requires a persistent background connection, which significantly drains battery on a device used for deliveries all day. WebSocket is reserved for customer-side real-time order tracking only (Section 11).
Why Not Polling?
Polling every few seconds is unsuitable for a 20-second acceptance window and would waste both battery and server resources. FCM push is instant and polling adds no value over the REST fallback.

9. Admin Panel
9.1 Admin Responsibilities
9.2 Store & Store Owner Creation (Admin Only)
Store registration is fully admin-controlled. There is no self-registration flow for store owners. Stores are created directly by admin and are immediately live upon creation — no approval or pending state exists.
Account Creation Flow
Admin creates store owner account (email + password)
        ↓
Admin creates store and assigns it to that owner
        ↓
Admin sets category, location, and opening hours
        ↓
Admin contacts store owner out-of-band and shares credentials
        ↓
Store owner logs in via POST /api/auth/login and starts working immediately
Credential Delivery
Credentials are shared by the admin directly with the store owner through manual out-of-band communication (phone call, message, in-person). No automated SMS or email is sent by the system. This is intentional and follows the same pattern as courier account activation.
Admin Endpoints
EndpointDescriptionPOST /api/admin/store-ownersCreate a new store owner accountPOST /api/admin/storesCreate a new store and assign to ownerPUT /api/admin/stores/{id}Edit store details, category, or locationDELETE /api/admin/stores/{id}Deactivate a storeGET /api/admin/storesList all stores with status and owner info
POST /api/admin/store-owners Request Body
json{
  "name": "Hassan Benali",
  "email": "hassan@pizzahouse.ma",
  "password": "securepassword",
  "phone": "+212600123456"
}
POST /api/admin/stores Request Body
json{
  "owner_id": 5,
  "name": "Pizza House Casablanca",
  "category_id": 1,
  "address": "123 Boulevard Mohammed V, Casablanca",
  "lat": 33.5731,
  "lng": -7.5898,
  "logo": "logo.png"
}
Removed Endpoints
The following endpoints are not needed and should be removed from the API guide as there is no store approval workflow:
Removed EndpointReasonGET /api/admin/stores/pendingNo pending state existsPOST /api/admin/stores/{id}/approveNo approval workflow exists

10. Payment System
10.1 Supported Methods

No wallet system exists in this version. Refunds are processed offline by admin via cash or bank transfer and logged in the refund_requests table.

10.2 Refund Endpoints & Flow
Customers may only request a refund after the order status is delivered. Refunds are processed manually by admin — no automated money movement occurs.
Customer Endpoints
POST /api/refunds
json{
  "order_id": 99,
  "reason": "Wrong items delivered"
}
ValidationRuleOrder must belong to authenticated customer403 if notOrder status must be delivered422 if notNo existing pending refund for same order422 if duplicate
Response (201 Created):
json{
  "message": "Refund request submitted successfully.",
  "refund_id": 12
}

GET /api/refunds
Returns all refund requests submitted by the authenticated customer.
Response (200 OK):
json[
  {
    "id": 12,
    "order_id": 99,
    "reason": "Wrong items delivered",
    "status": "pending",
    "refund_method": null,
    "admin_note": null,
    "created_at": "2026-02-27T10:00:00Z",
    "resolved_at": null
  }
]

Admin Endpoints
GET /api/admin/refunds
Returns paginated list of all refund requests filterable by status.
Query Params: ?status=pending&page=1

PUT /api/admin/refunds/{id}/approve
json{
  "refund_method": "cash",
  "admin_note": "Confirmed wrong items. Refund approved."
}
FieldTypeDescriptionrefund_methodenumcash or bank_transferadmin_notetext NULLABLEOptional note visible to customer

PUT /api/admin/refunds/{id}/reject
json{
  "admin_note": "Order was delivered correctly based on store confirmation."
}

Customer Notification on Resolution
When admin approves or rejects a refund, the customer is notified via both FCM push notification and SMS:
EventPush MessageSMS MessageApproved"Your refund for order #99 has been approved. You will receive your money via cash/bank transfer."Same message via SMSRejected"Your refund request for order #99 has been reviewed. Please check the app for details."Same message via SMS
Both the push notification and SMS are logged in the sms_logs table and the notifications table respectively.
refund_requests Table — Updated Fields
FieldTypeDescriptionidbigint PKorder_idFK → ordersuser_idFK → usersreasontextCustomer's reasonstatusenumpending / approved / rejectedrefund_methodenum NULLABLEcash or bank_transferadmin_notetext NULLABLEAdmin resolution noteresolved_attimestamp NULLABLEWhen admin actedcreated_attimestampWhen customer submitted

10.3 Promo Codes & Admin Management
The promo_codes table supports flexible discount configuration managed exclusively by admin. Each promo code can be restricted to specific categories or applied globally across all categories.
Promo Code Structure
The eligible_categories field controls scope:

Empty array or null → applies to all categories (Food, Pharmacy, Market, Smoking)
Array of category IDs → restricted to specified categories only (e.g. Food only, or Food + Pharmacy)

Admin Endpoints
EndpointDescriptionPOST /api/admin/promo-codesCreate a new promo codeGET /api/admin/promo-codesList all promo codes with usage statsPUT /api/admin/promo-codes/{id}Edit or deactivate an existing promo codeDELETE /api/admin/promo-codes/{id}Delete a promo code
Promo Code Fields
FieldTypeDescriptioncodevarcharUnique coupon stringdiscount_typeenumpercentage or fixeddiscount_valuedecimalAmount or percentage to deducteligible_categoriesjsonbArray of category IDs. Null = all categoriesmin_order_amountdecimalMinimum cart total to apply. Null = no minimummax_usesintTotal usage limit. Null = unlimitedexpires_attimestampExpiry date. Null = no expiryis_activebooleanAdmin can disable without deleting
Validation Logic at Checkout
When a customer applies a promo code at checkout, the backend validates in this order:

Code exists and is_active = true
Not expired (expires_at is null or in the future)
Usage limit not exceeded (max_uses is null or current uses < max_uses)
Cart total meets min_order_amount
Store's category is in eligible_categories (resolved via stores → category_id join). If eligible_categories is empty or null, this check is skipped.

If all checks pass, the discount is applied and a record is inserted into promo_code_uses.

You can place this under Section 10 — Payment System, right after 10.2 Refund Flow.


11. Notifications & Real-Time Tracking
11.1 Notification Channels

11.2 SMS Provider Requirements
Must support Moroccan phone numbers (+212)
Must provide Sender ID (check license requirements with ANRT)
All SMS events logged in sms_logs table for dispute handling
Recommended providers: Twilio, Vonage, or local Moroccan gateway

11.3 Mobile Framework & Push Notification Provider
The Falco Delivery mobile application is built using Flutter, targeting Android and iOS from a single codebase. Flutter was selected for its native performance, built-in RTL (Right-to-Left) support for Arabic, and strong presence in the Moroccan Android-dominant market.
Push Notification Stack
Push notifications are delivered via Firebase Cloud Messaging (FCM) directly, with no dependency on Expo or any third-party notification proxy.
Device Token Registration
When the app launches and the user grants notification permission, the Flutter app retrieves the FCM token from Firebase and registers it with the backend:
POST /api/notifications/device-token
json{
  "token": "fcm_device_token_here",
  "platform": "android"
}
FieldTypeDescriptiontokenvarcharFCM device token from Firebaseplatformenumandroid or ios
Backend Requirements
The backend must integrate the Firebase Admin SDK (available as a Laravel package) to send push notifications to FCM tokens. The device_tokens table should store one active token per user/device, updated on every app launch to handle token rotation.
Notification Events That Trigger a Push
EventRecipientOrder placedStore ownerOrder accepted by storeCustomerCourier assignedCustomerCourier picked up orderCustomerOrder deliveredCustomerNew order assignmentCourier (20s window)Menu change request approved/rejectedStore ownerRefund request approved/rejectedCustomer

12. Security Requirements


13. Localization & Language Support
13.1 Supported Languages

13.2 Implementation Approach (Option A — Static i18n)
All UI strings stored in 4 JSON translation files on the frontend
Default language detected from device locale — user can switch manually
Language preference stored on device (local storage / async storage)
Backend returns raw data in whatever language the owner entered — no backend translation
Arabic requires RTL layout support — must be configured in the frontend framework from day one
Store and product names are entered by owners in their preferred language — no multi-language DB content in Phase 1


14. Performance Requirements


15. Scalability Roadmap


16. KPIs & Success Metrics


17. Legal & Compliance (Morocco)
Age-restricted products require self-declaration confirmation — modal must be shown and cannot be bypassed
age_confirmation_at timestamp stored on every order containing restricted items
SMS logs retained for dispute handling and telecom compliance
ToS acceptance logged per user with version, timestamp, and IP address
SMS Sender ID and campaigns must comply with ANRT (Agence Nationale de Réglementation des Télécommunications) regulations
No biometric or ID verification required in Phase 1 — self-declaration model only


