<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\models;

use Craft;
use craft\base\Model;

/**
 * Segment Model
 *
 * Represents an individual slice on the promotional prize wheel.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Segment extends Model
{
    public const REWARD_PERCENT_DISCOUNT = 'percent_discount';
    public const REWARD_FIXED_DISCOUNT = 'fixed_discount';
    public const REWARD_FREE_SHIPPING = 'free_shipping';
    public const REWARD_NO_PRIZE = 'no_prize';
    public const REWARD_CUSTOM = 'custom';

    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var int
     */
    public int $campaignId = 0;

    /**
     * @var string
     */
    public string $label = '';

    /**
     * @var string
     */
    public string $rewardType = self::REWARD_NO_PRIZE;

    /**
     * @var float
     */
    public float $discountValue = 0.00;

    /**
     * @var int|null
     */
    public ?int $discountId = null;

    /**
     * @var int Relative weight for probability calculation (min 1)
     */
    public int $probabilityWeight = 10;

    /**
     * @var int Number of prizes allocated per probability spin pool
     */
    public int $allocatedCount = 0;

    /**
     * @var int Total number of times this prize has been awarded historically
     */
    public int $awardedCount = 0;

    /**
     * @var int Number of times this prize has been awarded in current pool cycle
     */
    public int $poolAwardedCount = 0;

    /**
     * @var int Validity period in days
     */
    public int $couponDurationDays = 7;

    /**
     * @var float Minimum order subtotal required to apply discount
     */
    public float $minOrderValue = 0.00;

    /**
     * @var int Maximum redemptions per generated coupon
     */
    public int $maxUsesPerCoupon = 1;

    /**
     * @var string Slice background hex color (e.g. #f43f5e)
     */
    public string $color = '#ef4444';

    /**
     * @var string Text color on the slice (e.g. #ffffff)
     */
    public string $textColor = '#ffffff';

    /**
     * @var int
     */
    public int $sortOrder = 0;

    /**
     * @var bool
     */
    public bool $isEnabled = true;

    /**
     * @var mixed
     */
    public mixed $dateCreated = null;

    /**
     * @var mixed
     */
    public mixed $dateUpdated = null;

    /**
     * @var string|null
     */
    public ?string $uid = null;

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        if ($this->rewardType === 'percentage_discount') {
            $this->rewardType = self::REWARD_PERCENT_DISCOUNT;
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['label', 'color', 'textColor', 'rewardType'], 'required'],
            [['label'], 'string', 'max' => 64],
            [['label'], 'trim'],
            [['rewardType'], 'in', 'range' => [
                self::REWARD_PERCENT_DISCOUNT,
                self::REWARD_FIXED_DISCOUNT,
                self::REWARD_FREE_SHIPPING,
                self::REWARD_NO_PRIZE,
                self::REWARD_CUSTOM,
            ]],
            [['color', 'textColor'], 'match', 'pattern' => '/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', 'message' => Craft::t('spin-to-win', 'Color must be a valid hex code (e.g. #ffffff).')],
            [['campaignId', 'probabilityWeight', 'allocatedCount', 'awardedCount', 'poolAwardedCount', 'couponDurationDays', 'maxUsesPerCoupon', 'sortOrder'], 'integer'],
            [['probabilityWeight'], 'integer', 'min' => 1, 'max' => 1000],
            [['allocatedCount', 'awardedCount', 'poolAwardedCount'], 'integer', 'min' => 0],
            [['couponDurationDays'], 'integer', 'min' => 0, 'max' => 365],
            [['discountValue', 'minOrderValue'], 'number', 'min' => 0],
            [['discountValue'], function($attribute) {
                if ($this->rewardType === self::REWARD_PERCENT_DISCOUNT && ($this->$attribute <= 0 || $this->$attribute > 100)) {
                    $this->addError($attribute, Craft::t('spin-to-win', 'Percentage discount must be between 1% and 100%.'));
                }
                if ($this->rewardType === self::REWARD_FIXED_DISCOUNT && $this->$attribute <= 0) {
                    $this->addError($attribute, Craft::t('spin-to-win', 'Fixed discount amount must be greater than 0.'));
                }
            }],
            [['isEnabled'], 'boolean'],
            [['discountId'], 'integer'],
        ];
    }

    /**
     * Get remaining available prize quota in the active pool cycle
     *
     * @return int
     */
    public function getRemainingPoolQuota(): int
    {
        return max(0, $this->allocatedCount - $this->poolAwardedCount);
    }

    /**
     * Calculate exact win probability percentage based on configured pool size
     * Formula: (Prizes per Pool / Probability Spin Pool) * 100
     *
     * @param int $poolSize
     * @return float
     */
    public function calculateWinProbability(int $poolSize): float
    {
        if ($poolSize <= 0) {
            return 0.0;
        }

        return round(($this->allocatedCount / $poolSize) * 100, 3);
    }

    /**
     * Returns whether this segment represents a genuine winning prize
     *
     * @return bool
     */
    public function getIsPrize(): bool
    {
        return $this->rewardType !== self::REWARD_NO_PRIZE;
    }

    /**
     * Formats the reward label description
     *
     * @return string
     */
    public function getRewardDescription(): string
    {
        return match ($this->rewardType) {
            self::REWARD_PERCENT_DISCOUNT => "{$this->discountValue}% Off Coupon",
            self::REWARD_FIXED_DISCOUNT => "$" . number_format($this->discountValue, 2) . " Off Coupon",
            self::REWARD_FREE_SHIPPING => "Free Shipping Coupon",
            self::REWARD_CUSTOM => "Custom Reward",
            default => "No Prize",
        };
    }
}
