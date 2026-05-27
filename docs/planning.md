# Laravel Billing Starter

A better goal is a reusable Billing Platform Starter Kit.

What companies typically need
Basic payments
One-time purchases
Checkout page
Payment confirmation
Refunds
Invoice generation
Transaction history

Examples:
Selling ebooks
Selling software licenses
Event tickets
Subscription billing
Monthly/yearly plans
Free trial
Upgrade/downgrade
Cancel/reactivate
Proration
Failed payment handling

Examples:
SaaS products
Membership sites
Marketplace payments
Multiple vendors
Revenue sharing
Payouts

Examples:
Upwork-like
Etsy-like

This is much more complex and usually requires special payment provider features.

Industry standards
For modern SaaS applications, typical architecture is:
Flutter App
|
Laravel API
|
Payment Gateway
(Stripe / PayPal)
|
Webhooks
|
Laravel Billing System

Key principle:
Never trust frontend payment success.
Always verify via webhook.
For example:
checkout.session.completed
invoice.paid
invoice.payment_failed
customer.subscription.deleted

Laravel updates its database only after receiving verified events.
What I'd implement in a reusable template
Authentication
Laravel Sanctum

Users table
Roles:
Admin
Customer
Plans
plans

Fields:
name
monthly_price
yearly_price
stripe_price_id
features_json
Subscriptions
subscriptions

Fields:
user_id
provider
provider_subscription_id
status
trial_ends_at
expires_at
Payments
payments

Fields:
user_id
amount
currency
provider
provider_payment_id
status
Invoices
invoices

Store:
invoice number
amount
PDF path
paid date
Webhook Logs

Very important.

webhook_events
Store:
event id
provider
payload
processed_at

Useful for debugging.
Stripe-specific knowledge worth learning
If you've only used subscriptions, I'd additionally learn:
Stripe Checkout
Hosted payment page.
Most SaaS companies use this.

Customer Portal
Allows users to:
Update card
Cancel subscription
Download invoices
Almost free functionality.

Payment Intents
Used for:
Custom checkout
Mobile payments
Important for Flutter apps.

Setup Intents
Save cards without charging.
Useful for future billing.

Connect
Marketplace payments.
Advanced topic.

Other payment providers worth learning
PayPal - Still requested surprisingly often. Useful for freelancers and international clients.
Square - Popular among physical businesses.
Moneris - Common in Canada. Some Canadian clients specifically ask for it.
Paddle - Interesting for SaaS. Handles taxes/VAT automatically. Many indie SaaS founders prefer it.

Laravel Billing Starter
subscriptions
coupons
invoices
webhook processing
customer portal
admin dashboard

Laravel Billing Template

Laravel 13 API
Sanctum authentication
Stripe Checkout
Subscription plans
Webhook processing
Invoice history
Admin dashboard
Flutter demo app consuming the API