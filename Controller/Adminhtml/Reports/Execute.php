<?php

namespace MageMojo\Cron\Controller\Adminhtml\Reports;
use MageMojo\Cron\Model\Schedule;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Backend reports controller
 */
class Execute extends Action
{
	/** @var PageFactory  */
	protected $resultPageFactory;
	protected $schedule;
	public function __construct(
	     Context $context,
	     PageFactory $resultPageFactory,
	     Schedule $schedule
	) {
	     $this->resultPageFactory = $resultPageFactory;
	     $this->schedule = $schedule;
	     parent::__construct($context);
	}

	/**
	* Load the page defined in view/adminhtml/layout/magemojocron_reports_index.xml
	*
	* @return Page
	*/
	public function execute()
	{
	    $jobcode = $this->getRequest()->getParam('jobcode');
	    $this->schedule->executeImmediate($jobcode);
	    return $this->resultRedirectFactory->create()->setUrl($this->getUrl("*/*/index"));
	}
}

