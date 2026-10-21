<?php
namespace abdulkadiragoliya\spintowin\models;

use Craft;
use craft\base\Model;

/**
 * Spin to Win Settings model
 *
 * Supports global default settings and individual per-site settings for multi-site setups.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Settings extends Model
{
    /**
     * @var string Default email sender name
     */
    public string $emailSenderName = '';

    /**
     * @var string Default email sender address
     */
    public string $emailSenderAddress = '';

    /**
     * @var string Default email reply-to address
     */
    public string $emailReplyTo = '';

    /**
     * @var bool Whether to track anonymous visitor impressions
     */
    public bool $trackImpressions = true;

    /**
     * @var bool Whether to prune stale spin logs after retention period
     */
    public bool $pruneOldSpins = false;

    /**
     * @var int Days to retain spin logs if pruning is enabled
     */
    public int $retentionDays = 90;

    /**
     * @var array Site-specific settings map keyed by site handle, e.g. ['default' => [...], 'gravesendSteel' => [...]]
     */
    public array $siteSettings = [];

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['emailSenderName', 'emailSenderAddress', 'emailReplyTo'], 'string'],
            [['emailSenderAddress', 'emailReplyTo'], 'email'],
            [['trackImpressions', 'pruneOldSpins'], 'boolean'],
            [['retentionDays'], 'integer', 'min' => 1],
            [['siteSettings'], 'safe'],
        ];
    }

    /**
     * Get settings array specifically resolved for a site handle
     *
     * @param string|null $siteHandle
     * @return array
     */
    public function getForSite(?string $siteHandle = null): array
    {
        if ($siteHandle === null) {
            $siteHandle = Craft::$app->getSites()->getCurrentSite()->handle;
        }

        $base = [
            'emailSenderName' => $this->emailSenderName,
            'emailSenderAddress' => $this->emailSenderAddress,
            'emailReplyTo' => $this->emailReplyTo,
            'trackImpressions' => $this->trackImpressions,
            'pruneOldSpins' => $this->pruneOldSpins,
            'retentionDays' => $this->retentionDays,
        ];

        if ($siteHandle && !empty($this->siteSettings[$siteHandle]) && is_array($this->siteSettings[$siteHandle])) {
            $override = $this->siteSettings[$siteHandle];
            foreach ($base as $key => $val) {
                if (array_key_exists($key, $override) && $override[$key] !== null) {
                    $base[$key] = $override[$key];
                }
            }
        }

        return $base;
    }
}
