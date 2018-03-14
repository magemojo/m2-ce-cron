[![MageMojo](https://magetalk.com/wp-content/uploads/2017/11/q7xJZaM5TImMN7mUIb0c.png)](https://magemojo.com/)

# Cron
#### This patch for Magento 2 overrides base magento cron functionality and replaces it with a cron service model.

## Features

- Enable / Disable running crons
- Ability to limit the number of simultanious crons jobs being run
- Ability to suspend cron functions is load average on the serer becomes too high
- Set the amount of history to retain in the cron_schedule table in days

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
