<?php
 
namespace MageMojo\Cron\Controller\Adminhtml\Settings;
use Magento\Backend\App\Action; 

class Index extends \Magento\Backend\App\Action
{
/** @var \Magento\Framework\View\Result\PageFactory  */
protected $resultPageFactory;
protected $scopeConfig;
public function __construct(
     \Magento\Backend\App\Action\Context $context,
     \Magento\Framework\View\Result\PageFactory $resultPageFactory,
     \Magento\Config\Model\ResourceModel\Config $scopeConfig
) {
     $this->resultPageFactory = $resultPageFactory;
     $this->scopeConfig = $scopeConfig;
     parent::__construct($context);
}
/**
* Load the page defined in view/adminhtml/layout/samplenewpage_sampleform_index.xml
*
* @return \Magento\Framework\View\Result\Page
*/
public function execute()
{
    if ($this->getRequest()->getParam('form_key')) {
      if ($this->getRequest()->getParam('enabled')) {
        $this->scopeConfig->saveConfig('magemojo/cron/enabled',1,'default',0);
      } else {
        $this->scopeConfig->saveConfig('magemojo/cron/enabled',0,'default',0);
      } 
      $this->scopeConfig->saveConfig('magemojo/cron/jobs',$this->getRequest()->getParam('maxjobs'),'default',0);
      $this->scopeConfig->saveConfig('magemojo/cron/phpproc',$this->getRequest()->getParam('phpproc'),'default',0);
      $this->scopeConfig->saveConfig('magemojo/cron/maxload',$this->getRequest()->getParam('maxload'),'default',0);
      $this->scopeConfig->saveConfig('magemojo/cron/history',$this->getRequest()->getParam('history'),'default',0);
    }
     

    return $this->resultPageFactory->create();
    
}
}
