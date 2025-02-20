<?php
/* 
单文件无需搭建数据库，访问一次自动缓存，下次访问缓存优先。
在服务器上创建一个epg文件夹，然后把index.php放进去。
此代码只能在二级目录下运行，如果在一级目录下运行，需要修改路径
请求接口优先走央视官方接口，（可前后日期查询）然后依次往下走。
可对第三方xml.gz压缩包（只能当日）

diyp请求例子：http://localhost/epg/ 
浏览器请求例子：http://localhost/epg/?ch=CCTV1&date=2024-08-13
一键清理：http://localhost/epg/?cache=123456（推荐宝塔定时访问 每日凌晨0:01）
一键更新：http://localhost/epg/?gx=666666 （推荐宝塔定时访问 间隔4-6小时访问一次）

更多源码群内下载。
IT技术群:200702731   YB:Feng
入群要求：本群不收小号，QQ等级大于50 且备注明确来源，否则一律拒绝。
 */

error_reporting(0);
header('Content-type:text/json;charset=utf8');
define('WHO1', 'cache/'); //缓存保存的文件夹 如果不缓存请无视
define('WHO2', 'epg/'); //自定义二级目录文件夹
$web_time = microtime(true);
$webApi =  domain();
$Vtche = $_GET['cache'];
$GX = $_GET['gx'];
if ($GX == '666666') {
    $urlxml = [//不要太贪心，否则卡死
        // 'http://epg.pw/xmltv/epg_HK.xml.gz', //香港
        // 'http://epg.pw/xmltv/epg_TW.xml.gz', //台湾
        'https://ghproxy.liuzhicong.com/https://raw.githubusercontent.com/Meroser/EPG-test/main/tvxml-test.xml.gz', //（今天）全部节目 压缩包文件
        //'https://gitee.com/Black_crow/xmlgz/raw/master/cc.xml.gz',
        //'http://epg.51zmt.top:8000/difang.xml.gz',
        // 地区查看：https://epg.pw/xmltv.html?lang=zh-hant
    ];
    $xmlData = [];
    foreach ($urlxml as $urlxmls) {
        $xmlgz = file_get_contents($urlxmls);
        $xmljieya = gzdecode($xmlgz);
        $xml = simplexml_load_string($xmljieya);
        $processedTitles = [];
        foreach ($xml->programme as $programme) {
            $channelId = (string)$programme['channel'];
            $channelNode = null;
            foreach ($xml->channel as $channel) {
                if ((string)$channel['id'] === $channelId) {
                    $channelNode = $channel;
                    break;
                }
            }
            if ($channelNode !== null) {
                $channelName = (string)$channelNode->{'display-name'};
                $channelKey = strtoupper(str_replace(' ', '', $channelName));
                $channelKey = preg_replace('/(-\d+).*$/', '$1', $channelKey);
                $channelKey = str_replace('-', '', $channelKey);
                $channelKey = str_replace('CCTV5PLUS', 'CCTV5+', $channelKey);
                $channelKey = preg_replace('/(HD|高清|超清|超高清|标清|频道|頻道).*$/u', '', $channelKey);
                $startDate = DateTime::createFromFormat('YmdHis O', (string)$programme['start']);
                $endDate = DateTime::createFromFormat('YmdHis O', (string)$programme['stop']);
                $start = $startDate->format('H:i');
                $end = $endDate->format('H:i');
                $title = (string)$programme->title;
                $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title);
                if (!in_array($title, $processedTitles)) {
                    $epg_data = [
                        "start" => $start,
                        "end" => $end,
                        "title" => $title,
                    ];
                    $xmlData[$channelKey][] = $epg_data;
                    $processedTitles[] = $title;
                }
            }
        }
    }
    $jsonData = json_encode($xmlData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (strlen($jsonData) < 700) {
        die("更新失败!远程xml网址无法访问,请联系管理员!");
    }
    file_put_contents('xmlData.json', $jsonData);
    chmod('xmlData.json', 0777);
    if (!empty($jsonData)) {
        die('更新完成!');
    } else {
        die('更新失败!');
    }
}
if ($Vtche == '123456') {
    $i = 0;
    if (is_dir(WHO1) == true) {
        $dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(WHO1), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($dirIterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();
                if (pathinfo($filePath, PATHINFO_EXTENSION) == 'json') {
                    unlink($filePath);
                    $i++;
                }
            }
        }
    }
    $emptyDirs = array_reverse(glob(WHO2, GLOB_ONLYDIR));
    foreach ($emptyDirs as $dir) {
        if (count(glob($dir . '/*')) === 0) {
            rmdir($dir);
        }
    }
    die('本次成功清理了' . $i . '个缓存文件！！！');
}
session_start();
// 设置允许访问的最大次数和时间间隔（防止请求过大封ip）
$maxRequests = 110;
$timeInterval = 60;
// 调用函数检查访问次数是否超过限制
if (!checkAccessLimit($maxRequests, $timeInterval)) {
    die("访问次数超过限制，请稍后再试");
} else {
    $name = strtoupper($_GET['ch']);
    $date = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");
    if ($name == '') {
        die("当前没有输入相关参数! 例如: ?ch=CCTV1&date=$date");
    }
    $name = trim($name);
    $name = str_replace('CCTV-0', 'CCTV-', $name);
    $name = str_replace('CCTV0', 'CCTV', $name);
    $name = str_replace('CCTV5＋', 'CCTV5PLUS', $name);
    $name = str_replace('CCTV5+', 'CCTV5PLUS', $name);
    $name = str_replace('CCTV5%2B', 'CCTV5PLUS', $name);
    $name = str_replace('CCTV兵器科技', '兵器科技', $name);
    $name = str_replace('CCTV第一剧场', '第一剧场', $name);
    $name = str_replace('CCTV怀旧剧场', '怀旧剧场', $name);
    $name = str_replace('CCTV风云剧场', '风云剧场', $name);
    $name = str_replace('CCTV风云音乐', '风云音乐', $name);
    $name = str_replace('CCTV风云足球', '风云足球', $name);
    $name = str_replace('CCTV电视指南', '电视指南', $name);
    $name = str_replace('CCTV女性时尚', '女性时尚', $name);
    $name = str_replace('CCTV央视文化精品', '央视精品', $name);
    $name = str_replace('CCTV世界地理', '世界地理', $name);
    $name = str_replace('CCTV高尔夫网球', '高尔夫网球', $name);
    $name = str_replace('CCTV央视台球', '央视台球', $name);
    $name = str_replace('CCTV卫生健康', '卫生健康', $name);
    $name = preg_replace('/(-\d+).*$/', '$1', $name);
    $name = str_replace('-', '', $name);
    $name = preg_replace('/(HD|高清|超清|超高清|标清|频道|頻道).*$/u', '', $name); 
    $name = preg_replace('/\s+.*/', '', $name);
    $ep_file = WHO1 . md5($name . $date) . '.json';
    if (!file_exists(WHO1)) {
        mkdir(WHO1, 0777, true);
    }
    if (file_exists($ep_file)) {
        die(file_get_contents($webApi . WHO2 . $ep_file));
    }

    $n = [
        'CCTV1' => 'cctv1',
        'CCTV2' => 'cctv2',
        'CCTV3' => 'cctv3',
        'CCTV4' => 'cctv4',
        'CCTV5' => 'cctv5',
        'CCTV5+' => 'cctv5plus',
        'CCTV5PLUS' => 'cctv5plus',
        'CCTV6' => 'cctv6',
        'CCTV7' => 'cctv7',
        'CCTV8' => 'cctv8',
        'CCTV9' => 'cctvjilu',
        'CCTV10' => 'cctv10',
        'CCTV11' => 'cctv11',
        'CCTV12' => 'cctv12',
        'CCTV13' => 'cctv13',
        'CCTV14' => 'cctvchild',
        'CCTV15' => 'cctv15',
        'CCTV16' => 'cctv16',
        'CCTV17' => 'cctv17',
        'CCTV4k' => 'cctv4k',
        '北京卫视' => 'btv1',
        '江苏卫视' => 'jiangsu',
        '浙江卫视' => 'zhejiang',
        '东方卫视' => 'dongfang',
        '安徽卫视' => 'anhui',
        '天津卫视' => 'tianjin',
        '山东卫视' => 'shandong',
        '广东卫视' => 'guangdong',
        '深圳卫视' => 'shenzhen',
        '湖北卫视' => 'hubei',
        '湖南卫视' => 'hunan',
        '黑龙江卫视' => 'heilongjiang',
        '辽宁卫视' => 'liaoning',
        '河北卫视' => 'hebei',
        '河南卫视' => 'henan',
        '重庆卫视' => 'chongqing',
        '东南卫视' => 'dongnan',
        '甘肃卫视' => 'gansu',
        '贵州卫视' => 'guizhou',
        '海南卫视' => 'hainan',
        '吉林卫视' => 'jilin',
        '江西卫视' => 'jiangxi',
        '宁夏卫视' => 'ningxia',
        '青海卫视' => 'qinghai',
        '四川卫视' => 'sichuan',
        '新疆卫视' => 'xinjiang',
        '西藏卫视' => 'xizang',
        '云南卫视' => 'yunnan',
        '广西卫视' => 'guangxi',
    ];

    $name2 = $n[$name];
    $date2 = str_replace("-", "", $date);
    $api = "https://api.cntv.cn/epg/getEpgInfoByChannelNew?c=$name2&serviceId=tvcctv&d=$date2";
    $header = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36',
        "CLIENT-IP:" . rand_ip(),
        "X-Real-IP:" . rand_ip(),
        "X-FORWARDED-FOR:" . rand_ip(),
    ];
    $html = GET_POST($api, $header, 0, '', true);
    $json = json_decode($html, true);
    $list = $json['data'][$name2]['list'];
    $arr = [];
    foreach ($list as $listr) {
        $arr[] = array(
            "title" => $listr['title'],
            "start" => date("H:i", $listr['startTime']),
            "end" => date("H:i", $listr['endTime']),
        );
    }
    $result['channel_name'] = $name;
    $result['date'] = $date;
    $result['IT'] = '技术群:200702731';
    $result['epg_data'] = $arr;
    if (!empty($arr)) {
        file_put_contents($ep_file, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        die(file_get_contents($webApi . WHO2 . $ep_file));
    }
    // 加载XML数据并缓存
    $xmlcache = 'xmlData.json';
    $xmlData = json_decode(file_get_contents($xmlcache), true);
    $result = array();
    if (isset($xmlData[$name])) {
        $result['channel_name'] = $name;
        $result['IT'] = '技术群:200702731';
        $result['date'] = $date;
        $result['epg_data'] = $xmlData[$name];
    }
    if (!empty($xmlData[$name])) {
        die(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    $url2 = "http://epg.51zmt.top:8000/api/i/?ch=$name&date=$date";
    $html2 = GET_POST($url2, $header, 0, '', true);
    preg_match_all('/\<li\>\<td\>(.*?)\<\/td\>(.*?)\<td\>(.*?)\<\/td\>(.*?)\<td\>(.*?)\<\/td\>\<\/li\>/', $html2, $arr);
    if (!empty($arr[1])) {
        $epg_data = array();
        for ($i = 0; $i < count($arr[1]); $i++) {
            if ($i % 2 == 0) {
                $start = $arr[1][$i];
                $end = $arr[1][$i + 2];
                if ($end == null) {
                    $end = $start;
                }
                $str = $numbers[$i + 1];
                $title = $arr[3][$i];
                $title = str_replace('没有此日期节目信息--', '', $title);
                $title = str_replace('没有此频道', '', $title);
                $a[$i] = array(
                    "start" => $start,
                    "end" => $end,
                    "title" => $title,
                );
                array_push($epg_data, $a[$i]);
            }
        }
        $result['channel_name'] = $name;
        $result['date'] = $date;
        $result['IT'] = '技术群:200702731';
        $result['epg_data'] = $epg_data;
        if (!empty($title)) {
            file_put_contents($ep_file, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            die(file_get_contents($webApi . WHO2 . $ep_file));
        }
    }
}
function GET_POST($url, $header = [], $type = 0, $post_data = '', $redirect = true, $getheader = false)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    if (empty($header) == false) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    }
    if ($type == 1) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    }
    if ($redirect == true) {
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    }
    curl_setopt($curl, CURLOPT_TIMEOUT, 5);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_HEADER, $getheader == true ? true : false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_ENCODING, '');
    $return = curl_exec($curl);
    if ($getheader == true) {
        $return_header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $return = substr($return, 0, $return_header_size);
    }
    curl_close($curl);
    return $return;
}
function domain()
{
    $http_type = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $http_type . '://' . $_SERVER['HTTP_HOST'] . '/';
}
function rand_ip()
{
    $ipLongRanges = [
        ['607649792', '608174079'],
        ['975044608', '977272831'],
        ['999751680', '999784447'],
        ['1019346944', '1019478015'],
        ['1038614528', '1039007743'],
        ['1783627776', '1784676351'],
        ['1947009024', '1947074559'],
        ['1987051520', '1988034559'],
        ['2035023872', '2035154943'],
        ['2078801920', '2079064063'],
        ['-569376768', '-564133889'],
    ];
    $randKey = array_rand($ipLongRanges);
    $ip = long2ip(mt_rand($ipLongRanges[$randKey][0], $ipLongRanges[$randKey][1]));
    return $ip;
}
function checkAccessLimit($maxRequests, $timeInterval)
{
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!isset($_SESSION['access_data'][$ip])) {
        $_SESSION['access_data'][$ip] = array('count' => 0, 'time' => time());
    }
    $accessData = $_SESSION['access_data'][$ip];
    if (time() - $accessData['time'] > $timeInterval) {
        $_SESSION['access_data'][$ip] = array('count' => 1, 'time' => time());
    } else {
        $_SESSION['access_data'][$ip]['count']++;
    }
    if ($_SESSION['access_data'][$ip]['count'] > $maxRequests) {
        return false;
    } else {
        return true;
    }
}

