<?php

echo "<h1>PHP 探针</h1>";

echo "<p><strong>PHP 版本:</strong> " . phpversion() . "</p>";

echo "<p><strong>服务器 IP 地址:</strong> " . $_SERVER['SERVER_ADDR'] . "</p>";
echo "<p><strong>服务器名称:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>服务器操作系统:</strong> " . php_uname() . "</p>";

echo "<p><strong>已加载的扩展:</strong></p>";
echo "<ul>";
foreach (get_loaded_extensions() as $extension) {
    echo "<li>" . $extension . "</li>";
}
echo "</ul>";

echo "<p><strong>PHP 配置信息:</strong></p>";
echo "<pre>";
ob_start();
phpinfo();
$phpinfo = ob_get_contents();
ob_end_clean();

// 使用正则表达式过滤掉敏感信息，比如密码等
$phpinfo = preg_replace('/password(.*?)=&nbsp;(.*?)<br \/>/i', 'password$1= ********<br />', $phpinfo);
$phpinfo = preg_replace('/(sendmail_pw)(.*?)=&nbsp;(.*?)<br \/>/i', '$1$2= ********<br />', $phpinfo);

echo $phpinfo;
echo "</pre>";

?>
