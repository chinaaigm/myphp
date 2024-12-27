<?php
// 定义一个函数用于安全地处理 URL
function sanitizeUrl($url) {
    // 首先使用 filter_var 验证 URL 格式
    $filteredUrl = filter_var($url, FILTER_VALIDATE_URL);

    if ($filteredUrl === false) {
        return ''; // 如果验证失败，返回空字符串
    }

    // 进一步检查 URL 的协议，只允许 http 和 https
    $protocol = parse_url($filteredUrl, PHP_URL_SCHEME);
    if ($protocol !== 'http' && $protocol !== 'https') {
        return ''; // 如果协议不是 http 或 https，返回空字符串
    }

    return $filteredUrl; // 返回过滤后的 URL
}

$targetUrl = '';
$output = '';
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $targetUrl = $_POST['url'];
    $targetUrl = sanitizeUrl($targetUrl);  // 清理用户输入的 URL

    if (empty($targetUrl)) {
        $error = "无效的 URL，请输入有效的 http 或 https URL。";
    } else {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 启用 SSL 验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 启用 SSL 验证
      
         // 转发请求头
        $requestHeaders = getallheaders();
        $forwardHeaders = [];
        foreach ($requestHeaders as $key => $value) {
            if($key != 'Host' && $key != 'Connection' ){
               $forwardHeaders[] = "$key: $value";
            }
           
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);


        // 处理 POST 数据
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
            $postData = http_build_query($_POST);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);

        if ($response === false) {
          $error = "CURL 错误: " . curl_error($ch);
         } else {
        // 获取 HTTP 状态码
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // 获取 Header 大小
         $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

         // 获取响应头
        $header = substr($response, 0, $headerSize);

         // 获取响应内容
         $body = substr($response, $headerSize);
            
         // 发送响应头
         $headers = explode("\r\n", $header);
         foreach ($headers as $h) {
          if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $h, $match)) {
             if ($match[1] == 100) {
                continue;
              }
           }
           if (!empty($h) && strpos($h, 'Transfer-Encoding') === false )
           {
            header($h);
           }
          }
            $output = $body;
        }
        curl_close($ch);
    }
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
