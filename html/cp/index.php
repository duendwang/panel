<?php
$this->respond('GET', '/bot', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        $service->render('index.phtml', array('action' => 'bot', 'page' => 'cp/admin/bot.phtml'));
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('GET', '/user', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        $perms['view'] = checkPermission($app, 'viewuser', 'perms_user');
        $perms['approveUser'] = checkPermission($app, 'approveuser', 'perms_user');
        $perms['deleteUser'] = checkPermission($app, 'deleteuser', 'perms_user');
        $perms['editUsers'] = checkPermission($app, 'edituser', 'perms_user');
        $service->render('index.phtml', array('action' => 'user', 'page' => 'cp/admin/user.phtml', 'perms' => $perms));
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('GET', '/ban', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        $service->render('index.phtml', array('action' => 'ban', 'page' => 'cp/admin/ban.phtml'));
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('/user/list/unapproved', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        $perms['view'] = checkPermission($app, 'viewuser', 'perms_user');
        if ($perms['view']) {
            try {
                $statement = $app->db->prepare("SELECT authkey as id,username as user,email FROM auth WHERE approved=0 and verified=1");
                $statement->execute();
                $accounts = $statement->fetchAll();
            } catch (PDOException $ex) {
                error_log(addSlashes($ex->getMessage()) . "\r");
                $accounts = array();
            }
        } else {
            $accounts = array();
        }
        echo json_encode($accounts);
    } else {
        echo "failed";
    }
});

$this->respond('POST', '/user/approve/[i:id]', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        try {
            $statement = $app->db->prepare("UPDATE auth SET approved=1 WHERE authkey=?");
            $statement->execute(array($request->id));
        } catch (PDOException $ex) {
            error_log(addSlashes($ex->getMessage()) . "\r");
        }
        $response->redirect("/user", 302);
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('POST', '/user/delete/[i:id]', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        try {
            $statement = $app->db->prepare("DELETE FROM auth WHERE authkey=?");
            $statement->execute(array($request->id));
        } catch (PDOException $ex) {
            error_log(addSlashes($ex->getMessage()) . "\r");
        }
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('GET', '/settings', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        $service->render('index.phtml', array('action' => 'settings', 'page' => 'cp/user/settings.phtml'));
    } else {
        $response->redirect("/auth/login", 302);
    }
});