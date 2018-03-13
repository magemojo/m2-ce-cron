<?php
namespace MageMojo\Cron\Block\Adminhtml;
use Magento\Framework\View\Element\Template;

class Settings extends \Magento\Framework\View\Element\Template
{
    private $_cronconfig;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->_resource = $resource;
        $this->scopeConfig = $scopeConfig;

        parent::__construct(
            $context,
            $data
        );
    }

    public function getConfig($path)
    {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function checkbox($path, $name) {
      $value = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE); 
      print '<input type="checkbox" name="'.$name.'" value="1" ';
      if ($value) {
        print 'checked';
      }
      print '>';
    }

    public function textfield($path, $name) {
      $value = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
      print '<input type="text" name="'.$name.'" value="'.$value.'">';
    }

}
