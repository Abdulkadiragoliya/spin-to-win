<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\controllers;

use Craft;
use abdulkadiragoliya\spintowin\SpinToWin;
use yii\web\Response;
use craft\db\Query;

/**
 * Reports Controller
 *
 * Provides control panel dashboards for Spin to Win analytics, funnels, and CSV exports.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class ReportsController extends BaseCpController
{
    /**
     * Reports and Analytics Overview
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $isPro = SpinToWin::getInstance()->editions->isPro();
        $isPlus = $isPro;
        $reportsService = SpinToWin::getInstance()->reports;

        $request = Craft::$app->getRequest();
        $campaignIdParam = ($request instanceof \craft\web\Request) ? $request->getQueryParam('campaignId') : null;
        $campaignId = $campaignIdParam !== null && $campaignIdParam !== '' ? (int)$campaignIdParam : null;

        $siteParam = ($request instanceof \craft\web\Request) ? ($request->getQueryParam('site') ?? $request->getQueryParam('siteId')) : null;
        $siteId = null;
        if ($isPro && $siteParam !== null && $siteParam !== '') {
            if (is_numeric($siteParam)) {
                $siteId = (int)$siteParam;
            } else {
                $site = Craft::$app->getSites()->getSiteByHandle((string)$siteParam);
                $siteId = $site ? $site->id : null;
            }
        }

        // Fetch all campaigns for filter dropdown
        $campaignsQuery = (new Query())
            ->select(['id', 'name'])
            ->from('{{%spintowin_campaigns}}');

        if ($siteId !== null) {
            $campaignsQuery->where(['or', ['siteId' => null], ['siteId' => $siteId]]);
        }

        $allCampaigns = $campaignsQuery->orderBy(['name' => SORT_ASC])->all();

        $stats = $reportsService->getOverviewStats($campaignId, $siteId);
        $funnel = $reportsService->getConversionFunnel($campaignId, $siteId);
        $dailyActivity = $reportsService->getDailyActivity(14, $campaignId, $siteId);
        $rewardBreakdown = $reportsService->getRewardBreakdown($campaignId, $siteId);
        $segmentBreakdown = $reportsService->getSegmentBreakdown($campaignId, $siteId);

        return $this->renderTemplate('spin-to-win/reports/index', [
            'title' => Craft::t('spin-to-win', 'Reports & Analytics'),
            'selectedSubnavItem' => 'reports',
            'isPro' => $isPro,
            'isPlus' => $isPlus,
            'campaignId' => $campaignId,
            'selectedSiteId' => $siteId,
            'allCampaigns' => $allCampaigns,
            'stats' => $stats,
            'funnel' => $funnel,
            'dailyActivity' => $dailyActivity,
            'rewardBreakdown' => $rewardBreakdown,
            'segmentBreakdown' => $segmentBreakdown,
        ]);
    }

    /**
     * Export data as CSV (Pro Edition Only)
     *
     * @return Response
     */
    public function actionExport(): Response
    {
        $editions = SpinToWin::getInstance()->editions;

        if (!$editions->isPro()) {
            Craft::$app->getSession()->setError(
                Craft::t('spin-to-win', 'CSV Export is an exclusive feature of the Pro edition. Please upgrade your license to unlock full reporting exports.')
            );
            return $this->redirect('spin-to-win/reports');
        }

        $request = Craft::$app->getRequest();
        $type = ($request instanceof \craft\web\Request) ? $request->getQueryParam('type', 'spins') : 'spins';
        $campaignIdParam = ($request instanceof \craft\web\Request) ? $request->getQueryParam('campaignId') : null;
        $campaignId = $campaignIdParam !== null && $campaignIdParam !== '' ? (int)$campaignIdParam : null;

        $siteParam = ($request instanceof \craft\web\Request) ? ($request->getQueryParam('site') ?? $request->getQueryParam('siteId')) : null;
        $siteId = null;
        if ($siteParam !== null && $siteParam !== '') {
            if (is_numeric($siteParam)) {
                $siteId = (int)$siteParam;
            } else {
                $site = Craft::$app->getSites()->getSiteByHandle((string)$siteParam);
                $siteId = $site ? $site->id : null;
            }
        }

        $reportsService = SpinToWin::getInstance()->reports;
        $timestamp = date('Y-m-d-His');

        if ($type === 'coupons') {
            $csvContent = $reportsService->exportCouponsCsv($campaignId, $siteId);
            $fileName = "spintowin-coupons-{$timestamp}.csv";
        } else {
            $csvContent = $reportsService->exportSpinsCsv($campaignId, $siteId);
            $fileName = "spintowin-spins-{$timestamp}.csv";
        }

        $response = Craft::$app->getResponse();
        if ($response instanceof \craft\web\Response) {
            return $response->sendContentAsFile($csvContent, $fileName, [
                'mimeType' => 'text/csv',
                'inline' => false,
            ]);
        }

        // CLI fallback
        $webResponse = new \craft\web\Response();
        $webResponse->format = \yii\web\Response::FORMAT_RAW;
        $webResponse->headers->set('Content-Type', 'text/csv');
        $webResponse->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $webResponse->content = $csvContent;
        return $webResponse;
    }
}
