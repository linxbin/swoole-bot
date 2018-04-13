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
<input hidden name="text" type="text" id="text"/>
<img id="img" src=""/>
<div id="msg"> The connection is not yet open.</div>
<input type="submit" value="登录" onclick="login()">
<div>
    选择群
    <select style="width:200px" name="groups" id="groups"></select>
</div>
<div>
    <input type="submit" value="开启游戏" onclick="start()">
</div>
</body>
<script>
    var msg = document.getElementById("msg");
    var img = document.getElementById("img");
    var groups = document.getElementById('groups');
    var wsServer = "ws://192.168.42.133:9501";

    // 调用websocket对象建立连接：
    // 参数：ws/wss(加密)：//ip:port （字符串）
    var websocket = new WebSocket(wsServer);
    //onopen监听连接打开
    websocket.onopen = function (evt) {
        //websocket.readyState 属性：
        /*
        CONNECTING  0   The connection is not yet open.
        OPEN    1   The connection is open and ready to communicate.
        CLOSING 2   The connection is in the process of closing.
        CLOSED  3   The connection is closed or couldn't be opened.
        */
        switch (websocket.readyState) {
            case 0 : msg.innerHTML = 'NETWORK STATUS : The connection is not yet open.';
                break;
            case 1 : msg.innerHTML = 'NETWORK STATUS : The connection is open and ready to communicate.';
                break;
            case 2 : msg.innerHTML = 'NETWORK STATUS : The connection is in the process of closing.';
                break;
            case 3 : msg.innerHTML = 'NETWORK STATUS : The connection is closed or couldn\'t be opened.';
                break;
        }

    };

    function login() {
        //向服务器发送数据
        var data = {
            'type' : 'login',
            'content' : 'not content'
        };
        websocket.send(JSON.stringify(data));
    }

    function start() {
        //向服务器发送数据
        var data = {
            'type' : 'start',
            'content' : 'group username is send '
        };
        websocket.send(JSON.stringify(data));
    }

    //监听连接关闭
    websocket.onclose = function (evt) {
        console.log("Disconnected");
    };

    //onmessage 监听服务器数据推送
    websocket.onmessage = function (evt) {
        var result = JSON.parse(evt.data);
        if (result.type == 'msg') {
            msg.innerHTML = result.data + '<br>';
        }
        if (result.type == 'url') {
            img.src = 'http://qr.liantu.com/api.php?text=' + result.data;
        }
        if (result.type == 'groups') {
            console.log(result);
            var groupsData = result.data;
            for (var i = 0; i < groupsData.length; i++) {
                var op = document.createElement("option");
                op.setAttribute("value", groupsData[i]['UserName']);
                op.appendChild(document.createTextNode(groupsData[i]['NickName']));
                groups.appendChild(op);
            }
        }


//        console.log('Retrieved data from server: ' + evt.data);
    };
    //监听连接错误信息
    //    websocket.onerror = function (evt, e) {
    //        console.log('Error occured: ' + evt.data);
    //    };
</script>
</html>