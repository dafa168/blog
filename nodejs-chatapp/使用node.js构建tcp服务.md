node.js与socket.io
--------
构建一个web聊天应用，需要处理的事情很多，前面提到的websock这个协议在低版本浏览器里面是不被支持的，也就是常说的 “有兼容性问题”。而 node.js socket.io 模块，就是帮我们来解决这些问题的。

官方网站:<http://socket.io/>             

github地址:<https://github.com/Automattic/socket.io>


首先来创建一个最简单的开始

首先选择一个项目目录，创建一个server.js 如下面这样

	var io = require('socket.io')();
	//监听客户端连接
	io.on('connection', function(socket){		
		console.log('client connection ..')	
		//自定义一个监听的通道[newMessage]
		socket.on('newMessage',function(data){	
			console.log(data);
			data.date = new Date();
			//往通道[newMessage]发送消息，如果客户端监听了该通道，将收到服务端发的消息 
			socket.emit('newMessage',data);		
		})
	});
	io.listen(3000);	

在项目目录中使用命令安装socket.io模块

	npm install socket.io  
	
	chmod +x server.js 
	
	node server.js  运行	


接下来创建一个简单的 html 文件 把它放在你的 webroot 目录下，我就叫它 index.html

也就是说你能够通过 http://servername/index.html 在浏览器访问到这个文件. 

它的内容如下面这样

index.html

	<script src="https://cdn.socket.io/socket.io-1.2.1.js"></script>
	<script type="text/javascript">
		//创建客户端socket对象，并连接到  http://192.168.33.10:3000  
		var socket = io('http://192.168.33.10:3000');
		//监听服务端往[newMessage]通道发送的消息
		socket.on('newMessage',function(data){
		  	console.log(data);
	    })
		//向通道[newMessage]发送消息，如果服务端监听了该通道,将收到客户端发的消息 
		socket.emit('newMessage',{msg:'Hello Server!'});
	</script>	
	

![Izhengyin icon](http://izhengyin.com/static/images/707265.png)

在服务端的命令行也能看到客服端发送的数据

可以看到socket.io 的api非常简洁,简单

这样客户端浏览器，与服务端就建立好了一个 ws 长连接，如果 浏览器不支持 websock 协议，socket.io 将做降级处理，以长轮询的方式来维护通道，api 是不变的。

有了上面的处理，服务端与客户端就能够互发消息了，当然也只是能做到“互发”而已。

如果我在一个新的浏览器，或者一个新的标签中再次访问 http://servername/index.html  ,那么之前已经建立好连接的客户端是不能接收到消息的。

在 chrome 中访问  http://servername/index.html 

控制台能够正常输出,但在之前已建立的连接是接收不到这条消息的



![Izhengyin icon](http://izhengyin.com/static/images/206710.png)

如果想让其它连接到服务端的客户端都能收到彼此发送的消息需要通过 socket.io 提供的另一个发送消息 api 实现。

修改 server.js 改成下面这个样子


	var io = require('socket.io')();
	//监听客户端连接
	io.on('connection', function(socket){		
		console.log('client connection ..')	
		//自定义一个监听的通道[newMessage]
		socket.on('newMessage',function(data){	
			console.log(data);
			data.date = new Date();
			//向通道[newMessage]发送消息，如果客户端监听了该通道，将收到服务端发的消息 
			
			//发送给当前的客户端，也就是这条消息是谁发来的
			socket.emit('newMessage',data);		
			
			//发送除了当前的其他客户端
			socket.broadcast.emit('newMessage',data);
		})
	});
	io.listen(3000);

这样我们多个客户端就能够互通消息了

实际上借助这个两个简单的 api 我们就能够实现一个简单的聊天室了，当然除此之外 socket.io 提供了很多丰富 api ，你可以去官网网站查看文档了解，在这我们将就 以上两个 api 展开。

