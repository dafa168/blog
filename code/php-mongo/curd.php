<?php
/**
 * Mongodb CURD 操作
 * @date 2014-11-12
 * @author zhengyin <zhengyin.name@gmail.com>
 * 
 */
date_default_timezone_set('PRC');
header('Content-type:text/html;charset=utf-8');
echo '<pre>';
class MongodbCURD
{
	private $clPosts;
	private $docId = 1;	// 为了演示方便,此处写死一个文档id
	public function __construct()
	{
		$m = new MongoClient('mongodb://localhost:27017');
		$db = $m->mydb;	//选择库
		$this->clPosts = $db->posts; // 取得集合对象
	}
	/**
	 * 向集合中新增一个文档
	 */
	public function create()
	{
		$doc = array(
			'docId'=>$this->docId,
			'title'=>'Hello mongodb.',
			'content'=>'使用 php mongo 扩展进行 CURD 操作.',
			'addTime'=>date('Y-m-d H:i:s')
		);
		$result = $this->clPosts->insert($doc);
		print_r($result);
	}
	/**
	 * 更新集合
	 */  
	public function update()
	{
		$macth = array('docId'=>$this->docId);
		$docs = $this->read();
		if(!is_array($docs) || empty($docs)){
			echo 'read Fail!';
			return false;
		}
		//更新下文档标题与时间
		$data = array(
			'title'=>'你好 mongodb',
			'addTime'=>date('Y-m-d H:i:s')
		);
		$doc = current($docs);
		$doc = array_merge($doc,$data);
		$result = $this->clPosts->update($macth,$doc);
		print_r($result);
	}
	/**
	 * 读取集合数据
	 */
	public function read()
	{
		$match = array('docId'=>$this->docId);
		$result = $this->clPosts->find($match);
		$data = array();
		foreach ($result as $v)
		{
			print_r($v);
			array_push($data, $v);
		}
		return $data;
	}
	/**
	 * 删除集合的某一个元素
	 * @param  $macth 匹配条件
	 */
	public function delete()
	{
		$macth = array('docId'=>$this->docId);
		$result = $this->clPosts->remove($macth);
		print_r($result);
	}
	/**
	 * 其他操作
	 */	
	public function other()
	{
		print_r(get_class_methods($this->clPosts));
		//统计集合元素
		echo '<hr>';
		echo 'count:'.$this->clPosts->count().'<br/>';
		//取得文档id
		$cursor = $this->clPosts->find();
		//转换为数组查看文档细节
		print_r(iterator_to_array($cursor));
		//遍历
		foreach ($cursor as $id=>$value)
		{
			echo '_id:'.$id.'<br/>';
			$_id = $value['_id'];
		}
		echo '<hr/>';
		$post = $this->clPosts->find(array('_id'=>$_id));
		//转换为数组查看文档细节
		print_r(iterator_to_array($post));
		
		
	}
}
$act = isset($_GET['act'])?$_GET['act']:'read';
$o = new MongodbCURD();
$o->$act();




