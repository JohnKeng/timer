<?php 
namespace Poabob\Swoole;

use swoole_websocket_server;

class Task
{
    private $switch = false;
    private $timer;
    private $job2_time;

    protected $serv;
    protected $host = '127.0.0.1';
    protected $port = 9502;
    // 進程id
    protected $taskName = 'swooleTask';
    // PID path
    protected $pidPath = '/opt/lampp/htdocs/swoole-app/swooletask.pid';
    // 設置運行參數
    protected $options = [
        'worker_num' => 4, 
        'daemonize' => true, 
        'log_file' => '/opt/lampp/htdocs/swoole-app/logs/swoole-task.log',
        'log_level' => 0, //，0-DEBUG，1-TRACE，2-INFO，3-NOTICE，4-WARNING，5-ERROR
        // 'dispatch_mode' => 1,
        'task_worker_num' => 1, 
        'task_ipc_mode' => 3, 
        'task_enable_coroutine' => false,
    ];

    public function __construct($options = [])
    {
        date_default_timezone_set('PRC'); 
        $this->serv = new swoole_websocket_server($this->host, $this->port);

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $this->serv->set($this->options);

        $this->serv->on('Start', [$this, 'onStart']);
        $this->serv->on('Open', [$this, 'onOpen']);
        $this->serv->on('Message', [$this, 'onMessage']);
        $this->serv->on('Task', [$this, 'onTask']);  
        $this->serv->on('Close', [$this, 'onClose']);
    }

    public function start()
    {
        // Run worker
        $this->serv->start();
    }

    public function onStart($serv)
    {
        cli_set_process_title($this->taskName);
        $pid = "{$serv->master_pid}\n{$serv->manager_pid}";
        file_put_contents($this->pidPath, $pid);
        echo 'Swoole Timer 已經啟動...'.PHP_EOL;
    }

    // 監聽
    public function onOpen(swoole_websocket_server $serv, $request)
    {
        echo "Swoole 已和{$request->fd}建立連接".PHP_EOL;
        $serv->push( $request->fd, "Swoole 已和{$request->fd}建立連接" );
    }

    public function onMessage(swoole_websocket_server $serv, $frame)
    {
        $client = json_decode($frame,JSON_UNESCAPED_UNICODE);
        echo "來自 {$client} 的消息: {$frame->data}\n";
        if($frame->data == 'START') {
            if($this->switch == true) {
                $serv->push($frame->fd, "任務已經啟用了...");
                return;
            }
            $this->SQL();
            $this->start_timer($serv, $frame);
        } else if($frame->data == 'STOP') {
            $this->stop_timer($serv, $frame);
        } else if($frame->data == 'RESTART') {
            $this->stop_timer($serv, $frame);
            $this->SQL();
            $this->start_timer($serv, $frame);
        }
    }

    public function onTask(swoole_websocket_server $serv, $task_id, $from_id, $data)
    {
    }

    public function onClose($serv, $frame, $from_id) {
        echo "{$frame} 已中斷連線...\n";
    }

    private function get($url)
    {
        $ch = \curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function getMemoryUsage()
    {
        // MEMORY
        if (false === ($str = @file("/proc/meminfo"))) return false;
        $str = implode("", $str);
        preg_match_all("/MemTotal\s{0,}\:+\s{0,}([\d\.]+).+?MemFree\s{0,}\:+\s{0,}([\d\.]+).+?Cached\s{0,}\:+\s{0,}([\d\.]+).+?SwapTotal\s{0,}\:+\s{0,}([\d\.]+).+?SwapFree\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buf);

        $memTotal = round($buf[1][0]/1024, 2);
        $memFree = round($buf[2][0]/1024, 2);
        $memUsed = $memTotal - $memFree;
        $memPercent = (floatval($memTotal)!=0) ? round($memUsed/$memTotal*100,2):0;

        return $memPercent;
    }

    private function SQL()
    {
        // $dsn = 'mysql:host=localhost;dbname=leconfig';
        // $user = 'leyan';
        // $pass = '2016leyan0429';
        // $db = new PDO($dsn, $user, $pass);
        // $result = $db->query("SELECT `value` FROM `zhi_settings` WHERE `key` = 'SMSauto';");
        // $value=$result->fetch();

        // if(intval($value['value'])) {
        //     $result = $db->query("SELECT `value` FROM `zhi_settings` WHERE `key` = 'SMStime';");
        //     $datetime=$result->fetch();
        //     if($datetime['value'] != null) {
        //         $this->job2_time = $datetime['value'];
        //     }
        // }
        $this->job2_time = '15:06:00';
    }


    private function stop_timer($serv, $frame) {
        foreach ($this->timer as $t) {
            swoole_timer_clear($t);
        }
        $this->switch = false;
        $this->job2_time = null;
    }

    private function start_timer($serv, $frame) {
        $timer1 = swoole_timer_tick(5 * 1000, function() use ($serv, $frame) { 
            $memPercent = $this->getMemoryUsage();
            $url = '/opt/lampp/htdocs/swoole-app/api.html';
            $return = $this->get($url);
            echo date('Y-m-d H:i:s') . " 使用率：{$memPercent} % task1 完成, 返回值 {$return}" . PHP_EOL;
            $serv->push($frame->fd, date('Y-m-d H:i:s') . " 使用率：{$memPercent} % task1 完成, 返回值 {$return}" . PHP_EOL); 
        });
        $timer2 = swoole_timer_tick(1 * 60 * 1000, function() use ($serv, $frame) {
            $memPercent = $this->getMemoryUsage();
            $time = (strtotime($this->job2_time) - strtotime(date('H:i:s')));
            if($time < 1 * 60  && $time >= 0) {
                $url = '/opt/lampp/htdocs/swoole-app/api.html';
                $return = $this->get($url);
                echo date('Y-m-d H:i:s') . " 使用率：{$memPercent} % task2 完成, 返回值 {$return}" . PHP_EOL;
                $serv->push($frame->fd, date('Y-m-d H:i:s') . " 使用率：{$memPercent} % task2 完成, 返回值 {$return}" . PHP_EOL);
            } elseif($time < 0) {
                $serv->push($frame->fd, date('Y-m-d H:i:s') . " 使用率：{$memPercent} % task2 等待明天 {$this->job2_time} 執行" . PHP_EOL);
                echo date('Y-m-d H:i:s') . " 使用率：{$memPercent} % task2 等待明天 {$this->job2_time} 執行" . PHP_EOL;
            } elseif($time > 0) {
                $serv->push($frame->fd, date('Y-m-d H:i:s') . " 使用率：{$memPercent} % task2 等待 {$this->job2_time} 執行" . PHP_EOL);
                echo date('Y-m-d H:i:s') . " 使用率：{$memPercent} % task2 等待 {$this->job2_time} 執行" . PHP_EOL;
            }
            
        });

        $this->timer = array($timer1, $timer2);
        $this->switch = true;
        $serv->push($frame->fd, "任務已啟動...");
    }
}
