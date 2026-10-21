<?php
namespace abdulkadiragoliya\spintowin\migrations;

use Craft;
use craft\db\Migration;

/**
 * m261019_100000_add_probability_pool_columns migration.
 *
 * Adds Probability Spin Pool and per-segment quota tracking.
 *
 * @author Abdulkadir Agoliya
 * @since 1.1.0
 */
class m261019_100000_add_probability_pool_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // 1. Update campaigns table
        if ($this->db->tableExists('{{%spintowin_campaigns}}')) {
            if (!$this->db->columnExists('{{%spintowin_campaigns}}', 'spinPoolSize')) {
                $this->addColumn('{{%spintowin_campaigns}}', 'spinPoolSize', $this->integer()->defaultValue(1000)->notNull()->after('spinLimitPolicy'));
            }
            if (!$this->db->columnExists('{{%spintowin_campaigns}}', 'allocationMode')) {
                $this->addColumn('{{%spintowin_campaigns}}', 'allocationMode', $this->string(32)->defaultValue('quota_guaranteed')->notNull()->after('spinPoolSize'));
            }
            if (!$this->db->columnExists('{{%spintowin_campaigns}}', 'poolCycle')) {
                $this->addColumn('{{%spintowin_campaigns}}', 'poolCycle', $this->integer()->defaultValue(1)->notNull()->after('allocationMode'));
            }
            if (!$this->db->columnExists('{{%spintowin_campaigns}}', 'poolStrategy')) {
                $this->addColumn('{{%spintowin_campaigns}}', 'poolStrategy', $this->string(32)->defaultValue('rollover')->notNull()->after('poolCycle'));
            }
        }

        // 2. Update segments table
        if ($this->db->tableExists('{{%spintowin_segments}}')) {
            if (!$this->db->columnExists('{{%spintowin_segments}}', 'allocatedCount')) {
                $this->addColumn('{{%spintowin_segments}}', 'allocatedCount', $this->integer()->defaultValue(0)->notNull()->after('probabilityWeight'));
            }
            if (!$this->db->columnExists('{{%spintowin_segments}}', 'awardedCount')) {
                $this->addColumn('{{%spintowin_segments}}', 'awardedCount', $this->integer()->defaultValue(0)->notNull()->after('allocatedCount'));
            }
            if (!$this->db->columnExists('{{%spintowin_segments}}', 'poolAwardedCount')) {
                $this->addColumn('{{%spintowin_segments}}', 'poolAwardedCount', $this->integer()->defaultValue(0)->notNull()->after('awardedCount'));
            }
        }

        // 3. Update spins table
        if ($this->db->tableExists('{{%spintowin_spins}}')) {
            if (!$this->db->columnExists('{{%spintowin_spins}}', 'poolCycle')) {
                $this->addColumn('{{%spintowin_spins}}', 'poolCycle', $this->integer()->defaultValue(1)->notNull()->after('siteId'));
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->tableExists('{{%spintowin_spins}}')) {
            if ($this->db->columnExists('{{%spintowin_spins}}', 'poolCycle')) {
                $this->dropColumn('{{%spintowin_spins}}', 'poolCycle');
            }
        }

        if ($this->db->tableExists('{{%spintowin_segments}}')) {
            if ($this->db->columnExists('{{%spintowin_segments}}', 'poolAwardedCount')) {
                $this->dropColumn('{{%spintowin_segments}}', 'poolAwardedCount');
            }
            if ($this->db->columnExists('{{%spintowin_segments}}', 'awardedCount')) {
                $this->dropColumn('{{%spintowin_segments}}', 'awardedCount');
            }
            if ($this->db->columnExists('{{%spintowin_segments}}', 'allocatedCount')) {
                $this->dropColumn('{{%spintowin_segments}}', 'allocatedCount');
            }
        }

        if ($this->db->tableExists('{{%spintowin_campaigns}}')) {
            if ($this->db->columnExists('{{%spintowin_campaigns}}', 'poolStrategy')) {
                $this->dropColumn('{{%spintowin_campaigns}}', 'poolStrategy');
            }
            if ($this->db->columnExists('{{%spintowin_campaigns}}', 'poolCycle')) {
                $this->dropColumn('{{%spintowin_campaigns}}', 'poolCycle');
            }
            if ($this->db->columnExists('{{%spintowin_campaigns}}', 'allocationMode')) {
                $this->dropColumn('{{%spintowin_campaigns}}', 'allocationMode');
            }
            if ($this->db->columnExists('{{%spintowin_campaigns}}', 'spinPoolSize')) {
                $this->dropColumn('{{%spintowin_campaigns}}', 'spinPoolSize');
            }
        }

        return true;
    }
}
