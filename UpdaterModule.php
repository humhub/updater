<?php

class UpdaterModule extends HWebModule {

    public static function onConsoleApplicationInit($event) {
        Yii::app()->addCommand('updater', 'application.modules.updater.console.UpdaterCommand');
    }

    public static function onAdminMenuInit(CEvent $event) {

        $event->sender->addItem(array(
            'label' => Yii::t('UpdaterModule.base', 'Update HumHub <sup>BETA</sup>'),
            'url' => Yii::app()->getController()->createUrl('//updater/update'),
            'icon' => '<i class="fa fa-cloud-download"></i>',
            'group' => 'manage',
            'sortOrder' => 9000,
            'isActive' => (Yii::app()->controller->module && Yii::app()->controller->module->id == 'updater')
        ));
    }

    public function getApiUrl() {
        $marketplaceApiUrl = "https://www.humhub.com/api/v1/modules/";
        if (isset(Yii::app()->getModule('admin')->marketplaceApiUrl)) {
            $marketplaceApiUrl = Yii::app()->getModule('admin')->marketplaceApiUrl;
        }
        return $marketplaceApiUrl;
    }

    public function getCurlOptions() {

        $useSsl = false;
        if (isset(Yii::app()->getModule('admin')->marketplaceApiValidateSsl)) {
            $useSsl = Yii::app()->getModule('admin')->marketplaceApiValidateSsl;
        }

        $options = array(
            CURLOPT_SSL_VERIFYPEER => ($useSsl) ? true : false,
            CURLOPT_SSL_VERIFYHOST => ($useSsl) ? 2 : 0,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS
        );

        if (HSetting::Get('enabled', 'proxy')) {
            $options[CURLOPT_PROXY] = HSetting::Get('server', 'proxy');
            $options[CURLOPT_PROXYPORT] = HSetting::Get('port', 'proxy');
            if (defined('CURLOPT_PROXYUSERNAME')) {
                $options[CURLOPT_PROXYUSERNAME] = HSetting::Get('user', 'proxy');
            }
            if (defined('CURLOPT_PROXYPASSWORD')) {
                $options[CURLOPT_PROXYPASSWORD] = HSetting::Get('pass', 'proxy');
            }
            if (defined('CURLOPT_NOPROXY')) {
                $options[CURLOPT_NOPROXY] = HSetting::Get('noproxy', 'proxy');
            }
        }

        return $options;
    }

}
