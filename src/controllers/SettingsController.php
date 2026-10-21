<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\controllers;

use Craft;
use abdulkadiragoliya\spintowin\SpinToWin;
use craft\helpers\UrlHelper;
use yii\web\Response;

/**
 * Settings Controller
 *
 * Handles global and per-site settings management for Spin to Win.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class SettingsController extends BaseCpController
{
    /**
     * Display Settings page inside full CP layout
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $plugin = SpinToWin::getInstance();
        $isPro = $plugin->editions->isPro();

        $siteParam = Craft::$app->getRequest()->getQueryParam('site') ?? Craft::$app->getRequest()->getQueryParam('siteId');
        $activeSite = null;
        if ($isPro && $siteParam !== null && $siteParam !== '') {
            if (is_numeric($siteParam)) {
                $activeSite = Craft::$app->getSites()->getSiteById((int)$siteParam);
            } else {
                $activeSite = Craft::$app->getSites()->getSiteByHandle((string)$siteParam);
            }
        }
        if (!$activeSite) {
            $activeSite = Craft::$app->getSites()->getCurrentSite();
        }

        $allSettings = $plugin->getSettings();
        $hasCustom = $allSettings->hasCustomSettingsForSite($activeSite->handle);
        $siteValues = $allSettings->getForSite($activeSite->handle, false);

        return $this->renderTemplate('spin-to-win/settings/index', [
            'title' => Craft::t('spin-to-win', 'Settings'),
            'selectedSubnavItem' => 'settings',
            'plugin' => $plugin,
            'settings' => $allSettings,
            'siteValues' => $siteValues,
            'hasCustomSettings' => $hasCustom,
            'activeSite' => $activeSite,
            'selectedSiteId' => $activeSite->id,
            'edition' => $plugin->getEdition(),
            'isPro' => $isPro,
            'featureMatrix' => $plugin->editions->getFeatureMatrix(),
        ]);
    }

    /**
     * Save plugin settings
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $plugin = SpinToWin::getInstance();
        $request = Craft::$app->getRequest();

        $posted = $request->getBodyParam('settings');
        if ($posted === null) {
            $posted = $request->getBodyParams();
        }

        $siteHandle = (string)$request->getBodyParam('siteHandle', '');
        if (empty($siteHandle)) {
            $siteId = $request->getBodyParam('siteId');
            $site = $siteId ? Craft::$app->getSites()->getSiteById((int)$siteId) : Craft::$app->getSites()->getCurrentSite();
            $siteHandle = $site ? $site->handle : 'default';
        }

        $currentSiteObj = Craft::$app->getSites()->getSiteByHandle($siteHandle) ?? Craft::$app->getSites()->getCurrentSite();
        $existingSettings = $plugin->getSettings();
        $siteSettings = $existingSettings->siteSettings ?? [];

        $isPro = $plugin->editions->isPro();

        if ($isPro) {
            // Save specifically under this site's handle (Pro Edition)
            $siteSettings[$siteHandle] = [
                'emailSenderName' => (string)($posted['emailSenderName'] ?? ''),
                'emailSenderAddress' => (string)($posted['emailSenderAddress'] ?? ''),
                'emailReplyTo' => (string)($posted['emailReplyTo'] ?? ''),
                'trackImpressions' => !empty($posted['trackImpressions']),
                'pruneOldSpins' => !empty($posted['pruneOldSpins']),
                'retentionDays' => max(1, (int)($posted['retentionDays'] ?? 90)),
            ];

            // Retain existing root properties to prevent them from being wiped out
            $saveData = [
                'emailSenderName' => $existingSettings->emailSenderName,
                'emailSenderAddress' => $existingSettings->emailSenderAddress,
                'emailReplyTo' => $existingSettings->emailReplyTo,
                'trackImpressions' => $existingSettings->trackImpressions,
                'pruneOldSpins' => $existingSettings->pruneOldSpins,
                'retentionDays' => $existingSettings->retentionDays,
                'siteSettings' => $siteSettings,
            ];

            // Also keep base settings updated if on primary site
            $primarySite = Craft::$app->getSites()->getPrimarySite();
            if ($siteHandle === $primarySite->handle) {
                $saveData['emailSenderName'] = $siteSettings[$siteHandle]['emailSenderName'];
                $saveData['emailSenderAddress'] = $siteSettings[$siteHandle]['emailSenderAddress'];
                $saveData['emailReplyTo'] = $siteSettings[$siteHandle]['emailReplyTo'];
                $saveData['trackImpressions'] = $siteSettings[$siteHandle]['trackImpressions'];
                $saveData['pruneOldSpins'] = $siteSettings[$siteHandle]['pruneOldSpins'];
                $saveData['retentionDays'] = $siteSettings[$siteHandle]['retentionDays'];
            }
        } else {
            // Lite Edition: Global base settings only
            $saveData = [
                'emailSenderName' => (string)($posted['emailSenderName'] ?? ''),
                'emailSenderAddress' => (string)($posted['emailSenderAddress'] ?? ''),
                'emailReplyTo' => (string)($posted['emailReplyTo'] ?? ''),
                'trackImpressions' => !empty($posted['trackImpressions']),
                'pruneOldSpins' => !empty($posted['pruneOldSpins']),
                'retentionDays' => max(1, (int)($posted['retentionDays'] ?? 90)),
                'siteSettings' => [],
            ];
        }

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $saveData)) {
            $this->setFailFlash(Craft::t('app', 'Couldn’t save plugin settings.'));
            return $this->actionIndex();
        }

        $this->setSuccessFlash(Craft::t('spin-to-win', 'Settings saved for {site}.', [
            'site' => $currentSiteObj->name ?? $siteHandle,
        ]));

        return $this->redirect(UrlHelper::cpUrl('spin-to-win/settings', ['site' => $siteHandle]));
    }
}
