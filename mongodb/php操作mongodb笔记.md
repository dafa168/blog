php操作mongodb笔记
-----



大致把它分为了如下几块记录

* <a href="https://github.com/zhengyin/blog/blob/master/code/php-mongo/curd.php" target="_blank">Mongodb CURD 操作</a>
* <a href="https://github.com/zhengyin/blog/blob/master/code/php-mongo/find.php" target="_blank">Mongodb 查询</a>
* <a href="https://github.com/zhengyin/blog/blob/master/code/php-mongo/modify.php" target="_blank">Mongodb 修改器操作</a>
* <a href="https://github.com/zhengyin/blog/blob/master/code/php-mongo/update.php" target="_blank">Mongodb 更多有关更新的内容</a>
* <a href="https://github.com/zhengyin/blog/blob/master/code/php-mongo/cursor.php" target="_blank">Mongodb 游标操作</a>


每一个对应一个类文件,你可以点以上链接查看到源码。

测试的时候根据 GET 里面的 act 执行具体的方法，具体代码如下面这样。

	$act = isset($_GET['act'])?$_GET['act']:'read';
	$o = new MongoDbCursor();
	$o->$act();

<!--izhengyin@more-->

目前只更新到游标部分，后续有更新我会继续在此帖补充。
