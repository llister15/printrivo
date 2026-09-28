# Printrivo Product Specification

## Company

Mber Digital

## Product

Printrivo

## Description

Printrivo is an online collection of tools designed for print shops,
apparel decorators, designers, and businesses preparing artwork for print.

## Included Tools

### Printrivo Gang Sheet Builder

Allows users to prepare and arrange artwork for DTF gang sheet production at
either 16-inch or 22-inch width, with matching usage details and PNG exports.

### Printrivo Image Studio

Allows users to prepare customer artwork for printing.

Features include:

- Background removal
- Image cleanup
- Image enhancement
- Image upscaling

### Printrivo QR Generator

Allows users to generate high-quality QR codes suitable for printed products.

## Business Model

Subscription SaaS.

### Standard Monthly Plan

$9.99/month for all three tools.

### Alpha Early-Adopter Discount

Enter Alpha in the optional discount-code field at registration for $4.99/month
while continuously subscribed. A dismissible banner advertises this offer.
The code is validated and saved on the account for future checkout. Registration
does not collect payment or activate a subscription; Stripe billing is deferred.

## Primary Conversion Funnel

Landing Page
→ Pricing
→ Create Account
→ Stripe Checkout
→ Payment
→ Account Activated
→ Dashboard
→ Tools

Existing user:

Login
→ Dashboard
→ Tools

Logged-in user without subscription:

Login
→ Subscription Required
→ Checkout
→ Dashboard

## Main Selling Points

- Multiple print tools under one subscription
- Built specifically around print-production workflows
- Simple interface
- No need to purchase multiple applications
- Affordable monthly pricing with an Alpha early-adopter discount
- Additional tools can be added in future versions

## Landing Page CTA

Get Started for $9.99/mo

## Brand

Mber Digital should appear as the company behind the application.

Suggested footer:

Printrivo
Professional print-production tools from Mber Digital.

© Mber Digital

Public marketing and registration display $9.99/month. The Alpha promotion may
be mentioned without publishing its discounted amount; show $4.99/month only
in the account confirmation when an Alpha code has been saved.

 
## PayPal sandbox implementation — September 18, 2026

The current authorized billing provider is PayPal, sandbox first. Stripe remains
deferred. Server-side checkout, signature-verified webhooks, payment-status
checks, cancellation, and environment-specific entitlements are implemented.
Checkout remains disabled until sandbox credentials, webhook ID, and both
monthly plan IDs are configured. See docs/PAYPAL_SETUP.md (PAYPAL_SETUP.md from
this directory) for configuration and acceptance testing.

Standard pricing remains $9.99 USD/month. A saved Alpha registration code selects
the $4.99 monthly plan on the server. Registration and approval redirects never
grant access. Confirmed payment is required. After a paid Alpha subscription is
canceled, a replacement subscription uses the standard plan. Sandbox testing
must use a separate database from a future live deployment.
