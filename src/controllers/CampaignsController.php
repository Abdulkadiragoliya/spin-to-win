<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\controllers;

use Craft;
use abdulkadiragoliya\spintowin\SpinToWin;
use abdulkadiragoliya\spintowin\models\Campaign;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Campaigns Controller
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class CampaignsController extends BaseCpController
{
    /**
     * List all promotional wheel campaigns
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();
        $isPlus = SpinToWin::getInstance()->editions->isPlus();

        $selectedSiteId = null;
        if ($isPlus) {
            $siteParam = $request->getQueryParam('site') ?? $request->getQueryParam('siteId');
            if ($siteParam !== null && $siteParam !== '') {
                if (is_numeric($siteParam)) {
                    $selectedSiteId = (int)$siteParam;
                } else {
                    $site = Craft::$app->getSites()->getSiteByHandle((string)$siteParam);
                    $selectedSiteId = $site ? $site->id : null;
                }
            }
        }

        $campaigns = SpinToWin::getInstance()->campaigns->getAllCampaigns($selectedSiteId);

        return $this->renderTemplate('spin-to-win/campaigns/index', [
            'title' => Craft::t('spin-to-win', 'Campaigns'),
            'selectedSubnavItem' => 'campaigns',
            'campaigns' => $campaigns,
            'isPlus' => $isPlus,
            'selectedSiteId' => $selectedSiteId,
        ]);
    }

    /**
     * Create or edit a promotional wheel campaign
     *
     * @param int|null $id
     * @param Campaign|null $campaign
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionEdit(?int $id = null, ?Campaign $campaign = null): Response
    {
        $plugin = SpinToWin::getInstance();
        $isPlus = $plugin->editions->isPlus();

        if ($campaign === null) {
            if ($id !== null) {
                $campaign = $plugin->campaigns->getCampaignById($id);
                if (!$campaign) {
                    throw new NotFoundHttpException('Campaign not found');
                }
            } else {
                $campaign = new Campaign();
                if ($isPlus) {
                    $siteParam = Craft::$app->getRequest()->getQueryParam('site') ?? Craft::$app->getRequest()->getQueryParam('siteId');
                    if ($siteParam !== null && $siteParam !== '') {
                        if (is_numeric($siteParam)) {
                            $campaign->siteId = (int)$siteParam;
                        } else {
                            $site = Craft::$app->getSites()->getSiteByHandle((string)$siteParam);
                            if ($site) {
                                $campaign->siteId = $site->id;
                            }
                        }
                    }
                }
            }
        }

        // Fetch campaign segments (or defaults if new)
        $segments = [];
        if ($campaign->id) {
            $segments = $plugin->segments->getSegmentsByCampaignId($campaign->id);
        }
        if (empty($segments)) {
            $segments = $plugin->segments->getDefaultSegments($campaign->id ?? 0, $campaign->spinPoolSize);
        }

        $title = $campaign->id ? $campaign->name : Craft::t('spin-to-win', 'New Campaign');
        $poolAllocationStatus = $plugin->segments->validatePoolAllocation($campaign->id ?? 0, $campaign->spinPoolSize);

        return $this->renderTemplate('spin-to-win/campaigns/edit', [
            'title' => $title,
            'selectedSubnavItem' => 'campaigns',
            'campaign' => $campaign,
            'segments' => $segments,
            'wheelSettings' => $campaign->getParsedWheelSettings(),
            'poolAllocationStatus' => $poolAllocationStatus,
            'isNew' => !$campaign->id,
            'isPlus' => $isPlus,
            'crumbs' => [
                [
                    'label' => Craft::t('spin-to-win', 'Campaigns'),
                    'url' => 'spin-to-win/campaigns',
                ],
            ],
        ]);
    }

    /**
     * Save a campaign
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $plugin = SpinToWin::getInstance();

        $id = $request->getBodyParam('id');
        if ($id) {
            $campaign = $plugin->campaigns->getCampaignById((int)$id);
            if (!$campaign) {
                throw new NotFoundHttpException('Campaign not found');
            }
        } else {
            $campaign = new Campaign();
        }

        $campaign->name = (string)$request->getBodyParam('name', $campaign->name);
        $campaign->handle = (string)$request->getBodyParam('handle', $campaign->handle);
        $campaign->description = $request->getBodyParam('description', $campaign->description);
        $campaign->enabled = (bool)$request->getBodyParam('enabled', true);
        $campaign->displayMode = (string)$request->getBodyParam('displayMode', 'popup');
        $campaign->triggerType = (string)$request->getBodyParam('triggerType', 'load');
        $campaign->triggerDelay = (int)$request->getBodyParam('triggerDelay', 0);
        $campaign->scrollThreshold = (int)$request->getBodyParam('scrollThreshold', 0);
        $campaign->siteId = $request->getBodyParam('siteId') ? (int)$request->getBodyParam('siteId') : null;
        $startDate = $request->getBodyParam('startDate');
        $campaign->startDate = !empty($startDate) ? \craft\helpers\DateTimeHelper::toDateTime($startDate) : null;
        $endDate = $request->getBodyParam('endDate');
        $campaign->endDate = !empty($endDate) ? \craft\helpers\DateTimeHelper::toDateTime($endDate) : null;

        $campaign->welcomeTitle = $request->getBodyParam('welcomeTitle', $campaign->welcomeTitle);
        $campaign->welcomeText = $request->getBodyParam('welcomeText', $campaign->welcomeText);
        $campaign->buttonText = (string)$request->getBodyParam('buttonText', 'Spin to Win!');
        $campaign->resultTitle = $request->getBodyParam('resultTitle', $campaign->resultTitle);
        $campaign->resultText = $request->getBodyParam('resultText', $campaign->resultText);
        $campaign->requireEmail = (bool)$request->getBodyParam('requireEmail', true);
        $campaign->requireName = (bool)$request->getBodyParam('requireName', false);
        $campaign->requireConsent = (bool)$request->getBodyParam('requireConsent', false);
        $campaign->consentText = $request->getBodyParam('consentText', $campaign->consentText);
        $campaign->requireVerification = (bool)$request->getBodyParam('requireVerification', false);
        $campaign->spinLimitPolicy = (string)$request->getBodyParam('spinLimitPolicy', 'daily_per_email');

        // Probability Spin Pool & Allocation Mode
        $campaign->spinPoolSize = max(10, (int)$request->getBodyParam('spinPoolSize', $campaign->spinPoolSize));
        $campaign->allocationMode = (string)$request->getBodyParam('allocationMode', $campaign->allocationMode);
        $campaign->poolStrategy = (string)$request->getBodyParam('poolStrategy', $campaign->poolStrategy);

        // Wheel Settings JSON
        $wheelSettings = $request->getBodyParam('wheelSettings');
        if (is_array($wheelSettings)) {
            $campaign->wheelSettings = json_encode($wheelSettings);
        } elseif (is_string($wheelSettings)) {
            $campaign->wheelSettings = $wheelSettings;
        }

        // Scheduling (Plus edition only)
        if ($plugin->editions->isPlus()) {
            $campaign->startDate = $request->getBodyParam('startDate');
            $campaign->endDate = $request->getBodyParam('endDate');
        }

        if (!$plugin->campaigns->saveCampaign($campaign)) {
            $this->setFailFlash(Craft::t('spin-to-win', 'Couldn’t save campaign.'));
            return $this->actionEdit($campaign->id, $campaign);
        }

        // Save segments
        $segmentsData = $request->getBodyParam('segments', []);
        if (is_array($segmentsData) && !empty($segmentsData)) {
            try {
                $plugin->segments->saveCampaignSegments($campaign->id, $segmentsData);
            } catch (\Throwable $e) {
                Craft::error("Error saving campaign segments: {$e->getMessage()}", __METHOD__);
                $this->setFailFlash(Craft::t('spin-to-win', 'Campaign saved, but slices had errors: {error}', ['error' => $e->getMessage()]));
                return $this->actionEdit($campaign->id, $campaign);
            }
        }

        $this->setSuccessFlash(Craft::t('spin-to-win', 'Campaign and wheel slices saved successfully.'));
        return $this->redirectToPostedUrl($campaign);
    }

    /**
     * Delete a campaign
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');
        SpinToWin::getInstance()->campaigns->deleteCampaignById($id);

        $this->setSuccessFlash(Craft::t('spin-to-win', 'Campaign deleted.'));
        return $this->redirectToPostedUrl();
    }
}
