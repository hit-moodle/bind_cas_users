<?php

    require_once('../config.php');

    // A suffix appended to every old local username
    $USERNAME_SUFFIX = '.local';

    // Contact who when meeting problem
    $CONTACT_EMAIL   = 'hit.moodle@gmail.com';

    // Debug mode?
    $DEBUG = false;

    $localusername  = moodle_strtolower(optional_param('lname', '', PARAM_NOTAGS));
    $localpassword  = optional_param('lpass', '', PARAM_TEXT);
    $remoteusername = moodle_strtolower(optional_param('rname', '', PARAM_NOTAGS));
    $confirmed      = optional_param('confirm', 0, PARAM_BOOL);
    
    $casauth   = get_auth_plugin('cas');
    $emailauth = get_auth_plugin('email');

    // Auth with cas
    if (empty($remoteusername)) {
        $casauth->connectCAS();
        phpCAS::setNoCasServerValidation();
        phpCAS::checkAuthentication();
		if (!phpCAS::isAuthenticated()) {
            phpCAS::forceAuthentication();
        }
        $remoteusername = trim(moodle_strtolower(phpCAS::getUser()));
        if (empty($remoteusername))
            die('CAS认证失败');
    }

    print_header('绑定CAS和本站帐号');

    echo '<p>您的统一认证(CAS)用户名是：<strong>'.$remoteusername.'</strong></p>';
    echo '<p>如果此用户名不正确，请关闭所有浏览器窗口，重新打开此页，再继续。</p>';

    // Auth locally
    if (!$emailauth->user_login($localusername.$USERNAME_SUFFIX, $localpassword)) {
        if (!empty($localusername)) {
            echo '<p><strong>您输入的本站用户名或密码错误，请重新输入。</strong></p>';
        }
        echo '<p>请输入您在<strong>本站</strong>的用户名和密码</p>';
        echo '<form method=post>';
        echo "用户名：<input type='text' name='lname' value='$localusername' />";
        echo "密码：<input type='password' name='lpass' value='$localpassword' />";
        echo "<input type='hidden' name='rname' value='$remoteusername' />";
        echo "<input type='submit'>";
        echo '</form>';
        die();
    }

    // Confirm
    $localuser = get_complete_user_data('username', $localusername.$USERNAME_SUFFIX);
    echo '<p>您在本地的信息：</p>';
    echo "<p>用户名：$localusername</p>";
    $course->id = 1;
    print_user($localuser, $course);
    if (!$confirmed) {
        echo '<p>如果确认以上信息正确，请点击确定按钮。否则，回退或关闭本页。</p>';
        echo '<form method=post>';
        echo "<input type='hidden' name='lname' value='$localusername' />";
        echo "<input type='hidden' name='lpass' value='$localpassword' />";
        echo "<input type='hidden' name='rname' value='$remoteusername' />";
        echo "<input type='hidden' name='confirm' value=1 />";
        echo "<input type='submit' value='确定'>";
        echo '</form>';
    } else {  // Update db table
        $newuser = new object();

        if ($localusername != $remoteusername) {
            $olduser = get_complete_user_data('username', $remoteusername);
            if ($olduser) {
                // Already have a local user with the same username
                if ($olduser->auth == 'cas') {
                    die('<p>您已经绑定过帐号，不能再次绑定。</p>');
                } else {
                    die('<p>发现用户名冲突，暂时不能绑定帐号。请将此页信息全文拷贝，发送给<a href="mailto:'.$CONTACT_EMAIL.'">'.$CONTACT_EMAIL.'</a>，他会尽力提供帮助</p>');
                }
            }
        }

        $newuser->id = $localuser->id;
        $newuser->username = $remoteusername;
        $newuser->auth = 'cas';

        if ($DEBUG) {
            die('<p>测试通过。</p>');
        }

        if (update_record('user', $newuser)) {
            echo '<p>已成功绑定帐号。以后将只能使用CAS登录。</p>';
        } else {
            print_object($newuser);
            die('<p>数据库更新出错。请将此页信息全文拷贝，发送给<a href="mailto:'.$CONTACT_EMAIL.'">'.$CONTACT_EMAIL.'</a>，他会尽力提供帮助</p>');
        }
    }

?>
