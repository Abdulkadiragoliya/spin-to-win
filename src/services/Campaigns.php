<?php
declare(strict_types=1);

namespace abdulkadiragoliya\spintowin\services;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\DateTimeHelper;
use abdulkadiragoliya\spintowin\models\Campaign;
use yii\base\Component;
use DateTime;

/**
 * Campaigns Service
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Campaigns extends Component
{
    /**
     * Get campaign query
     *
     * @return Query
     */
    public function createCampaignQuery(): Query
    {
        return (new Query())
            ->from('{{%spintowin_campaigns}}');
    }

    /**
     * Get total campaign count
     *
     * @return int
     */
    public function getTotalCampaignsCount(): int
    {
        return (int)$this->createCampaignQuery()->count();
    }

    /**
     * Get active campaign count
     *
     * @return int
     */
    public function getActiveCampaignsCount(): int
    {
        return (int)$this->createCampaignQuery()
            ->where(['enabled' => true])
            ->count();
    }

    /**
     * Get all campaigns, optionally filtered by site
     *
     * @param int|null $siteId Optional site ID to filter by
     * @return Campaign[]
     */
    public function getAllCampaigns(?int $siteId = null): array
    {
        $query = $this->createCampaignQuery();

        if ($siteId !== null) {
            $query->andWhere(['or', ['siteId' => null], ['siteId' => $siteId]]);
        }

        $rows = $query
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();

        return array_map(fn($row) => new Campaign($row), $rows);
    }

    /**
     * Get active campaigns eligible for display
     *
     * @param int|null $siteId Optional site ID to filter by
     * @return Campaign[]
     */
    public function getActiveCampaigns(?int $siteId = null): array
    {
        $query = $this->createCampaignQuery()
            ->where(['enabled' => true]);

        if ($siteId !== null) {
            $query->andWhere(['or', ['siteId' => null], ['siteId' => $siteId]]);
            $query->orderBy(new \yii\db\Expression('CASE WHEN [[siteId]] = :siteId THEN 0 ELSE 1 END ASC, [[id]] ASC', [':siteId' => $siteId]));
        } else {
            $query->orderBy(['id' => SORT_ASC]);
        }

        $rows = $query->all();

        $campaigns = array_map(fn($row) => new Campaign($row), $rows);
        return array_values(array_filter($campaigns, fn(Campaign $c) => $c->getIsActive()));
    }

    /**
     * Get campaign by ID
     *
     * @param int $id
     * @return Campaign|null
     */
    public function getCampaignById(int $id): ?Campaign
    {
        $row = $this->createCampaignQuery()
            ->where(['id' => $id])
            ->one();

        return $row ? new Campaign($row) : null;
    }

    /**
     * Get campaign by handle
     *
     * @param string $handle
     * @return Campaign|null
     */
    public function getCampaignByHandle(string $handle): ?Campaign
    {
        $row = $this->createCampaignQuery()
            ->where(['handle' => $handle])
            ->one();

        return $row ? new Campaign($row) : null;
    }

    /**
     * Save a campaign
     *
     * @param Campaign $campaign
     * @param bool $runValidation
     * @return bool
     */
    public function saveCampaign(Campaign $campaign, bool $runValidation = true): bool
    {
        $isNew = !$campaign->id;

        if ($runValidation && !$campaign->validate()) {
            Craft::info('Campaign not saved due to validation errors.', __METHOD__);
            return false;
        }

        $now = Db::prepareDateForDb(new DateTime());
        $startDate = $campaign->startDate ? Db::prepareDateForDb(DateTimeHelper::toDateTime($campaign->startDate)) : null;
        $endDate = $campaign->endDate ? Db::prepareDateForDb(DateTimeHelper::toDateTime($campaign->endDate)) : null;

        $values = [
            'name' => $campaign->name,
            'handle' => $campaign->handle,
            'description' => $campaign->description,
            'enabled' => $campaign->enabled,
            'siteId' => $campaign->siteId,
            'displayMode' => $campaign->displayMode,
            'triggerType' => $campaign->triggerType,
            'triggerDelay' => $campaign->triggerDelay,
            'scrollThreshold' => $campaign->scrollThreshold,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'welcomeTitle' => $campaign->welcomeTitle,
            'welcomeText' => $campaign->welcomeText,
            'buttonText' => $campaign->buttonText,
            'resultTitle' => $campaign->resultTitle,
            'resultText' => $campaign->resultText,
            'requireEmail' => $campaign->requireEmail,
            'requireName' => $campaign->requireName,
            'requireConsent' => $campaign->requireConsent,
            'consentText' => $campaign->consentText,
            'requireVerification' => $campaign->requireVerification,
            'spinLimitPolicy' => $campaign->spinLimitPolicy,
            'spinPoolSize' => $campaign->spinPoolSize,
            'allocationMode' => $campaign->allocationMode,
            'poolCycle' => $campaign->poolCycle,
            'poolStrategy' => $campaign->poolStrategy,
            'wheelSettings' => $campaign->wheelSettings,
            'dateUpdated' => $now,
        ];

        if ($isNew) {
            $values['uid'] = StringHelper::UUID();
            $values['dateCreated'] = $now;

            $success = (bool)Craft::$app->getDb()->createCommand()
                ->insert('{{%spintowin_campaigns}}', $values)
                ->execute();

            if ($success) {
                $campaign->id = (int)Craft::$app->getDb()->getLastInsertID('{{%spintowin_campaigns}}');
            }
        } else {
            $success = (bool)Craft::$app->getDb()->createCommand()
                ->update('{{%spintowin_campaigns}}', $values, ['id' => $campaign->id])
                ->execute();
        }

        return $success;
    }

    /**
     * Delete campaign by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteCampaignById(int $id): bool
    {
        return (bool)Craft::$app->getDb()->createCommand()
            ->delete('{{%spintowin_campaigns}}', ['id' => $id])
            ->execute();
    }
}
