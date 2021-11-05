<?php
namespace MageMojo\Cron\Block\Adminhtml;
use MageMojo\Cron\Model\ResourceModel\Schedule;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Backend reports block
 */
class Reports extends Template
{
    private $_cronconfig;
    protected $resourceconfig;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        Schedule $resourceconfig,
        array $data = []
    ) {
        $this->_resource = $resource;
        $this->resourceconfig = $resourceconfig;

        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Get summarized data for cron_schedule
     *
     * @return collection
     */
    public function getReport() {
      return $this->resourceconfig->getReport();
    }

    /**
     * Get summarized error data for cron_schedule
     *
     * @return collection
     */
    public function getErrorReport() {
      return $this->resourceconfig->getErrorReport();
    }

    /**
     * Get timezone locale
     *
     * @return string
     */
    public function getLocalTimezone()
    {
        return $this->resourceconfig->getConfigValue('general/locale/timezone', 'default', 0);
    }

    /**
     * Get cluster support configuration value
     *
     * @return string
     */
    public function getClusterSupport()
    {
        return $this->resourceconfig->getConfigValue('magemojo/cron/cluster_support', 'default', 0);
    }

}
