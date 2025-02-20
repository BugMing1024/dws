<?php

/**
 * 发送短信
 * Created by PhpStorm.
 * User: dengchao
 * Date: 17/4/10
 * Time: 16:39
 */
class Alert_Adapter_Sms extends Alert_SendAdapter
{
    public function send()
    {
        $body = $this->filterContent($this->MessageBody);
        //读取短信网关
        $aGates   = Configs_SmsConfig::getSmsMasterSlave();
        $aMaster  = array_keys($aGates);
        $sendGate = $aMaster[mt_rand(0, count($aMaster) - 1)];
        //发送
        $result = array();
        foreach ($this->MessageTo as $to) {
            $sendResult  = false;
            $countryCode = $to->countryCode;
            $phoneNum    = $to->to;
            if ($countryCode != "86") {
                $logcontent = Helper_EnvParams::getLogger()->getLogContent(Configs_LogRuleConfig::LOG_TYPE_BIZ_SUCCESS, "sms,Non mainland number,use twilio channle", [], ["countryCode" => $countryCode, "phoneNum" => $phoneNum]);
                Seaslog::info($logcontent, array(), Helper_EnvParams::getLogger()->loggerName);
                //JKBVIP-3718 海外通道twilio
                $toPhoneNum = "+" . $countryCode . $phoneNum;
                $sendResult = $this->sendSms(new Services_SmsChannel_Twilio($toPhoneNum, $body));
            } else {
                $logcontent = Helper_EnvParams::getLogger()->getLogContent(Configs_LogRuleConfig::LOG_TYPE_BIZ_SUCCESS, "sms,mainland number", [], ["countryCode" => $countryCode, "phoneNum" => $phoneNum, "gate" => $sendGate]);
                Seaslog::info($logcontent, array(), Helper_EnvParams::getLogger()->loggerName);
                $masterResult = $this->sendSmsByGate($sendGate, $phoneNum, $body);
                //主网关发送失败,则用从网关再发送
                if (!$masterResult) {
                    $logcontent = Helper_EnvParams::getLogger()->getLogContent(Configs_LogRuleConfig::LOG_TYPE_BIZ_FAILED, "sms,master gate send sms failed.try slave gate again。", [], ["countryCode" => $countryCode, "phoneNum" => $phoneNum]);
                    Seaslog::info($logcontent, array(), Helper_EnvParams::getLogger()->loggerName);
                    foreach ($aGates[$sendGate] as $gate_id) {
                        $slaveResult = $this->sendSmsByGate($gate_id, $phoneNum, $body);
                        if ($slaveResult) {
                            $sendResult = true;
                            break;
                        }
                    }
                } else {
                    $sendResult = true;
                }
            }
            array_push($result, array(
                "to"          => $phoneNum,
                "countryCode" => $countryCode,
                "status"      => $sendResult ? "success" : "failed"
            ));
        }
        return $result;
    }
    
    private function sendSms(Services_SmsChannel_SmsAbstract $channel)
    {
        return $channel->sendSms();
    }
    
    private function sendSmsByGate($gate, $to, $body)
    {
        $result = false;
        switch (intval($gate)) {
            case 2:
                $result = $this->sendSms(new Services_SmsChannel_Gate2($to, $body));
                break;
            case 5:
                $result = $this->sendSms(new Services_SmsChannel_Gate5($to, $body));
                break;
            case 9:
                $result = $this->sendSms(new Services_SmsChannel_Gate9($to, $body));
                break;
            default:
                break;
        }
        return $result;
    }
    
    /**
     * 替换内容中的关键字
     * @param $content
     * @return mixed
     */
    private function filterContent($content)
    {
        $patterns     = array('/电信/', '/联通/', '/移动/', '/网通/');
        $replacements = array('电 信', '联 通', '移 动', '网 通');
        return preg_replace($patterns, $replacements, $content);
    }
    
    public function dbsSend()
    {
        // 读取dbs配置
        $conf = Configs_SmsConfig::getDbsConfig();
        //请求参数
        $smsHeader = array(
            'Content-Type' => 'application/x-www-form-urlencoded',
        );
        $smsBody   = array(
            'message'  => $this->MessageBody,
            'username' => $conf['smsUserName'],
        );
        $result    = array();
        foreach ($this->MessageTo as $item) {
            $phone                = $item->to;
            $country              = $item->countryCode;
            $smsBody['recipient'] = $country . $phone;
            
            $resp = Services_Common::makeCurl($conf['smsApiUrl'], Configs_AlertConfig::CALLBACK_METHOD_POST, $smsHeader, $smsBody, Configs_AlertConfig::CALLBACK_FORMAT_FROM);
            if (!$resp) {
                $result[] = array(
                    "to"          => $phone,
                    "countryCode" => $country,
                    "status"      => "failed"
                );
            } else {
                $objResp = json_decode($resp);
                if (strtolower($objResp->responseMessage) != "success") {
                    $result[] = array(
                        "to"          => $phone,
                        "countryCode" => $country,
                        "status"      => "failed"
                    );
                } else {
                    $result[] = array(
                        "to"          => $phone,
                        "countryCode" => $country,
                        "status"      => "success"
                    );
                }
            }
        }
        return $result;
    }
}
