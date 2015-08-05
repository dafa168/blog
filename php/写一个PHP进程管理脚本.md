写一个PHP进程管理脚本
---

进程管理脚本有Shell干嘛非要去折腾个PHP呢，这个问题让我来回答我会这样说。

用PHP来写很爽好吗，而且如果你的应用程序是PHP，这样会更灵活。总该为折腾找个理由，以上便是。

废话不多说，咱开始. 首先准备一些基础知识

###Shell部分

	1.如何后台运行一个PHP脚本，格式如下  "PHP执行路径  脚本路径  >> 日志路径  & " 如：/opt/app/php/bin/php worker.php >> /tmp/log.log & 
	2.查看进程："ps aux | grep worker.php | grep -v grep"   (跟着上面的例子)
	3.取得进程PID: "ps aux | grep worker.php | grep -v grep | awk '{print $2}'" 
	4.杀死一个进程: kill -9 {Pid}

###PHP部分

	1.PHP执行Shell函数： shell_exec($cmd);  $cmd 就是Shell命令 返回Shell输出流
	2.直接在PHP脚本中执行Shell: `ps aux | grep worker.php`  返回Shell输出流
<!--izhengyin@more-->
有了上面这些基础，下面的代码就比较简单了

###程序代码部分 WorkerAdmin.php


```

	<?php
	/**
	 * @date 2015-07-17
	 * @author zhengyin <zhengyin.name@gmail.com>
	 * PHP 进程管理脚本
	 */
	class WorkerAdmin{
		
		const PHP_BIN = '/opt/app/php/bin/php';
		
		private static $process;
		
		private static $log;
		
		private static $num;
		
		private static $cmds = array('start','restart','stop');
		/**
		 * @param Array $argv
		 */
		public static function main($argv){
			
			self::$process = isset($argv[1])?$argv[1]:'';
		
			$cmd = isset($argv[2])?$argv[2]:'';
			
			self::$num = isset($argv[3])?intval($argv[3]):0;
	
			self::$log = isset($argv[4])?$argv[4]:'/dev/null';	//默认丢弃日志
			//脚本不存在，或者不可执行
			if(!file_exists(self::$process) || !is_executable(self::$process)){
				echo self::$process." not exists\n";
				echo chr(7);
				exit;
			}
			//cmd 不正确
			if(!in_array($cmd, self::$cmds)){
				echo "usage: ".self::$process." ".implode('|', self::$cmds)." [num]\n";
				echo chr(7);
				exit;
			}
			
			self::$cmd();
		}
		/**
		 * 启动
		 */
		public static function start(){
			if(self::$num<1){
				echo "start num is invalid\n";
				echo chr(7);
				exit;
			}else{
				for ($i=0;$i<self::$num;$i++){
					self::runWorker();
				}
				echo "start done\n";
			}
		}
		/**
		 * 重启
		 */
		public static function restart(){
			$count = 0;
			$pids = self::getWorkerPids();
			for($i=0;$i<count($pids);$i++){
				self::killWorker($pids[$i]);
				//杀掉老进程后，新启一个进程
				$count++;
				self::runWorker();
				//缓冲1秒，避免瞬间杀死worker后，照成脚本无法工作
				sleep(1);
			}
			//最后检查下，是否达到了需要的启动数量
			if($count <self::$num){
				for($n=0;$n<self::$num-$count;$n++){
					self::runWorker();
				}
			}
			echo "restart done\n";
		}
		/**
		 * 停止
		 */
		public static function stop(){
			$pids = self::getWorkerPids();
			for ($i=0;$i<count($pids);$i++){
				self::killWorker($pids[$i]);
				//缓冲1秒，避免瞬间杀死worker后，照成脚本无法工作
				sleep(1);	
			}
			echo "stop done\n";
		}
		/**
		 * 运行一个 worker
		 */
		private static function runWorker(){
			$cmd = sprintf("%s %s >> %s &",self::PHP_BIN,self::$process,self::$log);
			echo $cmd.PHP_EOL;
			shell_exec($cmd);
		}
		/**
		 * 杀死一个 worker
		 */
		private static function killWorker($pid){
			`kill -9 $pid`;
			echo "kill {$pid} done \n";
		}
		/**
		 * 获取正在运行的worker pid 
		 */
		private static function getWorkerPids(){
			
			$cmd = 'ps aux | grep '.basename(self::$process).'| grep -v grep | grep -v '.basename(__FILE__).' | awk \'{print $2}\'';
			$result = shell_exec($cmd);
			$pids = array_filter(explode("\n", $result));
			return $pids;
		}
	}
	WorkerAdmin::main($argv);

```

在同级目录建立一个测试文件： worker.php 代码如下

```

	<?php
	while (true){
		echo date('Y-m-d H:i:s').PHP_EOL;
		sleep(3);
	}

```

如果要对该 Worker 进行管理命令如下:

	php WorkerAdmin.php worker.php start 10  启动10个进程，无日志
	php WorkerAdmin.php worker.php restart  重启
	php WorkerAdmin.php worker.php stop  停止

实际上如果你的守护进程和你的应用程序息息相关,完全可以走配置的文件进行更好的管理,这需要修改下 WorkerAdmin

###修改后的 WorkerAdmin.php

```

	<?php
	/**
	 * @date 2015-07-17
	 * @author zhengyin <zhengyin.name@gmail.com>
	 * PHP 进程管理脚本
	 */
	class WorkerAdmin{
		
		const PHP_BIN = '/opt/app/php/bin/php';
		
		private static $workerConfig = array(
				'worker'=>array(
					'process'=>'/data/webroot/izhengyin/public/test/shell/worker.php',
					'num'=>10,
					'log'=>'/tmp/worker.log'		
				)
		);
		
		private static $process;
		
		private static $log;
		
		private static $num;
		
		private static $cmds = array('start','restart','stop');
		/**
		 * @param Array $argv
		 */
		public static function main($argv){
			
			$name = isset($argv[1])?$argv[1]:'';
			$cmd = isset($argv[2])?$argv[2]:'';
			
			//worker不存在
			if(!isset(self::$workerConfig[$name])){
				echo 'Worker:'.$name." not exists\n";
				echo chr(7);
				exit;
			}
			//取得相应的进程配置
			self::$process = self::$workerConfig[$name]['process'];
			self::$num = self::$workerConfig[$name]['num'];
			self::$log = self::$workerConfig[$name]['log'];
			
			//脚本不存在，或者不可执行
			if(!file_exists(self::$process) || !is_executable(self::$process)){
				echo self::$process." not exists\n";
				echo chr(7);
				exit;
			}
			//cmd 不正确
			if(!in_array($cmd, self::$cmds)){
				echo "usage: ".self::$process." ".implode('|', self::$cmds)." [num]\n";
				echo chr(7);
				exit;
			}
			
			self::$cmd();
		}
		/**
		 * 启动
		 */
		public static function start(){
			if(self::$num<1){
				echo "start num is invalid\n";
				echo chr(7);
				exit;
			}else{
				for ($i=0;$i<self::$num;$i++){
					self::runWorker();
				}
				echo "start done\n";
			}
		}
		/**
		 * 重启
		 */
		public static function restart(){
			$count = 0;
			$pids = self::getWorkerPids();
			for($i=0;$i<count($pids);$i++){
				self::killWorker($pids[$i]);
				//杀掉老进程后，新启一个进程
				$count++;
				self::runWorker();
				//缓冲1秒，避免瞬间杀死worker后，照成脚本无法工作
				sleep(1);
			}
			//最后检查下，是否达到了需要的启动数量
			if($count <self::$num){
				for($n=0;$n<self::$num-$count;$n++){
					self::runWorker();
				}
			}
			echo "restart done\n";
		}
		/**
		 * 停止
		 */
		public static function stop(){
			$pids = self::getWorkerPids();
			for ($i=0;$i<count($pids);$i++){
				self::killWorker($pids[$i]);
				//缓冲1秒，避免瞬间杀死worker后，照成脚本无法工作
				sleep(1);	
			}
			echo "stop done\n";
		}
		/**
		 * 运行一个 worker
		 */
		private static function runWorker(){
			$cmd = sprintf("%s %s >> %s &",self::PHP_BIN,self::$process,self::$log);
			echo $cmd.PHP_EOL;
			shell_exec($cmd);
		}
		/**
		 * 杀死一个 worker
		 */
		private static function killWorker($pid){
			`kill -9 $pid`;
			echo "kill {$pid} done \n";
		}
		/**
		 * 获取正在运行的worker pid 
		 */
		private static function getWorkerPids(){
			
			$cmd = 'ps aux | grep '.basename(self::$process).'| grep -v grep | grep -v '.basename(__FILE__).' | awk \'{print $2}\'';
			$result = shell_exec($cmd);
			$pids = array_filter(explode("\n", $result));
			return $pids;
		}
	}
	WorkerAdmin::main($argv);

```

下面你就可以通过如下命令管理Worker了:

	php WorkerAdmin.php worker start 启动worker
	php WorkerAdmin.php worker restart  重启
	php WorkerAdmin.php worker stop  停止

程序里面的 WorkerConfig 你完全可以把它摘除到外部，比如你使用框架，你可以把摘除到你的应用配置里，这里就不展开讨论了。

So , 这篇文章就到此为止。 
