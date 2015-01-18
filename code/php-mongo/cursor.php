<?php
/**
 * Mongodb 游标操作
 * @date 2014-11-12
 * @author zhengyin <zhengyin.name@gmail.com>
 */
date_default_timezone_set('PRC');
header('Content-type:text/html;charset=utf-8');
ini_set('mongo.cmd','$');
echo '<pre>';
class MongoDbCursor
{
	private $clUsers;
	public function __construct()
	{
		$m = new MongoClient('mongodb://localhost:27017');
		$db = $m->mydb;	//选择库
		$this->clUsers = $db->users; // 取得集合对象
	}
	public function help()
	{
		print_r(get_class_methods($this->clUsers->find()));
	}
	
	/**
	 * 插人多条纪录用于测试
	 */
	public function create()
	{
		$data = array(
				array( 'name'=>'zhangsan','age'=>20,'sex'=>1,'birthday'=>'1994-10-10'),
				array( 'name'=>'lisi','age'=>20,'sex'=>0,'birthday'=>'1994-9-9'),
				array( 'name'=>'wangwu','age'=>26,'sex'=>0,'birthday'=>'1988-8-8'),
				array( 'name'=>'songliu','age'=>28,'sex'=>0,'birthday'=>'1986-7-7'),
				array( 'name'=>'heqi','age'=>30,'sex'=>1,'birthday'=>'1984-6-6')
		);
		$result = $this->clUsers->batchInsert($data);
		var_dump($result);
	}
	
	public function all()
	{
		// 执行顺序  sort=>skip=>limit 
		$result = $this->clUsers->find()->limit(3)->skip(1)->sort(array('age'=>-1));
		//高级查询选项
		
		var_dump(iterator_to_array($result));
	}
	
	
	
	
}
$act = isset($_GET['act'])?$_GET['act']:'read';
$o = new MongoDbCursor();
$o->$act();