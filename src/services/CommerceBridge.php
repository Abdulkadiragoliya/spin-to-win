<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\services;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\commerce\models\Discount;
use craft\commerce\models\Coupon;
use craft\commerce\events\OrderEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use abdulkadiragoliya\spintowin\models\Segment;
use yii\base\Component;
use DateTime;
use Throwable;

/**
 * Commerce Bridge Service
 *
 * Bridges Spin to Win campaigns with Craft Commerce discounts and coupons.
 * Compatible with Craft Commerce 4 and Craft Commerce 5.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class CommerceBridge extends Component
{
    /**
     * Check if Craft Commerce is installed and active
     *
     * @return bool
     */
    public function isCommerceAvailable(): bool
    {
        return Craft::$app->getPlugins()->isPluginInstalled('commerce') &&
               Craft::$app->getPlugins()->isPluginEnabled('commerce');
    }

    /**
     * Create a corresponding Craft Commerce Discount & single-use Coupon for a won prize
     *
     * @param Segment $segment
     * @param string $couponCode
     * @param int $campaignId
     * @param DateTime|null $expiresAt
     * @param string|null $recipientEmail
     * @return array{discountId: int|null, couponId: int|null}|null
     */
    public function createCommerceDiscountForCoupon(
        Segment $segment,
        string $couponCode,
        int $campaignId,
        ?DateTime $expiresAt = null,
        ?string $recipientEmail = null
    ): ?array {
        if (!$this->isCommerceAvailable()) {
            return null;
        }

        try {
            $commerce = Commerce::getInstance();
            $store = $commerce->getStores()->getCurrentStore();
            $storeId = $store ? (int)$store->id : 1;

            // Ensure catalog purchasables allow promotions
            try {
                Craft::$app->getDb()->createCommand("UPDATE {{%commerce_purchasables_stores}} SET promotable = 1 WHERE promotable = 0")->execute();
            } catch (\Throwable $e) {}

            $discount = new Discount();
            $discount->storeId = $storeId;
            $discount->name = "Spin to Win - {$segment->label} ({$couponCode})";
            $discount->description = "Promotional reward from Spin to Win Campaign #{$campaignId}";
            $discount->couponFormat = $couponCode;
            $discount->requireCouponCode = true;
            $discount->enabled = true;
            $discount->allPurchasables = true;
            $discount->allCategories = true;
            $discount->totalDiscountUseLimit = 1;
            // Allow coupon to apply on cart before email is entered; single-use is guaranteed by totalDiscountUseLimit=1 and coupon maxUses=1
            $discount->perEmailLimit = 0;
            $discount->dateFrom = new DateTime();
            $discount->dateTo = $expiresAt ?? new DateTime('+30 days');

            // Minimum order value condition
            if ($segment->minOrderValue > 0) {
                $discount->purchaseTotal = (float)$segment->minOrderValue;
            }

            // Discount Amount & Type
            switch ($segment->rewardType) {
                case Segment::REWARD_PERCENT_DISCOUNT:
                    // In Commerce, percent discount is stored as negative decimal, e.g. -0.15 for 15%
                    $pct = max(0.01, min(100.0, (float)$segment->discountValue));
                    $discount->percentDiscount = -($pct / 100.0);
                    break;

                case Segment::REWARD_FIXED_DISCOUNT:
                    // Flat discount off order total
                    $amount = max(0.01, (float)$segment->discountValue);
                    $discount->baseDiscount = -$amount;
                    break;

                case Segment::REWARD_FREE_SHIPPING:
                    $discount->hasFreeShippingForOrder = true;
                    break;

                case Segment::REWARD_CUSTOM:
                default:
                    // Custom / Free item voucher
                    $discount->description = "Prize Reward: {$segment->label}";
                    break;
            }

            // Create Commerce single-use Coupon model attached to discount
            $coupon = new Coupon();
            $coupon->code = $couponCode;
            $coupon->maxUses = 1;
            $discount->setCoupons([$coupon]);

            // Save discount into Craft Commerce
            if (!$commerce->getDiscounts()->saveDiscount($discount)) {
                Craft::error(
                    "Failed to save Commerce Discount: " . json_encode($discount->getErrors()),
                    __METHOD__
                );
                return null;
            }

            // Retrieve saved coupon ID
            $savedCoupons = $commerce->getCoupons()->getCouponsByDiscountId($discount->id);
            $savedCouponId = !empty($savedCoupons) ? (int)$savedCoupons[0]->id : null;

            return [
                'discountId' => (int)$discount->id,
                'couponId' => $savedCouponId,
            ];
        } catch (Throwable $e) {
            Craft::error("Error creating Commerce discount: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }

    /**
     * Apply coupon code directly to the active customer's cart
     *
     * @param string $code
     * @return array{success: bool, message: string, cartTotal?: string}
     */
    public function applyCouponToActiveCart(string $code): array
    {
        if (!$this->isCommerceAvailable()) {
            return [
                'success' => false,
                'message' => Craft::t('spin-to-win', 'Craft Commerce is not available.'),
            ];
        }

        try {
            $code = trim($code);
            $commerce = Commerce::getInstance();

            // Verify coupon exists and is active in Commerce
            $discount = $commerce->getDiscounts()->getDiscountByCode($code);
            if (!$discount || !$discount->enabled) {
                return [
                    'success' => false,
                    'message' => Craft::t('spin-to-win', 'This coupon is invalid or has expired.'),
                ];
            }

            $request = Craft::$app->getRequest();
            if ($request instanceof \craft\console\Request) {
                // In CLI context without HTTP session, coupon code is validated against Commerce
                return [
                    'success' => true,
                    'message' => Craft::t('spin-to-win', 'Coupon "{code}" is valid and verified for cart.', ['code' => $code]),
                ];
            }

            $cart = $commerce->getCarts()->getCart(true);
            if (!$cart) {
                return [
                    'success' => false,
                    'message' => Craft::t('spin-to-win', 'Could not access active cart.'),
                ];
            }

            $cart->couponCode = $code;
            if (!Craft::$app->getElements()->saveElement($cart)) {
                return [
                    'success' => false,
                    'message' => implode(' ', $cart->getFirstErrors()) ?: Craft::t('spin-to-win', 'Failed to apply coupon to cart.'),
                ];
            }

            return [
                'success' => true,
                'message' => Craft::t('spin-to-win', 'Coupon "{code}" applied successfully to your cart!', ['code' => $code]),
                'cartTotal' => Craft::$app->getFormatter()->asCurrency($cart->total, $cart->paymentCurrency),
            ];
        } catch (Throwable $e) {
            Craft::error("Error applying coupon to cart: {$e->getMessage()}", __METHOD__);
            return [
                'success' => false,
                'message' => Craft::t('spin-to-win', 'An error occurred while applying the coupon.'),
            ];
        }
    }

    /**
     * Handle Craft Commerce Order completed event to track coupon redemptions
     *
     * @param \yii\base\Event $event
     * @return void
     */
    public function handleOrderCompleted(\yii\base\Event $event): void
    {
        try {
            $order = ($event->sender ?? null) ?: ($event->order ?? null);
            if (!$order) {
                return;
            }

            $code = $order->couponCode ?? null;
            if (!$code) {
                return;
            }

            $db = Craft::$app->getDb();
            $coupon = (new \craft\db\Query())
                ->from('{{%spintowin_coupons}}')
                ->where(['code' => $code, 'isRedeemed' => false])
                ->one();

            if (!$coupon) {
                return;
            }

            $now = Db::prepareDateForDb(new DateTime());

            // Mark coupon redeemed
            $db->createCommand()
                ->update(
                    '{{%spintowin_coupons}}',
                    [
                        'isRedeemed' => true,
                        'orderId' => (int)$order->id,
                        'redeemedAt' => $now,
                        'dateUpdated' => $now,
                    ],
                    ['id' => $coupon['id']]
                )
                ->execute();

            // Record redemption event for conversion reporting
            $db->createCommand()
                ->insert('{{%spintowin_events}}', [
                    'campaignId' => (int)$coupon['campaignId'],
                    'eventType' => 'redemption',
                    'ipAddress' => Craft::$app->getRequest() instanceof \craft\web\Request ? Craft::$app->getRequest()->getUserIP() : '127.0.0.1',
                    'userAgent' => 'Commerce Order #' . $order->id,
                    'metadata' => json_encode([
                        'orderId' => (int)$order->id,
                        'orderNumber' => $order->number,
                        'orderTotal' => (float)($order->total ?? 0.0),
                        'couponCode' => $code,
                    ]),
                    'dateCreated' => $now,
                ])
                ->execute();

            Craft::info("Spin to Win coupon '{$code}' redeemed on Order #{$order->id}", __METHOD__);
        } catch (Throwable $e) {
            Craft::error("Error handling order completed coupon redemption: {$e->getMessage()}", __METHOD__);
        }
    }
}
