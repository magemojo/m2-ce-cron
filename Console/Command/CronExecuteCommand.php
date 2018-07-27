<?php
namespace MageMojo\Cron\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use MageMojo\Cron\Model\Schedule;
use Magento\Framework\Console\Cli;
use Magento\Framework\Shell\ComplexParameter;

/**
 * Command for executing cron jobs
 */
class CronExecuteCommand extends Command
{

    const INPUT_KEY_JOBCODE = 'jobcode';

    /**
     * Object manager factory
     *
     * @var ObjectManagerFactory
     */
    private $objectManagerFactory;
    protected $schedule;

    /**
     * Constructor
     *
     * @param ObjectManagerFactory $objectManagerFactory
     */
    public function __construct(ObjectManagerFactory $objectManagerFactory, \MageMojo\Cron\Model\Schedule $schedule)
    {
        $this->objectManagerFactory = $objectManagerFactory;
        $this->schedule = $schedule;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $options = [
            new InputOption(
                self::INPUT_KEY_JOBCODE,
                null,
                InputOption::VALUE_REQUIRED,
                'Job code to be run'
            ),
            new InputOption(
                Cli::INPUT_KEY_BOOTSTRAP,
                null,
                InputOption::VALUE_REQUIRED,
                'Add or override parameters of the bootstrap'
            ),
        ];
        $this->setName('cron:execute')
            ->setDescription('Runs a job by job_code immediately')
            ->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$this->schedule->executeImmediate($input->getOption(self::INPUT_KEY_JOBCODE));
    }
}

