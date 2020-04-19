<?php
namespace MageMojo\Cron\Block\Adminhtml;
use Magento\Framework\View\Element\Template;

/**
 * Backend settings block
 */
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

    /**
     * Get value from core_config_data
     *
     * @return string
     */
    public function getConfig($path)
    {
        return $this->resourceconfig->getConfigValue($path, 'default', 0);
    }

    /**
     * Get rendered checkbox html
     *
     * @return string
     */
    public function checkbox($path, $name) {
      $value = $this->resourceconfig->getConfigValue($path, 'default', 0);
      print '<input type="checkbox" name="'.$name.'" value="1" ';
      if ($value) {
        print 'checked';
      }
      print '>';
    }

    /**
     * Get rendered textbox html
     *
     * @return string
     */
    public function textfield($path, $name, $size, $max) {
      $value = $this->resourceconfig->getConfigValue($path, 'default', 0);
      print '<input type="text" name="'.$name.'" size="'.$size.'" maxchar="'.$max.'" value="'.htmlspecialchars($value).'">';
    }

}
