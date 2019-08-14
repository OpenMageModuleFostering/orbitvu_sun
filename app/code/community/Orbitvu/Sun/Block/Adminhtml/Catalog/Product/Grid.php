<?php

/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */
class Orbitvu_Sun_Block_Adminhtml_Catalog_Product_Grid extends Mage_Adminhtml_Block_Catalog_Product_Grid {
    
    const XML_SEVERITY_ICONS_URL_PATH  = 'system/adminnotification/severity_icons_url';
    
    /*
     * Get column "Orbitvu"
     * for products view
     */
    public function setCollection($collection) {
        //------------------------------------------------------------------------------------------------------------------
        //$store = $this->_getStore();
        //------------------------------------------------------------------------------------------------------------------
        /*if ($store->getId() && !isset($this->_joinAttributes['orbitvu_sun'])) {
            $collection->joinAttribute(
                    'orbitvu_sun', 'catalog_product/orbitvu_sun', 'entity_id', null, 'left', $store->getId()
            );
        }
        else {
            $collection->addAttributeToSelect('orbitvu_sun');
        }*/
        //------------------------------------------------------------------------------------------------------------------
        parent::setCollection($collection);
        //------------------------------------------------------------------------------------------------------------------
    }
    
    /*
     * Don't know why Magento links this notification icons to external resources
     * But OK...
     */
    public function getSeverityIconsUrl($icon_type = 'NOTICE') {
        //------------------------------------------------------------------------------------------------------------------
        $url = (Mage::app()->getFrontController()->getRequest()->isSecure() ? 'https://' : 'http://')
            .sprintf(Mage::getStoreConfig(self::XML_SEVERITY_ICONS_URL_PATH), Mage::getVersion(), 'SEVERITY_'.$icon_type);
        //------------------------------------------------------------------------------------------------------------------
        return $url;
    }

    /*
     * Render columns with our new one
     * Also, add buttons "Link all presentations" and "Configuration"
     */
    protected function _prepareColumns() {
        //------------------------------------------------------------------------------------------------------------------
        $store = $this->_getStore();
        $request = Mage::app()->getRequest();
        $cookies = Mage::getModel('core/cookie');
        //------------------------------------------------------------------------------------------------------------------
        $observer = Mage::getSingleton('sun/observer');
        $observer->ExtendOrbitvu();
        
        $orbitvu_connected = $observer->_Orbitvu->IsConnected();
        //------------------------------------------------------------------------------------------------------------------
        $o_configuration_url = explode('/system_config/', $this->getUrl('*/system_config/edit/section/orbitvu/'));
        $o_configuration_url = '*/system_config/'.$o_configuration_url[count($o_configuration_url)-1];

        $o_update_url = explode('/catalog_product/', $this->getUrl('*/catalog_product/index/sun/update'));
        $o_update_url = '*/catalog_product/'.$o_update_url[count($o_update_url)-1];
        
        $o_close_url = explode('/catalog_product/', $this->getUrl('*/catalog_product/index/sun/dismiss'));
        $o_close_url = '*/catalog_product/'.$o_close_url[count($o_close_url)-1];
        
        $o_close_welcome_url = explode('/catalog_product/', $this->getUrl('*/catalog_product/index/sun/dismiss_welcome'));
        $o_close_welcome_url = '*/catalog_product/'.$o_close_welcome_url[count($o_close_welcome_url)-1];

        $display_first_time = true;
        //------------------------------------------------------------------------------------------------------------------
        
        /*
         * Add column Orbitvu
         */
        //------------------------------------------------------------------------------------------------------------------
        $this->addColumnAfter('orbitvu_sun', array(
            'header' => Mage::helper('catalog')->__('Orbitvu'),
            'type' => 'text',
            'width' => '100px',
            'renderer' => 'Orbitvu_Sun_Block_Adminhtml_Catalog_Product_Gridrenderer',
            'index' => 'orbitvu_sun',
            ), 'sku'
        );
        //------------------------------------------------------------------------------------------------------------------

        /*
         * SUN actions
         */
        //------------------------------------------------------------------------------------------------------------------
        if (date('Y-m-d', strtotime($observer->_Orbitvu->GetConfiguration('last_refreshed'))) != date('Y-m-d')) {
            $observer->_Orbitvu->SynchronizePresentationsItems();
        }
        //------------------------------------------------------------------------------------------------------------------
        if ($request->getParam('sun') == 'update') {
            $observer->SynchronizeAllProducts();
        }
        //------------------------------------------------------------------------------------------------------------------
        else if ($request->getParam('sun') == 'register') {
            $email = strip_tags($_GET['orbitvu_register_email']);
            
            $response = $observer->_Orbitvu->CreateAccount($email);

            if ($response->status == 'BAD') {
                //------------------------------------------------------------------------------------------------------------------
                $buttons = '<a href="'.$this->getUrl('*/catalog_product/index/').'" onclick="closeMessagePopup();">'.Mage::helper('catalog')->__('OK').'</a>';
                //------------------------------------------------------------------------------------------------------------------
                $orbitvu_popup = $this->genPopup(
                    Mage::helper('catalog')->__('Error!'),
                    Mage::helper('catalog')->__($response->error),
                    $buttons,
                    '*/catalog_product/index/',
                    'MAJOR' 
                );
                //------------------------------------------------------------------------------------------------------------------
                $display_first_time = false;
                //------------------------------------------------------------------------------------------------------------------
            }
            else {
                //------------------------------------------------------------------------------------------------------------------
                $buttons = '<a href="'.$this->getUrl('*/catalog_product/index/').'" onclick="closeMessagePopup();">'.Mage::helper('catalog')->__('OK').'</a>';
                //------------------------------------------------------------------------------------------------------------------
                $orbitvu_popup = $this->genPopup(
                    Mage::helper('catalog')->__('Your account was created!'),
                    Mage::helper('catalog')->__('Now you have your own account on Orbitvu SUN.<br />We sent your an email with details.').' <br /><strong>'.Mage::helper('catalog')->__('Your trial License Key was created and it\'s now in use.').'</strong>.',
                    $buttons,
                    '*/catalog_product/index/',
                    'NOTICE' 
                );
                //------------------------------------------------------------------------------------------------------------------
                /*
                 * <br />Your login details:').'<br />'.Mage::helper('catalog')->__('Login:').' <strong>'.$response->email.'</strong><br />'.Mage::helper('catalog')->__('Password: ').' <strong>'.$response->password.'</strong><br />'.Mage::helper('catalog')->__('License key:').'<br /><strong>'.$response->key.'</strong>
                 * 
                 */
                $observer->_Orbitvu->SetConfiguration('first_time', 'false');
                $display_first_time = false;
                //------------------------------------------------------------------------------------------------------------------
            }
        }
        //------------------------------------------------------------------------------------------------------------------

        /*
         * Orbitvu Pop-up
         */
        //------------------------------------------------------------------------------------------------------------------
        // Disconnected error
        //------------------------------------------------------------------------------------------------------------------
        if (!$orbitvu_connected) {
            //------------------------------------------------------------------------------------------------------------------
            if ($request->getParam('sun') == 'dismiss' || $cookies->get('sun_cookie') == 'dismiss') {
                //------------------------------------------------------------------------------------------------------------------
                $cookies->set('sun_cookie', 'dismiss');
                //------------------------------------------------------------------------------------------------------------------
            }
            else {
                //------------------------------------------------------------------------------------------------------------------
                $buttons = '<a href="'.$this->getUrl($o_configuration_url).'">'.Mage::helper('catalog')->__('Update your key').'</a>
                     <a href="mailto:dev@orbitvu.com" onclick="this.target=\'_blank\';">'.Mage::helper('catalog')->__('Contact Orbitvu').'</a>';
                //------------------------------------------------------------------------------------------------------------------
                $orbitvu_popup = $this->genPopup(
                    Mage::helper('catalog')->__('License Key is not valid'),
                    Mage::helper('catalog')->__('Your Orbitvu extension License Key is not valid. Please update your Key. Orbitvu extension functionality is now limited.'),
                    $buttons,
                    $o_close_url,
                    'MAJOR' 
                );
                //------------------------------------------------------------------------------------------------------------------
            }
            //------------------------------------------------------------------------------------------------------------------
        }
        //------------------------------------------------------------------------------------------------------------------
        // First time
        //------------------------------------------------------------------------------------------------------------------
        if (($observer->_Orbitvu->GetConfiguration('first_time') == 'true' && $display_first_time) || ($request->getParam('sun') == 'show_welcome' && $display_first_time) || $request->getParam('sun') == 'dismiss_welcome') {
            //------------------------------------------------------------------------------------------------------------------
            if ($request->getParam('sun') == 'dismiss_welcome') {
                //------------------------------------------------------------------------------------------------------------------
                $cookies->set('sun_cookie', 'dismiss');
                
                $observer->_Orbitvu->SetConfiguration('first_time', 'false');
                $observer->_Orbitvu->SetDemoAccessToken();
                
                $orbitvu_popup = '';
                //------------------------------------------------------------------------------------------------------------------
            }
            else {
                //------------------------------------------------------------------------------------------------------------------
                $orbitvu_popup = $this->genPopup(
                    Mage::helper('catalog')->__('Orbitvu Extension Wizard'),
                    '<strong>'.Mage::helper('catalog')->__('You need a <a href="http://orbitvu.co" target="_blank">Orbitvu SUN</a> account and License Key to use the extension.').'</strong> '.Mage::helper('catalog')->__('Choose an option:'),
                    '<a href="'.$this->getUrl($o_configuration_url).'">'.Mage::helper('catalog')->__('Enter License Key').'</a>',
                    $o_close_welcome_url,
                    'NOTICE',
                    '',    
                    '2'
                );
                //------------------------------------------------------------------------------------------------------------------
            }
            //------------------------------------------------------------------------------------------------------------------
        }

        /*
         * Render Orbitvu buttons actions
         * <a href="javascript:if (confirm(\''.Mage::helper('catalog')->__('Do you really want to link Orbitvu SUN presentations to all products matched with SKU or name? This operation will only be applied to not yet linked products.').'\')) { window.location.href = \''.$o_update_url.'\'; }'">
         */
        //------------------------------------------------------------------------------------------------------------------
        $this->addRssList(
                '></a>', 
                '<span style="display: inline-block; position: relative; width: 1px; height: 16px; background: white;"><span style="position: absolute; left: -20px; top: 0; width: 20px; background: white; height: 20px; cursor: default;" onclick="return false;"></span></span>'.
                (!$orbitvu_connected ? '<a href="#" style="cursor: default;">' : 
                
                '<a href="javascript:if (confirm(\''.Mage::helper('catalog')->__('Do you really want to link Orbitvu SUN presentations to all products matched with SKU or name?').' '.Mage::helper('catalog')->__('This operation will only be applied to not yet linked products.').'\')) { window.location.href = \''.$this->getUrl($o_update_url).'\'; }">'
                
                ).
                
                '<button type="button" '.(!$orbitvu_connected ? 
                
                ' disabled="disabled" onclick="return false;" style="opacity: 0.5;" class="scalable orbitvu-button disabled disable"' 
                
                : 
                
                ' class="scalable orbitvu-button"'
                
                ).' title="'.Mage::helper('catalog')->__('Link Orbitvu SUN presentations to all products matched with SKU or name.').' '.Mage::helper('catalog')->__('This operation will only be applied to not yet linked products.').'" id="button_orbitvu_update"><span><span style="float: left; width: 16px; height: 16px; margin: 0 4px 0 0 !important; background-image: url('.Mage::getBaseUrl('media').'orbitvu/white.png) !important; background-repeat: no-repeat; background-position: 0 -108px !important;"></span>'.Mage::helper('catalog')->__('Link Orbitvu SUN presentations').'</span></button>'.(!$orbitvu_connected ? '<a href="#">' : '').'');
        //------------------------------------------------------------------------------------------------------------------
        $this->addRssList($o_configuration_url, '<button type="button" class="scalable orbitvu-button" title="'.Mage::helper('catalog')->__('Orbitvu Configuration').'" id="button_orbitvu_configuration"><span style="float: left; width: 16px !important; height: 16px !important; margin: 0 5px 0 0; padding: 0; background-image: url('.Mage::getBaseUrl('media').'orbitvu/white.png); background-repeat: no-repeat; background-position: -64px -108px;"></span><span>'.$this->__('Configuration').'<span></span></span></button><script>document.getElementById(\'button_orbitvu_configuration\').parentNode.className = \'\';document.getElementById(\'button_orbitvu_update\').parentNode.className = \'\';
    window.onbeforeunload = function() {
        document.getElementById(\'orbitvu_postloader\').style.display = \'block\';
    }            
</script></a>'.$orbitvu_popup.'<div id="orbitvu_postloader">
    <div id="orbitvu_postloader_bg"></div>
    <div id="orbitvu_postloader_fg"><img src="'.Mage::getBaseUrl('media').'orbitvu/loader.gif" style="margin-top: 150px;" alt="" /></div>
</div><style type="text/css">
            #orbitvu_postloader { display: none; }
    #orbitvu_postloader_bg { position: fixed; width: 100%; height: 100%; top: 0; left: 0; z-index: 999998; background: white; opacity: 0.8; }
    #orbitvu_postloader_fg { position: fixed; width: 100%; top: 0; left: 0; z-index: 999999; text-align: center; }
.orbitvu-button { border-color: #1191A6; background: #8dd5e0; background: -moz-linear-gradient(top,  #8dd5e0 0%, #009cb5 45%); background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#8dd5e0), color-stop(45%,#009cb5)); background: -webkit-linear-gradient(top,  #8dd5e0 0%,#009cb5 45%); background: -o-linear-gradient(top,  #8dd5e0 0%,#009cb5 45%); background: -ms-linear-gradient(top,  #8dd5e0 0%,#009cb5 45%); background: linear-gradient(to bottom,  #8dd5e0 0%,#009cb5 45%); filter: progid:DXImageTransform.Microsoft.gradient( startColorstr=\'#8dd5e0\', endColorstr=\'#009cb5\',GradientType=0 ); }
.orbitvu-button:hover { background: #ddfaff; background: -moz-linear-gradient(top,  #ddfaff 0%, #15adc6 59%); background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#ddfaff), color-stop(59%,#15adc6)); background: -webkit-linear-gradient(top,  #ddfaff 0%,#15adc6 59%); background: -o-linear-gradient(top,  #ddfaff 0%,#15adc6 59%);background: -ms-linear-gradient(top,  #ddfaff 0%,#15adc6 59%); background: linear-gradient(to bottom,  #ddfaff 0%,#15adc6 59%); filter: progid:DXImageTransform.Microsoft.gradient( startColorstr=\'#ddfaff\', endColorstr=\'#15adc6\',GradientType=0 ); }
</style><a href="#">');
        //------------------------------------------------------------------------------------------------------------------
        return parent::_prepareColumns();
        //------------------------------------------------------------------------------------------------------------------
    }
    
    /*
     * Generate popup to own usage
     * Based on Magento default notification popup template 
     * (unfortunately can't be used without this)
     * Not proud of this part of file... but other solution will be waiste of time
     */
    public function genPopup($header, $message, $buttons, $close_url = '#', $level = 'NOTICE', $icon_text = '', $tpl = '1') {
        
        $out = '
        <script type="text/javascript">
        //<![CDATA[
        var messagePopupClosed = false;
        function openMessagePopup() {
        var height = $(\'html-body\').getHeight();
        $(\'message-popup-window-mask\').setStyle({\'height\':height+\'px\'});
        toggleSelectsUnderBlock($(\'message-popup-window-mask\'), false);
        Element.show(\'message-popup-window-mask\');
        $(\'message-popup-window\').addClassName(\'show\');
        }

        function closeMessagePopup() {
        toggleSelectsUnderBlock($(\'message-popup-window-mask\'), true);
        Element.hide(\'message-popup-window-mask\');
        $(\'message-popup-window\').removeClassName(\'show\');
        messagePopupClosed = true;
        }

        Event.observe(window, \'load\', openMessagePopup);
        Event.observe(window, \'keyup\', function(evt) {
        if(messagePopupClosed) return;
        var code;
        if (evt.keyCode) code = evt.keyCode;
        else if (evt.which) code = evt.which;
        if (code == Event.KEY_ESC) {
        closeMessagePopup();
        }
        });
        //]]>
        </script>
        <div id="message-popup-window-mask" style="display:none;"></div>';
        
        if ($tpl == '1') {
            $out .= '
            <div id="message-popup-window" class="message-popup">
            <div class="message-popup-head">
            <a href="'.$this->getUrl($close_url).'" onclick="closeMessagePopup();"><span>'.Mage::helper('catalog')->__('close').'</span></a>
            <h2>'.$header.'</h2>
            </div>
            <div class="message-popup-content">
            <div class="message">
            <span class="message-icon message-error" style="background-image:url('.$this->getSeverityIconsUrl($level).');">'.$icon_text.'</span>
            <p class="message-text">'.$message.'</p>
            </div>
            <p class="read-more">
                '.$buttons.'
            </p>
        </div>
        </div>
        ';
        }
        else {
        
            $out .= '
            <div id="message-popup-window" class="message-popup">
                <div class="message-popup-head">
                    <a href="'.$this->getUrl($close_url).'" onclick="closeMessagePopup();"><span>'.Mage::helper('catalog')->__('close').'</span></a>
                    <h2>'.$header.'</h2>
                </div>
                <div class="message-popup-content">
                    <div class="message" id="orbitvu_options">
                        <p class="message-text" style="float: none; width: auto; min-height: 1px;">'.$message.'</p> 
                        <div style="clear: both; overflow: hidden; zoom: 1.0;">
                            <div style="padding: 10px 0 10px 0; border-bottom: 1px dotted #f3bf8f;">
                                <p class="read-more" style="margin: 0; text-align: left;">
                                    <a href="'.$this->getUrl($close_url).'" onclick="closeMessagePopup();">'.Mage::helper('catalog')->__('Try extension with DEMO account').'</a>
                                    <br />
                                    '.Mage::helper('catalog')->__('You get access to predefined set of 360&deg; presentations.').'
                                </p>
                            </div>
                            <div style="padding: 10px 0 10px 0; border-bottom: 1px dotted #f3bf8f;">
                                <p class="read-more" style="margin: 0; text-align: left;">
                                    <a href="#" onclick="document.getElementById(\'orbitvu_options\').style.display = \'none\'; document.getElementById(\'orbitvu_html_register\').style.display = \'block\';">'.Mage::helper('catalog')->__('Register Orbitvu SUN FREE trial account').'</a>
                                    <br />
                                    '.Mage::helper('catalog')->__('Create and customize your own presentations.').'
                                </p>
                            </div>
                            <div style="margin: 0 0 10px 0; padding: 10px 0 10px 0; border-bottom: 1px dotted #f3bf8f;">
                                <p class="read-more" style="margin: 0; text-align: left;">
                                    <a href="'.$this->getUrl('*/system_config/edit/section/orbitvu/').'">'.Mage::helper('catalog')->__('Enter License Key').'</a>
                                    <br />
                                    '.Mage::helper('catalog')->__('Connect to your Orbitvu SUN account.').'
                                </p>
                            </div>
                        </div>
                        <a href="http://orbitvu.co" target="_blank"><img src="'.Mage::getBaseUrl('media').'orbitvu/logo.png" style="float: right;" alt="" /></a>
                    </div>

                    <div class="message" id="orbitvu_html_register" style="display: none;">
                        <p class="message-text" style="float: none; width: auto; min-height: 1px;">'.
                Mage::helper('catalog')->__('Creating new account is free. Just enter your e-mail address.').'<br />'.Mage::helper('catalog')->__('We will send you the password and generate trial License Key automaticaly.').'</p>
                        <form action="'.$this->getUrl('*/catalog_product/index/sun/register').'" method="get">
                            <div class="message-text" style="min-height: 1px; padding: 10px 0 10px 0;">
                                <input id="orbitvu_register_email" type="text" name="orbitvu_register_email" placeholder="'.Mage::helper('catalog')->__('Enter your e-mail...').'" value="'.Mage::getStoreConfig('trans_email/ident_general/email').'" />
                                <button type="submit" class="scalable orbitvu-button">'.Mage::helper('catalog')->__('Register').'</button>
                                <button type="button" onclick="document.getElementById(\'orbitvu_options\').style.display = \'block\'; document.getElementById(\'orbitvu_html_register\').style.display = \'none\';" class="scalable back">'.Mage::helper('catalog')->__('Cancel').'</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            ';
        }
        //------------------------------------------------------------------------------------------------------------------
        return $out;
        //------------------------------------------------------------------------------------------------------------------
    }

}

?>