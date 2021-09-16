<?php

namespace MageMojo\Cron\Controller\Adminhtml\Reports;
use MageMojo\Cron\Model\ResourceModel\Schedule;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Backend reports controller
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
	* Load the page defined in view/adminhtml/layout/magemojocron_reports_index.xml
	*
	* @return Page
	*/
	public function execute()
	{
	    return $this->resultPageFactory->create();
	}
}
