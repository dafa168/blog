关于 node.js与websocket
-----

很早就听说websocket 这个东西，之前在项目中也用过，不过当时服务端是 C++ 写的。

首先来看看webSocket的 API

	var ws = new WebSocket("ws://192.168.33.10:3000");  
	
	//建立连接完成后触发
	ws.onopen = function(){
		console.log('connect ok.');
		ws.send('Hello server.');
	};
	
	//接收到新消息时触发
	ws.onmessage = function(socket){
		console.log(socket.data);
	}
	
	//服务端关闭连接的时候触发
	ws.onclose = function(){
		console.log('server close.')
	}
	
	//发送错误时触发
	ws.onerror = function(e){
		console.log(e);
	}
	
咋一看，这个API和上篇文章中的tcp客户端API很像，这会脑子里面冒出一个想法，能不能够直接连接TCP的服务端呢，就像TCP客户端的一样？
试一试，
写个index.html文件,代码如下
index.html

	<!DOCTYPE html>
	<html lang="zh-CN">
	<meta charset='utf-8'>
	<head>
	<title>WebSocket Test</title>
	<script type="text/javascript">
	
	var ws = new WebSocket("ws://192.168.33.10:3000");  
	
	ws.onopen = function(){
		console.log('connect ok.');
		ws.send('Hello server.');
	};
	
	ws.onmessage = function(socket){
		console.log(socket.data);
	}
	
	ws.onclose = function(){
		console.log('server close.')
	}
	
	ws.onerror = function(e){
		console.log(e);
	}
	
	</script>
	</head>
	</body>
	</html>

然后我们在服务端启动 server.js ,代码与 [使用node.js构建tcp服务] 一样

在浏览器访问这个文件,浏览器控制台输出如下内容，错误很清晰连接不上

![Mou icon](http://izhengyin.com/static/images/944649.png)


那么服务端，是否接收到这个请求了呢？

下面是服务端控制台的输出

	➜  tcp  node server.js 
	listen:3000
	Clinet connect ..
	==GET / HTTP/1.1
	Host: 192.168.33.10:3000
	User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:32.0) Gecko/20100101 Firefox/32.0
	Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
	Accept-Language: zh-cn,zh;q=0.8,en-us;q=0.5,en;q=0.3
	Accept-Encoding: gzip, deflate
	Sec-WebSocket-Version: 13
	Origin: http://izhengyin.com
	Sec-WebSocket-Key: GidjCZdGeqx9lMs0IaSbMA==
	Connection: keep-alive, Upgrade
	Pragma: no-cache
	Cache-Control: no-cache
	Upgrade: websocket

可以看到服务端是接收到客户端的请求了，那么为什么客户端连接中断了呢，其实这个问题和为什么通过http协议才能访问我们的网站一样,所以到这里应该不难猜出是协议的原因。 

在深入浅出node.js 一书中 第7章 网络编程 7.4节 构建 websocket 服务   对websocket协议有详细讲解，有兴趣的同学可以看看。

下面这篇文章也是关于 node.js 如何实现 websocket 协议的
http://www.cnblogs.com/ambar/archive/2011/06/30/node-websocket-server.html
这里就不在贴出相关代码。

在node.js 中也有别人写好的处理 websocket 协议的模块，比如它 https://github.com/Worlize/WebSocket-Node  .

首先安装下


	npm install websocket
	
直接把示例代码拿过来
server.js

	#!/usr/bin/env node
	var WebSocketServer = require('websocket').server;
	var http = require('http');
	
	var server = http.createServer(function(request, response) {
	    console.log((new Date()) + ' Received request for ' + request.url);
	    response.writeHead(404);
	    response.end();
	});
	server.listen(8080, function() {
	    console.log((new Date()) + ' Server is listening on port 8080');
	});
	
	wsServer = new WebSocketServer({
	    httpServer: server,
	    // You should not use autoAcceptConnections for production
	    // applications, as it defeats all standard cross-origin protection
	    // facilities built into the protocol and the browser.  You should
	    // *always* verify the connection's origin and decide whether or not
	    // to accept it.
	    autoAcceptConnections: false
	});
	
	function originIsAllowed(origin) {
	  // put logic here to detect whether the specified origin is allowed.
	  return true;
	}
	
	wsServer.on('request', function(request) {
	    if (!originIsAllowed(request.origin)) {
	      // Make sure we only accept requests from an allowed origin
	      request.reject();
	      console.log((new Date()) + ' Connection from origin ' + request.origin + ' rejected.');
	      return;
	    }
	
	    var connection = request.accept('echo-protocol', request.origin);
	    console.log((new Date()) + ' Connection accepted.');
	    connection.on('message', function(message) {
	        if (message.type === 'utf8') {
	            console.log('Received Message: ' + message.utf8Data);
	            connection.sendUTF(message.utf8Data);
	        }
	        else if (message.type === 'binary') {
	            console.log('Received Binary Message of ' + message.binaryData.length + ' bytes');
	            connection.sendBytes(message.binaryData);
	        }
	    });
	    connection.on('close', function(reasonCode, description) {
	        console.log((new Date()) + ' Peer ' + connection.remoteAddress + ' disconnected.');
	    });
	});
	
对 index.js 进行些修改

var ws = new WebSocket("ws://192.168.33.10:8080",'echo-protocol'); 

启动 server.js

node server.js

在浏览器访问

![Mou icon](http://izhengyin.com/static/images/198477.png)


可以看到这次连接成功了。

关于这行代码中的  echo-protocol

var ws = new WebSocket("ws://192.168.33.10:8080",'echo-protocol'); 

如果不传，服务端会拒绝访问，看下 server.js 37行，实际上它会出现在请求头信息里。

![Mou icon](http://izhengyin.com/static/images/906975.png)


当然，如果使用它来做我们应用的开发，我们需要处理很多的事情，最现实的一点，至少我们得处理各浏览器兼容，这就不是一个轻松的事情，这里使用它，只是为了试验下 websocket的 api ，实际应用中，我们会使用 socket.io 这样更成熟的模块。

