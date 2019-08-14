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
//$prefix = Mage::getConfig()->getTablePrefix();

$installer = $this;
$installer->startSetup();

$inc = Mage::getBaseDir('app').'/code/community/Orbitvu/Sun/controllers/OrbitvuAdmin.php';
include_once($inc);

$_Orbitvu = new OrbitvuAdmin(false);
$_Orbitvu->Install();
    
$installer->endSetup();
?>