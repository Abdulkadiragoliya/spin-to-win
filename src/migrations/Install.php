<?php
namespace abdulkadiragoliya\spintowin\migrations;

use Craft;
use craft\db\Migration;

/**
 * Installation Migration
 *
 * @author Abdulkadir Agoliya
 * @since 1.0.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%spintowin_events}}');
        $this->dropTableIfExists('{{%spintowin_coupons}}');
        $this->dropTableIfExists('{{%spintowin_spins}}');
        $this->dropTableIfExists('{{%spintowin_segments}}');
        $this->dropTableIfExists('{{%spintowin_campaigns}}');

        return true;
    }

    /**
     * Creates database tables
     */
    protected function createTables(): void
    {
        // 1. Campaigns
        if (!$this->db->tableExists('{{%spintowin_campaigns}}')) {
            $this->createTable('{{%spintowin_campaigns}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull(),
                'handle' => $this->string()->notNull(),
                'description' => $this->text(),
                'enabled' => $this->boolean()->defaultValue(true)->notNull(),
                'siteId' => $this->integer(),
                'displayMode' => $this->string(32)->defaultValue('popup')->notNull(),
                'triggerType' => $this->string(32)->defaultValue('load')->notNull(),
                'triggerDelay' => $this->integer()->defaultValue(0)->notNull(),
                'scrollThreshold' => $this->integer()->defaultValue(0)->notNull(),
                'startDate' => $this->dateTime(),
                'endDate' => $this->dateTime(),
                'welcomeTitle' => $this->string(),
                'welcomeText' => $this->text(),
                'buttonText' => $this->string(64)->defaultValue('Spin to Win!')->notNull(),
                'resultTitle' => $this->string(),
                'resultText' => $this->text(),
                'requireEmail' => $this->boolean()->defaultValue(true)->notNull(),
                'requireName' => $this->boolean()->defaultValue(false)->notNull(),
                'requireConsent' => $this->boolean()->defaultValue(false)->notNull(),
                'consentText' => $this->text(),
                'requireVerification' => $this->boolean()->defaultValue(false)->notNull(),
                'spinLimitPolicy' => $this->string(32)->defaultValue('daily_per_email')->notNull(),
                'spinPoolSize' => $this->integer()->defaultValue(1000)->notNull(),
                'allocationMode' => $this->string(32)->defaultValue('quota_guaranteed')->notNull(),
                'poolCycle' => $this->integer()->defaultValue(1)->notNull(),
                'poolStrategy' => $this->string(32)->defaultValue('rollover')->notNull(),
                'wheelSettings' => $this->text(), // JSON config for visual styling & colors
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        // 2. Segments
        if (!$this->db->tableExists('{{%spintowin_segments}}')) {
            $this->createTable('{{%spintowin_segments}}', [
                'id' => $this->primaryKey(),
                'campaignId' => $this->integer()->notNull(),
                'label' => $this->string()->notNull(),
                'rewardType' => $this->string(32)->defaultValue('no_prize')->notNull(),
                'discountValue' => $this->decimal(10, 2)->defaultValue(0.00),
                'discountId' => $this->integer(),
                'probabilityWeight' => $this->integer()->defaultValue(1)->notNull(),
                'allocatedCount' => $this->integer()->defaultValue(0)->notNull(),
                'awardedCount' => $this->integer()->defaultValue(0)->notNull(),
                'poolAwardedCount' => $this->integer()->defaultValue(0)->notNull(),
                'couponDurationDays' => $this->integer()->defaultValue(7),
                'minOrderValue' => $this->decimal(10, 2)->defaultValue(0.00),
                'maxUsesPerCoupon' => $this->integer()->defaultValue(1),
                'color' => $this->string(32)->notNull(),
                'textColor' => $this->string(32)->defaultValue('#ffffff')->notNull(),
                'sortOrder' => $this->smallInteger()->unsigned()->defaultValue(0),
                'isEnabled' => $this->boolean()->defaultValue(true)->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        // 3. Spins
        if (!$this->db->tableExists('{{%spintowin_spins}}')) {
            $this->createTable('{{%spintowin_spins}}', [
                'id' => $this->primaryKey(),
                'campaignId' => $this->integer()->notNull(),
                'segmentId' => $this->integer(),
                'email' => $this->string()->notNull(),
                'emailNormalized' => $this->string()->notNull(),
                'customerId' => $this->integer(),
                'name' => $this->string(),
                'ipAddress' => $this->string(45),
                'userAgent' => $this->string(255),
                'siteId' => $this->integer(),
                'poolCycle' => $this->integer()->defaultValue(1)->notNull(),
                'isWinner' => $this->boolean()->defaultValue(false)->notNull(),
                'rewardType' => $this->string(32),
                'couponCode' => $this->string(64),
                'verificationToken' => $this->string(64),
                'isVerified' => $this->boolean()->defaultValue(false)->notNull(),
                'verifiedAt' => $this->dateTime(),
                'spinDate' => $this->date()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        // 4. Coupons
        if (!$this->db->tableExists('{{%spintowin_coupons}}')) {
            $this->createTable('{{%spintowin_coupons}}', [
                'id' => $this->primaryKey(),
                'spinId' => $this->integer()->notNull(),
                'campaignId' => $this->integer()->notNull(),
                'segmentId' => $this->integer(),
                'commerceDiscountId' => $this->integer(),
                'commerceCouponId' => $this->integer(),
                'code' => $this->string(64)->notNull(),
                'discountType' => $this->string(32)->notNull(),
                'discountAmount' => $this->decimal(10, 2)->defaultValue(0.00),
                'minOrderValue' => $this->decimal(10, 2)->defaultValue(0.00),
                'expiresAt' => $this->dateTime(),
                'isRedeemed' => $this->boolean()->defaultValue(false)->notNull(),
                'orderId' => $this->integer(),
                'redeemedAt' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        // 5. Events
        if (!$this->db->tableExists('{{%spintowin_events}}')) {
            $this->createTable('{{%spintowin_events}}', [
                'id' => $this->primaryKey(),
                'campaignId' => $this->integer()->notNull(),
                'eventType' => $this->string(32)->notNull(),
                'ipAddress' => $this->string(45),
                'userAgent' => $this->string(255),
                'metadata' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
            ]);
        }
    }

    /**
     * Creates indexes
     */
    protected function createIndexes(): void
    {
        $this->createIndex(null, '{{%spintowin_campaigns}}', ['handle'], true);
        $this->createIndex(null, '{{%spintowin_campaigns}}', ['enabled']);

        $this->createIndex(null, '{{%spintowin_segments}}', ['campaignId']);
        $this->createIndex(null, '{{%spintowin_segments}}', ['sortOrder']);

        $this->createIndex(null, '{{%spintowin_spins}}', ['campaignId', 'emailNormalized', 'spinDate']);
        $this->createIndex(null, '{{%spintowin_spins}}', ['verificationToken']);
        $this->createIndex(null, '{{%spintowin_spins}}', ['spinDate']);

        $this->createIndex(null, '{{%spintowin_coupons}}', ['code'], true);
        $this->createIndex(null, '{{%spintowin_coupons}}', ['spinId']);
        $this->createIndex(null, '{{%spintowin_coupons}}', ['isRedeemed']);

        $this->createIndex(null, '{{%spintowin_events}}', ['campaignId', 'eventType']);
        $this->createIndex(null, '{{%spintowin_events}}', ['dateCreated']);
    }

    /**
     * Adds foreign keys
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(
            null,
            '{{%spintowin_segments}}',
            'campaignId',
            '{{%spintowin_campaigns}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%spintowin_spins}}',
            'campaignId',
            '{{%spintowin_campaigns}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%spintowin_spins}}',
            'segmentId',
            '{{%spintowin_segments}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%spintowin_coupons}}',
            'spinId',
            '{{%spintowin_spins}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%spintowin_coupons}}',
            'campaignId',
            '{{%spintowin_campaigns}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%spintowin_events}}',
            'campaignId',
            '{{%spintowin_campaigns}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }
}
