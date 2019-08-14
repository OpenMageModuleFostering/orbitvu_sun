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

$installer->run("INSERT IGNORE INTO {$this->getTable('core_config_data')} (`path`, `value`) VALUES ('orbitvu/layout/supplement_color', '#ffffff')");
$installer->run("INSERT IGNORE INTO {$this->getTable('orbitvu_configuration')} (`var`, `value`, `type`, `info`) VALUES ('supplement_color', '#ffffff', 'layout', '')");

$installer->endSetup();
?>