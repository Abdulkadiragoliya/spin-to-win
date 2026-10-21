<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\services;

use Craft;
use yii\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use abdulkadiragoliya\spintowin\SpinToWin;
use abdulkadiragoliya\spintowin\models\Campaign;
use abdulkadiragoliya\spintowin\models\Segment;
use DateTime;
use Throwable;

/**
 * Spins Service (Server-Authoritative Spin Engine)
 *
 * Handles visitor spin rate-limiting, server-side prize determination,
 * coupon generation, and audit logging.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Spins extends Component
{
    /**
     * Create spins query
     *
     * @return Query
     */
    public function createSpinQuery(): Query
    {
        return (new Query())
            ->from('{{%spintowin_spins}}');
    }

    /**
     * Normalize email for safe, reliable deduplication and daily limits
     *
     * @param string $email
     * @return string
     */
    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Check visitor eligibility to spin according to campaign policy
     *
     * @param Campaign $campaign
     * @param string $email
     * @return array ['eligible' => bool, 'reason' => string|null]
     */
    public function checkEligibility(Campaign $campaign, string $email): array
    {
        $normalized = $this->normalizeEmail($email);
        if (empty($normalized) || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return [
                'eligible' => false,
                'reason' => Craft::t('spin-to-win', 'Please enter a valid email address.'),
            ];
        }

        if (!$campaign->getIsActive()) {
            return [
                'eligible' => false,
                'reason' => Craft::t('spin-to-win', 'This campaign is not currently active.'),
            ];
        }

        $policy = $campaign->spinLimitPolicy;

        if ($policy === 'daily_per_email') {
            $today = (new DateTime())->format('Y-m-d');
            $existing = $this->createSpinQuery()
                ->where([
                    'campaignId' => $campaign->id,
                    'emailNormalized' => $normalized,
                    'spinDate' => $today,
                ])
                ->exists();

            if ($existing) {
                return [
                    'eligible' => false,
                    'reason' => Craft::t('spin-to-win', 'You have already spun the wheel today. Come back tomorrow!'),
                ];
            }
        } elseif ($policy === 'once_per_campaign') {
            $existing = $this->createSpinQuery()
                ->where([
                    'campaignId' => $campaign->id,
                    'emailNormalized' => $normalized,
                ])
                ->exists();

            if ($existing) {
                return [
                    'eligible' => false,
                    'reason' => Craft::t('spin-to-win', 'You have already participated in this promotional campaign.'),
                ];
            }
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Execute a server-authoritative promotional spin
     *
     * @param Campaign $campaign
     * @param string $email
     * @param string|null $name
     * @param bool $consent
     * @return array
     * @throws Throwable
     */
    public function executeSpin(Campaign $campaign, string $email, ?string $name = null, bool $consent = false): array
    {
        // 1. Eligibility Check
        $eligibility = $this->checkEligibility($campaign, $email);
        if (!$eligibility['eligible']) {
            return [
                'success' => false,
                'message' => $eligibility['reason'],
            ];
        }

        // 2. Consent Check if required
        if ($campaign->requireConsent && !$consent) {
            return [
                'success' => false,
                'message' => Craft::t('spin-to-win', 'Please agree to the terms to spin.'),
            ];
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $normalizedEmail = $this->normalizeEmail($email);
            $segmentsService = SpinToWin::getInstance()->segments;

            // 3. Load all enabled segments to identify wheel slice index
            $allSegments = $segmentsService->getSegmentsByCampaignId($campaign->id, true);
            if (empty($allSegments)) {
                throw new \Exception('No active wheel slices configured for this campaign.');
            }

            // 4. Server-Authoritative Winning Segment Selection
            $winningSegment = $segmentsService->selectWinningSegment($campaign, false);

            // Find index of winning segment in active slices array (0-indexed for wheel canvas)
            $segmentIndex = 0;
            foreach ($allSegments as $i => $s) {
                if ($s->id === $winningSegment->id) {
                    $segmentIndex = $i;
                    break;
                }
            }

            $isWinner = $winningSegment->getIsPrize();
            $couponCode = null;
            $expiresAt = null;
            $expiresAtFormatted = null;

            // 5. Generate coupon if winning prize
            if ($isWinner) {
                $couponCode = $this->generateCouponCode($winningSegment, $campaign->id);
                $durationDays = max(1, $winningSegment->couponDurationDays);
                $expiresDate = new DateTime("+{$durationDays} days");
                $expiresAt = Db::prepareDateForDb($expiresDate);
                $expiresAtFormatted = Craft::$app->getFormatter()->asDate($expiresDate, 'short');
            }

            $now = Db::prepareDateForDb(new DateTime());
            $today = (new DateTime())->format('Y-m-d');
            $request = Craft::$app->getRequest();
            $isWeb = $request instanceof \craft\web\Request;
            $ipAddress = $isWeb ? $request->getUserIP() : '127.0.0.1';
            $userAgent = $isWeb ? substr((string)$request->getUserAgent(), 0, 255) : 'CLI';
            $currentUser = (Craft::$app->has('user') && Craft::$app->getUser()) ? Craft::$app->getUser()->getIdentity() : null;
            $currentSite = Craft::$app->getSites()->getCurrentSite();
            $siteId = $currentSite ? $currentSite->id : null;

            $isPro = SpinToWin::getInstance()->editions->isPro();
            $requiresVerification = $isPro && (bool)$campaign->requireVerification && $isWinner;
            $verificationToken = StringHelper::randomString(32);
            $isVerified = !$requiresVerification;

            // 6. Record spin in spintowin_spins
            $spinValues = [
                'campaignId' => $campaign->id,
                'segmentId' => $winningSegment->id,
                'email' => trim($email),
                'emailNormalized' => $normalizedEmail,
                'customerId' => $currentUser ? $currentUser->id : null,
                'name' => $name ? trim($name) : null,
                'ipAddress' => $ipAddress,
                'userAgent' => $userAgent,
                'siteId' => $siteId,
                'poolCycle' => $campaign->poolCycle,
                'isWinner' => $isWinner,
                'rewardType' => $winningSegment->rewardType,
                'couponCode' => $couponCode,
                'verificationToken' => $verificationToken,
                'isVerified' => $isVerified,
                'spinDate' => $today,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ];

            $db->createCommand()
                ->insert('{{%spintowin_spins}}', $spinValues)
                ->execute();

            $spinId = (int)$db->getLastInsertID('{{%spintowin_spins}}');

            // 7. If winner, record coupon in spintowin_coupons and sync with Craft Commerce
            $commerceAvailable = SpinToWin::getInstance()->commerce->isCommerceAvailable();
            if ($isWinner && $couponCode) {
                $commerceDiscountId = null;
                $commerceCouponId = null;

                if ($commerceAvailable) {
                    $commerceRes = SpinToWin::getInstance()->commerce->createCommerceDiscountForCoupon(
                        $winningSegment,
                        $couponCode,
                        (int)$campaign->id,
                        $expiresDate ?? null,
                        $normalizedEmail
                    );
                    if ($commerceRes) {
                        $commerceDiscountId = $commerceRes['discountId'];
                        $commerceCouponId = $commerceRes['couponId'];
                    }
                }

                $couponValues = [
                    'spinId' => $spinId,
                    'campaignId' => $campaign->id,
                    'segmentId' => $winningSegment->id,
                    'commerceDiscountId' => $commerceDiscountId,
                    'commerceCouponId' => $commerceCouponId,
                    'code' => $couponCode,
                    'discountType' => $winningSegment->rewardType,
                    'discountAmount' => $winningSegment->discountValue,
                    'minOrderValue' => $winningSegment->minOrderValue,
                    'expiresAt' => $expiresAt,
                    'isRedeemed' => false,
                    'dateCreated' => $now,
                    'dateUpdated' => $now,
                    'uid' => StringHelper::UUID(),
                ];

                $db->createCommand()
                    ->insert('{{%spintowin_coupons}}', $couponValues)
                    ->execute();

                // If double opt-in is required, send verification link; otherwise dispatch winner notification
                if ($requiresVerification) {
                    SpinToWin::getInstance()->emails->sendVerificationEmail(
                        $campaign,
                        $winningSegment,
                        $email,
                        $verificationToken,
                        $name
                    );
                } else {
                    // Dispatch winner notification email (Pro Edition feature)
                    SpinToWin::getInstance()->emails->sendWinnerNotification(
                        $campaign,
                        $winningSegment,
                        $email,
                        $couponCode,
                        $expiresAtFormatted,
                        $name
                    );
                }
            }

            // 8. Record spin event in spintowin_events for conversion analytics
            $db->createCommand()
                ->insert('{{%spintowin_events}}', [
                    'campaignId' => $campaign->id,
                    'eventType' => 'spin',
                    'ipAddress' => $ipAddress,
                    'userAgent' => $userAgent,
                    'metadata' => json_encode(['email' => $normalizedEmail, 'winner' => $isWinner, 'code' => $couponCode]),
                    'dateCreated' => $now,
                ])
                ->execute();

            $transaction->commit();

            return [
                'success' => true,
                'isWinner' => $isWinner,
                'segmentIndex' => $segmentIndex,
                'segment' => [
                    'id' => $winningSegment->id,
                    'label' => $winningSegment->label,
                    'rewardType' => $winningSegment->rewardType,
                    'discountValue' => $winningSegment->discountValue,
                    'rewardDescription' => $winningSegment->getRewardDescription(),
                    'color' => $winningSegment->color,
                    'textColor' => $winningSegment->textColor,
                ],
                'couponCode' => $requiresVerification ? null : $couponCode,
                'requiresVerification' => $requiresVerification,
                'expiresAt' => $expiresAtFormatted,
                'commerceAvailable' => $commerceAvailable && !$requiresVerification,
                'resultTitle' => $requiresVerification 
                    ? Craft::t('spin-to-win', 'Check Your Email!')
                    : ($isWinner ? ($campaign->resultTitle ?: Craft::t('spin-to-win', 'Congratulations!')) : Craft::t('spin-to-win', 'Better Luck Next Time!')),
                'resultText' => $requiresVerification
                    ? Craft::t('spin-to-win', 'We’ve emailed you a confirmation link. Please click it to verify your address and unlock your coupon code.')
                    : ($isWinner ? ($campaign->resultText ?: Craft::t('spin-to-win', 'Here is your promotional coupon:')) : Craft::t('spin-to-win', 'Thank you for playing! Check back soon for more chances to win.')),
            ];
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error("Error executing spin: {$e->getMessage()}\n{$e->getTraceAsString()}", __METHOD__);
            return [
                'success' => false,
                'message' => Craft::t('spin-to-win', 'An unexpected error occurred while spinning. Please try again.'),
            ];
        }
    }

    /**
     * Generate an unambiguous, memorable coupon code
     *
     * @param Segment $segment
     * @param int $campaignId
     * @return string
     */
    public function generateCouponCode(Segment $segment, int $campaignId): string
    {
        $prefix = match ($segment->rewardType) {
            Segment::REWARD_PERCENT_DISCOUNT => 'SAVE' . (int)$segment->discountValue,
            Segment::REWARD_FIXED_DISCOUNT => 'OFF' . (int)$segment->discountValue,
            Segment::REWARD_FREE_SHIPPING => 'FREESHIP',
            default => 'WIN',
        };

        // Generate 6 random uppercase alphanumeric chars (excluding ambiguous 0/O, 1/I)
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $randStr = '';
        for ($i = 0; $i < 6; $i++) {
            $randStr .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return "{$prefix}-{$randStr}";
    }

    /**
     * Record storefront widget impression
     *
     * @param int $campaignId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return bool
     */
    public function recordImpression(int $campaignId, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        try {
            $site = Craft::$app->getSites()->getCurrentSite();
            $siteSettings = SpinToWin::getInstance()->getSettings()->getForSite($site->handle ?? null);
            if (isset($siteSettings['trackImpressions']) && !$siteSettings['trackImpressions']) {
                return false;
            }

            $request = Craft::$app->getRequest();
            $isWeb = $request instanceof \craft\web\Request;
            $ip = $ipAddress ?? ($isWeb ? $request->getUserIP() : '127.0.0.1');
            $ua = $userAgent ?? ($isWeb ? substr((string)$request->getUserAgent(), 0, 255) : 'CLI');
            $now = Db::prepareDateForDb(new DateTime());

            Craft::$app->getDb()->createCommand()
                ->insert('{{%spintowin_events}}', [
                    'campaignId' => $campaignId,
                    'eventType' => 'impression',
                    'ipAddress' => $ip,
                    'userAgent' => $ua,
                    'metadata' => null,
                    'dateCreated' => $now,
                ])
                ->execute();

            return true;
        } catch (\Throwable $e) {
            Craft::error("Failed to record impression: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }
}

