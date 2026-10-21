<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\variables;

use Craft;
use craft\helpers\Template;
use Twig\Markup;
use abdulkadiragoliya\spintowin\SpinToWin;
use abdulkadiragoliya\spintowin\models\Campaign;

/**
 * SpinToWin Variable
 *
 * Provides storefront template helpers: {{ craft.spinToWin.render(...) }}
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class SpinToWinVariable
{
    /**
     * Render the promotional prize wheel widget
     *
     * @param string|null $handle Optional campaign handle; defaults to first active campaign
     * @param array $options Optional override settings (displayMode, triggerType, triggerDelay)
     * @return Markup
     */
    public function render(?string $handle = null, array $options = []): Markup
    {
        $plugin = SpinToWin::getInstance();
        $campaign = null;

        $segments = [];
        if ($handle) {
            $campaign = $plugin->campaigns->getCampaignByHandle($handle);
            $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id ?? null;
            if ($campaign && $campaign->getIsActive() && ($campaign->siteId === null || $campaign->siteId === $currentSiteId)) {
                $segments = $plugin->segments->getSegmentsByCampaignId($campaign->id, true);
            } else {
                $campaign = null;
            }
        } else {
            $currentSiteId = Craft::$app->getSites()->getCurrentSite()->id ?? null;
            $active = $plugin->campaigns->getActiveCampaigns($currentSiteId);
            foreach ($active as $candidate) {
                $candidateSegments = $plugin->segments->getSegmentsByCampaignId($candidate->id, true);
                if (!empty($candidateSegments)) {
                    $campaign = $candidate;
                    $segments = $candidateSegments;
                    break;
                }
            }
        }

        if (!$campaign || empty($segments)) {
            return Template::raw('');
        }

        $html = Craft::$app->getView()->renderTemplate('spin-to-win/_frontend/widget', [
            'campaign' => $campaign,
            'segments' => $segments,
            'wheelSettings' => $campaign->getParsedWheelSettings(),
            'options' => $options,
            'csrfTokenName' => Craft::$app->getConfig()->getGeneral()->csrfTokenName,
            'csrfTokenValue' => (Craft::$app->getRequest() instanceof \craft\web\Request) ? Craft::$app->getRequest()->getCsrfToken() : '',
        ]);

        return Template::raw($html);
    }

    /**
     * Get campaign by handle
     *
     * @param string $handle
     * @return Campaign|null
     */
    public function getCampaign(string $handle): ?Campaign
    {
        return SpinToWin::getInstance()->campaigns->getCampaignByHandle($handle);
    }

    /**
     * Get all active campaigns
     *
     * @param int|null $siteId
     * @return Campaign[]
     */
    public function getActiveCampaigns(?int $siteId = null): array
    {
        $siteId = $siteId ?? (Craft::$app->getSites()->getCurrentSite()->id ?? null);
        return SpinToWin::getInstance()->campaigns->getActiveCampaigns($siteId);
    }

    /**
     * Get active plugin edition
     *
     * @return string
     */
    public function getEdition(): string
    {
        return SpinToWin::getInstance()->getEdition();
    }

    /**
     * Check if active edition is Pro
     *
     * @return bool
     */
    public function isPro(): bool
    {
        return SpinToWin::getInstance()->editions->isPro();
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
     * Get settings specifically resolved for current or requested site
     *
     * @param string|null $siteHandle
     * @return array
     */
    public function getSettings(?string $siteHandle = null): array
    {
        $siteHandle = $siteHandle ?? (Craft::$app->getSites()->getCurrentSite()->handle ?? null);
        return SpinToWin::getInstance()->getSettings()->getForSite($siteHandle);
    }
}
