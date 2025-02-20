<?php

/**
 * 短信网关配置
 * Created by PhpStorm.
 * User: dengchao
 * Date: 17/4/15
 * Time: 10:56
 */
class Configs_SmsConfig
{
    /**
     * 网关的配置信息
     * @var array
     */
    private static $smsGates = array(
        '2' => array(
            'gateway_id' => 2,
            'signature'  => '【监控宝】',
            'url'        => 'http://cf.lmobile.cn/submitdata/Service.asmx/g_Submit',
            'uid'        => 'dlyunzh0',
            'pwd'        => '275157f52a'
        ),
        '5' => array(
            'gateway_id' => 5,
            'signature'  => '【监控宝】',
            'url'        => 'http://115.28.112.245:8082/SendMT/SendMessage',
            'uid'        => 'jiankongbao01',
            'pwd'        => 'ks!Mo84jO',
        ),
    );
    
    public static function getSmsGates($gateway_id)
    {
        $commonConf  = new Yaf_Config_Ini(CONF_PATH . '/jkb.ini', 'common');
        $environment = $commonConf->get('yaf')->environment;
        
        $configs  = new Yaf_Config_Ini(CONF_PATH . '/sms.ini', $environment);
        $sms_conf = $configs->get('smsGates');
        if ($sms_conf->get($gateway_id)) {
            return $sms_conf->get($gateway_id);
        }
        return false;
    }
    
    /**
     * 短信网关的主从配置
     * (主网关发送失败后,再采用从网关发送)
     * @var array
     */
    private static $smsMasterSlave = array(
        '2' => array('5'),
    );
    
    public static function getSmsMasterSlave()
    {
        $commonConf  = new Yaf_Config_Ini(CONF_PATH . '/jkb.ini', 'common');
        $environment = $commonConf->get('yaf')->environment;
        
        $configs       = new Yaf_Config_Ini(CONF_PATH . '/sms.ini', $environment);
        $sms_master    = $configs->get('smsMaster');
        $sms_slave     = $configs->get('smsSlave');
        $sms_slave_arr = array();
        foreach ($sms_slave as $val) {
            $sms_slave_arr[] = $val;
        }
        return array($sms_master => $sms_slave_arr);
        
        //return self::$smsMasterSlave;
    }
    
    
    /**
     * 获取dbs email和sms配置
     * @return array
     */
    public static function getDbsConfig()
    {
        $commonConf  = new Yaf_Config_Ini(CONF_PATH . '/jkb.ini', 'common');
        $environment = $commonConf->get('yaf')->environment;
        
        $configs = new Yaf_Config_Ini(CONF_PATH . '/dbs.ini', $environment);
        return array(
            'emailApiUrl'     => $configs->get('DBS')->get('Email')->get('ApiUrl'),
            'smsApiUrl'       => $configs->get('DBS')->get('SMS')->get('ApiUrl'),
            'appKey'          => $configs->get('DBS')->get('AppKey'),
            'appCode'         => $configs->get('DBS')->get('AppCode'),
            'smsUserName'     => $configs->get('DBS')->get('SMS')->get('UserName'),
        );
    }
}
