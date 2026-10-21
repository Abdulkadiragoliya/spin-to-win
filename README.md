# Spin to Win for Craft CMS & Craft Commerce

> High-converting, gamified promotional prize wheel and automated coupon system for Craft CMS 4 & 5 with native Craft Commerce integration.

[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-4.x%20%7C%205.x-E5422B.svg?style=flat-square)](https://craftcms.com/)
[![Craft Commerce](https://img.shields.io/badge/Craft%20Commerce-4.x%20%7C%205.x-38B2AC.svg?style=flat-square)](https://craftcms.com/commerce)
[![PHP](https://img.shields.io/badge/PHP-8.0.2%2B-777BB4.svg?style=flat-square)](https://php.net/)
[![License](https://img.shields.io/badge/License-Craft%20License-blue.svg?style=flat-square)](https://plugins.craftcms.com/)
[![Editions](https://img.shields.io/badge/Editions-Lite%20%7C%20Pro-4F46E5.svg?style=flat-square)](https://plugins.craftcms.com/)

---

## Table of Contents

- [Overview](#overview)
- [Editions & Feature Matrix](#editions--feature-matrix)
- [Requirements](#requirements)
- [Installation](#installation)
- [Multi-Site Architecture (Pro Edition)](#multi-site-architecture-pro-edition)
- [Probability Spin Pool Engine](#probability-spin-pool-engine)
- [Frontend Quick Start & Twig Guide](#frontend-quick-start--twig-guide)
  - [1. Automatic Placement](#1-automatic-placement)
  - [2. Default Active Campaign](#2-default-active-campaign)
  - [3. Targeting Specific Campaigns by Handle](#3-targeting-specific-campaigns-by-handle)
  - [4. Inline Parameter Overrides](#4-inline-parameter-overrides)
  - [5. Custom Storefront Buttons & Links (`data-stw-trigger`)](#5-custom-storefront-buttons--links-data-stw-trigger)
  - [6. Programmatic JavaScript API & Custom Events](#6-programmatic-javascript-api--custom-events)
  - [7. Instant Testing Mode (`?spin=1`)](#7-instant-testing-mode-spin1)
  - [8. Twig Helper Functions](#8-twig-helper-functions)
- [Control Panel Campaign Builder: Complete Field Guide](#control-panel-campaign-builder-complete-field-guide)
  - [Tab 1: General & Targeting](#tab-1-general--targeting)
  - [Tab 2: Welcome & Result Text](#tab-2-welcome--result-text)
  - [Tab 3: Prize Wheel & Slices](#tab-3-prize-wheel--slices)
  - [Tab 4: Rules, Limits & Scheduling](#tab-4-rules-limits--scheduling)
- [Native Craft Commerce Integration](#native-craft-commerce-integration)
- [Automated Winner Email Delivery](#automated-winner-email-delivery)
- [Analytics, Funnels & CSV Data Export](#analytics-funnels--csv-data-export)
- [Headless & AJAX REST Endpoints](#headless--ajax-rest-endpoints)
- [Security & Cryptography](#security--cryptography)
- [Local Development & License Testing](#local-development--license-testing)
- [Troubleshooting & FAQs](#troubleshooting--faqs)
- [Changelog](#changelog)
- [Support & Feedback](#support--feedback)

---

## Overview

**Spin to Win** turns casual storefront visitors into engaged paying customers through interactive gamification. Visitors submit their email address for a chance to spin the wheel and win genuine Craft Commerce discount coupons, free shipping, or custom promotional rewards.

### Key Highlights

- **100% Server-Authoritative Cryptographic RNG**: Outcome slices and coupon values are calculated and verified strictly on the server using PHP `random_int()`—never manipulable from client browser tools.
- **Craft Commerce Deep Integration**: Automatically creates authentic Craft Commerce discount coupon codes and provides one-click cart application.
- **Multi-Site Support (Pro Edition)**: Run campaigns globally across all storefronts or isolate wheels to specific sites with dedicated per-site settings, reporting, and dropdown scoping.
- **Smart Probability Pool System**: Choose between depleting capped prize pools (guaranteed win quotas per cycle) or independent probabilistic percentages.
- **Multiple Display Modes**: Centered modal popup or slide-in drawers (**Left**, **Right**, or **Top**) with responsive styling optimized for mobile phones (iPhone SE, iPhone 16, Pixel 9/Pro), tablets, and high-res desktops.
- **Flexible Triggers**: Display immediately on page load, after time delay, on scroll percentage depth, on exit-intent, after user inactivity, or via custom storefront trigger buttons.
- **Double Opt-In Email Verification (Pro Edition)**: Prevent fake or mistyped emails by issuing secure verification tokens before coupons unlock.
- **CSV Data Export & Analytics**: Monitor a 5-stage conversion funnel and export spin logs and customer emails with one click.

---

## Editions & Feature Matrix

Spin to Win is available in two commercial editions through the **Craft Plugin Store**:

| Feature | 🟢 Lite Edition ($69) | 💎 Pro Edition ($99) |
| :--- | :---: | :---: |
| **Price** | **$69** *(renews at $29/yr)* | **$99** *(renews at $39/yr)* |
| **Interactive Live SVG Wheel Builder** | ✅ | ✅ |
| **Custom Slice Labels, Colors & Weights** | ✅ | ✅ |
| **Center Hub Logo Upload or Text** | ✅ | ✅ |
| **Display Modes (Modal Popup & Slide Drawers)** | ✅ | ✅ |
| **Basic Triggers (Page Load, Time Delay, Manual)** | ✅ | ✅ |
| **Craft Commerce Discount Generation** | ✅ | ✅ |
| **Daily Spin Limit & Cooldown (24h / Lifetime)** | ✅ | ✅ |
| **Standard Dashboard & Spin History** | ✅ | ✅ |
| **Multi-Site Assignment & Regional Targeting** | ❌ | ✅ |
| **Per-Site Settings Isolation** | ❌ | ✅ |
| **Campaign Start & End Scheduling (Date/Time)** | ❌ | ✅ |
| **Advanced Triggers (Scroll %, Exit-Intent, Inactivity)** | ❌ | ✅ |
| **Automated Winner Notification Emails** | ❌ | ✅ |
| **Double Opt-In Email Verification Tokens** | ❌ | ✅ |
| **Advanced Coupon Expiry & Minimum Order Totals** | ❌ | ✅ |
| **Comprehensive Analytics & Funnel Reports** | ❌ | ✅ |
| **Filterable Reports by Campaign & Site** | ❌ | ✅ |
| **Safe CSV Data Export (Spins & Coupons)** | ❌ | ✅ |

> **Commercial Licensing**:
> On production domains, plugin editions are enforced and verified by the official Craft Plugin Store. To run the Pro edition on production, purchase a Pro license ($99) in the Craft Control Panel under **Plugin Store** or **Settings → Plugins**.
> *(For testing locally on `.test`, `.local`, or `localhost`, you can test either edition freely without a commercial license key).*

---

## Requirements

- **Craft CMS**: `^4.0.0` or `^5.0.0`
- **Craft Commerce**: `^4.0.0` or `^5.0.0` *(optional, recommended for discount coupon automation)*
- **PHP**: `>= 8.0.2` (PHP 8.2+ recommended for Craft 5)
- **Database**: MySQL 5.7.8+ / MySQL 8.0+ / MariaDB 10.3+ / PostgreSQL 12+

---

## Installation

### Via Craft Plugin Store (Recommended)

1. In your Craft Control Panel, navigate to **Plugin Store**.
2. Search for **Spin to Win**.
3. Choose your desired edition (**Lite** for $69 or **Pro** for $99) and click **Try** or **Buy**.

### Via Composer

```bash
composer require abdulkadiragoliya/spin-to-win
php craft plugin/install spin-to-win
```

---

## Multi-Site Architecture (Pro Edition)

In multi-site Craft installations, Spin to Win Pro provides native, comprehensive site isolation:

### 1. Global vs Site-Specific Campaigns
When creating a campaign, the **Target Site** selector allows you to:
- **All Sites (Global)**: Campaign runs universally across every site in your Craft installation.
- **Specific Site Target**: Restrict the promotional wheel exclusively to a specific storefront (e.g., `Store 1` or `Store 2`).

### 2. Isolated Per-Site Settings
Under **CP → Spin to Win → Settings**, settings are saved independently per site:
- Sender Name & Sender Email Address (e.g. `orders@store1.com` vs `sales@store2.com`).
- Reply-To Email.
- Impression tracking toggle.
- Data retention rules (auto-pruning old spin logs).

### 3. Top Header Site Switcher Bar
A custom popover switcher in the CP header lets you seamlessly filter between **All Sites (Global)** and individual sites. All views automatically update based on your selected site:
- **Dashboard**: Filter metrics, recent spin attempts, and won coupons.
- **Campaigns**: Filter active/inactive campaigns assigned to the active site or global.
- **Reports**: View site-isolated conversion funnels, daily activity, and export site-filtered CSV data.
- **Settings**: Manage site-specific email and configuration fields without overwriting other sites.

### 4. Automatic Storefront Resolution
On the front-end, `{{ craft.spinToWin.render() }}` automatically detects the visitor's current site. It serves site-targeted campaigns first; if none match, it gracefully falls back to global campaigns.

---

## Probability Spin Pool Engine

Spin to Win includes an enterprise-grade probability engine that prevents profit erosion:

### 1. Guaranteed Quota Mode (`quota_guaranteed`)
- **Strict Prize Caps**: Within a cycle of e.g. 1,000 spins, you can guarantee that a 50% Off grand prize is awarded *exactly 2 times*.
- **Live Depletion**: Each winning spin decrements the remaining quota. When a slice reaches 0, it can no longer be won during the active cycle.
- **Cycle Action**: Configure what happens when a pool cycle completes:
  - **Rollover**: Automatically reset awarded counts and replenish quotas for cycle #2.
  - **Deactivate**: Turn off the campaign until reviewed by an administrator.

### 2. Probability Random Mode (`probability_random`)
- **Independent Odds**: Every spin executes an independent cryptographic roll against the mathematical odds (`Allocated Count / Pool Size`).
- **Ideal for High-Volume Stores**: Ensures fairness without depleting quotas early in a sales cycle.

---

## Frontend Quick Start & Twig Guide

### 1. Automatic Placement

Add the `{{ craft.spinToWin.render() }}` tag to your primary storefront layout template before the closing `</body>` tag (typically `templates/_layout.twig` or `templates/_layouts/base.twig`):

```twig
{# templates/_layout.twig #}
<!DOCTYPE html>
<html lang="{{ craft.app.language }}">
<head>
    <meta charset="utf-8">
    <title>{{ siteName }}</title>
</head>
<body>
    {% block content %}{% endblock %}

    {# Render Spin to Win Widget #}
    {{ craft.spinToWin.render() }}
</body>
</html>
```

---

### 2. Default Active Campaign

If no handle is provided, the plugin automatically renders the first active campaign eligible for the current site:

```twig
{{ craft.spinToWin.render() }}
```

---

### 3. Targeting Specific Campaigns by Handle

If you run multiple campaigns simultaneously (e.g. `welcome-wheel`, `black-friday`, `vip-club`), pass the campaign handle:

```twig
{{ craft.spinToWin.render('black-friday-prize-wheel') }}
```

---

### 4. Inline Parameter Overrides

Override campaign settings directly from Twig:

```twig
{{ craft.spinToWin.render('black-friday-prize-wheel', {
    displayMode: 'sidebar',         {# 'popup' or 'sidebar' #}
    sidebarPosition: 'right',       {# 'left', 'right', or 'top' #}
    triggerType: 'time',            {# 'load', 'time', 'scroll', 'exit', 'inactivity', 'manual' #}
    triggerDelay: 4,                {# seconds #}
    scrollThreshold: 35             {# scroll depth % #}
}) }}
```

---

### 5. Custom Storefront Buttons & Links (`data-stw-trigger`)

Trigger the promotional wheel from anywhere on your storefront using HTML data attributes:

```html
<!-- Floating promotional button or banner link -->
<button type="button" class="btn btn-promotional" data-stw-trigger="black-friday-prize-wheel">
    🎁 Spin the Wheel to Win 20% Off!
</button>
```

When clicked, the wheel opens immediately even if the user has previously seen or closed the popup.

---

### 6. Programmatic JavaScript API & Custom Events

Trigger the wheel dynamically from your frontend scripts:

```javascript
// Global JavaScript helper
if (window.openSpinToWinWidget) {
    window.openSpinToWinWidget('black-friday-prize-wheel');
}

// Or dispatch standard DOM Event
document.dispatchEvent(new CustomEvent('stw:open', {
    detail: { handle: 'black-friday-prize-wheel' }
}));
```

---

### 7. Instant Testing Mode (`?spin=1`)

To test and view your wheel at any time without clearing cookies or resetting campaign session storage:
Simply append `?spin=1` or `?test_spin=1` to any storefront URL:

```
https://yourstore.test/?spin=1
https://yourstore.test/shop/products?spin=1
```

This bypasses all cooldown checks and triggers the widget immediately.

---

### 8. Twig Helper Functions

Access campaign data, plugin edition status, and settings directly in your templates:

```twig
{# Check current edition #}
{% set edition = craft.spinToWin.getEdition() %} {# 'lite' or 'pro' #}
{% set isPro = craft.spinToWin.isPro() %}

{# Fetch active campaigns for current site #}
{% set activeCampaigns = craft.spinToWin.getActiveCampaigns() %}

{# Fetch campaign model by handle #}
{% set campaign = craft.spinToWin.getCampaign('holiday-promotional-wheel') %}

{# Inspect plugin settings for current site #}
{% set settings = craft.spinToWin.getSettings() %}
```

---

## Control Panel Campaign Builder: Complete Field Guide

Manage campaigns under **CP → Spin to Win → Campaigns**. The Campaign Builder features 4 comprehensive tabs:

```
┌────────────────────────┬──────────────────────────┬────────────────────────────┬────────────────────────────┐
│ ⚙️ General & Targeting │ 📄 Welcome & Result Text │ 🎡 Prize Wheel & Slices    │ 🛡️ Rules, Limits & Sched   │
└────────────────────────┴──────────────────────────┴────────────────────────────┴────────────────────────────┘
```

---

### Tab 1: General & Targeting

| Field | Handle | Type | Description |
| :--- | :--- | :--- | :--- |
| **Campaign Name** | `name` | Text | Internal descriptive name (e.g., *"Black Friday 2026 Prize Wheel"*). |
| **Campaign Handle** | `handle` | Text | Unique identifier used in Twig `{{ craft.spinToWin.render('handle') }}`. |
| **Description** | `description` | Textarea | Optional internal notes for marketing staff. |
| **Enable Campaign** | `enabled` | Lightswitch | Toggle campaign active / inactive. |
| **Target Site** | `siteId` | Select *(Pro)* | Choose **All Sites (Global)** or restrict to a specific site in your multi-site setup. |
| **Display Mode** | `displayMode` | Select | Choose **Modal Popup** (centered responsive card) or **Slide-in Sidebar** (drawer). |
| **Sidebar Position** | `sidebarPosition` | Select | Choose drawer origin: **Left**, **Right**, or **Top** (full-width top slide down). |
| **Trigger Type** | `triggerType` | Select | When to display: <br>• **Page Load**: Immediately on page load.<br>• **Time Delay**: After X seconds.<br>• **Scroll Depth** *(Pro)*: After scrolling X% down.<br>• **Exit Intent** *(Pro)*: When visitor moves cursor toward browser window close.<br>• **User Inactivity** *(Pro)*: After visitor is idle for X seconds.<br>• **Manual Only**: Only when clicking a `data-stw-trigger` button. |
| **Trigger Delay** | `triggerDelay` | Number | Delay in seconds for time trigger. |
| **Scroll Threshold** | `scrollThreshold`| Number | Scroll percentage (1–100%) for scroll depth trigger. |

---

### Tab 2: Welcome & Result Text

| Field | Handle | Type | Description |
| :--- | :--- | :--- | :--- |
| **Welcome Title** | `welcomeTitle` | Text | Primary headline on the welcome view (e.g., *"Spin the Wheel to Win!"*). |
| **Welcome Text** | `welcomeText` | Textarea | Persuasive instructions or promotional terms. |
| **Spin Button Text** | `buttonText` | Text | Text on the main spin button (e.g., *"Try My Luck!"*). |
| **Require Email** | `requireEmail` | Lightswitch | Require visitor to provide a valid email before spinning. |
| **Require Name** | `requireName` | Lightswitch | Ask for customer's first name. |
| **Require Consent** | `requireConsent`| Lightswitch | GDPR/privacy policy consent checkbox. |
| **Consent Text** | `consentText` | Textarea | Privacy policy disclosure text with HTML links. |
| **Require Verification** | `requireVerification` | Lightswitch *(Pro)* | Issue double opt-in email verification token before coupon unlocks. |
| **Result Title** | `resultTitle` | Text | Headline displayed on winning spin (e.g., *"Congratulations!"*). |
| **Result Text** | `resultText` | Textarea | Next-step instructions, coupon code, and discount details. |

---

### Tab 3: Prize Wheel & Slices

#### 1. Probability Spin Pool Configuration
- **Probability Spin Pool** (`spinPoolSize`): Total spins per pool cycle (100, 1,000, 5,000, 10,000, etc.).
- **Prize Allocation Mode** (`allocationMode`):
  - **Guaranteed Quota Mode (`quota_guaranteed`)**: Strict prize caps per pool cycle.
  - **Probability Random Mode (`probability_random`)**: Independent odds per spin.
- **Pool Cycle Action** (`poolStrategy`):
  - **Rollover & Replenish Quotas**: Automatically start next cycle with fresh quotas.
  - **Deactivate Campaign**: Pause the campaign when pool completes.

#### 2. Visual Customization & Logo Hub
- **Outer Rim Color & Width**: Border color and thickness framing the wheel.
- **Pointer Pin Color**: Stylized pointer arrow pinned at the top center.
- **Center Logo Hub**: Upload your store brand logo asset or customize center hub text and colors.

#### 3. Slice Table & Live SVG Preview
Add, delete, reorder, and configure wheel segments with live interactive preview:
- **Slice Label**: Text shown on the wheel slice.
- **Colors**: Hex background and text color pickers.
- **Prize Type**:
  - `percent_discount`: Generates a real Craft Commerce % discount coupon (e.g., 10%, 15%, 25%).
  - `fixed_discount`: Generates a real Craft Commerce fixed currency discount coupon (e.g., $5, $10, $50).
  - `free_shipping`: Generates a 100% Free Shipping Commerce coupon code.
  - `no_prize`: A "Try Again" / non-winning slice.
  - `custom`: Custom reward text (e.g., "Free Gift with Order").
- **Discount Amount**: Numeric value for percentage or fixed discount.
- **Min Order Value**: Minimum cart subtotal required to use coupon *(Pro)*.
- **Coupon Duration (Days)**: Coupon validity period before expiring *(Pro)*.
- **Allocated Count**: Number of times this prize can be won within the pool cycle.
- **Calculated Probability %**: Automatically computes exact odds based on your Pool Size.
- **Enabled**: Toggle individual slices on/off without deleting them.

---

### Tab 4: Rules, Limits & Scheduling

| Field | Handle | Type | Description |
| :--- | :--- | :--- | :--- |
| **Spin Limit Policy** | `spinLimitPolicy` | Select | • **Once per 24 Hours** (daily limit)<br>• **Once per Lifetime** (single spin forever)<br>• **Unlimited (Testing Only)** |
| **Start Date & Time** | `startDate` | DateTime *(Pro)* | Date/time when campaign automatically begins displaying. |
| **End Date & Time** | `endDate` | DateTime *(Pro)* | Date/time when campaign automatically stops displaying. |

---

## Native Craft Commerce Integration

Spin to Win integrates natively with **Craft Commerce**:
1. When a visitor lands on a discount slice, an **authentic single-use discount coupon code** is dynamically created inside Craft Commerce.
2. The coupon code is tied to the winning parameters (percentage, fixed amount, or free shipping).
3. The winner screen displays the coupon code with a one-click **"Copy Code"** button.
4. Clicking **"Apply & Shop Now"** automatically stores the coupon in the customer's active Commerce cart session, applying the discount immediately at checkout.

---

## Automated Winner Email Delivery (Pro Edition)

Ensure customers never lose their discount code:
- Automatically delivers a branded transactional email to the winning customer.
- Contains the unique Commerce coupon code, discount summary, and direct link to the store with the coupon pre-applied.
- Configurable sender name, reply-to address, and email templates under **CP → Spin to Win → Settings**.
- Full multi-site isolation: Each site in your Craft installation can use its own sender address and brand signature.

---

## Analytics, Funnels & CSV Data Export

Monitor your campaign conversion funnel under **CP → Spin to Win → Reports**:

### 1. 5-Stage Conversion Funnel
1. **Impressions**: Wheel trigger evaluated / rendered on storefront.
2. **Wheel Opens**: Modal popup viewed or drawer opened.
3. **Completed Spins**: Emails collected & wheels spun.
4. **Winning Spins**: Spins resulting in a prize / coupon.
5. **Commerce Redemptions**: Coupons used at checkout on completed orders.

### 2. Overview Metrics & Charts
- **Total Spins & Win Rate %**
- **Total Coupons & Redemption Rate %**
- **Daily Activity Timeline (Last 14 Days)**
- **Reward Breakdown** (Percentage, Fixed, Free Shipping, Custom)
- **Live Segment Quota Tracker** (Allocated vs Awarded)

### 3. Filterable Reports
Filter all metrics, funnels, charts, and tables by **Campaign** and **Site Scope**.

### 4. CSV Data Export Streaming (Pro Edition)
- **Export Spins CSV**: Full spin attempts with Spin ID, Campaign, Email, Customer Name, Outcome, Reward Type, Coupon Code, IP Address, Verification Status, and Timestamp.
- **Export Coupons CSV**: Coupon ID, Campaign, Code, Discount Type, Discount Amount, Min Order Value, Expiry Date, Redemption Status, Order ID, and Redeemed At.

---

## Headless & AJAX REST Endpoints

For headless frontends (Next.js, Nuxt, Astro, Vue, React, Gatsby), Spin to Win provides clean JSON API actions:

### 1. Execute a Spin
`POST /actions/spin-to-win/spins/spin`

**Request Body (JSON or Form Data):**
```json
{
  "campaignId": 2,
  "email": "customer@example.com",
  "name": "Jane Doe",
  "consent": 1,
  "CRAFT_CSRF_TOKEN": "..."
}
```

**Response (JSON):**
```json
{
  "success": true,
  "isWinner": true,
  "winningSegmentIndex": 2,
  "rewardType": "percent_discount",
  "rewardLabel": "15% Off Your Order",
  "couponCode": "SPIN-A8F2K9",
  "discountAmount": 15,
  "message": "Congratulations! You won 15% Off Your Order!"
}
```

### 2. Log Widget Event
`POST /actions/spin-to-win/spins/log-event`
- Parameters: `campaignId`, `eventType` (`impression` or `open`)

### 3. Apply Coupon to Commerce Cart
`POST /actions/spin-to-win/spins/apply-coupon`
- Parameters: `couponCode`

### 4. Email Verification Token
`GET /actions/spin-to-win/spins/verify-email?token=...`

---

## Security & Cryptography

- **Cryptographic Server-Side RNG**: Uses PHP's CSPRNG `random_int()` to compute winning slices. The frontend cannot manipulate the outcome.
- **Input Sanitization & Normalization**: Emails are normalized (lowercased, whitespace-stripped) and sanitized against SQL injection and XSS.
- **CSRF Protected**: All actions enforce strict Craft CSRF token validation.
- **Content Security Policy (CSP) Safe**: No unsafe-inline JavaScript execution in templates.

---

## Local Development & License Testing

### Testing Pro Edition Locally
In local development environments (`*.test`, `*.local`, `localhost`), Craft CMS allows testing commercial plugin editions without requiring a paid license key:

To test the **Pro Edition** locally:
```bash
php craft project-config/set plugins.spin-to-win.edition pro --update-timestamp
```
To switch back to **Lite Edition**:
```bash
php craft project-config/set plugins.spin-to-win.edition lite --update-timestamp
```

---

## Troubleshooting & FAQs

### The wheel widget is not appearing on my storefront:
1. Ensure the campaign is **Enabled** in the Control Panel.
2. In multi-site setups, ensure the campaign is targeted to the **current site** or **All Sites (Global)**.
3. If you have already spun today, append `?spin=1` to your URL to bypass frequency limits.
4. Verify that `{{ craft.spinToWin.render() }}` is included in your primary layout template before `</body>`.

### Discount codes are not generating:
1. Verify that **Craft Commerce** is installed and enabled.
2. Verify that the winning slice has a valid **Prize Type** (`percent_discount`, `fixed_discount`, or `free_shipping`) and a discount amount greater than 0.

### Emails are not sending:
1. Verify your Craft CMS mailer settings under **Settings → Email**.
2. For local testing, use tools like **Mailpit** or **Mailhog**.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full release history and notes.

---

## Support & Feedback

- **Author**: Abdulkadir Agoliya
- **GitHub Repository**: [https://github.com/Abdulkadiragoliya/spin-to-win](https://github.com/Abdulkadiragoliya/spin-to-win)
- **Issue Tracker & Discussions**: [https://github.com/Abdulkadiragoliya/spin-to-win/issues](https://github.com/Abdulkadiragoliya/spin-to-win/issues)
