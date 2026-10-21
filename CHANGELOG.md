# Release Notes for Spin to Win for Craft CMS

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## 1.0.0 - 2026-10-21

### Added
- **Initial official release** of **Spin to Win** for Craft CMS 4 & 5.
- **Two Commercial Editions**:
  - **Lite Edition ($69)**:
    1. Interactive Live SVG Wheel Builder
    2. Custom Slice Labels, Colors & Weights
    3. Center Hub Logo Upload or Text
    4. Display Modes (Modal Popup & Slide Drawers)
    5. Basic Triggers (Page Load, Time Delay, Manual)
    6. Craft Commerce Discount Generation
    7. Daily Spin Limit & Cooldown (24h / Lifetime)
    8. Standard Dashboard & Spin History
  - **Pro Edition ($99)**:
    *(Includes all 8 Lite features plus 10 advanced features)*:
    9. Multi-Site Assignment & Regional Targeting
    10. Per-Site Settings Isolation
    11. Campaign Start & End Scheduling (Date/Time)
    12. Advanced Triggers (Scroll %, Exit-Intent, Inactivity)
    13. Automated Winner Notification Emails
    14. Double Opt-In Email Verification Tokens
    15. Advanced Coupon Expiry & Minimum Order Totals
    16. Comprehensive Analytics & Funnel Reports
    17. Filterable Reports by Campaign & Site
    18. Safe CSV Data Export (Spins & Coupons)
- **Multi-Site Architecture**:
  - Global vs Site-Specific campaign targeting.
  - Isolated per-site settings storage under `siteSettings[<handle>]` (prevents cross-site setting overwriting).
  - Modern CP top header site switcher bar with handle pills, active indicators, and global scope.
  - Site-filtered campaigns, dashboard analytics, and CSV data exports.
  - Automatic front-end storefront site resolution.
- **Probability Spin Pool Engine**:
  - **Guaranteed Quota Mode (`quota_guaranteed`)**: Strict prize caps per cycle with live depletion tracking.
  - **Probability Random Mode (`probability_random`)**: Independent cryptographic odds roll per spin.
  - **Pool Cycle Actions**: Automatic rollover quota replenishment or campaign deactivation.
- **Native Craft Commerce Integration**:
  - Dynamic generation of authentic Craft Commerce single-use discount coupons (% discount, fixed currency discount, free shipping).
  - One-click coupon code copy button and "Apply to Cart & Shop" session bridge.
  - Order redemption tracking and coupon lifecycle management.
- **Responsive Frontend Display Modes**:
  - Centered Modal Popup with soft backdrop blur.
  - Slide-in Drawer with **Left**, **Right**, or **Top** origins.
  - Mobile-first responsiveness tested across iPhone SE, iPhone 16, Pixel 9/Pro, iPad, and desktop viewports.
- **Display Triggers**:
  - Immediate page load.
  - Configurable time delay (seconds).
  - Scroll depth percentage threshold (Pro).
  - Exit-intent cursor detection (Pro).
  - User inactivity timer (Pro).
  - Custom storefront buttons with `data-stw-trigger="<handle>"`.
  - Programmatic JavaScript trigger `window.openSpinToWinWidget('<handle>')`.
- **Anti-Abuse & Compliance**:
  - 24-hour daily limits, lifetime limits, and custom cooldown periods per normalized email.
  - GDPR consent checkbox with configurable terms text and privacy policy links.
  - Double opt-in verification tokens for unverified entries.
  - Server-authoritative cryptographic RNG (`random_int`) to prevent client-side outcome manipulation.
- **Control Panel Campaign Builder**:
  - 4-tab intuitive interface: General & Targeting, Welcome & Result Copy, Prize Wheel & Slices Builder, Rules & Scheduling.
  - Live interactive SVG wheel preview reflecting slice colors, labels, and center logo hub in real-time.
- **Reporting & Analytics**:
  - 5-stage conversion funnel: Impressions → Opens → Completed Spins → Winners → Commerce Redemptions.
  - 14-day daily spin activity timeline chart.
  - Prize type breakdown distribution.
  - Live segment quota tracker table.
  - CSV export streaming for spins and generated coupons.
- **Headless & REST API Actions**:
  - JSON actions for headless storefronts (Next.js, Nuxt, Astro, Vue, React).
