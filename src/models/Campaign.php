<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\models;

use Craft;
use craft\base\Model;
use craft\validators\HandleValidator;
use craft\helpers\DateTimeHelper;
use DateTime;

/**
 * Campaign Model
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Campaign extends Model
{
    public const MODE_QUOTA_GUARANTEED = 'quota_guaranteed';
    public const MODE_PROBABILITY_RANDOM = 'probability_random';
    public const STRATEGY_ROLLOVER = 'rollover';
    public const STRATEGY_RESET = 'reset';

    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string
     */
    public string $name = '';

    /**
     * @var string
     */
    public string $handle = '';

    /**
     * @var string|null
     */
    public ?string $description = null;

    /**
     * @var bool
     */
    public bool $enabled = true;

    /**
     * @var int|null
     */
    public ?int $siteId = null;

    /**
     * @var string 'popup' or 'sidebar'
     */
    public string $displayMode = 'popup';

    /**
     * @var string 'load', 'delay', 'scroll', 'manual'
     */
    public string $triggerType = 'load';

    /**
     * @var int
     */
    public int $triggerDelay = 0;

    /**
     * @var int
     */
    public int $scrollThreshold = 0;

    /**
     * @var DateTime|string|null
     */
    public mixed $startDate = null;

    /**
     * @var DateTime|string|null
     */
    public mixed $endDate = null;

    /**
     * @var string|null
     */
    public ?string $welcomeTitle = 'Spin the Wheel to Win!';

    /**
     * @var string|null
     */
    public ?string $welcomeText = 'Enter your email address to spin the prize wheel and unlock exclusive discounts.';

    /**
     * @var string
     */
    public string $buttonText = 'Spin to Win!';

    /**
     * @var string|null
     */
    public ?string $resultTitle = 'Congratulations!';

    /**
     * @var string|null
     */
    public ?string $resultText = 'Here is your exclusive coupon code:';

    /**
     * @var bool
     */
    public bool $requireEmail = true;

    /**
     * @var bool
     */
    public bool $requireName = false;

    /**
     * @var bool
     */
    public bool $requireConsent = false;

    /**
     * @var string|null
     */
    public ?string $consentText = 'I agree to receive promotional updates and offers.';

    /**
     * @var bool
     */
    public bool $requireVerification = false;

    /**
     * @var string 'daily_per_email', 'unlimited', 'once_per_campaign'
     */
    public string $spinLimitPolicy = 'daily_per_email';

    /**
     * @var int Configured total probability spin pool size (e.g. 100, 1000, 10000)
     */
    public int $spinPoolSize = 1000;

    /**
     * @var string 'quota_guaranteed' or 'probability_random'
     */
    public string $allocationMode = self::MODE_QUOTA_GUARANTEED;

    /**
     * @var int Active pool iteration cycle
     */
    public int $poolCycle = 1;

    /**
     * @var string 'rollover' or 'reset'
     */
    public string $poolStrategy = self::STRATEGY_ROLLOVER;

    /**
     * @var string|null JSON string of wheel configuration
     */
    public ?string $wheelSettings = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var string|null
     */
    public ?string $uid = null;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'trim'],
            [['name'], 'string', 'max' => 255],
            [['handle'], 'match', 'pattern' => '/^[a-zA-Z0-9_-]+$/', 'message' => Craft::t('spin-to-win', 'Handle must contain only letters, numbers, dashes, and underscores.')],
            [['description', 'welcomeTitle', 'welcomeText', 'buttonText', 'resultTitle', 'resultText', 'consentText', 'wheelSettings'], 'string'],
            [['enabled', 'requireEmail', 'requireName', 'requireConsent', 'requireVerification'], 'boolean'],
            [['siteId', 'triggerDelay', 'scrollThreshold', 'spinPoolSize', 'poolCycle'], 'integer'],
            [['spinPoolSize'], 'integer', 'min' => 10, 'max' => 1000000],
            [['poolCycle'], 'integer', 'min' => 1],
            [['displayMode'], 'in', 'range' => ['popup', 'sidebar']],
            [['triggerType'], 'in', 'range' => ['load', 'delay', 'scroll', 'manual']],
            [['spinLimitPolicy'], 'in', 'range' => ['daily_per_email', 'unlimited', 'once_per_campaign']],
            [['allocationMode'], 'in', 'range' => [self::MODE_QUOTA_GUARANTEED, self::MODE_PROBABILITY_RANDOM]],
            [['poolStrategy'], 'in', 'range' => [self::STRATEGY_ROLLOVER, self::STRATEGY_RESET]],
            [['startDate', 'endDate'], 'safe'],
        ];
    }

    /**
     * Returns whether campaign enforces guaranteed quota mode
     *
     * @return bool
     */
    public function getIsQuotaMode(): bool
    {
        return $this->allocationMode === self::MODE_QUOTA_GUARANTEED;
    }

    /**
     * Returns whether the campaign is currently active based on dates and status
     *
     * @return bool
     */
    public function getIsActive(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $now = new DateTime();
        if ($this->startDate && DateTimeHelper::toDateTime($this->startDate) > $now) {
            return false;
        }

        if ($this->endDate && DateTimeHelper::toDateTime($this->endDate) < $now) {
            return false;
        }

        return true;
    }

    /**
     * Get parsed wheel appearance settings with robust defaults
     *
     * @return array
     */
    public function getParsedWheelSettings(): array
    {
        $defaults = [
            'hubText' => 'SPIN',
            'hubColor' => '#ffffff',
            'hubTextColor' => '#0f172a',
            'pointerColor' => '#ef4444',
            'rimColor' => '#1e293b',
            'showRimDots' => true,
            'panelBgColor' => '#ffffff',
            'headlineColor' => '#0f172a',
            'subtextColor' => '#64748b',
            'inputBgColor' => '#ffffff',
            'inputBorderColor' => '#cbd5e1',
            'inputTextColor' => '#0f172a',
            'buttonBgColor' => '#4f46e5',
            'buttonTextColor' => '#ffffff',
        ];

        if (!$this->wheelSettings) {
            return $defaults;
        }

        try {
            $decoded = json_decode($this->wheelSettings, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
        } catch (\Throwable) {
            return $defaults;
        }
    }

    /**
     * Get configured wheel segments for this campaign
     *
     * @param bool $onlyEnabled
     * @return \abdulkadiragoliya\spintowin\models\Segment[]
     */
    public function getSegments(bool $onlyEnabled = false): array
    {
        if (!$this->id) {
            return [];
        }
        return \abdulkadiragoliya\spintowin\SpinToWin::getInstance()->segments->getSegmentsByCampaignId((int)$this->id, $onlyEnabled);
    }
}
