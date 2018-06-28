<?php

use phpFastCache\CacheManager;

/**
 * 网络好的地方执行
 * 将lantern.exe & apk 发送到邮箱
 * 以供网络不好的地方使用
 */
class sendLanternEmailCmd extends baseCmd
{

    protected $commitsUrl = 'https://api.github.com/repos/getlantern/lantern-binaries/commits';
    protected $exeUrl = 'https://raw.githubusercontent.com/getlantern/lantern-binaries/master/lantern-installer-beta.exe';
    // protected $dmgUrl = 'https://raw.githubusercontent.com/getlantern/lantern-binaries/master/lantern-installer-beta.dmg';
    // protected $apkUrl = 'https://raw.githubusercontent.com/getlantern/lantern-binaries/master/lantern-installer-beta.apk';

    function __construct()
    {
        CacheManager::setup(array(
            'path' => APP_PATH . '/cache/',
        ));
        $this->InstanceCache = CacheManager::getInstance('files');
    }

    function run()
    {
        // 获取缓存
        $key = 'lantern_last_commits';
        $cacheCommits = $this->InstanceCache->getItem($key);
        $oldCommits = $cacheCommits->get();

        // 获取最新commit
        $res = simpleCurl($this->commitsUrl);
        $lastCommits = json_decode($res, true)[0];

        if (empty($lastCommits)) {
            $this->log(401, 'Get commit error', $res);
            return;
        }
        if ($oldCommits['sha'] == $lastCommits['sha']) {
            $this->log(402, 'No commit', $lastCommits['commit']['message']);
            return;
        }

        // 发送邮件
        $exeFile = APP_PATH . "/cache/files/{$lastCommits['sha']}.exe";
        $log['down_exe'] = downloadUrl($this->exeUrl, $exeFile);
        $subject = $content = $lastCommits['commit']['message'] ?: 'New Lantern';
        $content .= '<br><br><br><br>' . indentToJson([
                $lastCommits['sha'],
                $lastCommits['commit'],
            ]);
        $content .= '<br><br><br><br>' . indentToJson([
                $oldCommits['sha'],
                $oldCommits['commit'],
            ]);

        $mailClass = $this->phpmailer();
        $mailClass->addAddress('wilonx@163.com', '王伟龙');
        $mailClass->Subject = $subject;
        $mail->Body = $content;
        $mail->AltBody = $content;
        $mailClass->MsgHTML($content);
        $mailClass->addAttachment($exeFile);
        $log['send_mail'] = $mailClass->Send();
        $log['subject'] = $subject;
        $this->log(200, 'New commit', $log);
        if ($log['send_mail'] === true) {
            // 设置缓存
            $cacheCommits->set($lastCommits)->expiresAfter(86400 * 365 * 10);
            $this->InstanceCache->save($cacheCommits);
        } else {
            throw new  Exception('Send email error', 1);
        }
    }

}
