<?php
namespace MageMojo\Cron\Block\Adminhtml;
use Magento\Framework\View\Element\Template;

class Settings extends \Magento\Framework\View\Element\Template
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

    public function getConfig($path)
    {
        return $this->resourceconfig->getConfigValue($path, 'default', 0);
    }

    public function checkbox($path, $name) {
      $value = $this->resourceconfig->getConfigValue($path, 'default', 0); 
      print '<input type="checkbox" name="'.$name.'" value="1" ';
      if ($value) {
        print 'checked';
      }
      print '>';
    }

    public function textfield($path, $name, $size, $max) {
      $value = $this->resourceconfig->getConfigValue($path, 'default', 0);
      print '<input type="text" name="'.$name.'" size="'.$size.'" maxchar="'.$max.'" value="'.$value.'">';
    }

}
