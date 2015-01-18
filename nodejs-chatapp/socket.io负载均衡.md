socket.io 负载均衡
----

上一篇文章中，了解了如何使用socket.io让多个客户端互通消息，但是客户端有很多，而我们的服务端只有个，当用户量不断增大时，显然这样是不靠谱的，所有我们需要增加我们的服务端数量，如通传统的nginx + php-fpm 的架构一样，我们可以由一个 nginx 节点 接收请求，然后由多个 php 节点来处理请求，那么把这一套搬到socket.io上行不行的通呢？试一试。

首先来 对 nginx 的反向代理与负载均衡,做一个最简单的配置 ，改完后重启 nginx 使之生效


	server{
	    listen 192.168.33.10:80;
	    server_name im.izhengyin.com;
	    location / {
	         proxy_pass http://im;
	}
	
	upstream im{
	        server 127.0.0.1:3030;
	        server 127.0.0.1:5080;
	}



我们配置了一个 server 处理到 im.izhengyin.com 的连接,然后我们把请求交由负载均衡模块 im 处理,因为我们在一台机器上操作，所以我们监听两个端口来模拟上层的节点。

我们把上篇文章中的server.js 拷贝一个修改监听端口，让他们监听 3030 与 5080,就像下面这样


	server_3030.js 
	io.listen(3030);
	
	server_5080.js
	io.listen(5080);


分别在终端中启动它们

	node server_3030.js 
	
	node server_5080.js 

紧接着修改下 index.html ,并修改本地 hosts


	var socket = io('http://192.168.33.10:3000');
	
	var socket = io('http://im.izhengyin.com');
	
	本地 hosts
	
	192.168.33.10  im.izhengyin.com


一切就绪，访问 http://servername/index.html

![Mou icon](http://izhengyin.com/static/images/735808.png)

不出意外，在控制台能看见这样的信息.

这是因为客户端发送协议升级数据包并没有被 socket.io 接收到，因为数据包首先是发送给 nginx 的，然而nginx 并没有做转发，我们需要进行配置

修改 nginx 配置,改成下面这样


	server{
	   listen 192.168.33.10:80;
	   server_name im.izhengyin.com;
	   location / {
	        proxy_pass http://im;
	        proxy_redirect    off;
	        proxy_set_header X-Real-IP $remote_addr;
	        proxy_set_header Host $host;
	        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
	        proxy_http_version 1.1;
	        proxy_set_header Upgrade $http_upgrade;
	        proxy_set_header Connection "upgrade";
	   }
	}	


同时修改负载均衡配置，改为 ip_hash ,改为 ip_hash 的原因是我们需要让客户的请求都落在相通的上层节点上.


	upstream im{
	   ip_hash; 
	   server 127.0.0.1:3030;
	   server 127.0.0.1:5080;
	}	


修改完成以后重启 nginx，访问 http://servername/index.html

![Mou icon](http://izhengyin.com/static/images/554128.png)

这从可以看到连接正常了,在来测试下他们能够互通消息吗？

在 新的浏览器或者新标签页访问 http://servername/index.html

如果在单机上测试，发现一切正常，如果放在服务器上，有不同地区的用户访问进来就有问题了，观察下命令行输出,发现请求都落在了某一个 server.js 上了，写文章在本地测试的时候，我的请求落在了， server_5080.js 上，因为请求都落在了一个 server.js 所以才能互通消息，如果请求落在不同的server.js上，是不能互通的。好在socket.io提供了解决方案，也就是 socket.io-redis。

socket.io-redis github地址: https://github.com/Automattic/socket.io-redis

使用很简单,首先安装

npm install socket.io-redis

然后修改 server.js

	var io = require('socket.io')();
	var redis = require('socket.io-redis’);		    #新增
	io.adapter(redis({host:'127.0.0.1',prot:6379}));  #新增

当然这样使用的前提是你环境需要安装有 redis ，如果涉及 node.js 分部在不同的 机器上时，也可以指定一个公用的 redis-server。
