<?php

ini_set('display_errors', 1);

if (!extension_loaded('redis')) {
    throw new Exception('PHP Redis extension is not installed');
}

class RedisStatus
{
    private $config;
    private $servers;

    public function __construct($configFile)
    {
        $this->loadConfig($configFile);
    }

    public function loadConfig($configFile)
    {
        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $configFile;
        if (is_readable($fullPath)) {
            $this->config = json_decode(file_get_contents($fullPath));
        } else {
            $config = new stdClass();
            $config->host = '127.0.0.1';
            $config->port = '6379';
            $this->config = array($config);
        }
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getServers()
    {
        if (is_null($this->servers)) {
            $this->servers = array();
            foreach ($this->getConfig() as $instance) {

                $redis = new Redis();
                $redis->connect($instance->host, $instance->port);
                $password = isset($instance->password) ? $instance->password : false;
                if ($password) {
                    try {
                        $redis->auth($password);
                    } catch (Exception $e) {
                        // intentionally left blank
                    }
                }
                $this->servers[$instance->host . ':' . $instance->port] = $redis;
            }
        }
        return $this->servers;
    }

    public static function getDatabases(Redis $redis)
    {
        return array_map(function($db) {
            return (int) substr($db, 2);
        }, preg_grep("/^db[0-9]+$/", array_flip($redis->info())));
    }
}

$redisStatus = new RedisStatus('config.json');

?><!DOCTYPE html>
<meta charset="utf-8">
<html>
<head>
    <title>Redis Status</title>
    <style type="text/css">
        body {
            font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
            margin: 0;
            padding: 0;
        }
        #main {
            margin: 0 auto;
            width: 960px;
        }
        #nav {
            list-style-type: none;
            margin: 0;
            padding: 0;
        }
        #nav li {
            background: #eee;
            display: block;
            float: left;
            padding: 15px;
            cursor: pointer;
            border: 1px solid #ddd;
        }
        #nav li + li {
            margin-left: 20px;
        }
        #nav li:hover {
            background: #ddd;
        }
        .clear {
            clear: both;
        }
        .server {
            display: none;
        }
        .server.active {
            display: block;
        }
        .server, .database {
            padding: 0 20px 20px;
            border: 1px solid #ddd;
            margin-top: 20px;
        }
        .chart {
            float: right;
        }
        .chart h4 {
            text-align: center;
            margin-top: 0;
        }
        table {
            border-collapse: collapse;
        }
        table tr {
            
        }
        table tr th, table tr td {
            text-align: left;
            background: #eee;
            border: 1px solid #ddd;
            padding: 5px;
        }
        table tr th {
            text-align: right;
        }
        table tr td {
            
        }
    </style>
    <script>
        var charts = [];
        function getRandomColor() {
            var letters = '0123456789ABCDEF'.split('');
            var color = '#';
            for (var i = 0; i < 6; i++ ) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        }
    </script>
</head>
<body>
    <div id="main">
        <h1>Redis Status</h1>
        <ul id="nav">
            <?php foreach ($redisStatus->getServers() as $server => $redis): ?>
            <li><?php echo $server ?></li>
            <?php endforeach ?>
        </ul>
        <div class="clear"></div>
        <div class="servers">
            <?php $i = 0 ?>
            <?php foreach ($redisStatus->getServers() as $server => $redis): ?>
            <div class="server<?php echo !$i++ ? ' active' : '' ?>">
                <h2><?php echo $server ?></h2>
                <?php
                    try {
                        $info = $redis->info();
                    } catch (Exception $e) {
                        echo $e->getMessage();
                        continue;
                    }
                ?>
                <div class="chart">
                    <h4>Keys</h4>
                    <canvas id="chart-<?php echo $i ?>" width="400" height="400"></canvas>
                </div>
                <script>
                    charts[<?php echo $i ?>] = [];
                <?php foreach (RedisStatus::getDatabases($redis) as $db): ?>
                    <?php $redis->select($db) ?>
                    charts[<?php echo $i ?>].push({
                        value: <?php echo $redis->dbSize() ?>,
                        label: 'Database <?php echo $db ?>',
                        color: getRandomColor(),
                        highlight: '#ddd'
                    });
                <?php endforeach ?>
                </script>
                <table>
                    <tr>
                        <th>Version</th>
                        <td><?php echo $info['redis_version'] ?></td>
                    </tr>
                    <tr>
                        <th>Uptime</th>
                        <td><?php printf(
                            '%s day(s) %02d:%02d:%02d',
                            floor($info['uptime_in_seconds'] / 86400),
                            floor($info['uptime_in_seconds'] / 3600) % 24,
                            floor($info['uptime_in_seconds'] / 60) % 60,
                            floor($info['uptime_in_seconds'] % 60)
                        ) ?></td>
                    </tr>
                    <tr>
                        <th>Connected Clients</th>
                        <td><?php echo $info['connected_clients'] ?></td>
                    </tr>
                    <?php if ($info['connected_slaves']): ?>
                    <tr>
                        <th>Connected Slaves</th>
                        <td><?php echo $info['connected_slaves'] ?></td>
                    </tr>
                    <?php endif ?>
                    <tr>
                        <th>Used Memory</th>
                        <td><?php echo $info['used_memory_human'] ?></td>
                    </tr>
                    <tr>
                        <th>Peak Used Memory</th>
                        <td><?php echo $info['used_memory_peak_human'] ?></td>
                    </tr>
                    <tr>
                        <th>Expired Keys</th>
                        <td><?php echo $info['expired_keys'] ?></td>
                    </tr>
                    <tr>
                        <th>Evicted Keys</th>
                        <td><?php echo $info['evicted_keys'] ?></td>
                    </tr>
                    <tr>
                        <th>Keyspace Hits</th>
                        <td><?php echo $info['keyspace_hits'] ?></td>
                    </tr>
                    <tr>
                        <th>Keyspace Misses</th>
                        <td><?php echo $info['keyspace_misses'] ?></td>
                    </tr>
                </table>
                <div class="clear"></div>
                <?php /* foreach (RedisStatus::getDatabases($redis) as $db): ?>
                <div class="database">
                    <h3>Database <?php echo $db ?></h3>
                    <?php $redis->select($db) ?>
                    <table>
                        <tr>
                            <th>Key Count</th>
                            <td><?php echo $redis->dbSize() ?></td>
                        </tr>
                    </table>
                </div>
                <?php endforeach */ ?>
            </div>
            <?php endforeach ?>
        </div>
    </div>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/Chart.js/1.0.1/Chart.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#nav li').click(function() {
                $('.server.active').removeClass('active');
                $('.server').eq($(this).index()).addClass('active');
            });
        });
        for (var i = 1; i < charts.length; i++) {
            new Chart(document.getElementById('chart-' + i).getContext('2d'))
                .Pie(charts[i]);
        }
    </script>
</body>        
</html>