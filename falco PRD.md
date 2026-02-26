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

3.4 ToS Acceptance
At the lazy signup moment (first checkout), before OTP is sent, the user must check a consent box: 'I agree to the Terms of Service and Privacy Policy.' This cannot be bypassed. Upon successful OTP verification, the acceptance is logged.

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


9. Admin Panel
9.1 Admin Responsibilities


10. Payment System
10.1 Supported Methods

No wallet system exists in this version. Refunds are processed offline by admin via cash or bank transfer and logged in the refund_requests table.

10.2 Refund Flow
Customer submits refund request with reason
Admin reviews and approves or rejects
If approved: admin selects refund_method (cash or bank_transfer)
Resolution noted in refund_requests record — no automated money movement


11. Notifications & Real-Time Tracking
11.1 Notification Channels

11.2 SMS Provider Requirements
Must support Moroccan phone numbers (+212)
Must provide Sender ID (check license requirements with ANRT)
All SMS events logged in sms_logs table for dispute handling
Recommended providers: Twilio, Vonage, or local Moroccan gateway


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