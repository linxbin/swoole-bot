<!--<html>-->

<!--<a href="client.php"> 登录</a>-->
<!--</html>-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>
<h1>我要变机器人</h1>
<input hidden name="text"  type="text" id="text" />
<img id="img" src=""/>
<div id="msg"> 网络连接中....</div>
<input type="submit" value="登录" onclick="song()">
<div>
    <select style="width:200px" name="groups" id="groups" >

    </select>
</div>
</body>
<script>
    var msg         = document.getElementById("msg");
    var img         = document.getElementById("img");
    var groups      = document.getElementById('groups');
    var wsServer    = "ws://192.168.42.133:9501";

    // 调用websocket对象建立连接：
    // 参数：ws/wss(加密)：//ip:port （字符串）
    var websocket   = new WebSocket(wsServer);
    //onopen监听连接打开
    websocket.onopen = function (evt) {
        //websocket.readyState 属性：
        /*
        CONNECTING  0   The connection is not yet open.
        OPEN    1   The connection is open and ready to communicate.
        CLOSING 2   The connection is in the process of closing.
        CLOSED  3   The connection is closed or couldn't be opened.
        */
        if(websocket.readyState == 1) {
            msg.innerHTML = '连接成功！';
        }

    };

    function song(){
        var text = document.getElementById('text').value;
        document.getElementById('text').value = '';
        //向服务器发送数据
        websocket.send(text);
    }
    //监听连接关闭
    //    websocket.onclose = function (evt) {
    //        console.log("Disconnected");
    //    };

    //onmessage 监听服务器数据推送
    websocket.onmessage = function (evt) {
        var result =  JSON.parse(evt.data);
        if(result.type == 'msg') {
            msg.innerHTML = result.data +'<br>';
        }
        if(result.type == 'url') {
            img.src = 'http://qr.liantu.com/api.php?text=' + result.data;
        }
        if(result.type == 'groups') {
            var data = result.data;
            for(var i = 0 , l = data.length; i < l ; i++) {
                var op = document.createElement("option");
                op.setAttribute("value",data[i].UserName);
                op.setAttribute("name",data[i].NickName);
            }
            groups.appendChild(op);
        }


//        console.log('Retrieved data from server: ' + evt.data);
    };
    //监听连接错误信息
    //    websocket.onerror = function (evt, e) {
    //        console.log('Error occured: ' + evt.data);
    //    };
</script>
</html>