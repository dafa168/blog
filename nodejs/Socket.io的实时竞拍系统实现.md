某天张同学来到了一个拍卖网站，看中了一件心仪的拍品，于是他愉快的参与了竞价，并处于了领先的地位，随后张同学死死盯住拍品倒计时和领先者，在倒计时结束后发现领先者还是他自己，正当他欣喜若狂准备付款时，页面刷新发现拍品已被别人抢走。后来当张同学在次来到网站，便长了记性，开始无止境的F5~ 

当然我们不能这样折腾我们的用户，那么如何实现拍品信息实时更新？ 要解决这个需求，实际上需要服务端主动“推” 信息到客户端。而客户端想要得到服务端推送的信息，实际上需要和服务端建立一个长连接，这样服务端才能通过这个连接把信息传递到客户端，也就是所谓“推”的概念，下面有篇文章详细介绍了WEB推送的系统的客户端实现方式：
    
  [Comet：基于 HTTP 长连接的“服务器推”技术](http://www.ibm.com/developerworks/cn/web/wa-lo-comet/)

Nginx也有相应的模块进行服务端的支持：

  [HTTP Push Stream](https://www.nginx.com/resources/wiki/modules/push_stream/)
  
<!--izhengyin@more-->
这里我们主要来说说通过Node平台下的Socket.io如何实现，首先来看下具体的业务场景是什么,一个最基本的实时竞拍系统应该包含以下三个场景：

1. 一个或者多个用户关注着某一个拍品
2. 当其中某个用户出价后，更新数据库中该拍品的信息
3. 将最新的拍品信息反应到关注该拍品用户的客户端上

那么根据这三个场景，能够确认推送的一个大概思路，也就是 “当拍品出价成功后，取得最新的拍品信息，推送给关注该拍品的所有用户”，基于这个思路可以确定一个大概的推送流程图:

  
![推送流程图.png](http://upload-images.jianshu.io/upload_images/1639948-8c0ede3a86d71389.png?imageMogr2/auto-orient/strip%7CimageView2/2/w/1240)

这个流程图中分为4个部分：
 * 客户端(接受推送消息，已经出价)
 * 竞价接口(处理竞价逻辑，加入拍品到推送队列)
 * 队列处理(取出需要推送的拍品调用推送API)
 * 推送服务(提供socket.io的服务端供客户端进行
连接，并提供一组推送的内网API供应用程序调用)  

根据以上流程，竞价接口以及队列处理我们可以采用任意的语言去实现，这里不在延伸。这两部分中队列处理，根据具体业务可以省去，这里引入队列处理的目录主要是考虑到，推送服务本身与竞价流程解耦，以及当推送服务故障时，失败队列的维护,当然如果使用队列，也需要考虑队列处理的及时性，避免推送信息的不及时。

 下面来看看推送服务如何构建:

 (以下均是伪代码,只为说明具体思路)


```

      var http = require("http");
      http.globalAgent.maxSockets = Infinity;
      var koa = require('koa');
      var app = koa();
      var bodyParser = require('koa-bodyparser');
      var route = require('koa-route');
      var io = require('socket.io');
      var ioRedis = require('socket.io-redis');
      var ioEmitter = require('socket.io-emitter')({ host: '127.0.0.1', port: '6379' });
      var server = http.createServer(app.callback());

      io = io(server);

      io.adapter(ioRedis({ host :'127.0.0.1', prot :'6379'}));

      /**************  推送API ******************/
      app.use(bodyParser());
      app.use(route.post('/pub', function *(next){

          var data = this.request.body;

          if(!data || typeof data != 'object'){
              this.throw('data error', 400);
          }

          var room = data.itemId;
          var channel = data.channel;
          var message = data.message;

          ioEmitter.to(room).emit(channel,message);   

          this.body = 'ok';
          
      }));
      app.use(function *(){
        this.response.status = 404;
      })
      /*****************************************/

      /*************Socket.io Server ***********/
      io.use(function(socket,next){
          var itemId = socket.handshake.query.itemId;
          socket.room = itemId;
          return next();
      });
      io.on('connection',function(socket){
          socket.join(socket.room);
      });
      /*****************************************/




      server.listen(3000,function(){});

```

推送服务实际上起到的是一个中间层的作用，下面看下客户端与服务端如何和它配合：

* 客户端代码

```
  <script>
    var socket = io('http://推送服务地址:3000?itemId=100');
    socket.on('auction', function (data) {
          //调用 Dom 更新拍品信息
    });
  </script>
```
客户端在连接是指明了itemId(假设它是拍品ID),这样在推送服务能够对其进行房间的划分,也就是在 socket.join(socket.room) 的时候。

* 服务端代码(PHP)

```
        
	private function http($data){
               /** 
                    $data = array(
                          'itemId'=>100,            //指明推送拍品
                          'channel'=>'auction',  //指明推送到的渠道
                          'message'=>array( )  //最新的拍品信息
                    );
               */

		$server = $this->getServer();  // http://127.0.0.1:3000/pub
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $server);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$response = curl_exec($ch);
		if(curl_errno($ch)){
			throw new \Exception('curl_error  '.curl_error($ch));
		}else{
			if(strtolower($response) == 'ok'){    
				curl_close($ch);
				return true;
			}else{
				throw new \Exception('response_error  '.$response);
			}
		}
		curl_close($ch);
		return false;
	}

```

服务端在推送时，调用 "http://127.0.0.1:3000/pub" ，也就是有 koa 搭建的http推送API ，并传入想应格式的数据，指明推送的拍品，渠道，信息。这样在 koa 接受到请求后，调用    ioEmitter.to(room).emit(channel,message);    将信息推送到客户端，这样就走完了一个流程。

总结：
  * 实时推送实际上就是消息的发布/订阅，客户端是消息订阅方，服务端是消息的发布方
  * 推送服务也就是消息发布/订阅的服务
  * 推送与竞价可以是两套不同业务，应该考虑解耦
  * 使用Socket.io做Web的实时推送，代码量下，可控性高