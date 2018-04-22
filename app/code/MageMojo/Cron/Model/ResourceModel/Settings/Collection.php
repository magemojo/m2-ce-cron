<?php

namespace MageMojo\Cron\Model\ResourceModel\Settings;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
/**
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(
            \Magento\Framework\App\Config\Value::class,
            \Magento\Config\Model\ResourceModel\Config\Data::class
        );
    }
}