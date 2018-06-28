<?php

use Illuminate\Database\Capsule\Manager as DB;
use DiDom\Document;

class followGithubUserCmd extends baseCmd
{

    function __construct()
    {
        // 配置
        $this->setDB();
        $githubConfig = include APP_PATH . '/config/github.php';
        $this->username = $githubConfig['username'];
        $this->password = $githubConfig['password'];
        $this->tablename = $githubConfig['tablename'];
        $this->addUserList = [];
    }

    function run()
    {
        $this
            ->login()
            ->doUnFollow()
            ;
    }

    protected function login()
    {
        // 更新cookie
        echo date('Y-m-d H:i:s') . " Start...\n";
        $loginUrl = "https://github.com/login";
        $loginHtml = simpleCurl($loginUrl);
        if ($loginHtml == '<html><body>You are being <a href="https://github.com/">redirected</a>.</body></html>') {
            echo "Logined...\n";
            return $this;
        }
        $token = $this->_getUserToken($loginHtml)[0];
        echo "Get authenticity_token ———— $token...\n";
        $loginData = [
            'commit' => 'Sign in',
            'utf8' => '%E2%9C%93',
            'authenticity_token' => $token,
            'login' => $this->username,
            'password' => $this->password
        ];
        // post数据登录
        $loginParams = [
                'header' => $this->_getHeaders(),
                'method' => 'post',
                'data' => $loginData,
            ];
        $html = simpleCurl('https://github.com/session', $loginParams);
        if ($html == '<html><body>You are being <a href="https://github.com/">redirected</a>.</body></html>') {
            echo "Login success...\n";
        } else {
            echo "Login error...\n$html\n";die;
        }
        return $this;
    }

    protected function getCollectUser()
    {
        $this->collectUser = DB::table($this->tablename)
                ->where('spidered', 0)
                ->first();
        $this->collectUser->username = $this->collectUser->username ?: 'itwanggj';
        echo "Collect User <{$this->collectUser->username}> ...\n";
        $this->log(100, "Collect User {$this->collectUser->username}");
        return $this;
    }

    protected function doFollow()
    {
        $collectUser = &$this->collectUser;
        $collectedTab = 0;
        foreach (['followers', 'following'] as $tab) {
            // 是否已采
            $tabFiled = $tab == 'followers' ? 'followers_page' : 'following_page';
            $page = $collectUser->{$tabFiled};
            if ($page < 0) {
                $collectedTab++;
                continue;
            };
            $count = 0;
            $page = $page == 0 ? 1 : $page;
            do {
                echo "============= $tab page $page =============\n";
                $url = "https://github.com/{$collectUser->username}?page=$page&tab=$tab";
                $params = [
                        'header' => $this->_getHeaders(),
                    ];
                $html = '';
                $html = simpleCurl($url, $params);
                $userList = [];
                $userList = $this->_getUserList($html);
                if (empty($userList)) {
                    $collectUser->{$tabFiled} = -1;
                    $collectedTab++;
                    break;
                }
                foreach ($userList as $username => $token) {
                    if (!$username) continue;
                    echo "    User $username ";
                    if (!$token) continue;
                    $hasFollowed = DB::table($this->tablename)
                        ->where('username', $username)
                        ->first()
                        ->followed;
                    if ($hasFollowed > 0) {
                        echo "followed\n";
                        continue;
                    };
                    $res = $this->_followUser($username, $token);
                    if ($res == '<html><body>You are being <a href="https://github.com/">redirected</a>.</body></html>') {
                        $this->log(200, "Follow $username success...");
                        echo "Follow success...\n";
                        $this->addUserList[] = [
                            'username' => $username,
                            'followed' => 1,
                            'date' => time(),
                        ];
                    } else {
                        echo "Follow error...\n";
                    }
                }
                $page++;
                $count++;
                $collectUser->{$tabFiled} = $page;
            } while ($userList && $count < 3);
        }
        if ($collectedTab === 2) {
            $collectUser->spidered = 1;
        }
    }

    protected function doUnFollow()
    {
        $num = 1;
        $page = $GLOBALS['argv'][1] ?: 1;
        do {
            $url = "https://github.com/wilon?page=10&tab=following";
            $params = [
                    'header' => $this->_getHeaders(),
                ];
            $html = '';
            $html = simpleCurl($url, $params);
            $userList = [];
            $userList = $this->_getUserList($html, true);
            if (empty($userList)) {
                die('Success!');
                break;
            }
            foreach ($userList as $username => $token) {
                if (!$username) continue;
                echo "    User $username ";
                $res = $this->_unFollowUser($username, $token);
                if ($res == '<html><body>You are being <a href="https://github.com/">redirected</a>.</body></html>') {
                    echo "UnFollow success...\n";
                } else {
                    echo "UnFollow error...\n";
                }
                $num++;
            }
            echo "Done num $num";
        } while (true);
    }

    private function _followUser($username, $token)
    {
        $params = [
                'header' => $this->_getHeaders(),
                'method' => 'post',
                'data' => [
                    'utf8' => '%E2%9C%93',
                    'authenticity_token' => $token
                ],
            ];
        $html = simpleCurl("https://github.com/users/follow?target=$username", $params);
        return $html;
    }

    private function _unFollowUser($username, $token)
    {
        $params = [
                'header' => $this->_getHeaders(),
                'method' => 'post',
                'data' => [
                    'utf8' => '%E2%9C%93',
                    'authenticity_token' => $token
                ],
            ];
        $html = simpleCurl("https://github.com/users/unfollow?target=$username", $params);
        return $html;
    }
    private function _getUserToken($html)
    {
        $doc = new Document($html);
        $list = $doc->find('input[name=authenticity_token]');
        foreach($list as $li) {
            $return[] = $li->attr('value');
        }
        return $return;
    }

    private function _getUserList($html, $unfollow = false)
    {
        $doc = new Document($html);
        $table = $doc->find('.py-4');
        $input = $unfollow == true ? 1 : 0;
        foreach($table as $k => $li) {
            $username = $li->find('.link-gray::text')[0];
            $token = $li->find('input[name=authenticity_token]')[$input]->value ?: '';
            $return[$username] = $token;
        }
        return $return;
    }

    private function _getHeaders($referer = false)
    {
        $headers[] = 'User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36';
        $headers[] = 'Connection:keep-alive';
        $headers[] = 'Cache-Control:max-age=0';
        $headers[] = 'Accept-Language:zh-CN,zh;q=0.8,en;q=0.6';
        $headers[] = 'Accept:*/*';
        if ($referer) $headers[] = "Referer: $referer";
        return $headers;
    }

    function __destruct()
    {
        // echo "\nupdate db : ";
        // $this->addUserList[] = $this->collectUser;
        // foreach ($this->addUserList as $user) {
        //     echo (int) $this->updateUser($user), ', ';
        // }
    }

    function updateUser($user)
    {
        $user = json_decode(json_encode($user), true);
        if (empty($user['username'])) return false;
        if (empty($user['date'])) $user['date'] = time();
        if (DB::table($this->tablename)->where('username', $user['username'])->first()) {
            return DB::table($this->tablename)
                ->where('username', $user['username'])
                ->update($user);
        } else {
            return DB::table($this->tablename)
                ->insertGetId($user);
        }
    }
}
