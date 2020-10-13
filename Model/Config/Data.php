<?php

namespace MageMojo\Cron\Model\Config;

use Magento\Cron\Model\Config\Reader\Xml as XmlReader;
use Magento\Cron\Model\Config\Reader\Db as DbReader;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Config\CacheInterface;

/**
 * Provides cron configuration
 */
class Data extends \Magento\Cron\Model\Config\Data
{
    protected $_dbReader;
    protected $_reader;

    /**
     * Constructor
     *
     * @param XmlReader $reader
     * @param CacheInterface $cache
     * @param DbReader $dbReader
     */
    public function __construct(
        XmlReader $reader,
        CacheInterface $cache,
        DbReader $dbReader
    ) {
        $this->_dbReader = $dbReader;
        $this->_reader = $reader;
        parent::__construct($reader, $cache,$dbReader);
        $this->merge($dbReader->get());
    }

    /**
     * Refresh config and get jobs
     *
     * @return array
     */
    public function getJobs()
    {
        $this->refreshConfig();
        return $this->get();
    }

    /**
     * Refresh data for configuration
     *
     * @return void
     */
    public function refreshConfig()
    {
        $this->_data = $this->_reader->read();
        $this->merge($this->_dbReader->get());
    }
}
