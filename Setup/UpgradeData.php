<?php

namespace MageMojo\Cron\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeData implements UpgradeDataInterface
{

	public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
	{
        if (version_compare($context->getVersion(), '1.3.0', '<')) {
            $connection = $setup->getConnection();


            $select = $connection->select()->from($setup->getTable('core_config_data'))->where('path like ?', 'magemojo/cron/consumers_timeout');
            $result = $connection->fetchAll($select);

            #Create core_config_data settings
            if (count($result) == 0) {
                $insertData = array();
                array_push($insertData,array('scope' => 'default', 'scope_id' => 0, 'path' => 'magemojo/cron/consumers_timeout', 'value' => '30'));
                $connection->insertMultiple($setup->getTable('core_config_data'), $insertData);
            }
        }
        if (version_compare($context->getVersion(), '1.3.6', '<')) {
            $select = $connection->select()->from($setup->getTable('core_config_data'))->where('path like ?', 'magemojo/cron/exporters_timeout');
            $result = $connection->fetchAll($select);

            #Create core_config_data settings
            if (count($result) == 0) {
                $insertData = array();
                array_push($insertData,array('scope' => 'default', 'scope_id' => 0, 'path' => 'magemojo/cron/exporters_timeout', 'value' => '3600'));
                $connection->insertMultiple($setup->getTable('core_config_data'), $insertData);
            }

        }
	}
}
