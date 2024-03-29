<?php

namespace MageMojo\Cron\Controller\Adminhtml\Settings;
use MageMojo\Cron\Model\ResourceModel\Schedule;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Backend settings controller
 */
class Index extends Action
{
	/** @var PageFactory  */
	protected $resultPageFactory;
	protected $resource;
	public function __construct(
	     Context $context,
	     PageFactory $resultPageFactory,
	     Schedule $resource
	) {
	     $this->resultPageFactory = $resultPageFactory;
	     $this->resource = $resource;
	     parent::__construct($context);
	}

	/**
	* Load the page defined in view/adminhtml/layout/magemojocron_settings_index.xml
	*
	* @return Page
	*/
	public function execute()
	{
		#Saving settings on page post
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
	      if (is_numeric($this->getRequest()->getParam('consumers_timeout'))) {
	        $this->resource->setConfigValue('magemojo/cron/consumers_timeout','default',0,$this->getRequest()->getParam('consumers_timeout'));
	      } else {
	        $fail = true;
	        $this->messageManager->addError('Consumers Timeout must be numeric');
	      }
          if (is_numeric($this->getRequest()->getParam('exporters_timeout'))) {
            $this->resource->setConfigValue('magemojo/cron/exporters_timeout','default',0,$this->getRequest()->getParam('exporters_timeout'));
          } else {
            $fail = true;
            $this->messageManager->addError('Exporters Timeout must be numeric');
          }
	      if ($this->getRequest()->getParam('consumersgovernor')) {
	        $this->resource->setConfigValue('magemojo/cron/consumersgovernor','default',0,1);
	      } else {
	        $this->resource->setConfigValue('magemojo/cron/consumersgovernor','default',0,0);
	      }
          if ($this->getRequest()->getParam('cluster_support')) {
              $this->resource->setConfigValue('magemojo/cron/cluster_support','default',0,$this->getRequest()->getParam('cluster_support'));
          } else {
              $this->resource->setConfigValue('magemojo/cron/cluster_support','default',0,0);
          }
	      if (!$fail) {
	        $this->messageManager->addSuccess('Cron Configuration Saved');
	      }
	    }

	    return $this->resultPageFactory->create();

	}
}
