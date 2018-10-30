<?php
/* Zabbix Config Only Backup Tool.
 *
 * Author: uraku. 
 * URL: https://www.sodo-shed.com/
 * Repository: https://github.com/lf-uraku-yuki/zabbix_db_config_backup
 * License: MIT License
 * Version: 0.3.0
 */

/* ---- User Configuration ---- */
define('ZABBIX_DB_HOST', 'localhost');
define('ZABBIX_DB_NAME', 'zabbix');
define('ZABBIX_DB_USER', 'zabbix');
define('ZABBIX_DB_PASSWORD', 'XXXXXXXXXX');
define('BACKUP_DIR_FULL_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config_backup' . DIRECTORY_SEPARATOR);
define('ZABBIX_VERSION', '3010');
/*
 * ZABBIX_VERSION 3010 : Zabbix 3.0.10
 * ZABBIX_VERSION 3403 : Zabbix 3.4.3
 * ZABBIX_VERSION 4001 : Zabbix 4.0.1
 */
 
/* ---- Static Defined ---- */
define('MODE_BACKUP', 100);
define('MODE_ROLLBACK', 200);
define('TOOL_VERSION', 'Zabbix Config Only Backup Tool. ver 0.3.0 for Zabbix 3.0.10, 3.4.3, 4.0.1');
 
/* ---- Code ---- */
$backup_mode = null;
 
// PHP Version Check
if (PHP_MAJOR_VERSION < 5) {
    echo "ERROR: PHP Version Not Supported.";
    exit(1);
} elseif (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION < 6) {
    echo "ERROR: PHP Version Not Supported.";
    exit(1);
}
 
// Mode Select
if (isset($argv) && isset($argv[1]) && $argv[1] == '--backup') {
    $backup_mode = MODE_BACKUP;
    echo "Zabbix DB Config Backup Start\n";
    execBackup();
    exit();
    
} elseif (isset($argv) && isset($argv[1]) && $argv[1] == '--rollback') {
    $backup_mode = MODE_ROLLBACK;
    echo "Zabbix DB Config Rollback Setup\n";
    echo "[[[ Please stop Zabbix-Server first!! ]]]\n";
    if (empty($argv[2])) {
        echo "ERROR: Require Backup file path\n";
        exit();
    }
    $file_path = $argv[2];
    execRollback($file_path);
    exit();
    
} else {
    echo TOOL_VERSION . "\n";
    echo "help:\n";
    echo " --backup\n";
    echo " --rollback [backup-filename]\n";
    echo "Used:\n";
    echo "PHP 5.6 or later. and mysqlnd, pdo, mbstring.\n";
    exit(1);
}
 
 
function execBackup() {
    $date = date('Ymd_His');
    $file_path = BACKUP_DIR_FULL_PATH . "zabbix_config_only_" . $date . ".sql.gz";
    echo "Output Path: " . $file_path . "\n";
    if (! is_writable(BACKUP_DIR_FULL_PATH)) {
        echo "ERROR: Backup Path is not wriable\n";
        exit(1);
    }
    if (file_exists($file_path)) {
        echo "ERROR: Backup File is Exist.\n";
        exit(1);
    }
 
    $ignore_tables = '--ignore-table=' . ZABBIX_DB_NAME . '.history ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.history_uint ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.trends_uint ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.trends ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.history_str ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.history_text ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.history_log ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.alerts ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.auditlog ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.auditlog_details ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.events ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.acknowledges ';
    $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.escalations ';
    // Support Zabbix 3.4.3
    if (ZABBIX_VERSION >= 3403) {
        $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.problem ';
        $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.problem_tag ';
        $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.event_recovery ';
        $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.task_acknowledge ';
        $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.task_close_problem ';
        $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.task_remote_command ';
        $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.task_remote_command_result ';
        // Support Zabbix 4.0.1
        if (ZABBIX_VERSION >= 4001) {
            $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.event_suppress ';
            $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.task_check_now ';
        }
        $ignore_tables .= '--ignore-table=' . ZABBIX_DB_NAME . '.task ';
    }

    $cmd = "nice -n 10 mysqldump -u " . ZABBIX_DB_USER . " -p" . ZABBIX_DB_PASSWORD .
     " --single-transaction --hex-blob " . $ignore_tables . ' ' . ZABBIX_DB_NAME . 
     " | gzip > " . $file_path;
    $res = [];
    echo "Execute: " . $cmd. "\n";
    exec($cmd, $res, $res_code);
    if ($res_code != 0) {
        echo "Error: Return Code " . $res_code;
        exit($res_code);
    }
    echo "Backup Completed.\n";
}
 
function execRollback($file_path) {
    
    if (! file_exists($file_path)) {
        echo "ERROR: Backup File Not Found\n";
        exit(1);
    }
    if (! is_readable($file_path)) {
        echo "ERROR: Backup File is not readable\n";
        exit(1);
    }
    // file name format check
    $file_name = basename($file_path);
    if (! preg_match('/\Azabbix_config_only_[0-9]{8}_[0-9]{6}.sql.gz\z/', $file_name)) {
        echo "ERROR: File Name Format Error\n";
        echo "Only backup created with this tool is supported\n";
        exit(1);
    }
    $year = mb_substr($file_name, 19, 4);
    $month = mb_substr($file_name, 23, 2);
    $day = mb_substr($file_name, 25, 2);
    $hour = mb_substr($file_name, 28, 2);
    $minutes = mb_substr($file_name, 30, 2);
    $second = mb_substr($file_name, 32, 2);
    
    $target_datetime = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minutes . ':' . $second;
    
    echo "Roll Back Target DateTime : " . $target_datetime . "\n";
    $target_unix_time = strtotime($target_datetime);
    if ($target_unix_time === false) {
        echo "ERROR: Roll Back Target DateTime Error\n";
        exit(1);
    }
    echo "Roll Back Target UnixTime : " . $target_unix_time . "\n";
    echo "Would you like to roll back? (Y/n)\n";
    while(true) {
        $line = trim(fgets(STDIN));
        if(!strcmp($line, "Y"))
        {
            break;
        }
        if(!strcasecmp($line, "n"))
        {
            echo "Rollback Canceled.\n";
            exit(0);
        }
        echo "(Y/n)? ";
    }
    echo "Rollback Start\n";
 
    if (!class_exists('PDO')) {
        echo "Error: Class Not Found: PDO\n";
        exit(1);
    }
    try {
        $dsn = 'mysql:dbname=' . ZABBIX_DB_NAME . ';host=' . ZABBIX_DB_HOST;
        $dbh = new PDO($dsn, ZABBIX_DB_USER, ZABBIX_DB_PASSWORD);
 
        // Delete logs recorded after backup
        $count = $dbh->exec('DELETE FROM history WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'history deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE FROM history_uint WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'history_uint deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE FROM trends_uint WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'trends_uint deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE FROM trends WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'trends deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE FROM history_str WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'history_str deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE FROM history_text WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'history_text deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE FROM history_log WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'history_log deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE FROM alerts WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'alerts deleted ' . $count . " rows.\n";

        // fix table relation events and acknowledges
        $count = $dbh->exec(<<<EOQ
            UPDATE events 
                LEFT JOIN (SELECT *, (comments_count - comments_count_new) AS diff_comments_count FROM events 
                    LEFT JOIN (SELECT eventid, COUNT(*) AS comments_count_new 
                        FROM (SELECT * FROM acknowledges WHERE clock > {$dbh->quote($target_unix_time)}) AS ack_new GROUP BY(eventid) 
                        ) AS ack_new_counts USING (eventid) 
                    LEFT JOIN (SELECT eventid, COUNT(*) AS comments_count FROM acknowledges AS ack_new GROUP BY(eventid) 
                        ) AS ack_counts USING (eventid) 
                    WHERE acknowledged > 0 AND (comments_count - comments_count_new) <= 0 AND comments_count_new >= 1 
                ) AS new_filter USING (eventid) 
            SET events.acknowledged = 0 
            WHERE events.acknowledged = 1 AND diff_comments_count = 0
EOQ
        );
        echo 'events fix updated ' . $count . " rows.\n";
        
        // Support Zabbix 3.4.3
        if (ZABBIX_VERSION >= 3403) {
            $count = $dbh->exec('DELETE FROM problem WHERE clock >=' . $dbh->quote($target_unix_time));
            echo 'problem deleted ' . $count . " rows.\n";
            $count = $dbh->exec('UPDATE problem SET r_eventid = NULL, r_clock = 0, r_ns = 0, userid = NULL WHERE r_clock >=' .
                $dbh->quote($target_unix_time));
            echo 'problem fix updated ' . $count . " rows.\n";

            $count = $dbh->exec('DELETE p_t FROM problem_tag AS p_t LEFT JOIN events USING(eventid) WHERE events.clock >=' .
                $dbh->quote($target_unix_time));
            echo 'problem_tag deleted ' . $count . " rows.\n";

            $count = $dbh->exec('DELETE e_r FROM event_recovery AS e_r LEFT JOIN events ON e_r.r_eventid = events.eventid ' .
                ' WHERE events.clock >=' . $dbh->quote($target_unix_time));
            echo 'event_recovery deleted ' . $count . " rows.\n";

            $count = $dbh->exec('DELETE t_ack FROM task_acknowledge AS t_ack LEFT JOIN task USING(taskid) ' .
                ' WHERE task.clock >=' . $dbh->quote($target_unix_time));
            echo 'task_acknowledge deleted ' . $count . " rows.\n";
            $count = $dbh->exec('DELETE t_c_p FROM task_close_problem AS t_c_p LEFT JOIN task USING(taskid) ' .
                ' WHERE task.clock >= ' . $dbh->quote($target_unix_time));
            echo 'task_acknowledge deleted ' . $count . " rows.\n";
            $count = $dbh->exec('DELETE t_r_c FROM task_remote_command AS t_r_c LEFT JOIN task USING(taskid) ' .
                ' WHERE task.clock >= ' . $dbh->quote($target_unix_time));
            echo 'task_remote_command deleted ' . $count . " rows.\n";
            $count = $dbh->exec('DELETE t_r_c_r FROM task_remote_command_result AS t_r_c_r LEFT JOIN task USING(taskid) ' .
                ' WHERE task.clock >= ' . $dbh->quote($target_unix_time));
            echo 'task_remote_command_result deleted ' . $count . " rows.\n";

            if (ZABBIX_VERSION >= 4001) {
                $count = $dbh->exec('DELETE FROM task_check_now LEFT JOIN task USING(taskid) ' .
                    ' WHERE task.clock >= ' . $dbh->quote($target_unix_time));
                echo 'task_check_now deleted ' . $count . " rows.\n";
            }

            $count = $dbh->exec('DELETE FROM task WHERE clock >=' . $dbh->quote($target_unix_time));
            echo 'task deleted ' . $count . " rows.\n";
        }

        $count = $dbh->exec('DELETE escs FROM escalations AS escs LEFT JOIN events ON escs.eventid = events.eventid ' .
            ' WHERE events.clock >= ' . $dbh->quote($target_unix_time));
        echo 'escalations (1) deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE escs FROM escalations AS escs LEFT JOIN events ON escs.r_eventid = events.eventid ' .
            ' WHERE events.clock >=' . $dbh->quote($target_unix_time));
        echo 'escalations (2) deleted ' . $count . " rows.\n";

        if (ZABBIX_VERSION >= 4001) {
            $count = $dbh->exec('DELETE FROM event_suppress LEFT JOIN events ON event_suppress.eventid = events.eventid ' .
                ' WHERE events.clock >= ' . $dbh->quote($target_unix_time));
            echo 'event_suppress deleted ' . $count . " rows.\n";
        }

        $count = $dbh->exec('DELETE FROM events WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'events deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE FROM acknowledges WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'acknowledges deleted ' . $count . " rows.\n";
 
        // You need to delete auditlog_details before deleting auditlog.
        $count = $dbh->exec('DELETE ad FROM auditlog_details AS ad LEFT JOIN auditlog USING(auditid) ' .
            ' WHERE auditlog.clock >=' . $dbh->quote($target_unix_time));
        echo 'auditlog_details deleted ' . $count . " rows.\n";
        $count = $dbh->exec('DELETE FROM auditlog WHERE clock >=' . $dbh->quote($target_unix_time));
        echo 'auditlog deleted ' . $count . " rows.\n";
 
    } catch (Exception $e) {
        echo 'Error: Any DB Exception ' . $e->getMessage() . "\n";
        exit(1);
    }
 
    // DataBase Import
    $cmd = "zcat " . $file_path . " | mysql -u " . ZABBIX_DB_USER . " -p" . ZABBIX_DB_PASSWORD . ' ' . ZABBIX_DB_NAME;
    $res = [];
    echo "Execute: " . $cmd. "\n";
    exec($cmd, $res, $res_code);
    if ($res_code != 0) {
        echo "Error: Return Code " . $res_code;
        exit($res_code);
    }
    echo "Rollback Completed.\n";
}
