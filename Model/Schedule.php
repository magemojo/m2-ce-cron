<?php
namespace MageMojo\Cron\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Filesystem\directoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\MessageQueue\ConnectionTypeResolver;
use Magento\Framework\MessageQueue\Consumer\Config\ConsumerConfigItemInterface;

class Schedule extends \Magento\Framework\Model\AbstractModel
{
    const VAR_FOLDER_PATH = BP . '/'. directoryList::VAR_DIR;
    const CRON_FOLDER_PATH = '/cron/schedule';

    private $simultaniousJobs;
    private $phpproc;
    private $maxload;
    private $history;
    private $config;
    private $cronenabled;
    private $runningPids;
    private $cronconfig;
    private $lastJobTime;
    private $pendingjobs;
    private $loadavgtest;
    private $directoryList;
    private $resource;
    private $maintenance;
    private $basedir;
    private $consumerConfig;
    private $deploymentConfig;
    private $scopeConfig;
    private $mqConnectionTypeResolver;

    /**
     * @var \Magento\Framework\Filesystem\Driver\File
     */
    private $file;

    /**
     * Schedule constructor.
     * @param \Magento\Cron\Model\Config $cronconfig
     * @param directoryList $directoryList
     * @param ResourceModel\Schedule $resource
     * @param \Magento\Framework\App\MaintenanceMode $maintenance
     * @param \Magento\Framework\Filesystem\Driver\File $file
     * @param \Magento\Framework\MessageQueue\Consumer\ConfigInterface $consumerConfig
     * @param \Magento\Framework\App\DeploymentConfig $deploymentConfig
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param ConnectionTypeResolver|null $mqConnectionTypeResolver
     */
    public function __construct(
        \Magento\Cron\Model\Config $cronconfig,
        directoryList $directoryList,
        \MageMojo\Cron\Model\ResourceModel\Schedule $resource,
        \Magento\Framework\App\MaintenanceMode $maintenance,
        \Magento\Framework\Filesystem\Driver\File $file,
        \Magento\Framework\MessageQueue\Consumer\ConfigInterface $consumerConfig,
        \Magento\Framework\App\DeploymentConfig $deploymentConfig,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        ConnectionTypeResolver $mqConnectionTypeResolver = null
    ) {
        $this->cronconfig = $cronconfig;
        $this->directoryList = $directoryList;
        $this->resource = $resource;
        $this->maintenance = $maintenance;
        $this->file = $file;
        $this->consumerConfig = $consumerConfig;
        $this->deploymentConfig = $deploymentConfig;
        $this->scopeConfig = $scopeConfig;
        $this->mqConnectionTypeResolver = $mqConnectionTypeResolver
            ?: ObjectManager::getInstance()->get(ConnectionTypeResolver::class);
    }

    /**
     * Get cron schedule config derrived from crontab.xml files
     *
     * @return array
     */
    public function getConfig() {
      $jobs = array();
      foreach($this->cronconfig->getJobs() as $groupname=>$group) {
        foreach($group as $name=>$job) {
          if (!is_array($job)) continue;
          if (!isset($job["schedule"])) {
            if (isset($job["config_path"])) {
              $schedule =  $this->scopeConfig->getValue($job["config_path"]);
              if ($schedule) {
                $job["schedule"] = $schedule;
              }
            }
          }
          $job["name"] = $name;
          $job["group"] = $groupname;
          $job["consumers"] = false;
          if ($job["name"] == "consumers_runner") {
            $job["consumers"] = true;
          }
          $jobs[$name] = $job;
        }
      }
      $this->config = $jobs;
    }

    /**
     * Set initial service startup parameters
     *
     * @return void
     */
    public function initialize() {
      #Keep the service alive indefinitely
      ini_set('max_execution_time', 0);

      $this->getConfig();
      $this->getRuntimeParameters();
      if (!$this->cronenabled) {
        $this->printWarn('Cron is disabled');
      } else {
        $this->cleanupProcesses();
        $this->lastJobTime = $this->resource->getLastJobTime();
        if ($this->lastJobTime < time() - 360) {
          $this->lastJobTime = time();
        }
        $pid = getmypid();
        $this->setPid('cron.pid',$pid);
        $this->pendingjobs = $this->resource->getAllPendingJobs();
        $this->loadavgtest = true;
        if (!is_readable('/proc/cpuinfo')) {
          $this->loadavgtest = false;
          $this->printWarn('Unable to test loadaverage disabling loadaverage checking');
        }
      }
    }

    /**
     * Check file in var/cron for running process pid or schedule output
     *
     * @return string
     */
    public function checkPid($pidfile) {
      if (file_exists(self::VAR_FOLDER_PATH.'/cron/'.$pidfile)){
        $scheduleid = file_get_contents(self::VAR_FOLDER_PATH.'/cron/'.$pidfile);
        return $scheduleid;
      }
      return false;
    }

    /**
     * Set file in var/cron for running process pid or schedule output
     *
     * @return void
     */
    public function setPid($file,$scheduleid) {
      file_put_contents(self::VAR_FOLDER_PATH.'/cron/'.$file,$scheduleid);
    }

    /**
     * Remove file in var/cron for ended process pid or schedule output
     *
     * @return void
     */
    public function unsetPid($pid) {
      $pidfile = self::VAR_FOLDER_PATH.'/cron/'.$pid;
      if(file_exists($pidfile)) {
        unlink($pidfile);
      }
    }

    /**
     * Get all pid files in var/cron for running processes
     *
     * @return array
     */
    public function getRunningPids() {
      $pids = array();
      $filelist = scandir(self::VAR_FOLDER_PATH.'/cron/');

      foreach ($filelist as $file) {
        if ($file != 'cron.pid') {
          $pid = str_replace('cron.','',$file);
          if (is_numeric($pid)) {
            $pids[$pid] = file_get_contents(self::VAR_FOLDER_PATH.'/cron/'.$file);
          }
        }
      }
      return $pids;
    }

    /**
     * Check if a pid is still running
     *
     * @return bool
     */
    public function checkProcess($pid) {
      if (file_exists( "/proc/$pid" )){
        return true;
      }
      return false;
    }

    /**
     * Get output of cron job from var/cron
     *
     * @return string
     */
    public function getJobOutput($scheduleid,$tail=False) {
      $file = self::VAR_FOLDER_PATH.self::CRON_FOLDER_PATH.".{$scheduleid}";
      if ($tail) {
        if (file_exists($file)){
          $handler = fopen($file,"r");
          fseek($handler, -2000, SEEK_END);
          return fread($handler,2000);
        }
      } else {
        if (file_exists($file)){
          return trim(file_get_contents($file));
        }
      }
      return NULL;
    }

    /**
     * On startup initialization clean process ids that are no longer running
     *
     * @return void
     */
    public function cleanupProcesses() {
      $this->printInfo('Running Process Cleanup');
      $running = array();
      $pids =  $this->getRunningPids();
      foreach ($pids as $pid=>$scheduleid) {
        if (!$this->checkProcess($pid)) {
          $this->unsetPid('cron.'.$pid);
          $this->resource->resetSchedule();
        } else {
          array_push($running,$pid);
        }
      }
      $this->runningPids = $running;
      if ($this->governor) {
        $this->consumersCleanup();
      }
    }

    public function consumersCleanup() {
      $this->printInfo('Running Consumers Cleanup');
      while ($pgrep = exec('pgrep -x strace')) {
        $pids = explode("\n",$pgrep);
        foreach ($pids as $pid) {
          $childpid = $this->getChildProcess($pid);
          $this->consumersTerminate($pid);
        }
      }
    }


    /**
     * Set runtime parameters
     *
     * @return void
     */
    public function getRuntimeParameters() {
      $this->simultaniousJobs = $this->resource->getConfigValue('magemojo/cron/jobs',0,'default');
      $this->phpproc = $this->resource->getConfigValue('magemojo/cron/phpproc',0,'default');
      $this->maxload = $this->resource->getConfigValue('magemojo/cron/maxload',0,'default');
      $this->history = $this->resource->getConfigValue('magemojo/cron/history',0,'default');
      $this->cronenabled = $this->resource->getConfigValue('magemojo/cron/enabled',0,'default');
      $this->governor = $this->resource->getConfigValue('magemojo/cron/consumersgovernor',0,'default');
    }

    /**
     * Checks an individual cron expression for validity
     *
     * @return bool
     */
    public function checkCronExpression($expr,$value) {
      foreach (explode(',',$expr) as $e) {
        if (($e == '*') or ($e == $value)) {
          return true;
        }
        $i = explode('/',$e);
        if (count($i) == 2) {
          if (is_int($value / $i[1])) {
            return true;
          }
        }
        $i = explode('-',$e);
        if (count($i) == 2) {
          if (($value >= $i[0]) and ($value <= $i[1])) {
            return true;
          }
        }
      }
      return false;
    }

    /**
     * Create cron_schedule entries for defined time period
     *
     * @return void
     */
    public function createSchedule($from, $to) {
      $this->getConfig();
      $allowedConsumers = $this->deploymentConfig->get('cron_consumers_runner/consumers', []);
      $runConsumersInCron = $this->deploymentConfig->get('cron_consumers_runner/cron_run', true);
      foreach($this->config as $job) {
        if (isset($job["schedule"])) {
          $schedule = array();
          $expr = explode(' ',$job["schedule"]);
          $buildtime = (floor($from/60)*60);
          while ($buildtime < $to) {
            $buildtime = $buildtime + 60;
            if (($this->checkCronExpression($expr[4],date('w',$buildtime)))
              and ($this->checkCronExpression($expr[3],date('n',$buildtime)))
              and ($this->checkCronExpression($expr[2],date('j',$buildtime)))
              and ($this->checkCronExpression($expr[1],date('G',$buildtime)))
              and ($this->checkCronExpression($expr[0],(int)date('i',$buildtime)))) {
              array_push($schedule,$buildtime);
            }
          }
          if (count($schedule) > 0) {
            #intercept the consumers_runner job and schedule it in a sane manner that doesn't cron bomb the system
            if ($job["consumers"]) {
              if(!$runConsumersInCron) {
                  continue;
              }
              foreach ($this->consumerConfig->getConsumers() as $consumer) {
                if ($this->canConsumerBeRun($consumer, $allowedConsumers)) {
                  $conjob = $job;
                  $conjob["name"] = "mm_consumer_".$consumer->getName();
                  $this->resource->saveSchedule($conjob,time(),$schedule);
                }
              }
            } else{
              $this->resource->saveSchedule($job,time(),$schedule);
            }
          }
        }
      }
    }

    public function checkCronFolderExistence()
    {
        try {
            $this->file->createDirectory(self::VAR_FOLDER_PATH.self::CRON_FOLDER_PATH);
        } catch (FileSystemException $e) {
            echo "Can't create folder in following path: " . self::VAR_FOLDER_PATH . self::CRON_FOLDER_PATH.PHP_EOL;
        }
    }

     /**
      * Initial startup process
      *
      * @return void
     */
    public function execute() {
      $this->basedir = $this->directoryList->getRoot();
      $this->checkCronFolderExistence();
      $this->printInfo('Healthchecking Cron Service');
      $pid = $this->checkPid('cron.pid');
      if (!$this->checkProcess($pid) or (!$pid)) {
        $this->initialize();
        if ($this->cronenabled == 0) {
          exit;
        } else {
          $this->service();
        }
      }
    }

    /**
     * Get an individual configuration for a job_code
     *
     * @return array|false
     */
    public function getJobConfig($jobname) {
      #if a consumers job get default consumers runner config
      if (strpos($jobname,"mm_consumer") > -1) {
        $job = $this->config["consumers_runner"];
        $job["name"] = $jobname;
      } elseif (isset($this->config[$jobname])) {
        $job = $this->config[$jobname];
      } else {
          $job = false;
      }
      return $job;
    }

    /**
     * Replace method and instance values in the stub proc to be executed
     *
     * @return string
     */
    public function prepareStub($jobconfig, $stub, $scheduleid) {
      if (!isset($jobconfig["instance"]) || !isset($jobconfig["method"])) {
        return false;
      }
      $code = trim($stub);
      $code = str_replace('<<basedir>>',$this->basedir,$code);
      $code = str_replace('<<method>>',$jobconfig["method"],$code);
      $code = str_replace('<<instance>>',$jobconfig["instance"],$code);
      $code = str_replace('<<scheduleid>>',$scheduleid,$code);
      $code = str_replace('<<name>>',$jobconfig["name"]??'',$code);
      return $code;
    }

    /**
     * Determine if new cron tasks can start
     *
     * @return bool
     */
    public function canRunJobs($jobcount, $pending) {
      if ($this->loadavgtest) {
        $cpunum = exec('cat /proc/cpuinfo | grep processor | wc -l');
        if (!$cpunum) {
          $cpunum = 1;
        }
        if ((sys_getloadavg()[0] / $cpunum) > $this->maxload) {
          $this->printWarn("Crons suspended due to high load average: ".(sys_getloadavg()[0] / $cpunum));
          return false;
        }
      }
      $exempt = $this->maintenance->getAddressInfo();
      /* Suspend crons in maintenance mode if no internal testing IPs are present */
      $maint = $this->maintenance->isOn() && empty($exempt);
      if ($maint) {
        $this->printWarn("Crons suspended due to maintenance mode being enabled");
        return false;
      }
      if ($jobcount > $this->simultaniousJobs) {
        return false;
      }
      return true;
    }

    /**
     * Get all pending jobs
     *
     * @return collection
     */
    function getPendingJobs() {
      $jobs = array();
      foreach ($this->pendingjobs as $job) {
        if (($job["status"] == 'pending') and ($job["scheduled_at"] < date('Y-m-d H:i:s',time()))) {
          if (isset($jobs[$job["job_code"]])) {
            $jobs[$job["job_code"]]["count"] = $jobs[$job["job_code"]]["count"] + 1;
          } else {
            $jobs[$job["job_code"]] = $job;
            $jobs[$job["job_code"]]["count"] = 1;
          }
        }
      }

    }

    /**
     * Set the status of a job
     *
     * @return void
     */
    function setJobStatus($scheduleid,$status,$output) {
      $this->pendingjobs[$scheduleid]["status"] = $status;
      $this->resource->setJobStatus($scheduleid,$status,$output);
    }

    /**
     * Set a job by schedule id
     *
     * @return array
     */
    function getJob($scheduleid) {
      if (isset($this->pendingjobs[$scheduleid])) {
        return $this->pendingjobs[$scheduleid];
      }
      return NULL;
    }

    /**
     * Service loop for running crons
     *
     * @return void
     */
    public function service() {
      #Get the code stub that executes individual crons
      $stub = file_get_contents(__DIR__.'/stub.txt');

      #Force UTC
      date_default_timezone_set('UTC');

      #Set transaction name for New Relic, if installed
      if (extension_loaded ('newrelic')) {
        newrelic_name_transaction ('magemojo_cron_service');
      }

      $this->printInfo("Starting Cron Service");
      #Loop until killed or heat death of the universe
      while (true) {
        $this->getRuntimeParameters();
        if ($this->cronenabled == 0) {
          $this->printWarn("Stopped Cron Service by maintenance is enabled");
          exit;
        }

        #Checking if new jobs need to be scheduled
        if ($this->lastJobTime < time()) {
          $this->printInfo("Creating schedule");
          $this->createSchedule($this->lastJobTime, $this->lastJobTime + 3600);
          $this->lastJobTime = $this->resource->getLastJobTime();
          $this->pendingjobs = $this->resource->getAllPendingJobs();
        }

        #Checking running jobs
        $running = $this->getRunningPids();
        $jobcount = 0;
        foreach ($running as $pid=>$scheduleid) {

          if ($this->governor) {
            $job = $this->getJob($scheduleid);
            if ($job) {
                $jobconfig = $this->getJobConfig($job["job_code"]);
                if (isset($jobconfig["consumers"]) && $jobconfig["consumers"]) {
                    #run the consumers governor
                    $this->consumersGovenor($pid, $scheduleid);
                }
            }
          }

          if (!$this->checkProcess($pid)) {
            #IF this is a consumers job it was run under strace and we do not want this output
            if (isset($jobconfig["consumers"]) && $jobconfig["consumers"]) {
              $output = '';
            } else {
              $output = $this->getJobOutput($scheduleid);
            }

            #If output had "error" in the text, assume it errored
            if (strpos(strtolower($output),'error') > 0) {
              $this->setJobStatus($scheduleid,'error',$output);
            } else {
              $this->setJobStatus($scheduleid,'success',$output);
            }
            $this->unsetPid('cron.'.$pid);
            $this->unsetPid('schedule.'.$scheduleid);
          } else {
            $jobcount++;
          }
        }

        #Get pending jobs
        $pending = $this->resource->getPendingJobs();
        $maxConsumerMessages = intval($this->deploymentConfig->get('cron_consumers_runner/max_messages', 10000));
        $consumersTimeout =  intval($this->resource->getConfigValue('magemojo/cron/consumers_timeout',0,'default'));
        $exportersTimeout =  intval($this->resource->getConfigValue('magemojo/cron/exporters_timeout',0,'default'));
        if (!$consumersTimeout) {
          $consumersTimeout = 0;
        }
        if (!$exportersTimeout) {
          $exportersTimeout = 0;
        }
        while (count($pending) && $this->canRunJobs($jobcount, $pending)) {
          $job = array_shift($pending);
          $runcheck = $this->resource->getJobByStatus($job["job_code"],'running');
          if (count($runcheck) == 0) {
            $jobconfig = $this->getJobConfig($job["job_code"]);
            if ($jobconfig == false) {
                continue;
            }
            #if this is a consumers job use a different runtime cmd
            if (isset($jobconfig["consumers"]) && $jobconfig["consumers"]) {
              $consumerName = str_replace("mm_consumer_","",$jobconfig["name"]);
              $runtime = "bin/magento queue:consumers:start " . escapeshellarg($consumerName);
              if ($maxConsumerMessages) {
                $runtime .= ' --max-messages=' . $maxConsumerMessages;
              }
              $runtime = escapeshellcmd($this->phpproc)." ".$runtime;
              if ($consumerName == 'exportProcessor') {
                if ($exportersTimeout != 0) {
                  $runtime = "timeout -s 9 " . $exportersTimeout . " " . $runtime;
                }
              } elseif ($consumersTimeout != 0) {
                $runtime = "timeout -s 9 ".$consumersTimeout." ".$runtime;
              }
              $cmd = $runtime;
              if ($this->governor) {
                $cmd = 'strace '.$cmd;
              }
            } else {
              $runtime = $this->prepareStub($jobconfig,$stub,$job["schedule_id"]);
              if ($runtime) {
                  $cmd = escapeshellcmd($this->phpproc) . " -r " . escapeshellarg($runtime);
              } else {
                  $this->setJobStatus($job["schedule_id"],'error','Incorrect config of cron job');
                  continue;
              }
            }
            $exec = sprintf("%s; %s > %s 2>&1 & echo $!",
              'cd ' . escapeshellarg($this->basedir),
              $cmd,
              escapeshellarg($this->basedir . "/var/cron/schedule." . $job["schedule_id"])
            );
            $pid = exec($exec);

            #If the output is not numeric then it errored due to syntax
            if (is_numeric($pid)) {
              $this->setPid('cron.'.$pid,$job["schedule_id"]);
              $this->setJobStatus($job["schedule_id"],'running',NULL);
              $jobcount++;
            } else {
              #Error output from command line
              $this->setJobStatus($job["schedule_id"],'error',$pid);
              $this->unsetPid('schedule.'.$job["schedule_id"]);
            }

            #If more than one job of the same code was returned mark one as missed
            if ($job["job_count"] > 1) {
              $this->resource->setMissedJobs($job["job_code"]);
            }
          }
        }

        #Sanity check processes and look for escaped inmates
        $this->asylum();

        #Take a break
        sleep(5);
      }
    }

    /**
     * Execute a cron from CLI
     *
     * @return void
     */
    public function executeImmediate($jobname) {
      #Force UTC
      date_default_timezone_set('UTC');
      $this->basedir = $this->directoryList->getRoot();

      $this->getConfig();
      $jobconfig = $this->getJobConfig($jobname);
      if ($jobconfig === false ) {
          return;
      }
      #create a schedule
      $schedule = array('scheduled_at' => time());
      $scheduled = $this->resource->saveSchedule($jobconfig, time(), $schedule);

      $state = ObjectManager::getInstance()->get("Magento\Framework\App\State");
      try {
          $state->setAreaCode("crontab");
      } catch (\Exception $e) {
      }
      $areaList = ObjectManager::getInstance()->get(AreaList::class);
      $areaList->getArea(Area::AREA_CRONTAB)->load(Area::PART_TRANSLATE);

      $instance = ObjectManager::getInstance()->get($jobconfig["instance"]);
      $schedule = ObjectManager::getInstance()->get("\Magento\Cron\Model\Schedule")->load($scheduled[0]["schedule_id"]);

      $this->resource->setJobStatus($scheduled[0]["schedule_id"],'running',NULL);
      try {
        $instance->{$jobconfig["method"]}($schedule);
        $this->resource->setJobStatus($scheduled[0]["schedule_id"],'success',NULL);
      } catch (Exception $e) {
        $this->resource->setJobStatus($scheduled[0]["schedule_id"],'error',$e->getMessage());
      }
    }


    /**
     * Check for consumers processes in infinate loop states and terminate them
     *
     * @return void
     */
    public function consumersGovenor($pid,$scheduleid) {
        $tail = $this->getJobOutput($scheduleid,True);
        #Check for repeating strings indicating an infinately looping processes
        $checks = array(
          $this->consumersCheck($tail,'SELECT `queue_message`.`top"',1),
          $this->consumersCheck($tail,'rt_sigsuspend([]',0)
        );
            if (in_array(True,$checks)) {
            $this->consumersTerminate($pid);
        }
    }

    /**
    * Check for consumers processes in infinate loop states and terminate them
    *
    * @return bool
    */
    public function consumersCheck($log,$loopstring,$instances) {
        if (substr_count($log,$loopstring) > $instances) {
            return True;
        }
        return False;
    }

    /**
    * Terminate a consumers process
    *
    * @return void
    */
    public function consumersTerminate($pid) {
        $childpid = $this->getChildProcess($pid);
        if ($this->checkProcess($pid)) {
          exec("kill -9 $childpid");
        }
    }

    /**
    * Gets the child process of a running cron
    *
    * @return int
    */
    public function getChildProcess($pid) {
      $childpid = exec('pgrep -P '.$pid);
      if ($childpid) {
        #Recursive call to get the final php process
        $childpid = $this->getChildProcess($childpid);
        return $childpid;
      } else {
        return $pid;
      }
    }

    /**
     * Check for processes that have gone insane and handle the errors
     *
     * @return void
     */
    public function asylum() {
      #Look for running pids and compare to jobs listed as running in cron_schedule
      $crons = $this->getRunningPids();
      $jobs = $this->resource->getJobsByStatus('running');
      $running = array();
      $schedules = array();
      $pids = array();
      foreach ($crons as $pid=>$scheduleid) {
        array_push($running,$scheduleid);
        $pids[$scheduleid] = $pid;
      }
      foreach ($jobs as $job) {
        array_push($schedules,$job["schedule_id"]);
      }
      $diff = array_diff($schedules,$running);
      foreach ($diff as $scheduleid) {
        $this->printInfo("Found mismatched job status for schedule_id ".$scheduleid);
        $this->resource->setJobStatus($scheduleid, 'error', 'Missing PID for process');
      }
      $diff = array_diff($running,$schedules);
      foreach ($diff as $scheduleid) {
        if (!isset($pids["scheduleid"])) {
          continue;
        }

        $pid = $pids["scheduleid"];
        $this->printInfo("Found orphaned pid file for schedule_id ".$scheduleid);
        $this->unsetPid('cron.'.$pid);
      }

      #Detect a coup and acquiesce
      $pid = getmypid();
      $execpid = $this->checkPid('cron.pid');
      if ($pid != $execpid){
        exit;
      }
    }

    /**
     * Get a list of all schedule output files
     *
     * @return array
     */
    public function getScheduleOutputIds() {
      $filelist = scandir(self::VAR_FOLDER_PATH.'/cron/');
      $scheduleids = array();
      foreach ($filelist as $file) {
        if (strpos($file,'schedule.') !== false) {
          array_push($scheduleids,explode('.',$file)[1]);
        }
      }
      return $scheduleids;
    }

    /**
     * Trim cron_schedule and cleanup shedule output files
     *
     * @return void
     */
    public function cleanup() {
      $this->basedir = $this->directoryList->getRoot();
      $this->checkCronFolderExistence();
      $this->initialize();
      $scheduleids = $this->resource->cleanSchedule($this->history);
      $fileids = $this->getScheduleOutputIds();
      $diff = array_diff($fileids,$scheduleids);
      foreach ($diff as $id) {
        $this->unsetPid('schedule.'.$id);
      }
    }

    /**
     * print info log
     *
     * @param string $msg
     * @return void
     */
    private function printInfo($msg = '') {
      $time = date('Y-m-d H:i:s', time());
      print "[$time] INFO $msg" . PHP_EOL;
    }
    /**
     * print warn log
     *
     * @param string $msg
     * @return void
     */
    private function printWarn($msg = '') {
      $time = date('Y-m-d H:i:s', time());
      print "[$time] WARN $msg" . PHP_EOL;
    }
    /**
     * print error log
     *
     * @param string $msg
     * @return void
     */
    private function printError($msg = '') {
      $time = date('Y-m-d H:i:s', time());
      print "[$time] ERR $msg" . PHP_EOL;
    }

    /**
     *  Checks if a consumers job can be run
     *
     * @return bool
     */
    private function canConsumerBeRun(ConsumerConfigItemInterface $consumerConfig, array $allowedConsumers = []): bool {
      $consumerName = $consumerConfig->getName();
      if (!empty($allowedConsumers) && !in_array($consumerName, $allowedConsumers)) {
        return false;
      }

      $connectionName = $consumerConfig->getConnection();
      try {
        $this->mqConnectionTypeResolver->getConnectionType($connectionName);
      } catch (\LogicException $e) {
        $this->printInfo(sprintf('Consumer "%s" skipped as required connection "%s" is not configured. %s',$consumerName,$connectionName,$e->getMessage()));
        return false;
      }
      return true;
    }

}
