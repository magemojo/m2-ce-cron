<?php
namespace MageMojo\Cron\Model;

use Magento\Framework\App\ObjectManager;

class Schedule extends \Magento\Framework\Model\AbstractModel
{
    private $cronconfig;
    private $directorylist;
    private $cronschedule;
    private $resource;
    private $maintenance;

    public function __construct(\Magento\Cron\Model\Config $cronconfig,
      \Magento\Framework\App\Filesystem\DirectoryList $directorylist,
      \MageMojo\Cron\Model\ResourceModel\Schedule $resource,
      \Magento\Framework\App\MaintenanceMode $maintenance) {
      $this->cronconfig = $cronconfig;
      $this->directorylist = $directorylist;
      $this->resource = $resource;
      $this->maintenance = $maintenance;
    }

    /**
     * Get cron schedule config derrived from crontab.xml files
     *
     * @return array
     */
    public function getConfig() {
      $jobs = array();
      foreach($this->cronconfig->getJobs() as $group) {
        foreach($group as $job) {
          if(isset($job["name"])) {
            if (!isset($job["schedule"])) {
              if (isset($job["config_path"])) {
                $schedule =  $this->resource->getConfigValue($job["config_path"],0,'default');
                if ($schedule) {
                  $job["schedule"] = $schedule;
                }
              }
            }
            $jobs[$job["name"]] = $job;
          }
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
      $this->cleanupProcesses();
      $this->lastJobTime = $this->resource->getLastJobTime();
      if ($this->lastJobTime < time() - 360) {
        $this->lastJobTime = time();
      }
      $pid = getmypid();
      $this->setPid('cron.pid',$pid);
    }

    /**
     * Check file in var/cron for running process pid or schedule output
     *
     * @return string
     */
    public function checkPid($pidfile) {
      if (file_exists($this->basedir.'/var/cron/'.$pidfile)){
        $scheduleid = file_get_contents($this->basedir.'/var/cron/'.$pidfile);
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
      #print 'file='.$file;
      file_put_contents($this->basedir.'/var/cron/'.$file,$scheduleid);
    }

    /**
     * Remove file in var/cron for ended process pid or schedule output
     *
     * @return void
     */
    public function unsetPid($pid) {
      unlink($this->basedir.'/var/cron/'.$pid);
    }

    /**
     * Get all pid files in var/cron for running processes
     *
     * @return array
     */
    public function getRunningPids() {
      $pids = array();
      $filelist = scandir($this->basedir.'/var/cron/');

      foreach ($filelist as $file) {
        if ($file != 'cron.pid') {
          $pid = str_replace('cron.','',$file);
          if (is_numeric($pid)) {
            $pids[$pid] = file_get_contents($this->basedir.'/var/cron/'.$file);
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
    public function getJobOutput($scheduleid) {
      $file = $this->basedir.'/var/cron/schedule.'.$scheduleid;
      if (file_exists($file)){
        return trim(file_get_contents($file));
      }
      return NULL;
    }

    /**
     * On startup initialization clean process ids that are no longer running
     *
     * @return void
     */
    public function cleanupProcesses() {
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
          if (ctype_digit($value / $i[1])) {
            return true;
          }
        }
        $i = explode('-',$e);
        if (count($i) == 2) {
          if (($value > $i[0]) and ($value < $i[1])) {
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
      foreach($this->config as $job) {
        if (isset($job["schedule"])) {
          $schedule = array();
          $expr = explode(' ',$job["schedule"]);
          $buildtime = (round($from/60)*60 + 60);
          while ($buildtime < $to) {
            #print $buildtime;
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
            $this->resource->saveSchedule($job,time(),$schedule);
          }
        }
      }
    }

     /**
      * Initial startup process
      *
      * @return void
     */
    public function execute() {
      $this->basedir = $this->directorylist->getRoot();
      print "Healthchecking Cron Service\n";
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
     * @return array
     */
    public function getJobConfig($jobname) {
      return $this->config[$jobname];
    }

    /**
     * Replace method and instance values in the stub proc to be executed
     *
     * @return string
     */
    public function prepareStub($jobconfig, $stub, $scheduleid) {
      $code = trim($stub);
      $code = str_replace('<<basedir>>',$this->basedir,$code);
      $code = str_replace('<<method>>',$jobconfig["method"],$code);
      $code = str_replace('<<instance>>',$jobconfig["instance"],$code);
      $code = str_replace('<<scheduleid>>',$scheduleid,$code);
      return $code;
    }

    /**
     * Determine if new cron tasks can start
     *
     * @return bool
     */
    public function canRunJobs($jobcount, $pending) {
      $cpunum = exec('cat /proc/cpuinfo | grep processor | wc -l');
      if (!$cpunum) {
        $cpunum = 1;
      }
      if ((sys_getloadavg()[0] / $cpunum) > $this->maxload) {
        print "Crons suspended due to high load average: ".(sys_getloadavg()[0] / $cpunum)."\n";
      }
      $maint = $this->maintenance->isOn();
      if ($maint) {
         print "Crons suspended due to maintenance mode being enabled \n";
      }
      if (($jobcount < $this->simultaniousJobs)
        and (count($pending) > 0)
        and ((sys_getloadavg()[0] / $cpunum) < $this->maxload)
        and (!$maint)) {
        return true;
      }
      return false;
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

      print "Starting Service\n";
      #Loop until killed or heat death of the universe
      while (true) {
        $this->getRuntimeParameters();
        if ($this->cronenabled == 0) {
          exit;
        }

        #Checking if new jobs need to be scheduled
        if ($this->lastJobTime < time()) {
          print "Creating schedule\n";
          $this->createSchedule($this->lastJobTime, $this->lastJobTime + 3600);
          $this->lastJobTime = $this->resource->getLastJobTime();
        }

        #Checking running jobs
        $running = $this->getRunningPids();
        $jobcount = 0;
        foreach ($running as $pid=>$scheduleid) {
          if (!$this->checkProcess($pid)) {
            $output = $this->getJobOutput($scheduleid);
            #If output had "error" in the text, assume it errored
            if (strpos(strtolower($output),'error') > 0) {
              $this->resource->setJobStatus($scheduleid,'error',$output);
            } else {
              $this->resource->setJobStatus($scheduleid,'success',$output);
            }
            $this->unsetPid('cron.'.$pid);
          } else {
            $jobcount++;
          }
        }

        #Get pending jobs
        $pending = $this->resource->getPendingJobs();
        while ($this->canRunJobs($jobcount, $pending)) {
          $job = array_pop($pending);
          $runcheck = $this->resource->getJobByStatus($job["job_code"],'running');
          if (count($runcheck) == 0) {
            $jobconfig = $this->getJobConfig($job["job_code"]);

            $runtime = $this->prepareStub($jobconfig,$stub,$job["schedule_id"]);
			#change to base directory and run stub code to execute cron method asychronously, should return pid id
			$cmd = 'cd '.$this->basedir.'; '.$this->phpproc." -r '".$runtime."' &> ".$this->basedir."/var/cron/schedule.".$job["schedule_id"]." & echo $!";
      		$pid = exec($cmd);

            #If the output is not numeric then it errored due to syntax
            if (is_numeric($pid)) {
              $this->setPid('cron.'.$pid,$job["schedule_id"]);
              $this->resource->setJobStatus($job["schedule_id"],'running',NULL);
              $jobcount++;
            } else {
              #Error output from command line
              $this->resource->setJobStatus($job["schedule_id"],'error',$pid);
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

    public function executeImmediate($jobname) {
      #Force UTC
      date_default_timezone_set('UTC');
      $this->basedir = $this->directorylist->getRoot();

      #Get the code stub that executes individual crons
	  $stub = file_get_contents(__DIR__.'/stub.txt');

      $this->getConfig();
      $jobconfig = $this->getJobConfig($jobname);

      #create a schedule
      $schedule = array('sceduled_at' => time());
      $scheduled = $this->resource->saveSchedule($jobconfig, time(), $schedule);


      $instance = ObjectManager::getInstance()->get($jobconfig["instance"]);
      $schedule = ObjectManager::getInstance()->get("\Magento\Cron\Model\Schedule")->load($scheduled[0]["schedule_id"]);

      $this->resource->setJobStatus($scheduled[0]["schedule_id"],'running',NULL);
      try {
        $instance->{$jobconfig["method"]}($schedule);
        $this->resource->setJobStatus($scheduled[0]["schedule_id"],'success',NULL);
      } catch (Exception $e) {
        $this->resource->setJobStatus($job["schedule_id"],'error',$e->message);
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
        print "Found mismatched job status for schedule_id ".$scheduleid."\n";
        $this->resource->setJobStatus($scheduleid, 'error', 'Missing PID for process');
      }
      $diff = array_diff($running,$schedules);
      foreach ($diff as $scheduleid) {
        $pid = $pids["schedule_id"];
        print "Found orphaned pid file for schedule_id ".$scheduleid."\n";
        $this->unsetPid('cron.'.$pid);
      }
    }

    /**
     * Get a list of all schedule output files
     *
     * @return array
     */
    public function getScheduleOutputIds() {
      $filelist = scandir($this->basedir.'/var/cron/');
      $scheduleids = array();
      foreach ($filelist as $file) {
        if (strpos($file,'schedule.') === true) {
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
      $this->basedir = $this->directorylist->getRoot();
      $this->initialize();
      $scheduleids = $this->resource->cleanSchedule($this->history);
      $fileids = $this->getScheduleOutputIds();
      $diff = array_diff($fileids,$scheduleids);
      foreach ($diff as $id) {
        $this->unsetPid('schedule.'.$id);
      }
    }
}


