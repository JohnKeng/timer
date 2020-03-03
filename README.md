# timer
base on PHP-swoole timer

### Install PSR-4 environment
```
composer install
```

### Run Server
```
php ./public/Server.php
```

### Run Client
開啟index.html即可連線


### Change api url
更改 ./src/App/Task.php的function start_timer() 的url

### Change SQL config
1. 開啟 ./src/App/Task.php的function SQL()
2. 刪掉 $this->job2_time = '15:06:00';
3. 把註解解開

### Change daemonize config
開啟 ./public/Server.php
```
$opt = [
    //守護進程
    'daemonize' => true
];
```