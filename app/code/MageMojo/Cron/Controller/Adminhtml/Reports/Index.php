<?php

namespace MageMojo\Cron\Controller\Adminhtml\Reports;
use Magento\Backend\App\Action;

/**
 * Backend reports controller
 */
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
	* Load the page defined in view/adminhtml/layout/magemojocron_reports_index.xml
	*
	* @return \Magento\Framework\View\Result\Page
	*/
	public function execute()
	{
	    return $this->resultPageFactory->create();
	}
}
