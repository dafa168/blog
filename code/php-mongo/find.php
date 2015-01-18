<?php
/**
 * Mongodb 查询
 * @date 2014-11-12
 * @author zhengyin <zhengyin.name@gmail.com>
 * 
 */
date_default_timezone_set('PRC');
header('Content-type:text/html;charset=utf-8');
ini_set('mongo.cmd','$');
echo '<pre>';
class MongodbFind
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
		print_r(get_class_methods($this->clPosts));
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
	/**
	 * 查询 find ,返回一个迭代器
	 */
	public function find()
	{
		//查询所有的
		$result = $this->clUsers->find();
		//查询 name = list 的				
		$result = $this->clUsers->find(array('name'=>'lisi'));
		//查询 sex=1 age=20 的
		$result = $this->clUsers->find(array('sex'=>1,'age'=>20));
		//只取得 sex age
		$result = $this->clUsers->find(array('name'=>'lisi'),array('sex'=>1,'age'=>1));
		//取得除了文档 _id 的
		$result = $this->clUsers->find(array('name'=>'lisi'),array('_id'=>0));
		var_dump(iterator_to_array($result));
	}
	/**
	 * 查询条件
	 * [DESC]
	 * 	 $lt    <
	 * 	 $lte   <=
	 * 	 $gt    >
	 *   $gte   >=
	 */
	public function findWhere()
	{
		//查询年龄小于 28 大于等于 20 的
		$result = $this->clUsers->find(array('age'=>array('$lt'=>28,'$gte'=>20)));
		//生日大于86年的
		$result = $this->clUsers->find(array('birthday'=>array('$gt'=>'1988-01-01')));
		//年龄是 26 或者 28 的
		/**
		 * $in,$nin  
		 */
		$result = $this->clUsers->find(array('age'=>array('$in'=>array(26,28))));
		//年龄是 20 或者 姓名是 lisi 的
		/**
		 * $or,$nor
		 */
		$result = $this->clUsers->find(array(
				'$or'=>array(array('age'=>20),array('name'=>'lisi'))
		));
		var_dump(iterator_to_array($result));
		
	}
	/**
	 * 正则查询
	 */
	public function regexFind()
	{
		$regex = new MongoRegex('/li/');
		//查询名字中包含 li 的
		$result = $this->clUsers->find(array('name'=>$regex));
		var_dump(iterator_to_array($result));
	}
	/**
	 * 查询数组
	 */
	public function findArr()
	{
		//修改下数据
		/*
		$interestList = array('football','basketball','swim','reading');
		$result = $this->clUsers->find();
		foreach ($result as $v){
			$_id = $v['_id'];
			$tmp = array();
			for($i=0;$i<count($interestList);$i++){
				$tmp[] = $interestList[rand(0,count($interestList)-1)];
			}
			$tmp = array_unique($tmp);
			sort($tmp);
			$this->clUsers->update(array('_id'=>$_id),array(
				'$set'=>array(
					'interest'=>$tmp
				)
			));
		}
		*/
		
		//兴趣爱好中包含 'basketball','swim' 他俩的
		$result =$this->clUsers->find(array(
			'interest'=>array(
				'$all'=>array('basketball','swim')
			)));
		
		//查询特定长度的数组
		$result =$this->clUsers->find(array(
				'interest'=>array(
						'$size'=>2
				)));
		//数组分段
		$result =$this->clUsers->find(array('name'=>'lisi'),array(
				'interest'=>array(
					'$slice'=>2   //也可以使用 -2  1,3
				)
			)
		);
		var_dump(iterator_to_array($result));
		
	}
	/**
	 * 使用 mongodb $where 查询
	 * $where 允许你传人一个js函数作为查询匹配的条件，返回 true 表示匹配 false 不匹配 
	 */	
	public function FindWhereCode()
	{
		//命令行这样写
		/**
			 db.users.find({"$where":function(){return this["age"]==26;}})
		 */
		//PHP api这样写
		$whereFn = new MongoCode('function(){
			return this["age"] == 26;
		}');
		$result = $this->clUsers->find(array('$where'=>$whereFn));
		var_dump(iterator_to_array($result));
	}
}
$act = isset($_GET['act'])?$_GET['act']:'read';
$o = new MongodbFind();
$o->$act();