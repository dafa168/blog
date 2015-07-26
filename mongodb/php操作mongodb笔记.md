php操作mongodb笔记
-----
前段时间买了一本书 《mongodb权威指南》 学习mongodb,做了些笔记，程序这个东西不用很容易就忘了，所以把详细的操作记录下来，以便日后查阅。

大致把它分为了如下几块记录
* [Mongodb CURD 操作](https://github.com/zhengyin/blog/blob/master/code/php-mongo/curd.php)
* [Mongodb 查询](https://github.com/zhengyin/blog/blob/master/code/php-mongo/find.php)
* [Mongodb 修改器操作](https://github.com/zhengyin/blog/blob/master/code/php-mongo/modify.php)
* [Mongodb 更多有关更新的内容](https://github.com/zhengyin/blog/blob/master/code/php-mongo/update.php)
* [Mongodb 游标操作](https://github.com/zhengyin/blog/blob/master/code/php-mongo/cursor.php)

每一个对应一个类文件,你可以点以上链接查看到源码。

测试的时候根据 GET 里面的 act 执行具体的方法，具体代码如下面这样。

	$act = isset($_GET['act'])?$_GET['act']:'read';
	$o = new MongoDbCursor();
	$o->$act();

<!-- ##more## -->

目前只更新到游标部分，后续有更新我会继续在此帖补充。
