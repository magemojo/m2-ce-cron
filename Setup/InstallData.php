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

        #create var/cron directory if not exists
        $basedir = $this->directorylist->getRoot();

        if (!file_exists($basedir.'/var/cron')) {
          mkdir($basedir.'/var/cron');
        }

        $setup->endSetup();
    }
}
