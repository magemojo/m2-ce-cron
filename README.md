[![MageMojo](https://magetalk.com/wp-content/uploads/2017/11/q7xJZaM5TImMN7mUIb0c.png)](https://magemojo.com/)

# Cron
#### This module for Magento 2 overrides base magento cron functionality, fixes known bugs, and provides a cron service model to control cron process execution. 

![Version 1.3.6](https://img.shields.io/badge/Version-1.3.6-green.svg)

NOTICE: Version 1.3x is only supported for Magento 2.3 and above. Older Magento 2 version use module version 1.2

The default cron can overlap and fill the cron_schedule table, which can cause exponentially more jobs to run on each cron interval, until finally the crons run continously and never complete.  The high number of cron jobs can also crash servers hosting Magento 2. 

This module replaces the cron management with a service that accepts jobs. As jobs are scheduled, they are picked up by this service for execution.  If a job is already running and another is picked up with the same job code, the new one is marked as missed.  Duplicate jobs are prevented from running, reducing server overhead.

Think of the default cron as a factory that suddenly appears and runs any number of tasks. If those tasks do not complete by the next cron interval, they keep processing but another factory spontaneously appears and run another set of jobs which can overlap with the original factory.  

The module removes the possibility of overlapping jobs by having a single source service that processes jobs in proper order without duplication. There is one factory working all the time to get your jobs done. 

In addition to the service model many other enhancements have been made.  For example a re-write of left join on update statement that forced a full table scan on cron_schedule for history.  Statement would lock because it's reading from same table it was trying to update.

In version 1.1 Cron Reporting was added to the admin to show job code statistics and list cron run errors.

In version 1.3 fixes are implemented for the consumers_runner cron job. This job code is a throwback from magento 1 and is more frequently used in Magento 2.3. It runs under its own scheduler which can execute many child jobs and bomb the system. In this version of the module this parent job is intercepted and written as individual jobs in the cron_schedule table and then run in a sane manner from there. These consumer jobs can also go into infinate loops, so a timeout is imposed on them by default of 30 seconds. This setting can be adjusted in the admin.

## Contributing
See [CONTRIBUTING.md](CONTRIBUTING.md).

## Benefits

* Speeds up execution of cron.

* Stops db locking.

* Prevents cron history records from exploding.

* Stops cron processes from overruning each other.

* Stops the cron from running while system is under configurable load conditions.

* Sets the max number of simultaneous cron processes.

* Sets the amount of history. 

## Admin Options

**Cron Enabled** - Turn the cron on/off.

**Maximum Cron Processes** - The number of cron threads running in parallel.  This option is the sum of all defined jobs.  Example: If you have 5 jobs set to run at midnight, Maximum Cron Processes set to 1, only 1 job will execute sequentially until all 5 are completed. Default 3.

**PHP Binary Name / Path** - The name of your php binary you run from the shell.  Usually php or php70.  You can optionally include the full path to the binary. Default php. You can also add inline options here such as php -dmemory_limit=8096M If you want to have a custom ini value.

**Max Load Average** - Defined by the php function sys.getloadavg() / number of cpu cores. The function sys.getloadavg() is reported 1.0 for each core in use, just like the load average reported in top.  The number of cpu cores is pulled from /proc/cpuinfo and load average is divided by this number. Example: If you have 8 cores and you're using 6 then this is returned as 0.75. If your Max Load Average is 0.76 your crons will not run. Your load average falls to 0.74.  Your crons will run.  Any cron that was scheduled to run but didn't will be run.  If the same cron was missed multiple times, the most recent job will run, and the rest will be marked as missed. Default is 0.75 (75% of your available cpu).

**History Retention** - The number of days history to keep in the cron_schedule table. Default 1 (1 day).

## Composer Install

- Run these commands in your terminal:

- For Magento Versions 2.3 and up
```bash
composer require magemojo/m2-ce-cron
```
- Otherwise
```bash
composer require magemojo/m2-ce-cron:1.2.9
```

- Then run the following
```bash
php bin/magento module:enable MageMojo_Cron
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```
- Log into your admin and set your options under System -> Cron Settings

- Enable cron jobs as defined if it is not already enabled here: https://devdocs.magento.com/guides/v2.3/config-guide/cli/config-cli-subcommands-cron.html

## License
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
