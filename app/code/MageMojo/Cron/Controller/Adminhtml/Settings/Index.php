<?php

namespace MageMojo\Cron\Controller\Adminhtml\Settings;
use Magento\Backend\App\Action;

class Index extends \Magento\Backend\App\Action
{
/** @var \Magento\Framework\View\Result\PageFactory  */
protected $resultPageFactory;
protected $resource;
public function __construct(
     \Magento\Backend\App\Action\Context $context,
     \Magento\Framework\View\Result\PageFactory $resultPageFactory,
     \MageMojo\Cron\Model\ResourceModel\Schedule $resource
) {
     $this->resultPageFactory = $resultPageFactory;
     $this->resource = $resource;
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
        $this->resource->setConfigValue('magemojo/cron/enabled','default',0,1);
      } else {
        $this->resource->setConfigValue('magemojo/cron/enabled','default',0,0);
      }
      $this->resource->setConfigValue('magemojo/cron/jobs','default',0,$this->getRequest()->getParam('maxjobs'));
      $this->resource->setConfigValue('magemojo/cron/phpproc','default',0,$this->getRequest()->getParam('phpproc'));
      $this->resource->setConfigValue('magemojo/cron/maxload','default',0,$this->getRequest()->getParam('maxload'));
      $this->resource->setConfigValue('magemojo/cron/history','default',0,$this->getRequest()->getParam('history'));
    }


    return $this->resultPageFactory->create();

}
}
