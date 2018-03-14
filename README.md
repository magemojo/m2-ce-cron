[![MageMojo](https://magetalk.com/wp-content/uploads/2017/11/q7xJZaM5TImMN7mUIb0c.png)](https://magemojo.com/)

# Cron
#### This patch for Magento 2 overrides base magento cron functionality and replaces it with a cron service model. 

The default cron can overlap and fill the cron_schedule table, which can cause exponentially more jobs to run on each cron interval, until finally the crons run continously and never complete.  The high number of cron jobs can also crash servers hosting Magento 2. 

This module changes the cron management into a service that accepts jobs. As jobs are scheduled, they are sent to this service for execution.  If a job is already running and another is sent with the same job code, the new one is marked as missed.  Duplicate jobs are prevented from running, reducing server overhead.

Think of the default cron as a factory that suddenly appears and runs any number of tasks. If those tasks do not complete by the next cron interval, they keep processing but another factory spontaneously appears and run another set of jobs which can overlap with the original factory.  

Our module implement removes the possibility of overlapping jobs by having a single source service that processes jobs in proper order without duplication. There is one factory working all the time to get your jobs done. 

## Features

- Enable / Disable running crons
- Limit the number of simultaneous crons jobs being run
- Suspend cron functions by load average threshold
- Set the amount of history to retain in the cron_schedule table

## Manual Install

- [Download this ZIP](https://github.com/magemojo/m2-ce-cron/archive/master.zip) and paste in your root folder.

- Run these commands in your terminal:

```bash
bin/magento module:enable MageMojo_SplitDb
bin/magento setup:upgrade
```
- enable cron jobs as defined here: http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands-cron.html

![Version 1.0.0](https://img.shields.io/badge/Version-1.0.0-green.svg)

## License
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
