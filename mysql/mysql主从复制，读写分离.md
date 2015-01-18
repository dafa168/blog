mysql 主从复制，读写分离
-----

主从复制简单很好理解，简单来说就是把一台机器上的数据同步到另一台机器上面去，而读写分离也就是我们在操作mysql的时候，写的操作与读的操作不在作用于同一台数据库上，降低单机压力。

首先我在本地装了三台虚拟机用于测试,它们的主机名和IP地址如下。

     master   192.168.0.121        [ubuntu]
     slave1   192.168.0.131        [debian]
     slave2   192.168.0.141        [debian]

myql版本： 5.5

以下关于mysql复制的一些内容摘自《高性能mysql》 一书，如果你对细节感兴趣，你可购买此书进行查阅。

###MYSQL主从复制

#####复制解决的问题

	1.数据分布
	2.负载均衡
	3.备份
	4.高可用性和故障切换
	5.mysql升级测试

#####复制如何工作
    1.在主库上把数据更改记录到二进制日志(Binary Log)中(这些记录被称为二进制日志事件)。
    2.备库将主库上的日志复制到自己的中继日志(Relay Log)中。
    3.备库读取中继日志中的事件，将其重放到备库数据之上。

#####复制配置

######1.创建复制账号
在主库上创建复制账号,在mysql终端中键入如下命令即可
<pre>
</code>
GRANT REPLICATION SLAVE  , REPLICATION  CLIENT ON *.* TO slave1 @"192.168.0.131"  IDENTFIED BY "password" ;
</code>
</pre>
配置完成后在 从库上 连接下主库，避免后面出现一些不必要的麻烦,如同下面这样。
            
  	mysql -h192.168.0.121 -uslave1 -p"password"
            
如果连接不成功，检查下主服务器上是否开放了对外连接，通常是防火墙设置问题。

######2.配置主库和备库
<pre>
	<code>       
主库配置：                        
[mysqld]
server-id = 121            //保证唯一性，这里我取 ip 的最后几位
log_bin = /data/mysql/logs/mysql-bin  // 二进制日志存放位置
    </code>
</pre>    
 配置完成后从启主库，进入终端键入命令  
 
 	mysql> show master status;
	+------------------+----------+--------------+------------------+
	| File             | Position | Binlog_Do_DB | Binlog_Ignore_DB |
	+------------------+----------+--------------+------------------+
	| mysql-bin.000011 |     236 |              |                  |
	+------------------+----------+--------------+------------------+                 
	留意下 File  ,Position 一会要用到，其中 File 是二进制日志名， Posttion 是数据点的位置

     			                
######从库配置:
<pre>
	<code>       
主库配置：                        
[mysqld]
server-id = 131
log_bin = /data/mysql/logs/mysql-bin
relay_log = /data/mysql/logs/mysql-relay-bin    //中继日志
log_slave_updates = 1    //允许备库将其重放的事件也记录到自身二进制日志中
read_only = 1        //只读
    </code>
</pre>  
            
######3.启动复制
从库配置完后重启下mysql，进入终端[从库],键入如下命令:
<pre><code>
mysql> CHANGE MASTER TO MASTER_HOST='192.168.0.121',
    -> MASTER_USER='slave1',
    -> MASTER_PASSWORD='password',
    -> MASTER_LOG_FILE='mysql-bin.000011',
    -> MASTER_LOG_POS=0;
 #MASTER_LOG_FILE 指定主库二进制日志文件
 #MASTER_LOG_POS 指定复制开始的位置
</code></pre>
这一步完成后，下面这个命令查看从库状态
<pre><code>
mysql> SHOW SLAVE STATUS\G;
*************************** 1. row ***************************
       Slave_IO_State:
        ..........
       Slave_IO_Running: No
       Slave_SQL_Running: No
       ..........
</code></pre>
这几个地方显示着我们的复制还未开始,接下来启动复制.
<pre><code>
mysql> START SLAVE;
mysql> SHOW SLAVE STATUS\G;
*************************** 1. row **************************
 Slave_IO_State: Waiting for master to send event
 Slave_IO_Running: Yes
 Slave_SQL_Running: Yes
</code></pre>
这样复制就开始运行了 ,创建一些数据测试下，下面的SQL就不解释了。
主库：
<pre><code>
mysql> create database test charset utf8;
Query OK, 1 row affected (0.00 sec)

mysql> use test
Database changed
mysql> create table user (uid int unsigned not null primary key auto_increment,name char(40) not null default '');
Query OK, 0 rows affected (0.01 sec)

mysql> insert into user set name='szws01';
Query OK, 1 row affected (0.00 sec)

mysql> select * from user;
+-----+--------+
| uid | name   |
+-----+--------+
|   1 | szws01 |
+-----+--------+
1 row in set (0.00 sec)
</code></pre>
从库：
<pre><code>
mysql> use test
Reading table information for completion of table and column names
You can turn off this feature to get a quicker startup with -A

Database changed
mysql> show tables;
+----------------+
| Tables_in_test |
+----------------+
| user           |
+----------------+
1 row in set (0.00 sec)

mysql> select * from user;
+-----+--------+
| uid | name   |
+-----+--------+
|   1 | szws01 |
+-----+--------+
1 row in set (0.00 sec)
</code></pre>

自此主从就配置完成了，当然这个配置很简单，这是基于主服务器，与从服务器都是新安装的mysql来的。    

###使用Amoeba做读写分离

######AMOEBA是什么
   • 专注于分布式数据库代理层（ Database Proxy） 开发
   
   • 座落与 Client、 service之间,对客户端透明
   
   • 具有负载均衡、高可用性、可路由相关请求到目标数据库、 可并发请求多台数 据库合并结果 
   
   • 通过Amoeba你能够完成多数据源的高可用、负载均衡、数据切片的功能
   
######读写分离结构图

![Mou icon](http://izhengyin.com/static/images/01.jpg)

######准备工作
    
        在线文档: http://docs.hexnova.com/amoeba/       
        下载地址：http://sourceforge.net/projects/amoeba/files/
        本文使用的版本是： amoeba-mysql-binary-2.2.0[点击可下载]
        使用之前需要安装 java jdk ,本文使用的是 jdk1.7
        Amoeba安装很简单
                1.下载对应的版本
                2.解压到安装目录,本文解压到了 /opt/app/amoeba
                3.进入   /opt/app/amoeba/bin  执行命令:
                     ➜  bin  pwd
                     /opt/app/amoeba/bin
                     ➜  bin  ./amoeba
                     amoeba start|stop
                     看到以上信息就成了，这里我为了方便把该目录加到环境变量PATH里：
                     vim ~/.zshrc
                     export PATH="$JAVA_HOME/bin:/usr/local/sbin:/opt/app/amoeba/bin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/local/mysql/bin" 
                     source ~/.zshrc     #根据你的shell而定
######Amoeba主从配置
        Amoeba 主要有三个配置文件
                1.dbServers.xml            //配置一些集群上mysql的数据库连接参数
                2.amoeba.xml              //Amoeba自身的配置，主机，端口，账号，密码,以及如何加载server,rule等。
                3.rule.xml                     //路由配置，主要是数据切片的一些配置，读写分离这块可以不用。
                配置文件放在   /opt/app/amoeba/conf/ 下。
               xml的配置文件看起来有些乱，看横线分割部分吧。

dbServers.xml 配置


		<?xml version="1.0" encoding="gbk"?> 
		
		<!DOCTYPE amoeba:dbServers SYSTEM "dbserver.dtd"> 
		<amoeba:dbServers xmlns:amoeba="http://amoeba.meidusa.com/"> 
		<dbServer name="abstractServer" abstractive="true"> 
		<factoryConfig class="com.meidusa.amoeba.mysql.net.MysqlServerConnectionFactory"> 
		<property name="manager">${defaultManager}</property>
		<property name="sendBufferSize">64</property>
		<property name="receiveBufferSize">128</property>
		
		<!-- mysql 连接主配置  -->
		
		<property name="port">3306</property>
		<property name="schema">test</property>
		<!-- mysql user -->
		<property name="user">root</property>
		<!-- mysql password -->
		<property name="password">password</property>
		
		
		
		</factoryConfig> 
		<poolConfig class="com.meidusa.amoeba.net.poolable.PoolableObjectPool"> 
		<property name="maxActive">500</property>
		<property name="maxIdle">500</property>
		<property name="minIdle">10</property>
		<property name="minEvictableIdleTimeMillis">600000</property>
		<property name="timeBetweenEvictionRunsMillis">600000</property>
		<property name="testOnBorrow">true</property>
		<property name="testOnReturn">true</property>
		<property name="testWhileIdle">true</property>
		</poolConfig> 
		</dbServer> 
		
		<!-- 单个 server 配置 ,账号密码继承了主配置 -->
		<dbServer name="master" parent="abstractServer"> 
		<factoryConfig>
		<property name="ipAddress">192.168.0.121</property>
		</factoryConfig>
		</dbServer> 
		<dbServer name="slave1" parent="abstractServer">
		<factoryConfig>
		<property name="ipAddress">192.168.0.131</property>
		</factoryConfig>
		</dbServer> 
		<dbServer name="slave2" parent="abstractServer">
		<factoryConfig>
		<property name="ipAddress">192.168.0.141</property>
		</factoryConfig>
		</dbServer>
		
		<!-- 单独为从库进行区分,按照 ROUNDROBIN  做负载均衡-->
		<dbServer name="virtualSlave" virtual="true"> 
		<poolConfig class="com.meidusa.amoeba.server.MultipleServerPool">
		<!-- Load balancing strategy: 1=ROUNDROBIN , 2=WEIGHTBASED , 3=HA-->
		<property name="loadbalance">1</property>
		<!-- Separated by commas,such as: server1,server2,server1 -->
		<property name="poolNames">slave1,slave2</property>
		</poolConfig>
		</dbServer>
		</amoeba:dbServers> 

amoeba.xml 配置

		<!-- service class must implements com.meidusa.amoeba.service.Service -->
		<service name="Amoeba for Mysql" class="com.meidusa.amoeba.net.ServerableConnectionManager">
		        <!--- 连接的ip和端口  --->
		
		        <!-- port -->
		        <property name="port">8066</property>
		        <!-- bind ipAddress -->
		        <property name="ipAddress">127.0.0.1</property>
		
		
		        <property name="manager">${clientConnectioneManager}</property>
		
		        <property name="connectionFactory">
		                <bean class="com.meidusa.amoeba.mysql.net.MysqlClientConnectionFactory">
		                        <property name="sendBufferSize">128</property>
		                        <property name="receiveBufferSize">64</property>
		                </bean>
		        </property>
		
		        <property name="authenticator">
		                <bean class="com.meidusa.amoeba.mysql.server.MysqlClientAuthenticator">
		
		       <!--- 连接的用户与密码 --->
		                        <property name="user">root</property>
		
		                        <property name="password">123456</property>
		
		                        <property name="filter">
		                                <bean class="com.meidusa.amoeba.server.IPAccessController">
		                                        <property name="ipFile">${amoeba.home}/conf/access_list.conf</property>
		                                </bean>
		                        </property>
		                </bean>
		        </property>
		
		</service>

		.................................
		
		<queryRouter class="com.meidusa.amoeba.mysql.parser.MysqlQueryRouter">
		
		        <property name="ruleLoader">
		                <bean class="com.meidusa.amoeba.route.TableRuleFileLoader">
		                        <property name="ruleFile">${amoeba.home}/conf/rule.xml</property>
		                        <property name="functionFile">${amoeba.home}/conf/ruleFunctionMap.xml</property>
		                </bean>
		        </property>
		
		        <property name="sqlFunctionFile">${amoeba.home}/conf/functionMap.xml</property>
		        <property name="LRUMapSize">1500</property>
		        <property name="defaultPool">master</property>
		        <!-- 读写配置 -->
		        <property name="writePool">master</property>
		        <property name="readPool">virtualSlave</property>
		
		        <property name="needParse">true</property>
		</queryRouter>

 启动Amoeba ：
 
 	➜  conf  amoeba start
  	log4j:WARN log4j config load completed from file:/opt/app/amoeba/conf/log4j.xml
      2015-01-11 15:19:17,732 INFO  context.MysqlRuntimeContext - Amoeba for Mysql current versoin=5.1.45-mysql-amoeba-proxy-2.2.0
      log4j:WARN ip access config load completed from file:/opt/app/amoeba/conf/access_list.conf
      2015-01-11 15:19:18,053 INFO  net.ServerableConnectionManager - Amoeba for Mysql listening on /127.0.0.1:8066.
      2015-01-11 15:19:18,059 INFO  net.ServerableConnectionManager - Amoeba Monitor Server listening on /127.0.0.1:894.
      
打开一个新终端,连接Amoeba:


	➜  amoeba  mysql -h127.0.0.1 -uroot -p123456 -P8066
       Welcome to the MySQL monitor.  Commands end with ; or \g.
       Your MySQL connection id is 1226617162
       Server version: 5.1.45-mysql-amoeba-proxy-2.2.0 Source distribution

       Copyright (c) 2000, 2014, Oracle and/or its affiliates. All rights reserved.

       Oracle is a registered trademark of Oracle Corporation and/or its
       affiliates. Other names may be trademarks of their respective
       owners.

       Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

       mysql>
       
看起来和正常进入mysql一样，你可以通过    Server version 区分它与mysql，下面做一些操作测试，怎样使用mysql怎样使用它。

	mysql> show databases;
        +--------------------+
        | Database           |
        +--------------------+
        | information_schema |
        | logs               |
        | mysql              |
        | performance_schema |
        | test               |
        +--------------------+
        5 rows in set (0.00 sec)
        mysql> use test
        Database changed
        mysql> select * from user;
        +-----+--------+
        | uid | name   |
        +-----+--------+
        |   1 | szws01 |
        +-----+--------+
        1 row in set (0.00 sec)
        mysql>
        
        

可以看到和我们之前数据是一样的，接下来测试下读写分离。

1.停掉从库 ,在 slave1 slave2 终端键入:   

        STOP SLAVE;                  
               
2.在Amoeba 终端写入数据,然后在查询.

		mysql> insert into user set name ='Amoeba';
		Query OK, 1 row affected (0.00 sec)
		
		mysql> select * from user;
		+-----+--------+
		| uid | name   |
		+-----+--------+
		|   1 | szws01 |
		+-----+--------+
		1 row in set (0.00 sec)

查询发现刚才的数据找不到了，别着急我们去主库找找。

	➜  amoeba  mysql -uroot -p                        
	Enter password:
	Welcome to the MySQL monitor.  Commands end with ; or \g.
	Your MySQL connection id is 20
	Server version: 5.5.41-log Source distribution

	Copyright (c) 2000, 2014, Oracle and/or its affiliates. All rights reserved.

	Oracle is a registered trademark of Oracle Corporation and/or its
	affiliates. Other names may be trademarks of their respective
	owners.

	Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

	mysql> use test
	Reading table information for completion of table and column names
	You can turn off this feature to get a quicker startup with -A

	Database changed
	mysql> select * from user;
	+-----+--------+
	| uid | name   |
	+-----+--------+
	|   1 | szws01 |
	|   2 | Amoeba |
	+-----+--------+
	2 rows in set (0.00 sec)

因为我们刚才关闭了主从，所以主库的数据并没有同步到从库，然而我们配置Amoeba的时候，设置了读是从 slave1,slave2上面读的，
所以没有查询到是正常的，接下来打开主从，slave1,slave2 执行命令:

 	START SLAVE;
 
然后在 Amoeba 终端中查询：

 	mysql> select * from user;
 	+-----+--------+
	 | uid | name   |
	 +-----+--------+
	 |   1 | szws01 |
	 |   2 | Amoeba |
	 +-----+--------+
	 2 rows in set (0.00 sec)

 现在有结果了，至此这篇文章就结束了，总体来说，不论是mysql主从还是Amoeba配置都是比较简单的。