<?php

namespace MageMojo\Cron\Controller\Adminhtml\Reports;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\View\Result\Page;
use MageMojo\Cron\Model\Schedule;

/**
 * Backend reports controller
 *
 * Class Execute
 * @package MageMojo\Cron\Controller\Adminhtml\Reports
 */
class Execute extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Schedule|Schedule\Proxy
     */
    protected $schedule;

    /**
     * Execute constructor.
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Schedule\Proxy $schedule
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Schedule\Proxy $schedule
    )
    {
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

