<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

abstract class baseCmd
{

    function setDB()
    {
        $dbConfig = require APP_PATH . '/config/db.php';
        $capsule = new DB;
        $capsule->addConnection($dbConfig);
        // $configIasset = $dbConfigAll['iasset'];
        // $capsule->addConnection($configIasset, 'iasset');
        $capsule->setEventDispatcher(new Dispatcher(new Container));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        DB::connection()->enableQueryLog();    // 开启调试
    }

    function phpmailer($user, $subject, $content, $file = '')
    {
        $mailConfig = include APP_PATH . '/config/mail.php';
        $mail = new PHPMailer;
        $mail->isSMTP();
        $mail->SMTPDebug = false;
        $mail->SMTPAuth = true;
        $mail->SMTPKeepAlive = true;
        $mail->SMTPSecure = 'ssl';
        $mail->Host = $mailConfig['host'];
        $mail->Username = $mailConfig['username'];
        $mail->Password = $mailConfig['password'];
        $mail->Port = $mailConfig['port'];
        $mail->CharSet = "utf-8";
        $mail->SMTPDebug  = 2;
        $mail->Debugoutput = function ($str, $level) {
            if (preg_match('/CLIENT\s->\sSERVER:\s.{76}/i', $str)) return;
            file_put_contents(APP_PATH . '/cache/log/smtp.log', date('Y-m-d H:i:s'). "\t$level\t$str\n", FILE_APPEND | LOCK_EX);
        };
        $mail->setFrom($mailConfig['username'], $mailConfig['username']);
        return $mail;
    }

    function log()
    {
        $logFile = APP_PATH . "/cache/log/{$GLOBALS['argv'][1]}.log";
        writeFileLog($logFile, func_get_args());
    }
}