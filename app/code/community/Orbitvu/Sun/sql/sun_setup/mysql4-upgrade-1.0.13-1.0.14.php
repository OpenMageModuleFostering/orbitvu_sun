<?php
/**
 * Orbitvu PHP eCommerce Orbitvu DB drivers
 * @Copyright: Orbitvu Sp. z o.o. is the owner of full rights to this code
 *
 * @For: Magento
 */

/*
 * Start installation
 */

$installer = $this;
$installer->startSetup();

$installer->run("INSERT IGNORE INTO {$this->getTable('core_config_data')} (`path`, `value`) VALUES ('orbitvu/mode/append_prepend', 'append')");
$installer->run("INSERT IGNORE INTO {$this->getTable('orbitvu_configuration')} (`var`, `value`, `type`, `info`) VALUES ('append_prepend', 'append', 'mode', '')");

$installer->endSetup();
?>