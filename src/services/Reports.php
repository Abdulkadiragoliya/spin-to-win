<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\services;

use Craft;
use yii\base\Component;
use craft\db\Query;

/**
 * Reports Service
 *
 * Provides aggregated analytics, conversion funnels, and CSV streaming for Spin to Win.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Reports extends Component
{
    /**
     * Get aggregated overview metrics
     *
     * @param int|null $campaignId
     * @param int|null $siteId
     * @return array
     */
    public function getOverviewStats(?int $campaignId = null, ?int $siteId = null): array
    {
        $spinQuery = (new Query())->from('{{%spintowin_spins}} s');
        $couponQuery = (new Query())->from('{{%spintowin_coupons}} cp');
        $eventQuery = (new Query())->from('{{%spintowin_events}} e');

        if ($campaignId !== null) {
            $spinQuery->andWhere(['s.campaignId' => $campaignId]);
            $couponQuery->andWhere(['cp.campaignId' => $campaignId]);
            $eventQuery->andWhere(['e.campaignId' => $campaignId]);
        }

        if ($siteId !== null) {
            $spinQuery->andWhere(['s.siteId' => $siteId]);
            $couponQuery->innerJoin('{{%spintowin_spins}} s', '[[s.id]] = [[cp.spinId]]')->andWhere(['s.siteId' => $siteId]);
            $eventQuery->innerJoin('{{%spintowin_campaigns}} c', '[[c.id]] = [[e.campaignId]]')->andWhere(['or', ['c.siteId' => null], ['c.siteId' => $siteId]]);
        }

        $totalSpins = (int)(clone $spinQuery)->count();
        $totalWins = (int)(clone $spinQuery)->andWhere(['s.isWinner' => true])->count();
        $totalCoupons = (int)(clone $couponQuery)->count();
        $redeemedCoupons = (int)(clone $couponQuery)->andWhere(['cp.isRedeemed' => true])->count();

        $impressions = (int)(clone $eventQuery)->andWhere(['e.eventType' => 'impression'])->count();
        $opens = (int)(clone $eventQuery)->andWhere(['e.eventType' => 'open'])->count();

        $winRate = $totalSpins > 0 ? round(($totalWins / $totalSpins) * 100, 1) : 0.0;
        $redemptionRate = $totalCoupons > 0 ? round(($redeemedCoupons / $totalCoupons) * 100, 1) : 0.0;
        $overallConversionRate = $impressions > 0 ? round(($totalSpins / $impressions) * 100, 1) : 0.0;

        return [
            'totalSpins' => $totalSpins,
            'totalWins' => $totalWins,
            'winRate' => $winRate,
            'totalCoupons' => $totalCoupons,
            'redeemedCoupons' => $redeemedCoupons,
            'redemptionRate' => $redemptionRate,
            'impressions' => $impressions,
            'opens' => $opens,
            'overallConversionRate' => $overallConversionRate,
        ];
    }

    /**
     * Get conversion funnel stages
     *
     * @param int|null $campaignId
     * @param int|null $siteId
     * @return array
     */
    public function getConversionFunnel(?int $campaignId = null, ?int $siteId = null): array
    {
        $stats = $this->getOverviewStats($campaignId, $siteId);

        $impressions = max($stats['impressions'], $stats['opens'], $stats['totalSpins']);
        $opens = max($stats['opens'], $stats['totalSpins']);
        $spins = $stats['totalSpins'];
        $wins = $stats['totalWins'];
        $redemptions = $stats['redeemedCoupons'];

        $openRate = $impressions > 0 ? round(($opens / $impressions) * 100, 1) : 0.0;
        $spinRate = $opens > 0 ? round(($spins / $opens) * 100, 1) : 0.0;
        $winRate = $spins > 0 ? round(($wins / $spins) * 100, 1) : 0.0;
        $redemptionRate = $wins > 0 ? round(($redemptions / $wins) * 100, 1) : 0.0;

        return [
            [
                'stage' => Craft::t('spin-to-win', '1. Impressions'),
                'description' => Craft::t('spin-to-win', 'Wheel trigger evaluated / rendered on storefront'),
                'count' => $impressions,
                'conversionRate' => 100.0,
            ],
            [
                'stage' => Craft::t('spin-to-win', '2. Wheel Opens'),
                'description' => Craft::t('spin-to-win', 'Modal popup viewed or launcher clicked'),
                'count' => $opens,
                'conversionRate' => $openRate,
            ],
            [
                'stage' => Craft::t('spin-to-win', '3. Completed Spins'),
                'description' => Craft::t('spin-to-win', 'Emails collected & wheels spun'),
                'count' => $spins,
                'conversionRate' => $spinRate,
            ],
            [
                'stage' => Craft::t('spin-to-win', '4. Winning Spins'),
                'description' => Craft::t('spin-to-win', 'Spins resulting in a prize / coupon'),
                'count' => $wins,
                'conversionRate' => $winRate,
            ],
            [
                'stage' => Craft::t('spin-to-win', '5. Commerce Redemptions'),
                'description' => Craft::t('spin-to-win', 'Coupons used at checkout on completed orders'),
                'count' => $redemptions,
                'conversionRate' => $redemptionRate,
            ],
        ];
    }

    /**
     * Get reward breakdown across winning spins
     *
     * @param int|null $campaignId
     * @param int|null $siteId
     * @return array
     */
    public function getRewardBreakdown(?int $campaignId = null, ?int $siteId = null): array
    {
        $query = (new Query())
            ->select(['rewardType', 'COUNT(*) as count'])
            ->from('{{%spintowin_spins}}')
            ->where(['isWinner' => true])
            ->andWhere(['not', ['rewardType' => null]]);

        if ($campaignId !== null) {
            $query->andWhere(['campaignId' => $campaignId]);
        }

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        return $query
            ->groupBy(['rewardType'])
            ->orderBy(['count' => SORT_DESC])
            ->all();
    }

    /**
     * Get segment breakdown and quota tracking
     *
     * @param int|null $campaignId
     * @param int|null $siteId
     * @return array
     */
    public function getSegmentBreakdown(?int $campaignId = null, ?int $siteId = null): array
    {
        $query = (new Query())
            ->select([
                'c.name as campaignName',
                'c.spinPoolSize',
                'c.allocationMode',
                's.id',
                's.label',
                's.rewardType',
                's.allocatedCount',
                's.poolAwardedCount',
                's.awardedCount',
            ])
            ->from('{{%spintowin_segments}} s')
            ->innerJoin('{{%spintowin_campaigns}} c', 'c.id = s.campaignId')
            ->where(['s.isEnabled' => true]);

        if ($campaignId !== null) {
            $query->andWhere(['c.id' => $campaignId]);
        }

        if ($siteId !== null) {
            $query->andWhere(['or', ['c.siteId' => null], ['c.siteId' => $siteId]]);
        }

        return $query
            ->orderBy(['c.name' => SORT_ASC, 's.sortOrder' => SORT_ASC])
            ->all();
    }

    /**
     * Get daily activity over the past N days
     *
     * @param int $days
     * @param int|null $campaignId
     * @param int|null $siteId
     * @return array
     */
    public function getDailyActivity(int $days = 14, ?int $campaignId = null, ?int $siteId = null): array
    {
        $startDate = (new \DateTime())->modify("-{$days} days")->format('Y-m-d');

        $query = (new Query())
            ->select([
                'spinDate',
                'COUNT(*) as totalSpins',
                'SUM(CASE WHEN isWinner = 1 THEN 1 ELSE 0 END) as totalWins',
            ])
            ->from('{{%spintowin_spins}}')
            ->where(['>=', 'spinDate', $startDate]);

        if ($campaignId !== null) {
            $query->andWhere(['campaignId' => $campaignId]);
        }

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        $results = $query
            ->groupBy(['spinDate'])
            ->orderBy(['spinDate' => SORT_ASC])
            ->all();

        // Index by date
        $indexed = [];
        foreach ($results as $row) {
            $indexed[$row['spinDate']] = [
                'spins' => (int)$row['totalSpins'],
                'wins' => (int)$row['totalWins'],
            ];
        }

        // Fill gaps so all days are represented
        $timeline = [];
        $current = new \DateTime($startDate);
        $today = new \DateTime();

        while ($current <= $today) {
            $dateStr = $current->format('Y-m-d');
            $timeline[] = [
                'date' => $dateStr,
                'spins' => $indexed[$dateStr]['spins'] ?? 0,
                'wins' => $indexed[$dateStr]['wins'] ?? 0,
            ];
            $current->modify('+1 day');
        }

        return $timeline;
    }

    /**
     * Export spins data as CSV string
     *
     * @param int|null $campaignId
     * @param int|null $siteId
     * @return string
     */
    public function exportSpinsCsv(?int $campaignId = null, ?int $siteId = null): string
    {
        $query = (new Query())
            ->select([
                's.id',
                'c.name as campaignName',
                's.siteId',
                's.email',
                's.name as customerName',
                's.isWinner',
                's.rewardType',
                's.couponCode',
                's.ipAddress',
                's.isVerified',
                's.spinDate',
                's.dateCreated',
            ])
            ->from('{{%spintowin_spins}} s')
            ->leftJoin('{{%spintowin_campaigns}} c', 'c.id = s.campaignId');

        if ($campaignId !== null) {
            $query->where(['s.campaignId' => $campaignId]);
        }

        if ($siteId !== null) {
            $query->andWhere(['s.siteId' => $siteId]);
        }

        $rows = $query->orderBy(['s.id' => SORT_DESC])->all();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'Spin ID',
            'Campaign',
            'Email',
            'Name',
            'Outcome',
            'Reward Type',
            'Coupon Code',
            'IP Address',
            'Verified',
            'Spin Date',
            'Timestamp',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['campaignName'] ?? 'Unknown',
                $row['email'],
                $row['customerName'] ?? '',
                $row['isWinner'] ? 'Winner' : 'Loss',
                $row['rewardType'] ?? 'None',
                $row['couponCode'] ?? '',
                $row['ipAddress'] ?? '',
                $row['isVerified'] ? 'Yes' : 'No',
                $row['spinDate'],
                $row['dateCreated'],
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }

    /**
     * Export coupons data as CSV string
     *
     * @param int|null $campaignId
     * @return string
     */
    public function exportCouponsCsv(?int $campaignId = null, ?int $siteId = null): string
    {
        $query = (new Query())
            ->select([
                'cp.id',
                'c.name as campaignName',
                'cp.code',
                'cp.discountType',
                'cp.discountAmount',
                'cp.minOrderValue',
                'cp.expiresAt',
                'cp.isRedeemed',
                'cp.orderId',
                'cp.redeemedAt',
                'cp.dateCreated',
            ])
            ->from('{{%spintowin_coupons}} cp')
            ->leftJoin('{{%spintowin_campaigns}} c', '[[c.id]] = [[cp.campaignId]]');

        if ($campaignId !== null) {
            $query->where(['cp.campaignId' => $campaignId]);
        }

        if ($siteId !== null) {
            $query->innerJoin('{{%spintowin_spins}} s', '[[s.id]] = [[cp.spinId]]')
                  ->andWhere(['s.siteId' => $siteId]);
        }

        $rows = $query->orderBy(['cp.id' => SORT_DESC])->all();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'Coupon ID',
            'Campaign',
            'Code',
            'Discount Type',
            'Discount Amount',
            'Min Order Value',
            'Expires At',
            'Status',
            'Order ID',
            'Redeemed At',
            'Created At',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['campaignName'] ?? 'Unknown',
                $row['code'],
                $row['discountType'],
                $row['discountAmount'],
                $row['minOrderValue'],
                $row['expiresAt'] ?? 'None',
                $row['isRedeemed'] ? 'Redeemed' : 'Active',
                $row['orderId'] ?? '',
                $row['redeemedAt'] ?? '',
                $row['dateCreated'],
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }
}
