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
      $fail = false;
      if ($this->getRequest()->getParam('enabled')) {
        $this->resource->setConfigValue('magemojo/cron/enabled','default',0,1);
      } else {
        $this->resource->setConfigValue('magemojo/cron/enabled','default',0,0);
      }
      if (is_numeric($this->getRequest()->getParam('maxjobs'))) {
        $this->resource->setConfigValue('magemojo/cron/jobs','default',0,$this->getRequest()->getParam('maxjobs'));
      } else {
        $fail = true;
        $this->messageManager->addError('Max Jobs must be numeric');
      }
      if ($this->getRequest()->getParam('phpproc')) {
        $this->resource->setConfigValue('magemojo/cron/phpproc','default',0,$this->getRequest()->getParam('phpproc'));
      } else {
        $fail = true;
        $this->messageManager->addError('PHP Binary cannot by null');
      }
      if (is_numeric($this->getRequest()->getParam('maxload'))) {
        $this->resource->setConfigValue('magemojo/cron/maxload','default',0,$this->getRequest()->getParam('maxload'));
      } else {
        $fail = true;
        $this->messageManager->addError('Max Load must be numeric');
      }
      if (is_numeric($this->getRequest()->getParam('history'))) {
        $this->resource->setConfigValue('magemojo/cron/history','default',0,$this->getRequest()->getParam('history'));
      } else {
        $fail = true;
        $this->messageManager->addError('History must be numeric');
      }
      if (!$fail) {
        $this->messageManager->addSuccess('Cron Configuration Saved');
      }
    }


    return $this->resultPageFactory->create();

}
}
