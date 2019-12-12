<?php

namespace MageMojo\Cron\Setup;

use Magento\Framework\Module\Setup\Migration;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */

class InstallData implements InstallDataInterface
{
    private $directorylist;

    public function __construct(\Magento\Framework\App\Filesystem\DirectoryList $directorylist)
    {
      $this->directorylist = $directorylist;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

        $setup->startSetup();


        $connection = $setup->getConnection();

        #Truncate the cron_schedule table as it's usually full of garbage with localized times
        $connection->delete($setup->getTable('cron_schedule'));

        $select = $connection->select()->from($setup->getTable('core_config_data'))->where('path like ?', 'magemojo/cron/%');
        $result = $connection->fetchAll($select);

        #Create core_config_data settings
        if (count($result) == 0) {
          $insertData = array();
          array_push($insertData,array('scope' => 'default', 'scope_id' => 0, 'path' => 'magemojo/cron/enabled', 'value' => '1'));
          array_push($insertData,array('scope' => 'default', 'scope_id' => 0, 'path' => 'magemojo/cron/jobs', 'value' => '3'));
          array_push($insertData,array('scope' => 'default', 'scope_id' => 0, 'path' => 'magemojo/cron/phpproc', 'value' => 'php'));
          array_push($insertData,array('scope' => 'default', 'scope_id' => 0, 'path' => 'magemojo/cron/history', 'value' => '1'));
          array_push($insertData,array('scope' => 'default', 'scope_id' => 0, 'path' => 'magemojo/cron/maxload', 'value' => '2'));

          $connection->insertMultiple($setup->getTable('core_config_data'), $insertData);
        }

        #create var/cron directory if not exists
        $basedir = $this->directorylist->getRoot();

        if (!file_exists($basedir.'/var/cron')) {
          mkdir($basedir.'/var/cron');
        }

        $setup->endSetup();
    }
}

