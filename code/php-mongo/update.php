<?php
/**
 * Mongodb 更多有关更新的内容
 * @date 2014-11-12
 * @author zhengyin <zhengyin.name@gmail.com>
 */
date_default_timezone_set('PRC');
header('Content-type:text/html;charset=utf-8');
ini_set('mongo.cmd','$');
echo '<pre>';
class MongodbCURD
{
	private $db;
	private $clPosts;
	private $docId = 1;	// 为了演示方便,此处写死一个文档id
	public function __construct()
	{
		$m = new MongoClient('mongodb://localhost:27017');
		$this->db = $m->mydb;	//选择库
		$this->clPosts = $this->db->posts; // 取得集合对象
	}
	/**
	 * upsert 模式，当匹配文档不存在，新建一个,存在更新
	 */
	public function upsert()
	{
		//清空这个集合
		$this->clPosts->drop();
		$match = array('docId'=>$this->docId);
		//第三个参数指定为 upsert 为 true 开始前 upsert 模式
		$result = $this->clPosts->update($match,array('$inc'=>array('views'=>1)),array("upsert" => true));
		var_dump($result);
		var_dump($this->findOne($this->docId));
		echo '<hr/>';
		$result = $this->clPosts->update($match,array('$inc'=>array('views'=>1)),array("upsert" => true));
		var_dump($result);
		var_dump($this->findOne($this->docId));
	}
	/**
	 * 使用 save 修改文档,该命令依靠 _id 文档id来辨别文档，存在更新，不存在插入
	 */
	public function save()
	{
		$doc = $this->findOne($this->docId);
		$doc['views'] = 1000;
		$doc['title'] = '使用 save 修改文档';
		$result = $this->clPosts->save($doc);
		var_dump($result);
		var_dump($this->findOne($this->docId));
	}
	/**	
	 * multiple 模式更新多个文档,更新操作默认只会更新一条记录,如果需要更新多个纪录需要单独设置
	 */
	public function multiple()
	{
		//把 views 为2的做逻辑删除
		$match = array('views'=>2);
		//第三个参数指定为 multiple 为 true 开始前 multiple 模式
		$result = $this->clPosts->update($match,array('$set'=>array('isDelete'=>1)),array('multiple'=>true));		
		var_dump($result);
	}
	/**
	 * findAndModify 更新文档的同时，返回已更新的文档
	 * [DESC]
	 * 命令简要说明：
	 * 	    findAndModify=>集合名
	 * 	    query=>检索条件
	 * 	   	sort=>排序结果条件
	 * 		update=>对所找到的文档进行更新
	 * 		remove=>对所找到的文档进行删除
	 * 		fields=>指定包含字段
	 * 		new=>布尔，true 返回更新前的文档，false 返回更新后的文档
	 * 		upsert=>是否使用 upsert 模式，默认false
	 * 
	 * 该命令参数的详细说明参考： http://docs.mongodb.org/manual/reference/method/db.collection.findAndModify/	
	 */
	public function findAndModify()
	{
		//把 views 为2的做逻辑删除
		$match = array('docId'=>$this->docId);
		//返回已更新的文档
		$result = $this->db->command(
				array(
						'findAndModify'=>'posts',
						'query'=>$match,
						'update'=>array(
							'$set'=>array(
								'isDelete'=>0
							)
						)
				)
		);
		//观察返回的 value 属性 
		var_dump($result);
	}
	
	
	/**
	 * 查询单条记录
	 * @param  $docId
	 */
	private function findOne($docId)
	{
		return $this->clPosts->findOne(array('docId'=>$docId));
	}
}
$act = isset($_GET['act'])?$_GET['act']:'read';
$o = new MongodbCURD();
$o->$act();




