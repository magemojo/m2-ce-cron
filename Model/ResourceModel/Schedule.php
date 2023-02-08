<?php
namespace MageMojo\Cron\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Zend_Db_Expr;

class Schedule extends AbstractDb
{
    public function _construct()
    {
        $this->_init('cron_schedule', 'schedule_id');
    }

    /**
     * Get a value from core_config_data
     *
     * @param $path
     * @param $scope
     * @param $scopeid
     * @return string
     */
    public function getConfigValue($path,$scope,$scopeid): string
    {
      #making our own function for this because it doesn't work anyplace consistently
      $connection = $this->getConnection();
      $select = $connection->select()
        ->from($this->getTable('core_config_data'),['value'])
        ->where('path = ?',  $path)
        ->where('scope_id = ?', $scopeid)
        ->where('scope = ?', $scope);
      $result = $connection->fetchOne($select);
      return $result;
    }

    /**
     * Set a value in core_config_data
     *
     * @return void
     */
    public function setConfigValue($path,$scope,$scopeid,$value) {
      #making our own function for this because it doesn't work anyplace consistently
      $connection = $this->getConnection();
      $updatedata = array('value' => $value);
      $connection->update($this->getTable('core_config_data'),$updatedata,['path = ?' => $path,'scope_id = ?' => $scopeid,'scope = ?' => $scope]);
    }

    /**
     * Get all magemojo/cron values from core_config_data
     *
     * @return array
     */
    public function getSettings() {
      $connection = $this->getConnection();

      $select = $connection->select()->from($this->getTable('core_config_data'))->where('path like ?', 'magemojo/cron/%');
      $result = $connection->fetchAll($select);

      return $result;
    }

    /**
     * Get the max date scheduled from cron_schedule
     *
     * @return string timestamp
     */
    public function getLastJobTime() {
      $connection = $this->getConnection();
      $select = $connection->select()
            ->from($this->getTable('cron_schedule'),['max(unix_timestamp(scheduled_at)) as maxtime']);
      $result = $connection->fetchOne($select);
      return $result;
    }

    /**
     * Create rows in cron_schedule for a job_code
     *
     * @param $job
     * @param $created
     * @param $schedule
     * @return array
     */
    public function saveSchedule($job, $created, $schedule)
    {
      $connection = $this->getConnection();
      $insertdata = array();
      foreach ($schedule as $time) {
        array_push($insertdata,array('job_code' => $job["name"], 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s',$created), 'scheduled_at' => date('Y-m-d H:i:s',$time)));
      }
      $connection->insertMultiple($this->getTable('cron_schedule'), $insertdata);

      $select = $connection->select()
          ->from($this->getTable('cron_schedule'))
          ->where('job_code = ?', $job["name"])
          ->where('status = ?', 'pending')
          ->where('created_at = ?', date('Y-m-d H:i:s',$created));
      $result = $connection->fetchAll($select);
      return $result;
    }

    /**
     * Update a cron status
     *
     * @return void
     */
    public function setJobStatus($scheduleid, $status, $output, $executionHost = null) {
      $connection = $this->getConnection();
      $updatedata = array('status' => $status);
      $updatedata["messages"] = $output;
      if ($status == 'success' || $status == 'error') {
        $updatedata["finished_at"] = date('Y-m-d H:i:s',time());
      }
      if ($status == 'running') {
        $updatedata["executed_at"] = date('Y-m-d H:i:s',time());

        if (!empty($executionHost)){
            $updatedata["execution_host"] = $executionHost;
        }
      }

      $connection->update($this->getTable('cron_schedule'),$updatedata,['schedule_id = ?' => $scheduleid]);
    }

    /**
     * Get pending jobs
     *
     * @return array
     */
    public function getPendingJobs() {
      $connection = $this->getConnection();
      $select = $connection->select()
            ->from($this->getTable('cron_schedule'), [
                'max(schedule_id) as schedule_id',
                'job_code',
                'count(*) as job_count',
                'min(scheduled_at) as scheduled_at'
            ])
            ->where('status = ?', 'pending')
            ->where('scheduled_at < ?', date('Y-m-d H:i:s',time()))
            ->group('job_code')
            ->order(new Zend_Db_Expr("scheduled_at ASC"));
      $result = $connection->fetchAll($select);
      return $result;
    }

    /**
     * Get all pending jobs
     *
     * @return array
     */
    public function getAllPendingJobs() {
      $connection = $this->getConnection();
      $select = $connection->select()
            ->from($this->getTable('cron_schedule'),['schedule_id','job_code','scheduled_at'])
            ->where('status = ?', 'pending')
            ->order('job_code')
            ->order('scheduled_at', 'desc');
      $result = $connection->fetchAll($select);
      $jobs = array();
      foreach ($result as $row) {
        $jobs[$row["schedule_id"]] = $row;
      }
      return $jobs;
    }

    /**
     * Set jobs to missed
     *
     * @return void
     */
    public function setMissedJobs($jobcode) {
      $connection = $this->getConnection();
      $connection->update($this->getTable('cron_schedule'),['status' => 'missed'],['job_code = ?' => $jobcode, 'status = ?' => 'pending', 'scheduled_at < ?' => date('Y-m-d H:i:s',time())]);
    }

    /**
     * Get jobs by job_code and status
     *
     * @return array
     */
    public function getJobByStatus($jobcode,$status) {
      $connection = $this->getConnection();
      $select = $connection->select()
            ->from($this->getTable('cron_schedule'))
            ->where('job_code = ?',$jobcode)
            ->where('status = ?',$status);
      $result = $connection->fetchAll($select);
      return $result;
    }

    /**
     * Get jobs by status and optionally the execution host
     *
     * @return array
     */
    public function getJobsByStatus($status, $host = null) {
      $connection = $this->getConnection();
      $select = $connection->select()
            ->from($this->getTable('cron_schedule'))
            ->where('status = ?',$status);
      if (!empty($host)){
          $select = $select->where('execution_host = ?',$host);
      }
      $result = $connection->fetchAll($select);
      return $result;
    }

    /**
     * Set jobs to error if runtime service was terminated for running jobs
     *
     * @return void
     */
    public function resetSchedule($scheduleId) {
      $connection = $this->getConnection();
      $message = 'Parent Cron Process Terminated Abnormally';

      /* if a scheduleId was provided, update it as having terminating abnormally; otherwise, update all running jobs*/
      $selectRunningJobs = ['status = ?' => 'running'];
      if (!empty($scheduleId)) {
          $selectRunningJobs['schedule_id'] = $scheduleId;
      }
      $connection->update($this->getTable('cron_schedule'),['status' => 'error', 'messages' => $message],$selectRunningJobs);

      $selectPendingJobs = ['status = ?' => 'pending', 'scheduled_at < ?' => date('Y-m-d H:i:s',time())];
      $connection->update($this->getTable('cron_schedule'),['status' => 'missed'],$selectPendingJobs);
    }

    /**
     * Trim cron_schedule table
     *
     * @return array
     */
    public function cleanSchedule($days) {
      $connection = $this->getConnection();
      $connection->delete($this->getTable('cron_schedule'),['scheduled_at < ?' => date('Y-m-d H:i:s',(time() - ($days * 84000)))]);
      $select = $connection->select()->from($this->getTable('cron_schedule'),'schedule_id');
      $result = $connection->fetchAll($select);
      $ids = array();
      foreach ($result as $id) {
        array_push($ids, $id["schedule_id"]);
      }
      return $ids;
    }

    /**
     * Get all data from cron_schedule and summarize for reporting
     *
     * @return array
     */
    public function getReport() {
      $connection = $this->getConnection();

      $columns = array(
        'job_code',
        'status',
        'count(*) as count',
        "max(executed_at) as executed_at",
        "max(finished_at) as finished_at"
      );
      $select = $connection->select()
        ->from($this->getTable('cron_schedule'),$columns)
        ->group('job_code')
        ->group('status')
        ->order('job_code')
        ->order('status');
      $result = $connection->fetchAll($select);
      return $result;
    }

    /**
     * Get all error data from cron_schedule
     *
     * @return array
     */
    public function getErrorReport() {
      $connection = $this->getConnection();

      $columns = array(
        'job_code',
        'messages'
      );
      $select = $connection->select()
        ->from($this->getTable('cron_schedule'),$columns)
        ->where('status = ?', 'error')
        ->group('job_code')
        ->order('job_code');
      $result = $connection->fetchAll($select);
      return $result;
    }
}

