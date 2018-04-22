<?php
namespace MageMojo\Cron\Model\Settings;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;

class DataProvider extends UiDataProvider
{
    public function __construct(string $name, string $primaryFieldName, string $requestFieldName, ReportingInterface $reporting, SearchCriteriaBuilder $searchCriteriaBuilder, RequestInterface $request, FilterBuilder $filterBuilder, array $meta = [], array $data = [])
    {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $reporting, $searchCriteriaBuilder, $request, $filterBuilder, $meta, $data);
    }
}
