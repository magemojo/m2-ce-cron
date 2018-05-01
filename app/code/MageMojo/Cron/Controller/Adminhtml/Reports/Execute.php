<?php

namespace MageMojo\Cron\Controller\Adminhtml\Reports;
use Magento\Backend\App\Action;

/**
 * Backend reports controller
 */
class Execute extends \Magento\Backend\App\Action
{
	/** @var \Magento\Framework\View\Result\PageFactory  */
	protected $resultPageFactory;
	protected $schedule;
	public function __construct(
	     \Magento\Backend\App\Action\Context $context,
	     \Magento\Framework\View\Result\PageFactory $resultPageFactory,
	     \MageMojo\Cron\Model\Schedule $schedule
	) {
	     $this->resultPageFactory = $resultPageFactory;
	     $this->schedule = $schedule;
	     parent::__construct($context);
	}

	/**
	* Load the page defined in view/adminhtml/layout/magemojocron_reports_index.xml
	*
	* @return \Magento\Framework\View\Result\Page
	*/
	public function execute()
	{
	    $jobcode = $this->getRequest()->getParam('jobcode');
	    $this->schedule->executeImmediate($jobcode);
	    return $this->resultRedirectFactory->create()->setUrl($this->getUrl("*/*/index"));
	}
}

