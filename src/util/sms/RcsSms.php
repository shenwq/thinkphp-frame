<?php
declare (strict_types=1);

namespace ffhome\frame\util\sms;

use ffhome\common\util\sms\RcsSmsApi;

class RcsSms
{
    /**
     * @var RcsSmsApi
     */
    private $api;

    public function __construct()
    {
        $this->api = new RcsSmsApi(sysValue('sms_rcscloud_account'), sysValue('sms_rcscloud_account'));
    }

    /**
     * 发送短信
     * @param string $tplId 模板ID
     * @param string $mobile 手机号码，只支持一个11位的手机号
     * @param string $content 参数值，多个参数以“||”隔开 如:@1@=HY001||@2@=3281
     */
    public function send(string $tplId, string $mobile, string $content)
    {
        $this->api->send($tplId, $mobile, $content);
    }
}