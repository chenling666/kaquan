<?php
/**
 * 实现方案：
 * 思路：
 * 1.定义一个二维数组，数组长度设置变量len，范围[1,100000]，值包含卡号no、密码password，for循环赋值
 *      例如：Array([0] => ['no' => '*****', 'password' => '*****'],
 *                 [1] => ['no' => '*****', 'password' => '*****'],
 *                  、、、
 *                )
 *
 * 2.卡号的生成：前缀（如会员类型0、1、2）+ date('YmdHis')(14位) + number(000001 -- 100000)
 *
 * 3.密码的生成：数字1-9，大写字母A-Z，去掉O，生成15位数字字母混合字符串 + 最后一位数字校验位（提取前15位字符串中数字，进行Luhn算法得出：
 *              ①.从校验位开始，从右往左，偶数位乘2（例如，1*2=2），若乘以2后得到两位数，将两位数字的个位与十位相加（例如，16：1+6=7）；
 *              ②.把得到的数字加在一起；
 *              ③.将数字的和取模10，再用10去减，得到校验位。）
 *
 * 4.加密密码(可逆加密)：openssl_encrypt加密,加密模式：AES-256-CBC
 *
 * 5.兑换码：卡号、密码（加密后的）入库
 *
 */

class KaquanTools
{
    private $hex_iv = '00000000000000000000000000000000';
    private $key = '397e2eb61307109f6e68006ebcb62f98';

    function __construct() {
        $this->key = hash('sha256', $this->key, true);
    }

    /**
     * 生成兑换码
     * @param $prefix
     * @param $nums
     * @return array
     */
    public function makeCard($prefix, $nums)
    {
        $card = [];
        for ($i = 0; $i < $nums; $i++) {
            $card[$i]['no'] = $prefix . date('YmdHis') . sprintf("%06d", $i +1);
            $password = $this->makePassword();
            //var_dump($password);
            $password = $this->encrypt($password);
            //var_dump($password);
            $card[$i]['password'] = $password;
            //$password = $this->decrypt($password);
            //var_dump($password);
        }
        return $card;
    }

    /**
     * 生成16位不重复密码
     * @return string
     */
    public function makePassword()
    {
        //生成15位密码
        $uniqid = uniqid('vip',true);
        $param_string = $_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].time().rand().$uniqid;
        $sha1 = sha1($param_string);
        for(
            $a = md5($sha1),
            $s = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ',
            $d = '',
            $f = 0;
            $f < 15;
            $g = ord($a[$f]),
            $d .= $s[($g ^ ord($a[$f + 16])) - $g & 0x1F],
            $f++
        );
        //补全16位校验位
        $d = self::gen($d);
        return $d;
    }

    /**
     * 补填校验位
     * @param $data
     * @return string 补填校验位后的号码
     */
    public function gen($data) {
        //提取数字
        $rawStr = $this->findNum($data);
        //Luhn算法生成校验位
        $lastNo = self::calcCheckNum($rawStr);
        return $data . $lastNo;
    }

    /**
     * 提取字符串中的数字位
     * @param string $str
     * @return string
     */
    public function findNum($str=''){
        $str=trim($str);
        if(empty($str)){return '';}
        $result='';
        for($i=0; $i<strlen($str); $i++){
            if(is_numeric($str[$i])){
                $result.=$str[$i];
            }
        }
        return $result;
    }

    /**
     * 计算校验值
     * @param string $rawStr 原始字符串(不含校验位)
     * @return int 校验值
     */
    private static function calcCheckNum($rawStr)
    {
        $strrev = strrev($rawStr);
        $sum    = 0;
        for ($i = 0; $i < strlen($strrev); $i++) {
            $val = intval($strrev[$i]);
            $sum += ($i % 2 == 0) ? ($val > 4 ? (2 * $val - 9) : (2 * $val)) : $val;
        }
        $ret = 10 - $sum % 10;
        return $ret == 10 ? 0 : $ret;
    }

    /**
     * 校验给定数据
     * @param $str
     * @return bool true:通过|false:不通过
     */
    public function verify($str)
    {
        $rawStr = substr($str, 0, -1);
        $checkNo = intval(substr($str, -1));
        $rawNo = self::findNum($rawStr);
        return $checkNo == self::calcCheckNum($rawNo);
    }

    /**
     * 加密
     * @param $input
     * @return false|string
     */
    public function encrypt($input)
    {
        $data = openssl_encrypt($input, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $this->hexToStr($this->hex_iv));
        $data = base64_encode($data);
        return $data;
    }

    /**
     * 解密
     * @param $input
     * @return false|string
     */
    public function decrypt($input)
    {
        $decrypted = openssl_decrypt(base64_decode($input), 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $this->hexToStr($this->hex_iv));
        return $decrypted;
    }

    public function hexToStr($hex)
    {
        $string='';
        for ($i=0; $i < strlen($hex)-1; $i+=2) {
            $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $string;
    }
}
