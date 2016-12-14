<?php
namespace iqianfang\yii2wxpay;

use Yii;
use yii\base\Component;
use yii\helpers\Url;
use yii\web\UrlManager;

ini_set('date.timezone', 'Asia/Shanghai');

/**
 * Created by PhpStorm.
 * User: 山东千方信息科技 qianfang.me
 * Date: 2016/12/11
 * Time: 18:12
 * @property array $result
 */
require_once 'WxPay.NativePay.php';
require_once "lib/WxPay.Api.php";
require_once 'lib/WxPay.Notify.php';
require_once 'lib/WxPay.Config.php';

class WxPay extends Component
{
    /**
     *
     * @param $order
     * @return string
     * @author WangTao <78861190@qq.com>
     */
    public function getQrcode($order)
    {
        //模式二
        /**
         * 流程：
         * 1、调用统一下单，取得code_url，生成二维码
         * 2、用户扫描二维码，进行支付
         * 3、支付完成之后，微信服务器会通知支付成功
         * 4、在支付成功通知中需要查单确认是否真正支付成功（见：notify.php）
         */
        $notify = new NativePay();
        $input = new \WxPayUnifiedOrder();
        $input->SetBody("购买课程");
        $input->SetAttach("");
        $input->SetOut_trade_no($order->sn);
        $input->SetTotal_fee($order->amount * 100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 30 * 60));
        $input->SetGoods_tag("");
        $input->SetNotify_url(Yii::$app->urlManager->createAbsoluteUrl(['payment/wxpay-notify']));
        $input->SetTrade_type("NATIVE");
        $input->SetProduct_id($order->sn);
        $result = $notify->GetPayUrl($input);
        $url = $result["code_url"];

        return "<div>" .
        "<h3 class='pb12'>请用微信扫描下面二维码</h3>" .
        "<img src='http://paysdk.weixin.qq.com/example/qrcode.php?data=" . urlencode($url) . "' width=200 height=200 />" .
        "</div>";
    }

    /**
     * @param $order
     * @return string
     * @author WangTao <78861190@qq.com>
     */
    public function submit($order)
    {
        return $this->getQrcode($order);
    }


    /**
     * 将获取到的通知由xml转为array
     * @return mixed
     * @author WangTao <78861190@qq.com>
     */
    public function getResult()
    {
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $result = $xml ? json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true) : array();
        return $result;
    }

    /**
     * 验证签名参数等，并返回数据
     * @author WangTao <78861190@qq.com>
     */
    public function checkSign()
    {
        $data = ['return_code' => 'SUCCESS', 'return_msg' => 'OK'];
        $result = $this->getResult();
        if (!array_key_exists("transaction_id", $result)) {
            $data['return_msg'] = "输入参数不正确";
            $data['return_code'] = 'FAIL';
        }
        if (array_key_exists('sign', $result)) {
            if ($result['sign'] != $this->makeSign()) {
                $data['return_msg'] = "签名错误";
                $data['return_code'] = 'FAIL';
            }
        } else {
            $data['return_msg'] = "输入参数不正确";
            $data['return_code'] = 'FAIL';
        }

        return $data;
    }

    /**
     * 根据返回结果 生成签名
     * @return string，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function makeSign()
    {
        //签名步骤一：按字典序排序参数
        ksort($this->getResult());
        $string = $this->ToUrlParams();
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . \WxPayConfig::KEY;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 格式化参数格式化成url参数
     */
    public function ToUrlParams()
    {
        $buff = "";
        foreach ($this->getResult() as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 回调同步返回
     * @author WangTao <78861190@qq.com>
     */
    public function replyNotify()
    {
        $data = $this->checkSign();
        $xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";

        echo $xml;
    }
}