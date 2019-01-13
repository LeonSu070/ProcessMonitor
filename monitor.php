<?php
/**
 * 队列监控
 * 
 * @author Leon
 *        
 */
class monitor
{
    private $process_list;
    public function __construct()
    {
        // 进程配置
        $this->process_list = array (
            'process1' => array (
                'script' => "/usr/bin/php test.php",
                //默认启动的进程数，如果sqs_count_p_count为空，则此设置生效
                'p_count' => 2,
                'timeout' => 1800,
                'q_name' => "xxxxx",
                //指定队列不同的堆积数启动不同的进程数
                'sqs_count_p_count' => array(
                    '1-5' => 1,
                    '5-10' => 2,
                    '10-50' => 4,
                    '50-100' => 6,
                    '100+' => 10,
                ),
            ),
            'process2' => array (
                //进程命令
                'script' => "/bin/sh test.sh",
                //默认启动的进程数，如果sqs_count_p_count为空，则此设置生效
                'p_count' => 2,
                'timeout' => 1800,
                'q_name' => "xxxxx",
                //指定队列不同的堆积数启动不同的进程数
                'sqs_count_p_count' => array(
                    '1-5' => 1,
                    '5-10' => 2,
                    '10-50' => 4,
                    '50-100' => 6,
                    '100+' => 10,
                ),
            ),
        );

    }
    public function monitor_process()
    {
        // 读取进程配置
        $process_conf = $this->process_list;

        // 获取取正在运行的进程
        foreach ($process_conf as $k => $cmd)
        {
            if (!isset($cmd['script']) || empty($cmd['script'])) {
                continue;    
            }
            $process_conf[$k] = $this->check_process($cmd);
        }
        
        // 启动缺失进程
        echo "===Run process===\n";
        $this->run_process($process_conf);
        return true;
    }
    /**
     * 检查进程
     *
     * @param array $cmd            
     */
    private function check_process($cmd)
    {
        $command = "ps aux | grep '" . $cmd['script'] . "' | grep -v grep";
        exec($command, $output);
        // 杀死僵尸进程
        $this->kill_zombie($output, $cmd);
        
        // 杀死超时进程
        $this->kill_timeout_process($output, $cmd);
        
        // 对比进程数是否正确
        $p_count = $cmd['p_count'];
        //如果设置了队列名，则根据队列堆积数决定启动多少进程
        if (isset($cmd['q_name']) && !empty($cmd['q_name']))
        {
            
            $sqs_msg_count = $this->get_message_count($cmd['q_name']);
            echo "sqs message count : $sqs_msg_count \n";
            //队列中没有消息，则不需要启动进程
            if (!$sqs_msg_count)
            {
                $p_count = 0;
            }
            elseif (isset($cmd['sqs_count_p_count']) && !empty($cmd['sqs_count_p_count']))
            {
                foreach ($cmd['sqs_count_p_count'] as $k => $v)
                {
                    list($min,$max) = explode("-", $k);
                    $min = trim($min, "+");
                    if(isset($min) && $min && isset($max) && $max && $sqs_msg_count >= $min && $sqs_msg_count <= $max)
                    {
                        $p_count = $v;
                        break;
                    }
                    elseif((!isset($max) || !$max) && isset($min) && $min && $sqs_msg_count >= $min)
                    {
                        $p_count = $v;
                        break;
                    }
                    continue;
                }
            }
        }
        
        $running_count = count($output);
        if ($running_count < $p_count)
        {
            $cmd['need_run_number'] = $p_count - $running_count;
        }
        return $cmd;
    }
    /**
     * kill僵尸进程
     *
     * @param array $processes            
     * @param array $cmd            
     */
    private function kill_zombie($processes)
    {
        if (empty($processes))
        {
            return true;
        }
        foreach ($processes as $pro)
        {
            $pro_arr = array();
            $pro = preg_replace("/[\s]+/is", " ", $pro);
            $pro_arr = explode(" ", $pro);
            if (isset($pro_arr[7]) && $pro_arr[7] == "Z")
            {
                $_p = popen("kill -9 {$pro_arr[1]}", 'r');
                pclose($_p);
            }
        }
        return true;
    }
    /**
     * kill超时进程
     *
     * @param array $processes            
     * @param array $cmd            
     */
    private function kill_timeout_process($processes, $cmd)
    {
        if (empty($processes) || empty($cmd))
        {
            return true;
        }
        //默认超时时间为半小时
        $timeout = isset($cmd['timeout']) ? $cmd['timeout'] : 1800;
        foreach ($processes as $pro)
        {
            $pro_arr = array();
            $pro = preg_replace("/[\s]+/is", " ", $pro);
            $pro_arr = explode(" ", $pro);
            if (isset($pro_arr[8]))
            {
                $start_time = strtotime($pro_arr[8]);
                if (time() - $start_time > $timeout)
                {
                    $_p = popen("kill -9 {$pro_arr[1]}", 'r');
                    pclose($_p);
                }
            }
        }
        return true;
    }
    
    /**
     * Run process
     */
    private function run_process($process)
    {
        foreach ($process as $k => $cmd)
        {
            if (isset($cmd['need_run_number']) && $cmd['need_run_number'] > 0)
            {
                echo "Need to run processe number: {$cmd['need_run_number']}  \n";
                for ($i = 0; $i < $cmd['need_run_number']; $i++)
                {
                    $script = $cmd['script'] . " > /dev/null &";
                    $_pp = popen($script, 'r');
                    pclose($_pp);
                    echo "Process $i : $script  | Run Success \n";
                }
            }
        }
        return true;
    }
    /**
     * Get messgae count
     */
    private function get_message_count($q_name){
        //从队列获取消息数
        //$count = getMessageCountFromQueue($q_name);
        $count = 2;
        return $count;
    }
}

$checkobj = new monitor();
$checkobj->monitor_process();
