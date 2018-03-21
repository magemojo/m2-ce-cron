<?php
namespace MageMojo\Cron\Block\Adminhtml;
use Magento\Framework\View\Element\Template;

class Reports extends \Magento\Framework\View\Element\Template
{
    private $_cronconfig;
    protected $resourceconfig;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\App\ResourceConnection $resource,
        \MageMojo\Cron\Model\ResourceModel\Schedule $resourceconfig,
        array $data = []
    ) {
        $this->_resource = $resource;
        $this->resourceconfig = $resourceconfig;

        parent::__construct(
            $context,
            $data
        );
    }
    
    public function getReport() {
      return $this->resourceconfig->getReport();
    }

    public function getErrorReport() {
      return $this->resourceconfig->getErrorReport();
    }

    public function getLocalTimezone()
    {
        return $this->resourceconfig->getConfigValue('general/locale/timezone', 'default', 0);
    }

}
