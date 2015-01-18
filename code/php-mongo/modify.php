<?php
/**
 * Mongodb 修改器操作
 * @date 2014-11-12
 * @author zhengyin <zhengyin.name@gmail.com>
 * 
 */
date_default_timezone_set('PRC');
header('Content-type:text/html;charset=utf-8');
ini_set('mongo.cmd','$');
echo '<pre>';
class MongodbModify
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
			'content'=>'使用修改器操作.',
			'views'=>1,
			'addTime'=>date('Y-m-d H:i:s')
		);
		$result = $this->clPosts->insert($doc);
		print_r($result);
	}
	/**
	 * 增加 views 的值
	 */  
	public function inc()
	{
		//更改帖子的查看数
		$macth = array('docId'=>$this->docId);
		$result = $this->read();
		echo 'views '.$result[0]['views'];
		echo '=>';
		$this->clPosts->update($macth,array('$inc'=>array('views'=>1)));
		$result = $this->read();
		echo 'views '.$result[0]['views'];
	}
	/**
	 * 设置或者更新一个新的文档属性 author
	 * [DESC]
	 *    修改器 $set 属性不存在新建，存在更新
	 */
	public function set()
	{
		$macth = array('docId'=>$this->docId);
		$this->clPosts->update($macth,array('$set'=>array('author'=>'izhengyin')));
		var_dump($this->findOne($this->docId));
		//更新现有值
		$this->clPosts->update($macth,array('$set'=>array('author'=>array('name'=>'izhengyin','email'=>'zhengyin.name@gmail.com'))));
		echo '<hr/>';
		var_dump($this->findOne($this->docId));
		//修改内嵌文档
		$this->clPosts->update($macth,array('$set'=>array('author.name'=>array('name'=>'izhengyin.com','email'=>'zhengyin.name@gmail.com'))));
		echo '<hr/>';
		var_dump($this->findOne($this->docId));
		
	}
	/**
	 * 数组修改器 push, 往数组末尾追加元素
	 */
	public function push()
	{
		$match = array('docId'=>$this->docId);
		//往帖子中追加评论
		$result = $this->clPosts->update($match,array('$push'=>array(
			'comments'=>array(
				'name'=>'小李',
				'content'=>'点个赞',
				'commentTime'=>	date('Y-m-d H:i:s')
			)
		)));
		var_dump($result);
		echo '<hr/>';
		var_dump($this->findOne($this->docId));
	}
	
	/**
	 * 使用addToSet 避免重复追加元素
	 */
	public function addToSet()
	{
		//首先使用修改器 $set 追加一个收藏属性,来记录收藏这篇帖子的用户id
		// db.posts.update({docId:1},{"$set":{collect:[1,2,3]}})
		$match = array('docId'=>$this->docId);
		//使用addToSet继续添加收藏的用户id，如果已添加了将不会在重复添加
		$result = $this->clPosts->update($match,array('$addToSet'=>array(
			'collect'=>5	
		)));
		var_dump($result);
		echo '<hr/>';
		//这条记录不会追加到数组中
		$result = $this->clPosts->update($match,array('$addToSet'=>array(
				'collect'=>5
		)));
		var_dump($result);
		echo '<hr/>';
		var_dump($this->findOne($this->docId));	
		//与$each配合使用
		$result = $this->clPosts->update($match,array('$addToSet'=>array(
			'collect'=>array(
				'$each'=>array(5,6,7,8)
			)
		)));
		echo '<hr/>';
		var_dump($this->findOne($this->docId));
	}
	/**
	 * 删除元素 pop
	 */
	public function pop()
	{
		$match = array('docId'=>$this->docId);
		$result = $this->clPosts->update($match,array(
			'$pop'=>array(
				'comments'=>-1,		//删除第一篇评论
				'collect'=>1		//删除最后一个收藏帖子用户的id		
			)
		));
		var_dump($result);
		echo '<hr/>';
		var_dump($this->findOne($this->docId));
	}
	/**
	 * 匹配删除 pull
	 */
	public function pull()
	{
		$match = array('docId'=>$this->docId);
		$result = $this->clPosts->update($match,array(
				'$pull'=>array(
						'collect'=>5		//删除收藏帖子用户id为5的这条记录
				)
		));
		var_dump($result);
		echo '<hr/>';
		var_dump($this->findOne($this->docId));
	}
	
	/**
	 * 数组定位修改器
	 */
	public function other()
	{
		$match = array('docId'=>$this->docId);
		//更新某一篇评论的修改时间
		$result = $this->clPosts->update($match,array(
			'$set'=>array(
				'comments.0.commentTime'=>date('Y-m-d H:i:s')	
			)
		));
		var_dump($result);
		echo '<hr/>';
		var_dump($this->findOne($this->docId));
		//如果我想修改“小李”评论的时间，可以这么干 [ ps: 测试把前面注释掉，免得收到干扰]
		$this->clPosts->update(array("comments.name"=>'小李'),array(
			'$set'=>array(
				'comments.$.commentTime'=>date('Y-m-d H:i:s')	//注意这个  $ ,实际上就是它匹配到的数组索引
			)
		));
		echo '<hr/>';
		var_dump($result);
		var_dump($this->findOne($this->docId));
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
$o = new MongodbModify();
$o->$act();