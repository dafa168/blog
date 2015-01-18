在Yaf中使用Yar 
----

本文提到的两个东西，都是 PHP 大神 惠新宸 的作品。

Yaf: PHP MVC框架  http://php.net/manual/zh/book.yaf.php

Yar: PHP RPC框架    http://php.net/manual/zh/book.yar.php

看着篇文章之前你需要 Yaf,Yar 有所了解。

随着网站的发展，原本的系统会逐渐的实现细分，而细分的系统之间并不是全无关联，这就涉及到各系统之前数据传输问题，Yar 就是帮助我们解决这些问题的。

虽然 Yar已经把底层数据传输给我们解决了，并且调用十分简单,但要部署上线还是需要自行处理一些问题的，所谓“师傅领进门，修行靠个人”。

大致来说我们要解决下面这三个问题：

	1.数据传输安全校验
	2.接口定义
	3.数据格式

本文是我在 Yaf 处理这些问题的一些做法，如果你阅读完本文后有不同想法可以在评论区给我留言。

先贴出目录结构方便后面对应文字描述：

	➜  izhengyin  tree
	.
	├── application
	│   ├── Bootstrap.php
	│   ├── controllers
	│   │   ├── Api.php
	│   │   ├── Error.php
	│   │   └── Index.php
	│   ├── library
	│   │   └── Api
	│   │       ├── Order.php
	│   │       └── User.php
	│   ├── models
	│   ├── modules
	│   │   └── Api
	│   │       └── controllers
	│   │           └── User.php
	│   ├── plugins
	│   │   └── Runtime.php
	│   └── views
	│       ├── error
	│       │   └── error.phtml
	│       └── index
	│           └── index.phtml
	├── conf
	│   └── application.ini
	├── libs
	│   └── IZY
	│       └── Sys
	│           ├── ApiClient.php
	│           └── ApiServer.php
	└── public
	    └── index.php



首先来定义服务端，定义之前考虑下，一个系统可能会为不同的模块单独定义不同的接口类

比如一个电商系统，有会员模块，和订单模块，所以在定义接口时，把它们区分开来分开定义，所以把每一类的接口抽象为一个Server，定义如下。

会员接口： http://servername/Api/user/
订单接口： http://servername/Api/Order/

所以在 Yaf 中需要这样写。 

Controller File:   /application/controllers/Api.php

	<?php
	class ApiController extends Yaf\Controller_Abstract
	{
		/**
		 * 会员接口
		 * @return boolean
		 */
		public function userAction()
		{
			$service = new Yar_Server(new \Api\User());
			$service->handle();
			return false;
		}
		/**
		 *	订单接口
		 *	@return boolean
		 */
		public function orderAction()
		{
			$service = new Yar_Server(new \Api\Order());
			$service->handle();
			return false;
		}
	}

以上我们的接口类文件指向了命令空间  \Api 下的  User 类 与 Order 类， 在Yaf 下需要如此定义。

User File:   /application/library/Api/User.php
	
	<?php
	/**
	 * @date 2015-1-17
	 * @author zhengyin <zhengyin.name@gmail.com>
	 * @desc 会员对外接口
	 */
	namespace Api;
	class User extends \IZY\Sys\ApiServer
	{
		/**
		 * 获取会员信息
		 * @param int $userId	用户ID
		 * return Array [会员信息]
		 */
		public function getUserInfo($userId)
		{
			
			// ... 业务处理
			
			$data = array('userName'=>'zhangsan','nickName'=>'张三','regTime'=>'2014-12-01 10:10:10');
		
			return $this->response(1,$data);
		}
		
	}

Order File: /application/library/Api/Order.php

	<?php
	/**
	 * @date 2015-1-17
	 * @author zhengyin <zhengyin.name@gmail.com>
	 * @desc 订单对外接口
	 */
	namespace Api;
	class Order extends \IZY\Sys\ApiServer
	{
		/**
		 * 获取会员信息
		 * @param int $orderId	 订单ID
		 * @param str $sign 签名
		 * return Array [订单信息]
		 */
	 	public function getOrderInfo($orderId,$sign)
		{
			//验证签名
			if(!$this->checkSign(func_get_args(),$sign)){
				return $this->response(0,array(
						'code'=>'SIGN_ERROR',
						'errMsg'=>'签名错误'
				));
			}
			
			// ... 业务处理
			
			$data = array('orderId'=>$orderId,'orderStatus'=>'success');
			return $this->response(1, $data);
		}
		
	}

这两个类文件都继承了命名空间  \IZY\Sys   ApiServer 类,这个类实际上是一些通用方法的封装

这个文件属于系统的核心文件，所以把它放在了 Yaf.ini 指定的 lib 下，这个根据具体指定而定，比如我的是:

yaf.library = /data/webroot/izhengyin/libs

内容如下:

	<?php
	namespace  IZY\Sys;
	class ApiServer
	{
		private static $signs = array(
				'sign1',
				'sign2'
				// .... 
		);
		/**
		 * 验证签名
		 * @param  $params 接口调用时的参数
		 * @param  $sign   签名
		 */
		protected function checkSign($params,$sign)
		{
			if(empty($sign)){
				return false;
			}
			ksort($params);
			$signStr = '';
			foreach($params as $key => $val)
			{
				if(empty($val) || $val == $sign) continue;
				$signStr .= $key.'='.$val.'&';
			}
			$signStr = rtrim($signStr,'&');
			foreach (self::$signs as $v){
				if(md5($signStr.$v) === $sign){
					return true;
				}
			}
			return false;
		}
		/**
		 * 返回接口处理结果
		 * @param  $status
		 * @param  $data
		 * @param  $other
		 * return  Array [格式化好了的结果]
		 */
		protected function response($status,$data,$other=array())
		{
			$response = array ();
			$response ['status'] = ( bool ) $status;
			$response ['data'] = $data;
			if (is_array ( $other ) && ! empty ( $other )) {
				foreach ( $other as $k => $v ) {
					// 附加信息不能使用的键名
					if (! in_array ( $k, array (
							'status',
							'data'
					) )) {
						$response [$k] = $v;
					}
				}
			}
			return $response;
		}
	}

简单的封装了两个方法，验证签名，和返回数据格式定义，实际上就是解决 数据安全校验，与数据格式定义问题。

完了以后写个测试文件，测试下。

test.php

	<?php
		header('Content-type:text/html;charset=utf-8'); 
		$client = new Yar_Client("http://izhengyin.com/Api/User"); 
		$result = $client->getUserInfo(10); 
		echo '<pre>'; 
		var_dump($result);
	?>

访问这个文件正常能看到  getUserInfo 的返回。

上面是 server 的一些封装，接下来看下， client 的封装

命名空间  \IZY\Sys   ApiClient 类

	<?php
	namespace  IZY\Sys;
	
	class ApiClient
	{
		private static $signs = array(
				'sign1',
				'sign2'
				// .... 
		);
		
		private $callBack;
		private $callNum=0;
		public function __construct()
		{
			
		}
		
		/**
		 * 取得签名
		 * @param  $params 接口调用时的参数
		 */
		protected function getSign($params)
		{
			ksort($params);
			$signStr = '';
			foreach($params as $key => $val)
			{
				if(empty($val)) continue;
				$signStr .= $key.'='.$val.'&';
			}
			$signStr = rtrim($signStr,'&');
			return md5($signStr.self::$signs[mt_rand(0,count(self::$signs)-1)]);
		}
		/**
		 * 调用服务端接口
		 * @param  $server		Api server
		 * @param  $api			接口
		 * @param  $params		参数
		 * @param  $openSign	开启签名
		 * @param  $callBack	回调
		 */	
		public function call($server,$api,$params,$openSign=false,$callBack=null)
		{
			if($openSign){
				$params['sign'] = $this->getSign($params);
			}
			
			if($callBack === null){
				$client = new \Yar_Client("http://izhengyin.com/Api/User");
				return call_user_method_array($api, $client, $params);
			}
			$this->callNum ++;
			$this->callBack = $callBack;
			return \Yar_Concurrent_Client::call($server,$api,$params,array($this, 'ApiClientCallBack'));
		}
		/**
		 * 执行并发调用
		 */
		public function loop()
		{
			return \Yar_Concurrent_Client::loop(); 
		}
		/**
		 * 并发调用回调
		 * @param  $retval
		 * @param  $callinfo
		 */
		public function ApiClientCallBack($retval,$callinfo)
		{
			if($callinfo === null){
				return $this->callBack($retval,$callinfo);
			}
			static $data = array();
			$data[$callinfo['method']] = $retval;
			if(count($data) == $this->callNum){
				$fn = $this->callBack;
				return $fn($data,$callinfo);
			}
		}
	}

这个类封装了，取得签名的方法，已经并发调用的方法,使用时类似于这样：

单个接口调用，并且不进行加密：

	<?php 
	
	class UserController extends Yaf\Controller_Abstract 
	{ 
		public function getUserInfoAction() 
		{ 
		    $apiClinet = new \IZY\Sys\ApiClient();
		    $userInfo = $apiClinet->call("http://izhengyin.com/Api/User", 'getUserInfo', array(10));
		    var_dump($userInfo );
		    return false; 
		} 
	}
	
输出：

array(2) { ["status"]=> bool(true) ["data"]=> array(3) { ["userName"]=> string(8) "zhangsan" ["nickName"]=> string(6) "张三" ["regTime"]=> string(19) "2014-12-01 10:10:10" } 



多个接口调用并且却 getOrderInfo 进行加密：

	<?php 
	
	class UserController extends Yaf\Controller_Abstract 
	{ 
		public function getUserInfoAction() 
		{ 
			$apiClinet = new \IZY\Sys\ApiClient(); 
			function callback($data,$callinfo){ 
				var_dump($data); 
			} 
			$apiClinet->call("http://izhengyin.com/Api/User", 'getUserInfo', array(10),false,'callback'); 
			$apiClinet->call("http://izhengyin.com/Api/Order", 'getOrderInfo', array(10),true,'callback'); 
			$apiClinet->loop(); 
			return false; 
		} 
	}

输出：

array(2) { 
    ["getOrderInfo"]=> array(2) { ["status"]=> bool(true) ["data"]=> array(2) { ["ord erId"]=> int(10) ["orderStatus"]=> string(7) "success" }
     } 
    ["getUserInfo"]=> array(2) { ["status"]=> bool(true) ["data"]=> array(3) { ["userName"]=> string(8) "zhangsan" ["nickName"]=> string(6) "张三" ["regTime"]=> string(19) "2014-12-01 10:10:10" }
     }
 }


本文代码下载： <a href="http://izhengyin.com/downloads/izhengyin.zip">izhengyin.zip</a> 