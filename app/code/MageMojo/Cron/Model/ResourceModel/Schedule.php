<?php
namespace MageMojo\Cron\Model\ResourceModel;

class Schedule extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init('cron_schedule', 'schedule_id');
    }
    
    #making our own function for this because it doesnt't work anyplace consistantly
    public function getConfigValue($path,$scope,$scopeid) {
      $connection = $this->getConnection();
      $select = $connection->select()
        ->from($this->getTable('core_config_data'),['value'])
        ->where('path = ?',  $path)
        ->where('scope_id = ?', $scopeid)
        ->where('scope = ?', $scope);
      $result = $connection->fetchOne($select);
      return $result;
    }

    public function setConfigValue($path,$scope,$scopeid,$value) {
      $connection = $this->getConnection();
      $updatedata = array('value' => $value);
      $connection->update($this->getTable('core_config_data'),$updatedata,['path = ?' => $path,'scope_id = ?' => $scopeid,'scope = ?' => $scope]);
    }

    public function getLastJobTime() {
      $connection = $this->getConnection();
      $select = $connection->select()
            ->from($this->getTable('cron_schedule'),['max(unix_timestamp(scheduled_at)) as maxtime']);
      $result = $connection->fetchOne($select);
      return $result;
    }
    
    public function saveSchedule($job, $created, $schedule) {
      $connection = $this->getConnection();
      $insertdata = array();
      foreach ($schedule as $time) {
        array_push($insertdata,array('job_code' => $job["name"], 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s',$created), 'scheduled_at' => date('Y-m-d H:i:s',$time)));
      }
      $connection->insertMultiple($this->getTable('cron_schedule'), $insertdata);
    }

    public function setJobStatus($scheduleid, $status, $output) {
      $connection = $this->getConnection();
      $updatedata = array('status' => $status);
      $updatedata["messages"] = $output;
      if ($status = 'complete') {
        $updatedata["finished_at"] = date('Y-m-d H:i:s',time());
      }
      if ($status = 'running') {
        $updatedata["executed_at"] = date('Y-m-d H:i:s',time());
      }
      $connection->update($this->getTable('cron_schedule'),$updatedata,['schedule_id = ?' => $scheduleid]);
    }

    public function getPendingJobs() {
      $connection = $this->getConnection();
      $select = $connection->select()
            ->from($this->getTable('cron_schedule'),['max(schedule_id) as schedule_id','job_code','count(*) as job_count'])
            ->where('status = ?', 'pending')
            ->where('scheduled_at < ?', date('Y-m-d H:i:s',time()))
            ->group('job_code');
      $result = $connection->fetchAll($select);
      return $result;
    }

    public function setMissedJobs($jobcode) {
      $connection = $this->getConnection();
      $connection->update($this->getTable('cron_schedule'),['status' => 'missed'],['job_code = ?' => $jobcode, 'status = ?' => 'pending', 'scheduled_at < ?' => date('Y-m-d H:i:s',time())]);
    }

    public function getJobByStatus($jobcode,$status) {
      $connection = $this->getConnection();
      $select = $connection->select()
            ->from($this->getTable('cron_schedule'))
            ->where('job_code = ?',$jobcode)
            ->where('status = ?',$status);
      $result = $connection->fetchAll($select);
      return $result;
    }

    public function getJobsByStatus($status) {
      $connection = $this->getConnection();
      $select = $connection->select()
            ->from($this->getTable('cron_schedule'))
            ->where('status = ?',$status);
      $result = $connection->fetchAll($select);
      return $result;
    }
    
    public function resetSchedule() {
      $connection = $this->getConnection();
      $message = 'Parent Cron Process Terminated Abnomally';
      $connection->update($this->getTable('cron_schedule'),['status' => 'error', 'messages' => $message],['status = ?' => 'running']);
      $connection->update($this->getTable('cron_schedule'),['status' => 'missed'],['status = ?' => 'pending', 'scheduled_at < ?' => date('Y-m-d H:i:s',time())]);
    }

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
    
    public function getSettings() {
      $connection = $this->getConnection();

      $select = $connection->select()->from($this->getTable('core_config_data'))->where('path like ?', 'magemojo/cron/%');
      $result = $connection->fetchAll($select);

      return $result;
    }
}
