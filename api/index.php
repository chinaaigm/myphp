<?php
function sanitizeUrl($url) {
    $filteredUrl = filter_var($url, FILTER_VALIDATE_URL);

    if ($filteredUrl === false) {
        return '';
    }
    $protocol = parse_url($filteredUrl, PHP_URL_SCHEME);
    if ($protocol !== 'http' && $protocol !== 'https') {
        return '';
    }

    return $filteredUrl;
}

$targetUrl = '';
$output = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $targetUrl = $_POST['url'];
    $targetUrl = sanitizeUrl($targetUrl);

    if (empty($targetUrl)) {
        $error = "无效的 URL，请输入有效的 http 或 https URL。";
    } else {
        // 使用 file_get_contents 发送 GET 请求
        $contextOptions = array(
            'http' => array(
                'method' => 'GET', // 默认是 GET
                'header' => getHeadersString(), //转发请求头
                 'ignore_errors' => true, // 不抛出错误
            )
         );

       
        $context = stream_context_create($contextOptions);

        $response = @file_get_contents($targetUrl, false, $context);
        
         if ($response === false) {
             $error = "file_get_contents 错误：无法获取远程内容";

             $lastError = error_get_last();
            if (isset($lastError['message'])) {
                $error .= " - " . $lastError['message'];
            }

         } else {

        // 获取响应头
         $headers = getHeadersFromStream($http_response_header);
            
        // 发送响应头
           foreach ($headers as $h) {
               if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $h, $match)) {
                if ($match[1] == 100) {
                    continue;
                   }
             }
           if (!empty($h) && strpos($h, 'Transfer-Encoding') === false && strpos($h, 'Connection: close') === false )
            {
               header($h);
            }
            }
          
          $output = $response;
         }
    }
}


// 转发请求头函数
function getHeadersString(){
    $requestHeaders = getallheaders();
        $forwardHeaders = [];
        foreach ($requestHeaders as $key => $value) {
            if($key != 'Host' && $key != 'Connection' ){
               $forwardHeaders[] = "$key: $value";
            }
           
        }
        
        return implode("\r\n", $forwardHeaders);
}
// 从stream获取响应头函数
function getHeadersFromStream($httpResponseHeader){
        $headers = [];
        if(is_array($httpResponseHeader)){
          foreach ($httpResponseHeader as $header){
             $headers[] = $header;
          }
        }
        return $headers;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>简单代理</title>
</head>
<body>
    <h1>代理网站</h1>
    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="post">
        <label for="url">请输入 URL：</label>
        <input type="text" name="url" id="url" value="<?php echo htmlspecialchars($targetUrl); ?>" size="50">
        <button type="submit">访问</button>
    </form>

    <?php if ($output): ?>
        <hr>
        <?php echo $output; ?>
    <?php endif; ?>
</body>
</html>
