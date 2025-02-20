<?php
/**
 * 发送邮件
 * Created by PhpStorm.
 * User: dengchao
 * Date: 17/4/10
 * Time: 16:04
 */
require_once 'phpmailer/class.phpmailer.php';

class Alert_Adapter_Email extends Alert_SendAdapter
{
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $from;
    private $fromName;
    private $subject;
    private $SMTPSecure;
    
    /**
     * 配置的邮件服务器
     * @var array
     */
    private $sysSmtp = array();
    
    /**
     * 专属邮箱服务器配置
     * @var array
     */
    private $exclusiveSmtp = array();
    private $exclusiveMailSuffix = '';
    
    private $exSmtpHost;
    private $exSmtpPort;
    private $exSmtpAuth;
    private $exSmtpSsl;
    private $exSmtpUser;
    private $exSmtpPass;
    private $exSmtpFrom;
    private $exSmtpFromName;
    
    public function __construct($sendType, $MessageBody, array $MessageTo, array $otherParams)
    {
        parent::__construct($sendType, $MessageBody, $MessageTo, $otherParams);
        $this->init();
    }
    
    public function send()
    {
        $mail           = new PHPMailer(true);
        $mail->Host     = $this->smtpHost;
        $mail->Port     = $this->smtpPort ? $this->smtpPort : 25;
        $mail->Username = $this->smtpUser;
        $mail->Password = $this->smtpPass;
        if ($this->smtpUser && $this->smtpPass) {
            $mail->SMTPAuth = true;
        }
        $mail->IsSMTP();
        $mail->Body     = $this->MessageBody;
        $mail->Subject  = $this->subject;
        $mail->From     = $this->from;
        $mail->FromName = $this->fromName;
        if ($this->SMTPSecure) {
            $mail->SMTPSecure = "{$this->SMTPSecure}";
        }
        //新手指南符件
        if ($this->OtherParams['guide']) {
            $mail->AddAttachment(APP_PATH . '/../locales/jkb.pdf', '监控宝新手指引（极速版）.pdf'); // 添加附件,并指定名称
        }
        if ($this->OtherParams['mailType'] === 'text') {
            $mail->isHTML(false);
        } else {
            $mail->isHTML(true);
        }
        $mail->SMTPDebug = false;
        $mail->CharSet   = 'UTF-8';
        
        $MailSuffix = array();
        if ($this->exclusiveMailSuffix) {
            //exclusiveSmtp
            $mailEx           = new PHPMailer(true);
            $mailEx->Host     = $this->exSmtpHost;
            $mailEx->Port     = $this->exSmtpPort ? $this->exSmtpPort : 25;
            $mailEx->Username = $this->exSmtpUser;
            $mailEx->Password = $this->exSmtpPass;
            if ($this->exSmtpAuth && $this->exSmtpUser && $this->exSmtpPass) {
                $mailEx->SMTPAuth = true;
            }
            $mailEx->IsSMTP();
            $mailEx->Body     = $this->MessageBody;
            $mailEx->Subject  = $this->subject;
            $mailEx->From     = $this->exSmtpFrom;
            $mailEx->FromName = $this->exSmtpFromName;
            if ($this->exSmtpSsl) {
                $mailEx->SMTPSecure = 'ssl';
            }
            //新手指南符件
            if ($this->OtherParams['guide']) {
                $mailEx->AddAttachment(APP_PATH . '/../locales/jkb.pdf', '监控宝新手指引（极速版）.pdf'); // 添加附件,并指定名称
            }
            if ($this->OtherParams['mailType'] === 'text') {
                $mailEx->isHTML(false);
            } else {
                $mailEx->isHTML(true);
            }
            $mailEx->SMTPDebug = false;
            $mailEx->CharSet   = 'UTF-8';
            
            $MailSuffix = explode(',', $this->exclusiveMailSuffix);
        }
        
        
        $result = array();
        foreach ($this->MessageTo as $to) {
            $cur_mail = $mail;
            $smtpInfo = array("to" => $to, "subject" => $cur_mail->Subject, 'retry' => 0, 'host' => $this->smtpHost, 'port' => $this->smtpPort, 'user' => $this->smtpUser, 'pass' => $this->smtpPass, 'from' => $this->from, 'fromName' => $this->fromName);
            foreach ($MailSuffix as $suf) {
                if (strpos($to, $suf) > 0) {
                    $cur_mail = $mailEx;
                    $smtpInfo = array("to" => $to, "subject" => $cur_mail->Subject, 'retry' => 0, 'host' => $this->exSmtpHost, 'port' => $this->exSmtpPort, 'user' => $this->exSmtpUser, 'pass' => $this->exSmtpPass, 'from' => $this->from, 'fromName' => $this->fromName);
                    break;
                }
            }
            
            $logcontent = Helper_EnvParams::getLogger()->getLogContent(Configs_LogRuleConfig::LOG_TYPE_BIZ_SUCCESS, "mail will send", [], ["to" => $to, "subject" => $cur_mail->Subject]);
            Seaslog::info($logcontent, array(), Helper_EnvParams::getLogger()->loggerName);
            
            $cur_mail->ClearAddresses();
            $arr  = explode('@', $to);
            $name = $arr[0];
            $cur_mail->AddAddress($to, $name);
            try {
                $retry = 0;
                $send  = false;
                while ($retry < 3) {
                    if (false == $cur_mail->send()) {
                        $smtpInfo['retry'] = $retry;
                        $logcontent        = Helper_EnvParams::getLogger()->getLogContent(Configs_LogRuleConfig::LOG_TYPE_BIZ_FAILED, "mail send", [], $smtpInfo);
                        Seaslog::info($logcontent, array(), Helper_EnvParams::getLogger()->loggerName);
                        $retry++;
                        usleep(500000);
                        if (!empty($this->sysSmtp)) {
                            $smtpServer = $this->selectOneSysSmtpServer();
                            $this->initSystemSmtpServer($smtpServer);
                        }
                    } else {
                        $send              = true;
                        $smtpInfo['retry'] = $retry;
                        $logcontent        = Helper_EnvParams::getLogger()->getLogContent(Configs_LogRuleConfig::LOG_TYPE_BIZ_SUCCESS, "mail send", [], $smtpInfo);
                        Seaslog::info($logcontent, array(), Helper_EnvParams::getLogger()->loggerName);
                        break;
                    }
                }
                array_push($result, array(
                    "to"     => $to,
                    "status" => $send ? "success" : "failed"
                ));
            } catch (Exception $e) {
                array_push($result, array(
                    "to"     => $to,
                    "status" => "failed"
                ));
                $smtpInfo['retry'] = $retry;
                $logcontent        = Helper_EnvParams::getLogger()->getLogContent(Configs_LogRuleConfig::LOG_TYPE_BIZ_FAILED, "mail send", [$e->getMessage()], $smtpInfo);
                Seaslog::error($logcontent, array(), Helper_EnvParams::getLogger()->loggerName);
                continue;
            }
        }
        unset($mail);
        unset($mailEx);
        return $result;
    }
    
    private function init()
    {
        $this->from     = $this->OtherParams['from'];
        $this->fromName = $this->OtherParams['fromName'];
        $this->subject  = $this->OtherParams['subject'];
        if ($this->OtherParams['smtpServer']) {
            $this->initCustomSmtpServer();
        } else {
            $this->getSysSmtpServer();
            $smtpServer = $this->selectOneSysSmtpServer();
            $this->initSystemSmtpServer($smtpServer);
            $exSmtpServer = $this->selectOneExclusiveMail();
            $this->initExclusiveSmtpServer($exSmtpServer);
        }
    }
    
    private function getSysSmtpServer()
    {
        //获取邮件发送配置
        $commonConf  = new Yaf_Config_Ini(CONF_PATH . '/jkb.ini', 'common');
        $environment = $commonConf->get('yaf')->environment;
        unset($commonConf);
        
        $configs       = new Yaf_Config_Ini(CONF_PATH . '/email.ini', $environment);
        $this->sysSmtp = $configs->get('yaf')->get('mail');
        if (isset($this->sysSmtp['host'])) {
            $this->sysSmtp = array($this->sysSmtp);
        }
        $this->exclusiveSmtp       = $configs->get('yaf')->get('exclusiveSmtp');
        $this->exclusiveMailSuffix = $configs->get('yaf')->get('exclusiveMailSuffix');
    }
    
    private function selectOneSysSmtpServer()
    {
        $mt_time = mt_rand();
        $index   = $mt_time % count($this->sysSmtp);
        $oneConf = $this->sysSmtp[$index];
        unset($this->sysSmtp[$index]);
        return $oneConf;
    }
    
    private function selectOneExclusiveMail()
    {
        $mt_time = mt_rand();
        $index   = $mt_time % count($this->exclusiveSmtp);
        $oneConf = $this->exclusiveSmtp[$index];
        unset($this->exclusiveSmtp[$index]);
        return $oneConf;
    }
    
    private function initCustomSmtpServer()
    {
        $smptServer       = $this->OtherParams['smtpServer'];
        $this->smtpHost   = $smptServer->host;
        $this->smtpPort   = $smptServer->port;
        $this->smtpUser   = $smptServer->user;
        $this->smtpPass   = $smptServer->password;
        $this->SMTPSecure = $smptServer->secure;
        if (!$this->from) {
            $fromEmail = Configs_AlertConfig::DEFAULT_EMAIL_FROM;
            if (strpos($fromEmail, "jiankongbao") !== false) {
                $this->from = str_replace('@', date("YmdHis") . '@', $fromEmail);
            } else {
                $this->from = $fromEmail;
            }
        }
        if (!$this->fromName) {
            $this->fromName = Configs_AlertConfig::DEFAULT_EMAIL_FROM_NAME;
        }
        if (!$this->subject) {
            $this->subject = Configs_AlertConfig::DEFAULT_EMAIL_SUBJECT;
        }
    }
    
    private function initSystemSmtpServer($smtpConf)
    {
        $this->smtpHost = $smtpConf->get('host');
        $this->smtpPort = $smtpConf->get('port');
        $this->smtpUser = $smtpConf->get('user');
        $this->smtpPass = Helper_RsaFacade::getRealPwd($smtpConf->get('pass'));
        if ($smtpConf->get('SMTPSecure')) {
            $this->SMTPSecure = $smtpConf->get('SMTPSecure');
        }
        
        if (!$this->from) {
            $fromEmail = $smtpConf->get('from') ? $smtpConf->get('from') : Configs_AlertConfig::DEFAULT_EMAIL_FROM;
            
            $cfgDataFmt = $smtpConf->get('fromDateFormat');
            $cfgDataFmt = strtolower($cfgDataFmt);
            if ($cfgDataFmt == "true") {
                $cfgDataFmt = true;
            } elseif ($cfgDataFmt == "false") {
                $cfgDataFmt = false;
            } else {
                //如果配置错误，则拼接
                $cfgDataFmt = true;
            }
            
            if ($cfgDataFmt) {
                $this->from = str_replace('@', date("YmdHis") . '@', $fromEmail);
            } else {
                $this->from = $fromEmail;
            }
        }
        if (!$this->fromName) {
            $this->fromName = $smtpConf->get('fromName') ? $smtpConf->get('fromName') : Configs_AlertConfig::DEFAULT_EMAIL_FROM_NAME;
        }
        if (!$this->subject) {
            $subject       = $smtpConf->get('subject');
            $this->subject = $subject ? $subject : Configs_AlertConfig::DEFAULT_EMAIL_SUBJECT;
        }
    }
    
    private function initExclusiveSmtpServer($smtpConf)
    {
        $this->exSmtpHost     = $smtpConf->get('host');
        $this->exSmtpPort     = $smtpConf->get('port');
        $this->exSmtpUser     = $smtpConf->get('name');
        $this->exSmtpPass     = Helper_RsaFacade::getRealPwd($smtpConf->get('pwd'));
        $this->exSmtpAuth     = $smtpConf->get('auth');
        $this->exSmtpSsl      = $smtpConf->get('ssl');
        $this->exSmtpFrom     = $smtpConf->get('from') ? $smtpConf->get('from') : $this->from;
        $this->exSmtpFromName = $smtpConf->get('fromName') ? $smtpConf->get('fromName') : $this->fromName;
    }
    
    public function dbsSend()
    {
        // 读取dbs配置
        $conf = Configs_SmsConfig::getDbsConfig();
        // 请求参数
        $mailBody   = array(
            "to"      => $this->MessageTo,
            "subject" => $this->subject,
            "body"    => $this->MessageBody
        );
        $mailHeader = array(
            'Content-Type' => Configs_AlertConfig::CALLBACK_FORMAT_FROM,
            'appCode'      => $conf['appCode'],
            'appKey'       => $conf['appKey']
        );
        //发送请求
        $resp     = Services_Common::makeCurl($conf['emailApiUrl'], Configs_AlertConfig::CALLBACK_METHOD_POST, $mailHeader, $mailBody, Configs_AlertConfig::CALLBACK_FORMAT_FROM);
        $sendFlag = true;
        if (!$resp) {
            $sendFlag = false;
        } else {
            $ojbResp = json_decode($resp);
            if ((int)$ojbResp->code != 200) {
                $sendFlag = false;
            }
        }
        $result = array();
        foreach ($this->MessageTo as $to) {
            $result[] = array(
                'to'     => $to,
                "status" => $sendFlag ? "success" : "failed"
            );
        }
        
        return $result;
    }
}
