<?php

/**
 * 递归的生成目录
 * @param  str $dir 必须是目录
 */
function mkdirs($dir)
{
    return is_dir($dir) ?: mkdirs(dirname($dir)) && mkdir($dir);
}

/**
 * simple curl
 * @param  string $url
 * @param  array  $param = [
 *                   'method' => 'get',    // post
 *                   'data' => [],    // get/post data
 *                   'return' => 'body',    // all or header
 *               ]
 * @return mix
 */
function simpleCurl($url = '', $param = [])
{
    if (!$url) return false;
    $parseUrl = parse_url($url);
    $sessionKey = md5($parseUrl['host'] . 'wilon');
    // 初始化
    $ch = curl_init();
    if ($param['method'] == 'get' && $param['data']) {
        $joint = parse_url($url)['query'] ? '&' : '?';
        $url .= $joint . http_build_query($param['data']);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // https支持
    if ($parseUrl['scheme'] == 'https') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }
    // header
    $header = [];
    if (strpos(json_encode($param['header']), 'User-Agent') === false) {
        $header[] = 'User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36';
    }
    if (is_string($param['header'])) {
        foreach (explode("\n", $param['header']) as $v) {
            $header[] = trim($v);
        }
    } else if (is_array($param['header'])) {
        $header += $param['header'];
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    // cookie
    $curloptCookie = '';
    $sessionCookie = &$_SESSION[$sessionKey]['cookie'];
    if (is_string($param['cookie'])) {
        $curloptCookie .= $param['cookie'];
    } else if (is_array($param['cookie']) && is_array($sessionCookie)) {
        $sessionCookie = array_merge($sessionCookie, $param['cookie']);
    }
    if ($sessionCookie) {
        foreach ($sessionCookie as $k => $v) {
            $curloptCookie .= "$k=$v;";
        }
    }
    curl_setopt($ch, CURLOPT_COOKIE, $curloptCookie);
    // post
    if ($param['method'] == 'post') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param['data']);
    }
    // response
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = trim(substr($response, 0, $headerSize));
    $body = trim(substr($response, $headerSize));
    curl_close($ch);
    // 自动刷新cookie
    preg_match_all('/Set-Cookie:(.*?)\n/', $header, $matchesCookie);
    foreach ($matchesCookie[1] as $setCookie) {
        foreach (explode(';', $setCookie) as $cookieStr) {
            list($key, $value) = explode('=', trim($cookieStr));
            $sessionCookie[$key] = $value;
        }
    }
    // 返回
    $return = $param['return'] == 'header' ? $header :
        ($param['return'] == 'all' ? [$header, $body] : $body);
    return $return;
}

/**
 * 获取远程文件方法
 * @param  string $url       地址
 * @param  string $file      文件名
 * @return array             文件信息
 */
function downloadUrl($url = '', $file = '')
{
    // 生成目录
    mkdirs(dirname($file));
    $fp = fopen($file, 'wb');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return file_exists($file);
}

/**
 * 写入文件日志方法
 */
function writeFileLog()
{
    // 处理动态参数
    $param = func_get_args();
    $file = $param[0];
    $arr['time'] = date('Y-m-d H:i:s');    // 增加时间参数
    array_shift($param);
    $arr['data'] = $param;
    if (count($param) == 1) {
        $arr['data'] = $param[0];
    }
    // json串化，并处理汉字显示
    $str = json_encode($arr, JSON_UNESCAPED_UNICODE);
    // 打开（创建）文件，写入并关闭
    mkdirs(dirname($file));
    $fp = fopen($file, 'a+');
    fwrite($fp, $str . "\r\n");
    fclose($fp);
}

/**
 * 缩进数据，以json在HTML上展示
 */
function indentToJson($data)
{
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $search = ["\n", " "];
    $replace = ['<br>', '&nbsp;'];
    return str_replace($search, $replace, $json);
}

function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
{
    $ckey_length = 4;
    $key = md5($key != '' ? $key : getglobal('authkey'));
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
    $cryptkey = $keya.md5($keya.$keyc);
    $key_length = strlen($cryptkey);
    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
    $string_length = strlen($string);
    $result = '';
    $box = range(0, 255);
    $rndkey = array();
    for($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }
    for($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }
    for($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }
    if($operation == 'DECODE') {
        if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc.str_replace('=', '', base64_encode($result));
    }
}
