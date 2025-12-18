<?php
require __DIR__.'/config/config.php';
require __DIR__.'/app/Lib/DB.php';
require __DIR__.'/app/Lib/Auth.php';
require __DIR__.'/app/Models/Settings.php';
require __DIR__.'/app/Models/Category.php';
require __DIR__.'/app/Models/User.php';
require __DIR__.'/app/Controllers/AdminController.php';
require __DIR__.'/app/Controllers/AuthController.php';
Auth::start();
$admin=new AdminController();
$auth=new AuthController();
if(!Auth::check()){ $auth->loginForm(); } else { $admin->dashboard(); }

