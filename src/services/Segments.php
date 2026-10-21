<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\services;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use abdulkadiragoliya\spintowin\models\Segment;
use yii\base\Component;
use DateTime;
use Throwable;

/**
 * Segments Service
 *
 * Manages wheel slices, probabilities, rewards, and slice persistence.
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Segments extends Component
{
    /**
     * Create segment query
     *
     * @return Query
     */
    public function createSegmentQuery(): Query
    {
        return (new Query())
            ->from('{{%spintowin_segments}}');
    }

    /**
     * Get all segments for a campaign
     *
     * @param int $campaignId
     * @param bool $onlyEnabled
     * @return Segment[]
     */
    public function getSegmentsByCampaignId(int $campaignId, bool $onlyEnabled = false): array
    {
        $query = $this->createSegmentQuery()
            ->where(['campaignId' => $campaignId])
            ->orderBy(['sortOrder' => SORT_ASC, 'id' => SORT_ASC]);

        if ($onlyEnabled) {
            $query->andWhere(['isEnabled' => true]);
        }

        $rows = $query->all();
        return array_map(fn($row) => new Segment($row), $rows);
    }

    /**
     * Get segment by ID
     *
     * @param int $id
     * @return Segment|null
     */
    public function getSegmentById(int $id): ?Segment
    {
        $row = $this->createSegmentQuery()
            ->where(['id' => $id])
            ->one();

        return $row ? new Segment($row) : null;
    }

    /**
     * Save a single segment
     *
     * @param Segment $segment
     * @param bool $runValidation
     * @return bool
     */
    public function saveSegment(Segment $segment, bool $runValidation = true): bool
    {
        $isNew = !$segment->id;

        if ($runValidation && !$segment->validate()) {
            Craft::info('Segment not saved due to validation errors.', __METHOD__);
            return false;
        }

        $now = Db::prepareDateForDb(new DateTime());

        $values = [
            'campaignId' => $segment->campaignId,
            'label' => $segment->label,
            'rewardType' => $segment->rewardType,
            'discountValue' => $segment->discountValue,
            'discountId' => $segment->discountId,
            'probabilityWeight' => $segment->probabilityWeight,
            'allocatedCount' => $segment->allocatedCount,
            'awardedCount' => $segment->awardedCount,
            'poolAwardedCount' => $segment->poolAwardedCount,
            'couponDurationDays' => $segment->couponDurationDays,
            'minOrderValue' => $segment->minOrderValue,
            'maxUsesPerCoupon' => $segment->maxUsesPerCoupon,
            'color' => $segment->color,
            'textColor' => $segment->textColor,
            'sortOrder' => $segment->sortOrder,
            'isEnabled' => $segment->isEnabled,
            'dateUpdated' => $now,
        ];

        if ($isNew) {
            $values['uid'] = StringHelper::UUID();
            $values['dateCreated'] = $now;

            $success = (bool)Craft::$app->getDb()->createCommand()
                ->insert('{{%spintowin_segments}}', $values)
                ->execute();

            if ($success) {
                $segment->id = (int)Craft::$app->getDb()->getLastInsertID('{{%spintowin_segments}}');
            }
        } else {
            $success = (bool)Craft::$app->getDb()->createCommand()
                ->update('{{%spintowin_segments}}', $values, ['id' => $segment->id])
                ->execute();
        }

        return $success;
    }

    /**
     * Batch save, update, reorder, and remove segments for a campaign
     *
     * @param int $campaignId
     * @param array $segmentsData Array of segment attributes from POST
     * @return bool
     * @throws Throwable
     */
    public function saveCampaignSegments(int $campaignId, array $segmentsData): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $existingIds = $this->createSegmentQuery()
                ->select(['id'])
                ->where(['campaignId' => $campaignId])
                ->column();

            $processedIds = [];

            foreach (array_values($segmentsData) as $index => $data) {
                $segment = null;
                $segmentId = !empty($data['id']) ? (int)$data['id'] : null;

                if ($segmentId && in_array($segmentId, $existingIds, true)) {
                    $segment = $this->getSegmentById($segmentId);
                }

                if (!$segment) {
                    $segment = new Segment();
                    $segment->campaignId = $campaignId;
                }

                $segment->label = (string)($data['label'] ?? 'Prize');
                $segment->rewardType = (string)($data['rewardType'] ?? Segment::REWARD_NO_PRIZE);
                $segment->discountValue = (float)($data['discountValue'] ?? 0);
                $segment->allocatedCount = max(0, (int)($data['allocatedCount'] ?? $data['probabilityWeight'] ?? 10));
                $segment->probabilityWeight = max(1, (int)($data['probabilityWeight'] ?? ($segment->allocatedCount > 0 ? $segment->allocatedCount : 10)));
                $segment->couponDurationDays = max(0, (int)($data['couponDurationDays'] ?? 7));
                $segment->minOrderValue = max(0, (float)($data['minOrderValue'] ?? 0));
                $segment->color = (string)($data['color'] ?? '#ef4444');
                $segment->textColor = (string)($data['textColor'] ?? '#ffffff');
                $segment->isEnabled = isset($data['isEnabled']) && ((string)$data['isEnabled'] === '1' || $data['isEnabled'] === true || $data['isEnabled'] === 1);
                $segment->sortOrder = $index;

                if (isset($data['awardedCount'])) {
                    $segment->awardedCount = max(0, (int)$data['awardedCount']);
                }
                if (isset($data['poolAwardedCount'])) {
                    $segment->poolAwardedCount = max(0, (int)$data['poolAwardedCount']);
                }

                if (!$this->saveSegment($segment)) {
                    throw new \Exception("Failed to save segment: " . implode(', ', $segment->getFirstErrors()));
                }

                $processedIds[] = $segment->id;
            }

            // Delete segments removed in builder
            $toDelete = array_diff($existingIds, $processedIds);
            if (!empty($toDelete)) {
                $db->createCommand()
                    ->delete('{{%spintowin_segments}}', ['id' => $toDelete])
                    ->execute();
            }

            $transaction->commit();
            return true;
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error("Error saving campaign segments: {$e->getMessage()}", __METHOD__);
            throw $e;
        }
    }

    /**
     * Delete segment by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteSegmentById(int $id): bool
    {
        return (bool)Craft::$app->getDb()->createCommand()
            ->delete('{{%spintowin_segments}}', ['id' => $id])
            ->execute();
    }

    /**
     * Get balanced default slices for a new wheel matching Example A (1,000 spin pool)
     *
     * @param int $campaignId
     * @param int $poolSize
     * @return Segment[]
     */
    public function getDefaultSegments(int $campaignId = 0, int $poolSize = 1000): array
    {
        $defaults = [
            [
                'label' => '10% OFF',
                'rewardType' => Segment::REWARD_PERCENT_DISCOUNT,
                'discountValue' => 10,
                'allocatedCount' => 100, // 10%
                'probabilityWeight' => 100,
                'color' => '#4f46e5',
                'textColor' => '#ffffff',
                'couponDurationDays' => 7,
            ],
            [
                'label' => '15% OFF',
                'rewardType' => Segment::REWARD_PERCENT_DISCOUNT,
                'discountValue' => 15,
                'allocatedCount' => 150, // 15%
                'probabilityWeight' => 150,
                'color' => '#06b6d4',
                'textColor' => '#ffffff',
                'couponDurationDays' => 7,
            ],
            [
                'label' => 'Free Product',
                'rewardType' => Segment::REWARD_CUSTOM,
                'discountValue' => 0,
                'allocatedCount' => 1, // 0.1%
                'probabilityWeight' => 1,
                'color' => '#8b5cf6',
                'textColor' => '#ffffff',
                'couponDurationDays' => 14,
            ],
            [
                'label' => '7% OFF',
                'rewardType' => Segment::REWARD_PERCENT_DISCOUNT,
                'discountValue' => 7,
                'allocatedCount' => 70, // 7%
                'probabilityWeight' => 70,
                'color' => '#10b981',
                'textColor' => '#ffffff',
                'couponDurationDays' => 5,
            ],
            [
                'label' => '8% OFF',
                'rewardType' => Segment::REWARD_PERCENT_DISCOUNT,
                'discountValue' => 8,
                'allocatedCount' => 80, // 8%
                'probabilityWeight' => 80,
                'color' => '#f59e0b',
                'textColor' => '#ffffff',
                'couponDurationDays' => 5,
            ],
            [
                'label' => 'Better Luck',
                'rewardType' => Segment::REWARD_NO_PRIZE,
                'discountValue' => 0,
                'allocatedCount' => 599, // 59.9%
                'probabilityWeight' => 599,
                'color' => '#ef4444',
                'textColor' => '#ffffff',
                'couponDurationDays' => 0,
            ],
        ];

        $segments = [];
        foreach ($defaults as $i => $item) {
            $seg = new Segment();
            $seg->campaignId = $campaignId;
            $seg->label = $item['label'];
            $seg->rewardType = $item['rewardType'];
            $seg->discountValue = (float)$item['discountValue'];
            $seg->allocatedCount = $item['allocatedCount'];
            $seg->probabilityWeight = $item['probabilityWeight'];
            $seg->color = $item['color'];
            $seg->textColor = $item['textColor'];
            $seg->couponDurationDays = $item['couponDurationDays'];
            $seg->sortOrder = $i;
            $seg->isEnabled = true;
            $segments[] = $seg;
        }

        return $segments;
    }

    /**
     * Validate that configured segment prizes match the campaign's Probability Spin Pool
     *
     * @param int $campaignId
     * @param int $poolSize
     * @return array
     */
    public function validatePoolAllocation(int $campaignId, int $poolSize): array
    {
        $segments = $this->getSegmentsByCampaignId($campaignId, true);
        $totalAllocated = 0;
        foreach ($segments as $s) {
            $totalAllocated += $s->allocatedCount;
        }

        $diff = $poolSize - $totalAllocated;

        return [
            'isValid' => $diff === 0,
            'totalAllocated' => $totalAllocated,
            'poolSize' => $poolSize,
            'diff' => $diff,
            'isUnder' => $diff > 0,
            'isOver' => $diff < 0,
        ];
    }

    /**
     * Secure server-side prize selection with concurrency protection
     *
     * In quota-based mode, guarantees exact prize counts per spin pool without exceeding quota.
     * In random mode, rolls probability independently based on allocated count / weights.
     *
     * @param \abdulkadiragoliya\spintowin\models\Campaign $campaign
     * @param bool $isTest If true, simulation only; does not decrement quota in database
     * @return Segment
     * @throws \Exception
     */
    public function selectWinningSegment(\abdulkadiragoliya\spintowin\models\Campaign $campaign, bool $isTest = false): Segment
    {
        $db = Craft::$app->getDb();
        $isQuota = $campaign->getIsQuotaMode();

        $transaction = null;
        if (!$isTest && $isQuota) {
            $transaction = $db->beginTransaction();
        }

        try {
            $query = $this->createSegmentQuery()
                ->where(['campaignId' => $campaign->id, 'isEnabled' => true])
                ->orderBy(['sortOrder' => SORT_ASC, 'id' => SORT_ASC]);

            $rows = $query->all();
            if (empty($rows)) {
                throw new \Exception("No active segments available for campaign ID {$campaign->id}");
            }

            $segments = array_map(fn($row) => new Segment($row), $rows);

            if ($isQuota) {
                // Filter segments that have remaining quota in current pool
                $available = array_values(array_filter($segments, fn(Segment $s) => $s->getRemainingPoolQuota() > 0));

                // If pool is exhausted (all quotas reached 0), handle pool cycle rollover
                if (empty($available)) {
                    if (!$isTest) {
                        $campaign->poolCycle++;
                        $db->createCommand()
                            ->update('{{%spintowin_campaigns}}', ['poolCycle' => $campaign->poolCycle], ['id' => $campaign->id])
                            ->execute();

                        $db->createCommand()
                            ->update('{{%spintowin_segments}}', ['poolAwardedCount' => 0], ['campaignId' => $campaign->id])
                            ->execute();
                    }

                    $rows = $query->all();
                    $segments = array_map(fn($row) => new Segment($row), $rows);
                    foreach ($segments as $s) {
                        $s->poolAwardedCount = 0;
                    }
                    $available = array_values(array_filter($segments, fn(Segment $s) => $s->getRemainingPoolQuota() > 0));
                }

                if (empty($available)) {
                    $available = $segments;
                }

                // Weighted selection based on remaining available prize quota
                $totalRemaining = 0;
                foreach ($available as $s) {
                    $totalRemaining += max(1, $s->getRemainingPoolQuota());
                }

                $rand = mt_rand(1, max(1, $totalRemaining));
                $running = 0;
                $winner = $available[0];

                foreach ($available as $s) {
                    $running += max(1, $s->getRemainingPoolQuota());
                    if ($rand <= $running) {
                        $winner = $s;
                        break;
                    }
                }

                if (!$isTest) {
                    $db->createCommand()
                        ->update('{{%spintowin_segments}}', [
                            'awardedCount' => new \yii\db\Expression('awardedCount + 1'),
                            'poolAwardedCount' => new \yii\db\Expression('poolAwardedCount + 1'),
                            'dateUpdated' => Db::prepareDateForDb(new DateTime()),
                        ], ['id' => $winner->id])
                        ->execute();

                    $winner->awardedCount++;
                    $winner->poolAwardedCount++;
                    $transaction?->commit();
                }

                return $winner;
            } else {
                // Probability Random Mode
                $totalWeight = 0;
                foreach ($segments as $s) {
                    $totalWeight += max(1, $s->allocatedCount > 0 ? $s->allocatedCount : $s->probabilityWeight);
                }

                $rand = mt_rand(1, max(1, $totalWeight));
                $running = 0;
                $winner = $segments[0];

                foreach ($segments as $s) {
                    $weight = max(1, $s->allocatedCount > 0 ? $s->allocatedCount : $s->probabilityWeight);
                    $running += $weight;
                    if ($rand <= $running) {
                        $winner = $s;
                        break;
                    }
                }

                if (!$isTest) {
                    $db->createCommand()
                        ->update('{{%spintowin_segments}}', [
                            'awardedCount' => new \yii\db\Expression('awardedCount + 1'),
                            'poolAwardedCount' => new \yii\db\Expression('poolAwardedCount + 1'),
                            'dateUpdated' => Db::prepareDateForDb(new DateTime()),
                        ], ['id' => $winner->id])
                        ->execute();

                    $winner->awardedCount++;
                    $winner->poolAwardedCount++;
                }

                return $winner;
            }
        } catch (\Throwable $e) {
            $transaction?->rollBack();
            throw $e;
        }
    }
}
