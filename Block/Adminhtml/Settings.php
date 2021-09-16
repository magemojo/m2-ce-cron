<?php
namespace MageMojo\Cron\Block\Adminhtml;
use MageMojo\Cron\Model\ResourceModel\Schedule;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Backend settings block
 */
class Settings extends Template
{
    private $_cronconfig;
    protected $resourceconfig;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        Schedule $resourceconfig,
        array $data = []
    ) {
        $this->_resource = $resource;
        $this->resourceconfig = $resourceconfig;

        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Get value from core_config_data
     *
     * @return string
     */
    public function getConfig($path)
    {
        return $this->resourceconfig->getConfigValue($path, 'default', 0);
    }

    /**
     * Get rendered checkbox html
     *
     * @return string
     */
    public function checkbox($path, $name) {
      $value = $this->resourceconfig->getConfigValue($path, 'default', 0);
      print '<input type="checkbox" name="'.$name.'" value="1" ';
      if ($value) {
        print 'checked';
      }
      print '>';
    }

    /**
     * Get rendered textbox html
     *
     * @return string
     */
    public function textfield($path, $name, $size, $max) {
      $value = $this->resourceconfig->getConfigValue($path, 'default', 0);
      print '<input type="text" name="'.$name.'" size="'.$size.'" maxchar="'.$max.'" value="'.htmlspecialchars($value).'">';
    }

    /**
     * Get rendered dropdown select html
     *
     * @return string
     */
    public function dropdown($path, $name) {
        $value = $this->resourceconfig->getConfigValue($path, 'default', 0);
        $html = <<<HTML
<select name="$name" id="$path">
  <option value="0">none</option>
  <option value="1">Magento Commerce Cloud</option>
</select>
HTML;

        if ($value) {
            $html .= <<<HTML
<script type="javascript" lang="js">document.getElementById('$path').value=$value;</script>
HTML;

        }
        print $html;
    }
}
