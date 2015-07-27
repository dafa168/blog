写一个PHP进程管理脚本.md
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
<!-- ##more## -->
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


