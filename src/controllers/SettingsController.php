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
        $isPlus = $plugin->editions->isPlus();

        $siteParam = Craft::$app->getRequest()->getQueryParam('site') ?? Craft::$app->getRequest()->getQueryParam('siteId');
        $activeSite = null;
        if ($isPlus && $siteParam !== null && $siteParam !== '') {
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
        $siteValues = $allSettings->getForSite($activeSite->handle);

        return $this->renderTemplate('spin-to-win/settings/index', [
            'title' => Craft::t('spin-to-win', 'Settings'),
            'selectedSubnavItem' => 'settings',
            'plugin' => $plugin,
            'settings' => $allSettings,
            'siteValues' => $siteValues,
            'activeSite' => $activeSite,
            'selectedSiteId' => $activeSite->id,
            'edition' => $plugin->getEdition(),
            'isPlus' => $isPlus,
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

        // Save specifically under this site's handle
        $siteSettings[$siteHandle] = [
            'emailSenderName' => (string)($posted['emailSenderName'] ?? ''),
            'emailSenderAddress' => (string)($posted['emailSenderAddress'] ?? ''),
            'emailReplyTo' => (string)($posted['emailReplyTo'] ?? ''),
            'trackImpressions' => !empty($posted['trackImpressions']),
            'pruneOldSpins' => !empty($posted['pruneOldSpins']),
            'retentionDays' => max(1, (int)($posted['retentionDays'] ?? 90)),
        ];

        $saveData = [
            'siteSettings' => $siteSettings,
        ];

        // Also keep base settings updated if on primary site or if global is empty
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        if ($siteHandle === $primarySite->handle || empty($existingSettings->emailSenderName)) {
            $saveData['emailSenderName'] = $siteSettings[$siteHandle]['emailSenderName'];
            $saveData['emailSenderAddress'] = $siteSettings[$siteHandle]['emailSenderAddress'];
            $saveData['emailReplyTo'] = $siteSettings[$siteHandle]['emailReplyTo'];
            $saveData['trackImpressions'] = $siteSettings[$siteHandle]['trackImpressions'];
            $saveData['pruneOldSpins'] = $siteSettings[$siteHandle]['pruneOldSpins'];
            $saveData['retentionDays'] = $siteSettings[$siteHandle]['retentionDays'];
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
