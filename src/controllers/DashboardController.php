<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\controllers;

use Craft;
use abdulkadiragoliya\spintowin\SpinToWin;
use yii\web\Response;
use craft\db\Query;

/**
 * Dashboard Controller
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class DashboardController extends BaseCpController
{
    /**
     * Display the main Spin to Win dashboard
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $plugin = SpinToWin::getInstance();
        $isPlus = $plugin->editions->isPlus();
        $request = Craft::$app->getRequest();

        $siteParam = $request->getQueryParam('site') ?? $request->getQueryParam('siteId');
        $siteId = null;
        if ($isPlus && $siteParam !== null && $siteParam !== '') {
            if (is_numeric($siteParam)) {
                $siteId = (int)$siteParam;
            } else {
                $site = Craft::$app->getSites()->getSiteByHandle((string)$siteParam);
                $siteId = $site ? $site->id : null;
            }
        }

        // Gather metrics
        $campaignQuery = (new Query())->from('{{%spintowin_campaigns}} c');
        $spinQuery = (new Query())->from('{{%spintowin_spins}} s');
        $couponQuery = (new Query())->from('{{%spintowin_coupons}} cp');
        $eventQuery = (new Query())->from('{{%spintowin_events}} e');

        if ($siteId !== null) {
            $campaignQuery->where(['or', ['c.siteId' => null], ['c.siteId' => $siteId]]);
            $spinQuery->where(['s.siteId' => $siteId]);
            $couponQuery->innerJoin('{{%spintowin_spins}} s', '[[s.id]] = [[cp.spinId]]')->where(['s.siteId' => $siteId]);
            $eventQuery->innerJoin('{{%spintowin_campaigns}} c', '[[c.id]] = [[e.campaignId]]')->where(['or', ['c.siteId' => null], ['c.siteId' => $siteId]]);
        }

        $totalCampaigns = (int)(clone $campaignQuery)->count();
        $activeCampaigns = (int)(clone $campaignQuery)->andWhere(['c.enabled' => true])->count();
        $totalSpins = (int)(clone $spinQuery)->count();
        $totalWins = (int)(clone $spinQuery)->andWhere(['s.isWinner' => true])->count();
        $totalCoupons = (int)(clone $couponQuery)->count();
        $redeemedCoupons = (int)(clone $couponQuery)->andWhere(['cp.isRedeemed' => true])->count();
        $totalImpressions = (int)(clone $eventQuery)->andWhere(['e.eventType' => 'impression'])->count();

        // Recent spins
        $recentSpinsQuery = (new Query())
            ->select([
                's.id',
                's.email',
                's.rewardType',
                's.couponCode',
                's.isWinner',
                's.dateCreated',
                'c.name as campaignName',
            ])
            ->from('{{%spintowin_spins}} s')
            ->leftJoin('{{%spintowin_campaigns}} c', '[[c.id]] = [[s.campaignId]]');

        if ($siteId !== null) {
            $recentSpinsQuery->where(['s.siteId' => $siteId]);
        }

        $recentSpins = $recentSpinsQuery
            ->orderBy(['s.dateCreated' => SORT_DESC])
            ->limit(5)
            ->all();

        return $this->renderTemplate('spin-to-win/dashboard/index', [
            'title' => Craft::t('spin-to-win', 'Spin to Win Dashboard'),
            'selectedSubnavItem' => 'dashboard',
            'edition' => $plugin->getEdition(),
            'isPlus' => $isPlus,
            'selectedSiteId' => $siteId,
            'stats' => [
                'totalCampaigns' => $totalCampaigns,
                'activeCampaigns' => $activeCampaigns,
                'totalSpins' => $totalSpins,
                'totalWins' => $totalWins,
                'totalCoupons' => $totalCoupons,
                'redeemedCoupons' => $redeemedCoupons,
                'totalImpressions' => $totalImpressions,
                'conversionRate' => $totalImpressions > 0 ? round(($totalSpins / $totalImpressions) * 100, 1) : 0,
            ],
            'recentSpins' => $recentSpins,
        ]);
    }
}
