<?php

$credisFile = __DIR__ . DIRECTORY_SEPARATOR . 'Credis_Client.php';

if (is_readable($credisFile)) {
    require_once $credisFile;
} elseif (!extension_loaded('redis')) {
    echo <<<HTML
<h3>Error: PHP Redis/Credis Client not installed.</h3>
<p>We are not able to show this page as required tools are missing.  To solve this:</p>
<ul>
    <li>Install the PHP Redis extension</li>
    <li>or, download the <a href="https://github.com/colinmollenhour/credis/blob/master/Client.php">Credis Client</a> and place it at <b>$credisFile</b></li>
</ul>
HTML;
    exit;
}

$config = array(
    array(
        'host' => '127.0.0.1',
        'port' => 6379
    ),
    //array(
    //    'host'     => '127.0.0.1',
    //    'port'     => 6380,
    //    'password' => 'password'
    //),
    // etc
);

$redisKeys = array(
    'redis_version'          => 'Version',
    'config_file'            => 'Config File',
    'uptime_in_seconds'      => 'Uptime',
    'connected_clients'      => 'Connected Clients',
    'connected_slaves'       => 'Connected Slaves',
    'used_memory_human'      => 'Used Memory',
    'used_memory_peak_human' => 'Peak Used Memory',
    'expired_keys'           => 'Expired Keys',
    'evicted_keys'           => 'Evicted Keys',
    'keyspace_hits'          => 'Keyspace Hits',
    'keyspace_misses'        => 'Keyspace Misses'

);

class RedisStatus
{
    /**
     * List of redis servers
     * 
     * @var array
     */
    private $config;

    /**
     * Sorted list of servers
     * 
     * @var array
     */
    private $servers;

    /**
     * Save config value
     * 
     * @param array $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Collect config values and instanciate Redis objects
     * 
     * @return void
     */
    public function getServers()
    {
        if (is_null($this->servers)) {
            $this->servers = array();
            foreach ($this->config as $instance) {
                $password = isset($instance['password']) ? $instance['password'] : null;
                if (class_exists('Credis_Client')) {
                    $redis = new Credis_Client($instance['host'], $instance['port'], null, '', 0, $password);
                } else {
                    $redis = new Redis();
                    $redis->connect($instance['host'], $instance['port']);
                    if ($password) {
                        try {
                            $redis->auth($password);
                        } catch (Exception $e) {
                            // intentionally left blank
                        }
                    }
                }
                $this->servers[$instance['host'] . ':' . $instance['port']] = $redis;
            }
        }
        return $this->servers;
    }

    /**
     * Get databases that exist in Redis instance
     * 
     * @param  Credis_Client|Redis $redis
     * @return array
     */
    public static function getDatabases($redis)
    {
        return array_map(function($db) {
            return (int) substr($db, 2);
        }, preg_grep("/^db[0-9]+$/", array_keys($redis->info())));
    }
}

/**
 * Get the next chart color
 * 
 * @param  integer $i
 * @return string
 */
function getColor($i)
{
    if ($i == 0) {
        return '#3392db';
    }
    switch ($i % 4) {
        case 0:
            return '#4D5360';
        case 1:
            return '#F7464A';
        case 2:
            return '#46BFBD';
        default:
            return '#FDB45C';
    }
}

/**
 * @var RedisStatus
 */
$redisStatus = new RedisStatus($config);

?><!DOCTYPE html>
<meta charset="utf-8">
<html>
<head>
    <title>Redis Status</title>
    <?php if (isset($_GET['refresh']) && $_GET['refresh'] > 0): ?>
    <meta http-equiv="refresh" content="<?php echo (int)$_GET['refresh'] ?>" />
    <?php endif ?>
    <style type="text/css">
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0 0 20px 0;
        }
        #main {
            margin: 0 auto;
            width: 960px;
        }
        #refresh {
            float: right;
            font-size: 12px;
        }
        #refresh input[type="text"] {
            width: 20px;
        }
        .clear {
            clear: both;
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
        table tr th, table tr td {
            text-align: left;
            background: #eee;
            border: 1px solid #ddd;
            padding: 5px;
        }
        table tr th {
            text-align: right;
        }
    </style>
    <script>
        var charts = [];
    </script>
</head>
<body>
    <div id="main">
        <form id="refresh" action="" method="get">
            <label>Refresh interval (seconds)
                <input type="text" name="refresh" value="<?php echo isset($_GET['refresh']) ? $_GET['refresh'] : 0 ?>" />
            </label>
            <input type="submit" value="Set" />
        </form>
        <h1>Redis Status</h1>
        <div class="servers">
            <?php $i = 0 ?>
            <?php foreach ($redisStatus->getServers() as $server => $redis): ?>
            <div class="server">
                <h2><?php echo $server ?></h2>
                <?php
                    try {
                        $info = $redis->info();
                    } catch (Exception $e) {
                        echo $e->getMessage() . '</div>';
                        continue;
                    }
                ?>
                <div class="chart">
                    <h4>Databases</h4>
                    <canvas id="chart-<?php echo ++$i ?>" width="400" height="400"></canvas>
                </div>
                <script>
                    charts[<?php echo $i ?>] = [];
                <?php $j = 0 ?>
                <?php foreach (RedisStatus::getDatabases($redis) as $db): ?>
                    <?php $redis->select($db) ?>
                    charts[<?php echo $i ?>].push({
                        value: <?php echo $redis->dbSize() ?>,
                        label: 'Database <?php echo $db ?>',
                        color: '<?php echo getColor($j) ?>',
                        highlight: '<?php echo getColor($j++) ?>'
                    });
                <?php endforeach ?>
                </script>
                <table>
                    <?php foreach ($redisKeys as $key => $label): ?>
                    <tr>
                        <th><?php echo $label ?></th>
                        <?php if ($key == 'uptime_in_seconds'): ?>
                        <td><?php printf(
                            '%s day(s) %02d:%02d:%02d',
                            floor($info[$key] / 86400),
                            floor($info[$key] / 3600) % 24,
                            floor($info[$key] / 60) % 60,
                            floor($info[$key] % 60)
                        ) ?></td>
                        <?php else: ?>
                        <td><?php echo $info[$key] ?></td>
                        <?php endif ?>
                    </tr>
                    <?php endforeach ?>
                </table>
                <div class="clear"></div>
            </div>
            <?php endforeach ?>
        </div>
    </div>
    <script src="//cdnjs.cloudflare.com/ajax/libs/Chart.js/1.0.1/Chart.min.js"></script>
    <script>
        for (var i = 1; i < charts.length; i++) {
            new Chart(document.getElementById('chart-' + i).getContext('2d'))
                .Pie(charts[i], {
                    animateRotate: false
                });
        }
    </script>
</body>        
</html>
