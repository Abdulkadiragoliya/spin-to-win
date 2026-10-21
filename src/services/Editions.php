<?php
namespace abdulkadiragoliya\spintowin\services;

use Craft;
use abdulkadiragoliya\spintowin\SpinToWin;
use yii\base\Component;
use yii\web\ForbiddenHttpException;

/**
 * Edition & Feature Gatekeeper Service
 *
 * Handles Lite and Pro edition verification and server-side feature access control.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Editions extends Component
{
    public const FEATURE_SCHEDULING = 'scheduling';
    public const FEATURE_ADVANCED_TRIGGERS = 'advanced_triggers';
    public const FEATURE_ADVANCED_RESTRICTIONS = 'advanced_restrictions';
    public const FEATURE_EMAIL_DELIVERY = 'email_delivery';
    public const FEATURE_EMAIL_VERIFICATION = 'email_verification';
    public const FEATURE_ADVANCED_REPORTS = 'advanced_reports';
    public const FEATURE_CSV_EXPORT = 'csv_export';
    public const FEATURE_MULTISITE = 'multisite';
    public const FEATURE_ADVANCED_STYLING = 'advanced_styling';

    /**
     * @var array Feature definitions mapped to required minimum edition
     */
    protected static array $featureMap = [
        self::FEATURE_SCHEDULING => SpinToWin::EDITION_PRO,
        self::FEATURE_ADVANCED_TRIGGERS => SpinToWin::EDITION_PRO,
        self::FEATURE_ADVANCED_RESTRICTIONS => SpinToWin::EDITION_PRO,
        self::FEATURE_EMAIL_DELIVERY => SpinToWin::EDITION_PRO,
        self::FEATURE_EMAIL_VERIFICATION => SpinToWin::EDITION_PRO,
        self::FEATURE_ADVANCED_REPORTS => SpinToWin::EDITION_PRO,
        self::FEATURE_CSV_EXPORT => SpinToWin::EDITION_PRO,
        self::FEATURE_MULTISITE => SpinToWin::EDITION_PRO,
        self::FEATURE_ADVANCED_STYLING => SpinToWin::EDITION_PRO,
    ];

    /**
     * Get the active plugin edition
     *
     * @return string
     */
    public function getEdition(): string
    {
        return SpinToWin::getInstance()->getEdition();
    }

    /**
     * Check if the current edition is Pro (or higher)
     *
     * @return bool
     */
    public function isPro(): bool
    {
        $edition = $this->getEdition();
        if ($edition === 'pro' || $edition === 'plus') {
            return true;
        }
        return SpinToWin::getInstance()->is(SpinToWin::EDITION_PRO, '>=');
    }

    /**
     * Alias for backwards compatibility
     *
     * @return bool
     */
    public function isPlus(): bool
    {
        return $this->isPro();
    }

    /**
     * Check if a specific feature is enabled in the current edition
     *
     * @param string $feature
     * @return bool
     */
    public function hasFeature(string $feature): bool
    {
        if (!isset(self::$featureMap[$feature])) {
            return true;
        }

        $required = self::$featureMap[$feature];
        return SpinToWin::getInstance()->is($required, '>=');
    }

    /**
     * Enforce a feature requirement, throwing ForbiddenHttpException if not entitled.
     *
     * @param string $feature
     * @param string|null $message
     * @throws ForbiddenHttpException
     */
    public function requireFeature(string $feature, ?string $message = null): void
    {
        if (!$this->hasFeature($feature)) {
            $msg = $message ?? Craft::t(
                'spin-to-win',
                'This feature requires the Spin to Win Pro edition.'
            );
            throw new ForbiddenHttpException($msg);
        }
    }

    /**
     * Convenience method to require Pro edition
     *
     * @param string|null $featureName
     * @throws ForbiddenHttpException
     */
    public function requirePro(?string $featureName = null): void
    {
        if (!$this->isPro()) {
            $suffix = $featureName ? " ($featureName)" : '';
            throw new ForbiddenHttpException(
                Craft::t('spin-to-win', 'This action requires the Spin to Win Pro edition{suffix}.', [
                    'suffix' => $suffix,
                ])
            );
        }
    }

    /**
     * Alias for backwards compatibility
     *
     * @param string|null $featureName
     * @throws ForbiddenHttpException
     */
    public function requirePlus(?string $featureName = null): void
    {
        $this->requirePro($featureName);
    }

    /**
     * Returns the full feature matrix for marketing/admin presentation
     *
     * @return array
     */
    public function getFeatureMatrix(): array
    {
        return [
            'lite' => [
                'name' => 'Lite',
                'description' => 'Core spin-to-win prize wheel for engaging visitors and growing sales.',
                'features' => [
                    'Interactive Live SVG Wheel Builder',
                    'Custom Slice Labels, Colors & Weights',
                    'Center Hub Logo Upload or Text',
                    'Display Modes (Modal Popup & Slide Drawers)',
                    'Basic Triggers (Page Load, Time Delay, Manual)',
                    'Craft Commerce Discount Generation',
                    'Daily Spin Limit & Cooldown (24h / Lifetime)',
                    'Standard Dashboard & Spin History',
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'Advanced marketing, automation, deep analytics, and multi-site management.',
                'features' => [
                    'All Lite Features',
                    'Multi-Site Assignment & Regional Targeting',
                    'Per-Site Settings Isolation',
                    'Campaign Start & End Scheduling (Date/Time)',
                    'Advanced Triggers (Scroll %, Exit-Intent, Inactivity)',
                    'Automated Winner Notification Emails',
                    'Double Opt-In Email Verification Tokens',
                    'Advanced Coupon Expiry & Minimum Order Totals',
                    'Comprehensive Analytics & Funnel Reports',
                    'Filterable Reports by Campaign & Site',
                    'Safe CSV Data Export (Spins & Coupons)',
                ],
            ],
        ];
    }
}
