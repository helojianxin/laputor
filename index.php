<?php
/***
 *
 *  从目标豆瓣某电影页提取元素推荐三个相关电影,并提供相关电影信息
 *  推荐依据:同导演一部、同编剧一部、同主演一部
 *  相关电影信息包括:简介、有用数最高的影评、海报、中英文电影名、影片豆瓣链接、豆瓣评分数、影评总数
 *
 *  输出的关联数组,按照:豆瓣评分>影评数 的优先级排序
 *
 *  author:jessey
 *  email:helojianxin@163.com
 *
 ****/

header("Content-type: text/html; charset=utf-8");
//echo $_SERVER["HTTP_USER_AGENT"];
if(strpos($_SERVER["HTTP_USER_AGENT"],'Chrome') && strpos($_SERVER["HTTP_USER_AGENT"],'Mac OS X'))
{
    include_once "index_chrome.html";
}else{
    include_once "index.html";
}
