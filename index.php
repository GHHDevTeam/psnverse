<?php
session_start();

$routes = [
    'rule'      => 'rule',
    'signup'    => 'signup', 
    'signin'    => 'signin',
    'signout'   => 'signout',
    'id'        => 'detail',
    'changelog' => 'changelog',
    'topic'     => 'topic',
    'top'       => 'index',
    'account'   => 'account',
    'user'      => 'user'
];

$page = 'index';
foreach ($routes as $key => $val) {
    if (isset($_GET[$key])) {
        $page = $val;
        break;
    }
}

// /user/ID 形式のURL処理
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
if (preg_match('/\/user\/([a-zA-Z0-9_]+)\/?/', $request_uri, $matches)) {
    $_GET['user'] = $matches[1];
    $page = 'user';
}

if ($page === 'signout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// HTMLヘッダー
function render_header($title = 'Psnverse') {
    $current_user = $_SESSION['user'] ?? null;
    echo '<!DOCTYPE html><html><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<link rel="stylesheet" href="main.css" type="text/css">';
    echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>';
    echo '<script>$(document).ready(function () {
        var $nav = $("mb > ul > li");
        $nav.hover(
            function() {
                $(this).children("a").addClass("hovered");
                $("ul", this).stop(true, true).slideDown("fast");
            },
            function() {
                $(this).children("a").removeClass("hovered"); 
                $("ul", this).slideUp("fast");
            }
        );
    });</script>';
    echo '</head><body>';
    
    // ナビ
    echo '<mb><ul>';
    echo '<li><left><a href="./">&nbsp;&nbsp;&nbsp;Psnverse&nbsp;&nbsp;&nbsp;</a></left></li>';
    echo '<li><left><a href="./?topic">&nbsp;&nbsp;&nbsp;<font size="2">トピック</font>&nbsp;&nbsp;&nbsp;</a></left></li>';
    echo '<li><right><a>&nbsp;&nbsp;&nbsp;≡&nbsp;&nbsp;&nbsp;</a></right>';
    echo '<ul class="smb">';
    echo '<li><a href="./?top">トップページ</a></li>';
    echo '<li><a href="./?rule">ガイド・ルール</a></li>';
    echo '<li><a href="./?changelog">更新履歴</a></li>';
    if ($current_user) {
        echo '<li><a href="?account">アカウント</a></li>';
        echo '<li><a href="?signout">ログアウト</a></li>';
    } else {
        echo '<li><a href="?signin">ログイン</a></li>';
        echo '<li><a href="./?signup">アカウント作成</a></li>';
    }
    echo '</ul></li>';
    if (!$current_user) {
        echo '<li><right><a href="?signin">&nbsp;&nbsp;&nbsp;<font size="2">ログイン</font>&nbsp;&nbsp;&nbsp;</a></right></li>';
    } else {
        echo '<li><right><a href="?signout">&nbsp;&nbsp;&nbsp;<font size="2">ログアウト</font>&nbsp;&nbsp;&nbsp;</a></right></li>';
    }
    echo '</ul></mb><br><br>';
}

function render_footer() {
    echo '<br><br><br><br><br><br>';
    echo '<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Psnverse ©2020-2025 Kao_766 (Pitan)</p>';
    echo '</body></html>';
}

// IPからゲストID生成
function generate_guest_id($ip) {
    $hash = hash('sha256', $ip . 'psnverse_salt');
    return substr($hash, 0, 8);
}

// URL自動リンク化
function auto_link($text) {
    $url_pattern = '/(?:(?:https?:\/\/)|(?:www\.))(?:[a-zA-Z0-9\-\.]+)(?:\.[a-zA-Z]{2,})(?:[\/\?\#][^\s<>\[\]{}|\\^`]*)?/i';
    
    $text = preg_replace_callback($url_pattern, function($matches) {
        $url = $matches[0];
        
        if (!preg_match('/^https?:\/\//', $url)) {
            $full_url = 'http://' . $url;
        } else {
            $full_url = $url;
        }
        
        return '<a href="' . htmlspecialchars($full_url) . '" target="_blank" rel="noopener">' . htmlspecialchars($url) . '</a>';
    }, $text);
    
    return $text;
}

// テキスト処理
function format_text($text) {
    $text = htmlspecialchars($text);
    $text = auto_link($text);
    $text = nl2br($text);
    return $text;
}

$posts_file = __DIR__ . '/posts.json';
$users_file = __DIR__ . '/users.json';
$topics_file = __DIR__ . '/topics.json';

// トピック一覧
function get_topics($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

// トピック追加
function add_topic($file, $title, $description, $author, $author_id) {
    $topics = get_topics($file);
    $id = uniqid();
    $topic = [
        'id' => $id,
        'title' => $title,
        'description' => $description,
        'author' => $author,
        'author_id' => $author_id,
        'created' => date('Y年m月d日 H:i'),
        'post_count' => 0
    ];
    $topics[] = $topic;
    file_put_contents($file, json_encode($topics, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    return $id;
}

// トピック取得
function get_topic_by_id($file, $id) {
    $topics = get_topics($file);
    foreach ($topics as $topic) {
        if ($topic['id'] === $id) {
            return $topic;
        }
    }
    return null;
}

// トピック削除
function delete_topic($file, $topic_id, $user_id) {
    $topics = get_topics($file);
    $updated_topics = [];
    $deleted = false;
    
    foreach ($topics as $topic) {
        if ($topic['id'] === $topic_id && $topic['author_id'] === '@' . $user_id) {
            $deleted = true;
            continue; // このトピックを除外
        }
        $updated_topics[] = $topic;
    }
    
    if ($deleted) {
        file_put_contents($file, json_encode($updated_topics, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    }
    
    return $deleted;
}

// トピック別投稿数更新
function update_topic_post_count($file, $topic_id) {
    $topics = get_topics($file);
    foreach ($topics as &$topic) {
        if ($topic['id'] === $topic_id) {
            // このトピックの投稿数をカウント
            $posts = get_posts($GLOBALS['posts_file']);
            $count = 0;
            foreach ($posts as $post) {
                if (isset($post['topic_id']) && $post['topic_id'] === $topic_id) {
                    $count++;
                }
            }
            $topic['post_count'] = $count;
            file_put_contents($file, json_encode($topics, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            break;
        }
    }
}

// 投稿一覧
function get_posts($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

// 投稿詳細
function get_post_by_id($file, $id) {
    $posts = get_posts($file);
    foreach ($posts as $post) {
        if (isset($post['id']) && $post['id'] == $id) return $post;
    }
    return null;
}

// ユーザー一覧
function get_users($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

// ユーザー登録
function add_user($file, $username, $user_id, $password) {
    $users = get_users($file);
    foreach ($users as $u) {
        if ($u['user_id'] === $user_id) return false;
    }
    $users[] = [
        'username' => $username,
        'user_id' => $user_id,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'bio' => ''
    ];
    file_put_contents($file, json_encode($users, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    return true;
}

// ログイン
function check_login($file, $user_id, $password) {
    $users = get_users($file);
    foreach ($users as $u) {
        if ($u['user_id'] === $user_id && password_verify($password, $u['password'])) {
            return $u;
        }
    }
    return false;
}

// ユーザー情報更新
function update_user($file, $old_user_id, $new_username, $new_user_id, $new_password, $bio) {
    $users = get_users($file);
    foreach ($users as &$user) {
        if ($user['user_id'] === $old_user_id) {
            // ID重複チェック
            if ($new_user_id !== $old_user_id) {
                foreach ($users as $u) {
                    if ($u['user_id'] === $new_user_id && $u['user_id'] !== $old_user_id) {
                        return false;
                    }
                }
            }
            
            $user['username'] = $new_username;
            $user['user_id'] = $new_user_id;
            if ($new_password) {
                $user['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            $user['bio'] = $bio;
            file_put_contents($file, json_encode($users, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            return true;
        }
    }
    return false;
}

// ユーザー取得
function get_user_by_id($file, $user_id) {
    $users = get_users($file);
    foreach ($users as $user) {
        if ($user['user_id'] === $user_id) {
            return $user;
        }
    }
    return null;
}

// 投稿追加
function add_post($file, $title, $body, $author, $author_id, $image = null, $topic_id = null) {
    $posts = get_posts($file);
    $id = uniqid();
    $post = [
        'id' => $id,
        'title' => $title,
        'body' => $body,
        'author' => $author,
        'author_id' => $author_id,
        'created' => date('Y年m月d日 H:i'),
        'good_count' => 0,
        'reply_count' => 0,
        'replies' => []
    ];
    if ($image) {
        $post['image'] = $image;
    }
    if ($topic_id) {
        $post['topic_id'] = $topic_id;
    }
    $posts[] = $post;
    file_put_contents($file, json_encode($posts, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    return $id;
}

// ゲスト投稿（パスワード付き）
function add_guest_post($file, $title, $body, $author, $author_id, $password, $image = null, $topic_id = null) {
    $posts = get_posts($file);
    $id = uniqid();
    $post = [
        'id' => $id,
        'title' => $title,
        'body' => $body,
        'author' => $author,
        'author_id' => $author_id,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created' => date('Y年m月d日 H:i'),
        'good_count' => 0,
        'reply_count' => 0,
        'replies' => []
    ];
    if ($image) {
        $post['image'] = $image;
    }
    if ($topic_id) {
        $post['topic_id'] = $topic_id;
    }
    $posts[] = $post;
    file_put_contents($file, json_encode($posts, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    return $id;
}

// グッド処理
function toggle_good($file, $post_id, $user_id) {
    $posts = get_posts($file);
    foreach ($posts as &$post) {
        if ($post['id'] === $post_id) {
            if (!isset($post['goods'])) $post['goods'] = [];
            $key = array_search($user_id, $post['goods']);
            if ($key !== false) {
                unset($post['goods'][$key]);
                $post['goods'] = array_values($post['goods']);
                $post['good_count'] = max(0, $post['good_count'] - 1);
            } else {
                $post['goods'][] = $user_id;
                $post['good_count'] = ($post['good_count'] ?? 0) + 1;
            }
            file_put_contents($file, json_encode($posts, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            return $post['good_count'];
        }
    }
    return 0;
}

// 返信追加
function add_reply($file, $post_id, $body, $author, $author_id) {
    $posts = get_posts($file);
    foreach ($posts as &$post) {
        if ($post['id'] === $post_id) {
            if (!isset($post['replies'])) $post['replies'] = [];
            $reply = [
                'id' => uniqid(),
                'body' => $body,
                'author' => $author,
                'author_id' => $author_id,
                'created' => date('Y年m月d日 H:i')
            ];
            $post['replies'][] = $reply;
            $post['reply_count'] = count($post['replies']);
            file_put_contents($file, json_encode($posts, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            return true;
        }
    }
    return false;
}

// 画像アップロード
function handle_image_upload() {
    if (isset($_FILES['post_pv_image']) && $_FILES['post_pv_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['post_pv_image']['name']);
        $extension = strtolower($file_info['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($extension, $allowed)) {
            $filename = hash('sha256', uniqid() . $_FILES['post_pv_image']['name']) . '.' . $extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['post_pv_image']['tmp_name'], $upload_path)) {
                return 'uploads/' . $filename;
            }
        }
    }
    return null;
}

switch ($page) {
    case 'rule':
        render_header('ガイド・ルール - Psnverse');
        echo <<<EOD
<center>
<h2>禁止事項</h2>
<ui>
<li>荒らし行為</li>
<li>不快、不適切、差別、暴力的な内容</li>
<li>誹謗中傷、イジメ</li>
<li>自分自身、または他人を特定できるプライバシーの拡散</li>
<li>成り済まし行為</li>
<li>運営に報告されていることを分かっていた上で対応しない</li>
<li>その他悪質行為</li>
</ui>
<h2>処罰について</h2>
<p>利用制限やアクセス禁止(BAN)、IPアクセス禁止(IPBAN)などの処罰をします。<br>
期間は1日、3日、1週間ですが、余りにも酷かったり、何回も同じことをしていたりすると1ヶ月、半年、1年、もしくは半永久的に処罰を受ける可能性があります。</p>
<h2>当サイトについて</h2>
<p>※Psnverseは個人が運営していますのでPlayStation様などは当サイトとの関係は一切ありません。<br>当サイトではPSN ID交換や、SNSとして使うことができます。</p>
</center>
EOD;
        render_footer();
        break;

    case 'changelog':
        render_header('更新履歴 - Psnverse');
        echo <<<EOD
<center>
<h2>更新履歴</h2>
<p>2025年7月27日 - Psnverse復刻版リリース</p>
<p>2020年8月14日 - <a href="https://web.archive.org/web/20200817074142/http://psnverse.66ghz.com/?changelog">オリジナル版最終更新</a></p>
</center>
EOD;
        render_footer();
        break;

    case 'signup':
        render_header('アカウント登録 - Psnverse');
        
        // サインアップ処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_button'])) {
            $username = trim($_POST['user_name'] ?? '');
            $user_id = trim($_POST['user_id'] ?? '');
            $password = $_POST['user_password'] ?? '';
            
            if ($username && $user_id && $password) {
                if (add_user($users_file, $username, $user_id, $password)) {
                    echo '<center><p>登録完了。<a href="?signin">ログイン</a></p></center>';
                } else {
                    echo '<center><p>そのユーザーIDは既に使われています。</p></center>';
                }
            } else {
                echo '<center><p>全て入力してください。</p></center>';
            }
        }
        
        echo <<<EOD
<center><h2>アカウント作成</h2>
<form action="" method="post">
<ele class="design1">
<br><p>ユーザー名(ディスプレイネーム)</p>
<input type="text" id="user_name" name="user_name" placeholder="ユーザー名" required/>
<br><br><p>ユーザーID(ログインID)</p>
<input type="text" id="user_id" name="user_id" placeholder="ユーザーID" maxlength="32" required/>
<br><br><p>パスワード</p>
<input type="password" id="user_password" name="user_password" placeholder="パスワード" required/>
<br><br>
</ele>
<ele class="designb">
<input type="submit" value="登録" id="signup_button" name="signup_button" style="width: 180px;"/>
</ele>
</form>
<br>既に持っているアカウントに接続するには<a href="./?signin">ログイン</a>
</center>
EOD;
        render_footer();
        break;

    case 'signin':
        render_header('ログイン - Psnverse');
        
        // サインイン処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_button'])) {
            $user_id = trim($_POST['user_id'] ?? '');
            $password = $_POST['user_password'] ?? '';
            
            if ($user_id && $password) {
                $user = check_login($users_file, $user_id, $password);
                if ($user) {
                    $_SESSION['user'] = $user['username'];
                    $_SESSION['user_id'] = $user['user_id'];
                    header('Location: index.php');
                    exit;
                } else {
                    echo '<center><p>ログイン失敗</p></center>';
                }
            } else {
                echo '<center><p>全て入力してください。</p></center>';
            }
        }
        
        echo <<<EOD
<center><h2>ログイン</h2>
<form action="" method="post">
<ele class="design1">
<br><p>ユーザーID(ログインID)</p>
<input type="text" id="user_id" name="user_id" placeholder="ユーザーID" maxlength="32" required/>
<br><br><p>パスワード</p>
<input type="password" id="user_password" name="user_password" placeholder="パスワード" required/>
<br><br>
</ele>
<ele class="designb">
<input type="submit" value="ログイン" id="login_button" name="login_button" style="width: 180px;"/>
</ele>
</form>
<br>新しくアカウントを登録するには<a href="./?signup">アカウント作成</a>
</center>
EOD;
        render_footer();
        break;

    case 'account':
        // アカウント編集（要ログイン）
        if (!isset($_SESSION['user'])) {
            header('Location: ?signin');
            exit;
        }
        
        $current_user = get_user_by_id($users_file, $_SESSION['user_id']);
        render_header('アカウント - Psnverse');
        
        // 更新処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
            $new_username = trim($_POST['username'] ?? '');
            $new_user_id = trim($_POST['user_id'] ?? '');
            $new_password = $_POST['password'] ?? '';
            $bio = trim($_POST['bio'] ?? '');
            
            if ($new_username && $new_user_id) {
                if (update_user($users_file, $_SESSION['user_id'], $new_username, $new_user_id, $new_password, $bio)) {
                    // セッション更新
                    $_SESSION['user'] = $new_username;
                    $_SESSION['user_id'] = $new_user_id;
                    echo '<center><p style="color:green;">アカウント情報を更新しました。</p></center>';
                    $current_user = get_user_by_id($users_file, $new_user_id);
                } else {
                    echo '<center><p style="color:red;">そのユーザーIDは既に使われています。</p></center>';
                }
            } else {
                echo '<center><p style="color:red;">ユーザー名とユーザーIDは必須です。</p></center>';
            }
        }
        
        $username = htmlspecialchars($current_user['username'] ?? '');
        $user_id = htmlspecialchars($current_user['user_id'] ?? '');
        $bio = htmlspecialchars($current_user['bio'] ?? '');
        
        echo <<<EOD
<center><h2>アカウント編集</h2>
<form action="" method="post">
<ele class="design1">
<br><p>ディスプレイネーム</p>
<input type="text" name="username" value="{$username}" placeholder="ディスプレイネーム" required/>
<br><br><p>ユーザーID</p>
<input type="text" name="user_id" value="{$user_id}" placeholder="ユーザーID" maxlength="32" required/>
<br><br><p>新しいパスワード（変更する場合のみ）</p>
<input type="password" name="password" placeholder="新しいパスワード"/>
<br><br><p>自己紹介</p>
<textarea name="bio" rows="4" cols="50" placeholder="自己紹介を入力...">{$bio}</textarea>
<br><br>
</ele>
<ele class="designb">
<input type="submit" value="更新" name="update_profile" style="width: 180px;"/>
</ele>
</form>
<br><a href="?user={$user_id}">プロフィールを表示</a>
</center>
EOD;
        render_footer();
        break;

    case 'user':
        // ユーザーページ
        $user_id = $_GET['user'] ?? '';
        $user = get_user_by_id($users_file, $user_id);
        
        render_header(($user ? htmlspecialchars($user['username']) : 'ユーザー') . ' - Psnverse');
        
        if ($user) {
            echo '<center>';
            echo '<h2>' . htmlspecialchars($user['username']) . '</h2>';
            echo '<p><grey_t>@' . htmlspecialchars($user['user_id']) . '</grey_t></p>';
            if (isset($user['bio']) && $user['bio']) {
                echo '<div style="margin: 20px 0;">';
                echo '<h3>自己紹介</h3>';
                echo '<p>' . format_text($user['bio']) . '</p>';
                echo '</div>';
            }
            
            // このユーザーの投稿
            $user_posts = [];
            $all_posts = get_posts($posts_file);
            foreach ($all_posts as $post) {
                if ($post['author_id'] === '@' . $user['user_id']) {
                    $user_posts[] = $post;
                }
            }
            
            if ($user_posts) {
                echo '<h3>投稿 (' . count($user_posts) . ')</h3>';
                echo '</center>';
                echo '<section>';
                foreach (array_reverse($user_posts) as $post) {
                    echo '<article>';
                    echo '<div class="link1">';
                    echo '<a href="?id=' . urlencode($post['id']) . '" style="text-decoration:none;color: black;">';
                    echo '<div class="info">';
                    echo '<h2 style="display:inline;"><object><a style="text-decoration:none;" href="?user=' . htmlspecialchars($user['user_id']) . '">' . htmlspecialchars($post['author']) . '</a></object></h2>';
                    echo ' <grey_t style="font-size:12.5px;">[ID_' . htmlspecialchars($post['author_id']) . ']</grey_t><br>';
                    echo '<time><grey_t>' . htmlspecialchars($post['created']) . '</grey_t></time><br><br>';
                    echo '</div>';
                    
                    if (isset($post['image'])) {
                        echo '<a href="' . htmlspecialchars($post['image']) . '" target="_blank">';
                        echo '<img class="post-image" src="' . htmlspecialchars($post['image']) . '"/></a><br>';
                    }
                    
                    echo '<p>' . format_text($post['body']) . '</p>';
                    echo '</a>';
                    echo '</div>';
                    echo '</article>';
                }
                echo '</section>';
            } else {
                echo '<p>まだ投稿がありません。</p>';
                echo '</center>';
            }
        } else {
            echo '<center><h1>ユーザーが見つかりません</h1></center>';
        }
        render_footer();
        break;

    case 'detail':
        $id = $_GET['id'] ?? '';
        $post = get_post_by_id($posts_file, $id);
        
        // 現在のユーザーID
        $current_user_id = $_SESSION['user_id'] ?? generate_guest_id($_SERVER['REMOTE_ADDR']);
        
        render_header('投稿ポスト - Psnverse');
        
        // グッド処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['good_button'])) {
            $good_count = toggle_good($posts_file, $_POST['good_button'], $current_user_id);
            header('Location: ?id=' . urlencode($id));
            exit;
        }
        
        // 返信処理
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_body'])) {
            $reply_body = trim($_POST['reply_body']);
            if ($reply_body) {
                $author = $_SESSION['user'] ?? trim($_POST['reply_author'] ?? 'ゲスト');
                add_reply($posts_file, $id, $reply_body, $author, $current_user_id);
                header('Location: ?id=' . urlencode($id));
                exit;
            }
        }
        
        // ゲスト投稿削除
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post']) && isset($_POST['delete_password'])) {
            $delete_password = $_POST['delete_password'];
            if ($post && strpos($post['author_id'], '@') === false && isset($post['password'])) {
                if (password_verify($delete_password, $post['password'])) {
                    // 投稿を削除
                    $posts = get_posts($posts_file);
                    $posts = array_filter($posts, function($p) use ($id) {
                        return $p['id'] !== $id;
                    });
                    file_put_contents($posts_file, json_encode(array_values($posts), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
                    header('Location: index.php');
                    exit;
                } else {
                    echo '<center><p style="color:red;">パスワードが間違っています。</p></center>';
                }
            }
        }
        
        if ($post) {
            echo '<section><article>';
            echo '<div class="link1">';
            echo '<div class="info">';
            if (strpos($post['author_id'], '@') === false) {
                // ゲストユーザーはリンクなし
                echo '<h2 style="display:inline;"><object>' . htmlspecialchars($post['author']) . '</object></h2>';
            } else {
                // 登録ユーザーはユーザーページへリンク
                echo '<h2 style="display:inline;"><object><a style="text-decoration:none;" href="?user=' . htmlspecialchars(ltrim($post['author_id'], '@')) . '">' . htmlspecialchars($post['author']) . '</a></object></h2>';
            }
            if (strpos($post['author_id'], '@') === false) {
                // ゲストID（@なし）
                echo ' <grey_t style="font-size:12.5px;">[ID_' . htmlspecialchars($post['author_id']) . ']</grey_t><br>';
            } else {
                // 登録ユーザー（@あり）
                echo ' <grey_t style="font-size:12.5px;">[ID_' . htmlspecialchars($post['author_id']) . ']</grey_t><br>';
            }
            echo '<time><grey_t>' . htmlspecialchars($post['created']) . '</grey_t></time><br><br>';
            echo '</div>';
            
            if (isset($post['image'])) {
                echo '<a href="' . htmlspecialchars($post['image']) . '" target="_blank">';
                echo '<img class="post-image" src="' . htmlspecialchars($post['image']) . '"/></a><br>';
            }
            
            echo '<p>' . format_text($post['body']) . '</p>';
            
            echo '<object>';
            echo '<div class="action_btn">';
            echo '<form style="display:inline" action="" method="post">';
            echo '<button name="good_button" type="submit" value="' . htmlspecialchars($post['id']) . '">';
            echo '<img src="./image/good.png" width="14" height="14" style="margin-right:4px;"/>';
            echo 'グッド ' . ($post['good_count'] ?? 0);
            echo '</button>';
            echo '</form>';
            echo '<form style="display:inline" action="?id=' . urlencode($post['id']) . '" method="get">';
            echo '<button type="submit">↰ 返信 ' . ($post['reply_count'] ?? 0) . '</button>';
            echo '</form>';
            
            // ゲスト投稿の削除ボタン
            if (strpos($post['author_id'], '@') === false) {
                echo '<form style="display:inline" action="" method="post">';
                echo '<input type="password" name="delete_password" placeholder="削除パスワード" size="15" style="margin-left:10px;"/>';
                echo '<button name="delete_post" type="submit" value="' . htmlspecialchars($post['id']) . '">削除</button>';
                echo '</form>';
            }
            echo '</div></object>';
            echo '</div>';
            echo '</article>';
            
            // 返信フォーム（すべてのユーザー）
            echo '<form method="post" style="margin:20px 0;">';
            if (!isset($_SESSION['user'])) {
                echo '<ele class="design1">';
                echo '<input type="text" name="reply_author" placeholder="お名前" size="30" required/><br>';
                echo '</ele>';
            }
            echo '<ele class="designt">';
            echo '<textarea name="reply_body" placeholder="返信を書く..." rows="3" cols="60" required></textarea><br>';
            echo '</ele>';
            echo '<button type="submit">返信する</button>';
            
            echo '</form>';
            
            // 返信一覧
            if (isset($post['replies']) && !empty($post['replies'])) {
                echo '<h3>返信</h3>';
                foreach ($post['replies'] as $reply) {
                    echo '<article>';
                    echo '<div class="info">';
                    if (strpos($reply['author_id'], '@') === false) {
                        // ゲストユーザーはリンクなし
                        echo '<strong>' . htmlspecialchars($reply['author']) . '</strong>';
                    } else {
                        // 登録ユーザーはユーザーページへリンク
                        echo '<strong><a href="?user=' . htmlspecialchars(ltrim($reply['author_id'], '@')) . '" style="text-decoration:none;">' . htmlspecialchars($reply['author']) . '</a></strong>';
                    }
                    if (strpos($reply['author_id'], '@') === false) {
                        // ゲストID（@なし）
                        echo ' <grey_t>[ID_' . htmlspecialchars($reply['author_id']) . ']</grey_t><br>';
                    } else {
                        // 登録ユーザー（@あり）
                        echo ' <grey_t>[ID_' . htmlspecialchars($reply['author_id']) . ']</grey_t><br>';
                    }
                    echo '<grey_t>' . htmlspecialchars($reply['created']) . '</grey_t><br>';
                    echo '</div>';
                    echo '<p>' . format_text($reply['body']) . '</p>';
                    echo '</article>';
                }
            }
            echo '</section>';
        } else {
            echo '<h1>投稿が見つかりません</h1>';
        }
        render_footer();
        break;

    case 'topic':
        render_header('トピック - Psnverse');
        
        // トピック作成処理（ログインユーザーのみ）
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic']) && isset($_SESSION['user'])) {
            $title = trim($_POST['topic_title'] ?? '');
            $description = trim($_POST['topic_description'] ?? '');
            
            if ($title && $description) {
                $topic_id = add_topic($topics_file, $title, $description, $_SESSION['user'], '@' . $_SESSION['user_id']);
                header('Location: ?topic&view=' . urlencode($topic_id));
                exit;
            }
        }
        
        // トピック削除処理（作成者のみ）
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_topic']) && isset($_SESSION['user'])) {
            $topic_id = $_POST['delete_topic'];
            if (delete_topic($topics_file, $topic_id, $_SESSION['user_id'])) {
                // トピック内の投稿も削除
                $posts = get_posts($posts_file);
                $updated_posts = array_filter($posts, function($post) use ($topic_id) {
                    return !isset($post['topic_id']) || $post['topic_id'] !== $topic_id;
                });
                file_put_contents($posts_file, json_encode(array_values($updated_posts), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
                header('Location: ?topic');
                exit;
            }
        }
        
        // 特定のトピック表示
        if (isset($_GET['view'])) {
            $topic_id = $_GET['view'];
            $topic = get_topic_by_id($topics_file, $topic_id);
            
            if ($topic) {
                // トピック内投稿処理
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_pv_value'])) {
                    $body = trim($_POST['post_pv_value']);
                    
                    if ($body) {
                        if (isset($_SESSION['user'])) {
                            $name = trim($_POST['poster_name'] ?? $_SESSION['user']);
                            $author_id = '@' . $_SESSION['user_id'];
                            $image = handle_image_upload();
                            $id = add_post($posts_file, '', $body, $name, $author_id, $image, $topic_id);
                        } else {
                            $name = trim($_POST['poster_name'] ?? 'ゲスト');
                            $password = $_POST['password'] ?? '';
                            if ($password) {
                                $guest_id = generate_guest_id($_SERVER['REMOTE_ADDR']);
                                $id = add_guest_post($posts_file, '', $body, $name, $guest_id, $password, null, $topic_id);
                            } else {
                                echo '<p style="color:red;">ゲスト投稿にはパスワードが必要です。</p>';
                            }
                        }
                        if (isset($id)) {
                            update_topic_post_count($topics_file, $topic_id);
                            header('Location: ?id=' . urlencode($id));
                            exit;
                        }
                    }
                }
                
                // グッド処理
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['good_button'])) {
                    $current_user_id = $_SESSION['user_id'] ?? generate_guest_id($_SERVER['REMOTE_ADDR']);
                    if (!isset($_SESSION['user_id'])) {
                        $current_user_id = $current_user_id;
                    } else {
                        $current_user_id = '@' . $current_user_id;
                    }
                    toggle_good($posts_file, $_POST['good_button'], $current_user_id);
                    header('Location: ?topic&view=' . urlencode($topic_id));
                    exit;
                }
                
                // トピック情報表示
                echo '<article class="topic-header">';
                echo '<h2>' . htmlspecialchars($topic['title']) . '</h2>';
                echo '<p>' . format_text($topic['description']) . '</p>';
                echo '<div class="topic-info">';
                echo '<span class="topic-author">作成者: ';
                if (strpos($topic['author_id'], '@') !== false) {
                    echo '<a href="?user=' . htmlspecialchars(ltrim($topic['author_id'], '@')) . '">' . htmlspecialchars($topic['author']) . '</a>';
                } else {
                    echo htmlspecialchars($topic['author']);
                }
                echo '</span>';
                echo ' | <span class="topic-created">' . htmlspecialchars($topic['created']) . '</span>';
                echo ' | <span class="topic-posts">投稿数: ' . ($topic['post_count'] ?? 0) . '</span>';
                
                // 削除ボタン（作成者のみ）
                if (isset($_SESSION['user']) && $topic['author_id'] === '@' . $_SESSION['user_id']) {
                    echo ' | <form style="display:inline;" method="post">';
                    echo '<button type="submit" name="delete_topic" value="' . htmlspecialchars($topic['id']) . '" onclick="return confirm(\'このトピックと全ての投稿を削除しますか？\')" style="color:red;">削除</button>';
                    echo '</form>';
                }
                echo '</div>';
                echo '</article>';
                
                // 投稿フォーム
                if (isset($_SESSION['user'])) {
                    $name_input = '<input id="poster_name" name="poster_name" type="text" placeholder="お名前" size="30" value="' . htmlspecialchars($_SESSION['user']) . '"/>
                    <input id="password" name="password" type="hidden" value=""/>';
                    $image_upload = '<div class="image-upload-container">
<ele class="designf">
<label style="width:51px;">画像<input type="file" name="post_pv_image" id="post_pv_image" onchange="selected_img.value = this.value.replace(/.*\\\\/g ,\'\');"/></label>
</ele>
<ele class="design1">
<input id="selected_img" type="text" value="画像が選択されていません。" disabled size="60"/>
</ele>
</div>';
                } else {
                    $name_input = '<input id="poster_name" name="poster_name" type="text" placeholder="お名前" size="30" required/>
                    <input id="password" name="password" type="password" placeholder="削除用パスワード" size="30" required/>';
                    $image_upload = '';
                }
                
                echo <<<EOD
<form action="" method="post" enctype="multipart/form-data">
<div class="post-form">
<ele class="design1">
{$name_input}
</ele><br>
<ele class="designt">
<textarea id="post_pv_value" name="post_pv_value" rows="8" cols="69" oninput="post_pv_onchange()" placeholder="このトピックに投稿してください..."></textarea>
</ele><br>
{$image_upload}
<ele class="designb">
<input class="submit-btn" type="submit" value="投稿" id="post_pv_button" disabled/>
</ele>
</div>
</form>
<script>
function post_pv_onchange() {
    let ppb = document.getElementById("post_pv_button");
    if (document.getElementById("post_pv_value").value.length == 0) {
        ppb.disabled = true;
    } else {
        ppb.disabled = false;
    }
};
</script>
EOD;
                
                // トピック内の投稿一覧
                $topic_posts = [];
                $all_posts = get_posts($posts_file);
                foreach ($all_posts as $post) {
                    if (isset($post['topic_id']) && $post['topic_id'] === $topic_id) {
                        $topic_posts[] = $post;
                    }
                }
                
                echo '<section>';
                if ($topic_posts) {
                    foreach (array_reverse($topic_posts) as $post) {
                        echo '<article>';
                        echo '<div class="link1">';
                        echo '<a href="?id=' . urlencode($post['id']) . '" style="text-decoration:none;color: black;">';
                        echo '<div class="info">';
                        if (strpos($post['author_id'], '@') === false) {
                            echo '<h2 style="display:inline;"><object>' . htmlspecialchars($post['author']) . '</object></h2>';
                        } else {
                            echo '<h2 style="display:inline;"><object><a style="text-decoration:none;" href="?user=' . htmlspecialchars(ltrim($post['author_id'], '@')) . '">' . htmlspecialchars($post['author']) . '</a></object></h2>';
                        }
                        echo ' <grey_t style="font-size:12.5px;">[ID_' . htmlspecialchars($post['author_id']) . ']</grey_t><br>';
                        echo '<time><grey_t>' . htmlspecialchars($post['created']) . '</grey_t></time><br><br>';
                        echo '</div>';
                        
                        if (isset($post['image'])) {
                            echo '<a href="' . htmlspecialchars($post['image']) . '" target="_blank">';
                            echo '<img class="post-image" src="' . htmlspecialchars($post['image']) . '"/></a><br>';
                        }
                        
                        echo '<p>' . format_text($post['body']) . '</p>';
                        echo '</a>';
                        
                        echo '<object>';
                        echo '<div class="action_btn">';
                        echo '<form style="display:inline" action="" method="post">';
                        echo '<button name="good_button" type="submit" value="' . htmlspecialchars($post['id']) . '">';
                        echo '<img src="./image/good.png" width="14" height="14" style="margin-right:4px;"/>';
                        echo 'グッド ' . ($post['good_count'] ?? 0);
                        echo '</button>';
                        echo '</form>';
                        echo '<form style="display:inline" action="?id=' . urlencode($post['id']) . '" method="get">';
                        echo '<button type="submit">↰ 返信 ' . ($post['reply_count'] ?? 0) . '</button>';
                        echo '</form>';
                        echo '</div></object>';
                        echo '</div>';
                        echo '</article>';
                    }
                } else {
                    echo '<p>まだ投稿がありません。最初の投稿をしてみましょう！</p>';
                }
                echo '</section>';
            } else {
                echo '<h1>トピックが見つかりません</h1>';
            }
        } else {
            // トピック一覧表示
            echo '<article>';
            echo '<h2>トピック一覧</h2>';
            echo '<p>興味のあるトピックを選んで投稿してみましょう！</p>';
            echo '</article>';
            
            // トピック作成フォーム（ログインユーザーのみ）
            if (isset($_SESSION['user'])) {
                echo <<<EOD
<div class="topic-create-form">
<h3>新しいトピックを作成</h3>
<form method="post">
<ele class="design1">
<input type="text" name="topic_title" placeholder="トピック名" required/>
</ele><br>
<ele class="designt">
<textarea name="topic_description" rows="4" cols="69" placeholder="トピックの説明を入力してください..." required></textarea>
</ele><br>
<ele class="designb">
<input type="submit" name="create_topic" value="トピック作成" class="submit-btn"/>
</ele>
</form>
</div>
EOD;
            } else {
                echo '<p><a href="?signin">ログイン</a>するとトピックを作成できます。</p>';
            }
            
            // 既存トピック一覧
            $topics = get_topics($topics_file);
            if ($topics) {
                echo '<section class="topic-list">';
                foreach (array_reverse($topics) as $topic) {
                    // 投稿数を更新
                    update_topic_post_count($topics_file, $topic['id']);
                    $topic = get_topic_by_id($topics_file, $topic['id']); // 更新後のデータを取得
                    
                    echo '<article class="topic-item">';
                    echo '<div class="topic-link">';
                    echo '<a href="?topic&view=' . urlencode($topic['id']) . '" style="text-decoration:none;color: black;">';
                    echo '<h3>' . htmlspecialchars($topic['title']) . '</h3>';
                    echo '<p>' . format_text($topic['description']) . '</p>';
                    echo '<div class="topic-meta">';
                    echo '<span>作成者: ';
                    if (strpos($topic['author_id'], '@') !== false) {
                        echo '<a href="?user=' . htmlspecialchars(ltrim($topic['author_id'], '@')) . '" class="author-link">' . htmlspecialchars($topic['author']) . '</a>';
                    } else {
                        echo htmlspecialchars($topic['author']);
                    }
                    echo '</span> | ';
                    echo '<span>' . htmlspecialchars($topic['created']) . '</span> | ';
                    echo '<span>投稿数: ' . ($topic['post_count'] ?? 0) . '</span>';
                    echo '</div>';
                    echo '</a>';
                    echo '</div>';
                    echo '</article>';
                }
                echo '</section>';
            } else {
                echo '<p>まだトピックがありません。最初のトピックを作成してみましょう！</p>';
            }
        }
        render_footer();
        break;

    case 'index':
    default:
        render_header('コミュニティ - Psnverse');
        
        // 投稿処理（すべてのユーザー）
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_pv_value'])) {
            $body = trim($_POST['post_pv_value']);
            
            if ($body) {
                if (isset($_SESSION['user'])) {
                    // ログインユーザーのみ画像アップロード可能
                    $name = trim($_POST['poster_name'] ?? $_SESSION['user']);
                    $author_id = '@' . $_SESSION['user_id'];
                    $image = handle_image_upload();
                    $id = add_post($posts_file, '', $body, $name, $author_id, $image);
                } else {
                    // ゲストユーザーは画像アップロード不可
                    $name = trim($_POST['poster_name'] ?? 'ゲスト');
                    $password = $_POST['password'] ?? '';
                    if ($password) {
                        $guest_id = generate_guest_id($_SERVER['REMOTE_ADDR']);
                        // ゲストユーザーは画像なし
                        $id = add_guest_post($posts_file, '', $body, $name, $guest_id, $password, null);
                    } else {
                        echo '<p style="color:red;">ゲスト投稿にはパスワードが必要です。</p>';
                        break;
                    }
                }
                if (isset($id)) {
                    header('Location: ?id=' . urlencode($id));
                    exit;
                }
            }
        }
        
        // グッド処理（すべてのユーザー）
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['good_button'])) {
            $current_user_id = $_SESSION['user_id'] ?? generate_guest_id($_SERVER['REMOTE_ADDR']);
            // ログインユーザーは@付き、ゲストは@なし
            if (!isset($_SESSION['user_id'])) {
                $current_user_id = $current_user_id; // ゲストID（@なし）
            } else {
                $current_user_id = '@' . $current_user_id; // 登録ユーザー（@付き）
            }
            toggle_good($posts_file, $_POST['good_button'], $current_user_id);
            header('Location: index.php');
            exit;
        }
        
        echo <<<EOD
<article>
<h2>ようこそ！Psnverseへ</h2>
<p>Psnverseは、PlayStationの非公式掲示板コミュニティです。<br>
現在このサイトはベータ版ですのでメッセージがリセットされている可能性がございます。
ガイド・ルールはこちら→<a href="./?rule">ガイド・ルール</a></p>
</article>
EOD;
        
        // 投稿フォーム
        if (isset($_SESSION['user'])) {
            $name_input = '<input id="poster_name" name="poster_name" type="text" placeholder="お名前" size="30" value="' . htmlspecialchars($_SESSION['user']) . '"/>
            <input id="password" name="password" type="hidden" value=""/>';
            $image_upload = '<div class="image-upload-container">
<ele class="designf">
<label style="width:51px;">画像<input type="file" name="post_pv_image" id="post_pv_image" onchange="selected_img.value = this.value.replace(/.*\\\\/g ,\'\');"/></label>
</ele>
<ele class="design1">
<input id="selected_img" type="text" value="画像が選択されていません。" disabled size="60"/>
</ele>
</div>';
        } else {
            $name_input = '<input id="poster_name" name="poster_name" type="text" placeholder="お名前" size="30" required/>
            <input id="password" name="password" type="password" placeholder="削除用パスワード" size="30" required/>';
            $image_upload = ''; // ゲストユーザーは画像アップロード不可
        }
        
        echo <<<EOD
<form action="" method="post" enctype="multipart/form-data">
<div class="post-form">
<ele class="design1">
{$name_input}
</ele><br>
<ele class="designt">
<textarea id="post_pv_value" name="post_pv_value" rows="8" cols="69" oninput="post_pv_onchange()" placeholder="文章を入力してください。"></textarea>
</ele><br>
{$image_upload}
<ele class="designb">
<input class="submit-btn" type="submit" value="投稿" id="post_pv_button" disabled/>
</ele>
</div>
</form>
<script>
function post_pv_onchange() {
    let ppb = document.getElementById("post_pv_button");
    if (document.getElementById("post_pv_value").value.length == 0) {
        ppb.disabled = true;
    } else {
        ppb.disabled = false;
    }
};
</script>
EOD;
        
        // 投稿一覧表示（トピックに属さない投稿のみ）
        $posts = get_posts($posts_file);
        $general_posts = [];
        foreach ($posts as $post) {
            if (!isset($post['topic_id']) || empty($post['topic_id'])) {
                $general_posts[] = $post;
            }
        }
        
        echo '<section>';
        if ($general_posts) {
            foreach (array_reverse($general_posts) as $post) {
                echo '<article>';
                echo '<div class="link1">';
                echo '<a href="?id=' . urlencode($post['id']) . '" style="text-decoration:none;color: black;">';
                echo '<div class="info">';
                if (strpos($post['author_id'], '@') === false) {
                    // ゲストユーザーはリンクなし
                    echo '<h2 style="display:inline;"><object>' . htmlspecialchars($post['author']) . '</object></h2>';
                } else {
                    // 登録ユーザーはユーザーページへリンク
                    echo '<h2 style="display:inline;"><object><a style="text-decoration:none;" href="?user=' . htmlspecialchars(ltrim($post['author_id'], '@')) . '">' . htmlspecialchars($post['author']) . '</a></object></h2>';
                }
                if (strpos($post['author_id'], '@') === false) {
                    // ゲストID（@なし）
                    echo ' <grey_t style="font-size:12.5px;">[ID_' . htmlspecialchars($post['author_id']) . ']</grey_t><br>';
                } else {
                    // 登録ユーザー（@あり）
                    echo ' <grey_t style="font-size:12.5px;">[ID_' . htmlspecialchars($post['author_id']) . ']</grey_t><br>';
                }
                echo '<time><grey_t>' . htmlspecialchars($post['created']) . '</grey_t></time><br><br>';
                echo '</div>';
                
                if (isset($post['image'])) {
                    echo '<a href="' . htmlspecialchars($post['image']) . '" target="_blank">';
                    echo '<img class="post-image" src="' . htmlspecialchars($post['image']) . '"/></a><br>';
                }
                
                echo '<p>' . format_text($post['body']) . '</p>';
                echo '</a>';
                
                echo '<object>';
                echo '<div class="action_btn">';
                echo '<form style="display:inline" action="" method="post">';
                echo '<button name="good_button" type="submit" value="' . htmlspecialchars($post['id']) . '">';
                echo '<img src="./image/good.png" width="14" height="14" style="margin-right:4px;"/>';
                echo 'グッド ' . ($post['good_count'] ?? 0);
                echo '</button>';
                echo '</form>';
                echo '<form style="display:inline" action="?id=' . urlencode($post['id']) . '" method="get">';
                echo '<button type="submit">↰ 返信 ' . ($post['reply_count'] ?? 0) . '</button>';
                echo '</form>';
                echo '</div></object>';
                echo '</div>';
                echo '</article>';
            }
        } else {
            echo '<p>投稿はまだありません。<a href="?topic">トピック</a>から話題を選んで投稿するか、ここで一般的な投稿をしてみましょう。</p>';
        }
        echo '</section>';
        render_footer();
        break;
}
?>
