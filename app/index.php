<?php
require __DIR__.'/bootstrap.php';
$me=user();
$page=(string)($_GET['page'] ?? ($me ? 'dashboard' : 'login'));
$allowed=['login','register','dashboard','users','view','edit','delete','logout'];
if (!in_array($page,$allowed,true)) { http_response_code(404); exit('Page not found.'); }
if (!in_array($page,['login','register'],true) && !$me) go('login');
if ($page==='users' || $page==='delete' || (($page==='view' || $page==='edit') && (int)($_GET['id'] ?? 0)!==(int)$me['id'])) {
    if (!$me || $me['role']!=='admin') { http_response_code(403); exit('Administrator access required.'); }
}
$target=null;
if (in_array($page,['view','edit','delete'],true)) {
    $q=db()->prepare('SELECT id,name,email,phone,role,created_at FROM users WHERE id=?'); $q->execute([(int)($_GET['id'] ?? 0)]); $target=$q->fetch();
    if (!$target) { http_response_code(404); exit('User not found.'); }
}
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    try {
        if ($page==='logout') { $_SESSION=[]; session_destroy(); go('login'); }
        if ($page==='register') {
            [$name,$email,$phone]=profile_data(); $password=(string)($_POST['password'] ?? '');
            if (strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Password must contain 12 to 72 bytes.');
            $q=db()->prepare('INSERT INTO users(name,email,phone,password_hash) VALUES(?,?,?,?)'); $q->execute([$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT)]); go('login&registered=1');
        }
        if ($page==='login') {
            $q=db()->prepare('SELECT id,password_hash FROM users WHERE email=?'); $q->execute([strtolower(trim((string)($_POST['email'] ?? '')))]); $account=$q->fetch();
            if (!$account || !password_verify((string)($_POST['password'] ?? ''),$account['password_hash'])) throw new RuntimeException('Email or password is incorrect.');
            session_regenerate_id(true); $_SESSION['uid']=$account['id']; $_SESSION['csrf']=bin2hex(random_bytes(32)); go('dashboard');
        }
        if ($page==='edit') {
            [$name,$email,$phone]=profile_data();
            $q=db()->prepare('UPDATE users SET name=?,email=?,phone=? WHERE id=?'); $q->execute([$name,$email,$phone,$target['id']]);
            go('view&id='.$target['id']);
        }
        if ($page==='delete') {
            if ($target['role']==='admin') throw new RuntimeException('Administrator accounts cannot be deleted through this sample.');
            $q=db()->prepare('DELETE FROM users WHERE id=?'); $q->execute([$target['id']]); go('users');
        }
    } catch (PDOException $ex) { error_log($ex->getMessage()); $error=$ex->getCode()==='23000' ? 'That email is already registered.' : 'Unable to save. Please try again.'; }
      catch (RuntimeException $ex) { $error=$ex->getMessage(); }
}
if ($page==='logout') go('dashboard');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>User Portal</title><link rel="stylesheet" href="/style.css"></head><body>
<header><a href="/">User Portal</a><nav><?php if($me): ?><a href="/?page=dashboard">Dashboard</a><a href="/?page=view&id=<?=e($me['id'])?>">My profile</a><?php if($me['role']==='admin'):?><a href="/?page=users">Manage users</a><?php endif;?><form method="post" action="/?page=logout"><?=csrf()?><button>Logout</button></form><?php else:?><a href="/?page=login">Login</a><a href="/?page=register">Register</a><?php endif;?></nav></header>
<main><?php if($error):?><p class="alert"><?=e($error)?></p><?php endif;?>
<?php if($page==='login' || $page==='register'): ?>
<section class="card narrow"><h1><?=$page==='register' ? 'Create your account' : 'Welcome back'?></h1>
<?php if(isset($_GET['registered'])):?><p class="success">Registration complete. Please log in.</p><?php endif;?>
<form method="post"><?=csrf()?>
<?php if($page==='register'):?><label>Name<input name="name" required maxlength="100" autocomplete="name" value="<?=e($_POST['name'] ?? '')?>"></label><label>Phone<input name="phone" maxlength="20" autocomplete="tel" value="<?=e($_POST['phone'] ?? '')?>"></label><?php endif;?>
<label>Email<input type="email" name="email" required maxlength="190" autocomplete="email" value="<?=e($_POST['email'] ?? '')?>"></label>
<label>Password<input type="password" name="password" required <?=$page==='register' ? 'minlength="12" maxlength="72" autocomplete="new-password"' : 'autocomplete="current-password"'?>></label>
<button class="primary"><?=$page==='register' ? 'Register' : 'Login'?></button></form></section>
<?php elseif($page==='dashboard'):?>
<h1>Hello, <?=e($me['name'])?></h1><p>You are logged in as <?=e($me['role'])?>.</p><section class="card"><h2>Account details</h2><p><?=e($me['email'])?></p><a class="button" href="/?page=view&id=<?=e($me['id'])?>">View my profile</a><?php if($me['role']==='admin'):?><a class="button" href="/?page=users">Manage registered users</a><?php endif;?></section>
<?php elseif($page==='users'): $rows=db()->query('SELECT id,name,email,phone,role FROM users ORDER BY id DESC LIMIT 200')->fetchAll();?>
<h1>Manage users</h1><p>Latest 200 accounts. Only administrators can view this list.</p><section class="card table"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?=e($row['id'])?></td><td><?=e($row['name'])?></td><td><?=e($row['email'])?></td><td><?=e($row['phone'])?></td><td><?=e($row['role'])?></td><td><a href="/?page=view&id=<?=e($row['id'])?>">View</a> · <a href="/?page=edit&id=<?=e($row['id'])?>">Edit</a><?php if($row['role']!=='admin'):?> · <a href="/?page=delete&id=<?=e($row['id'])?>">Delete</a><?php endif;?></td></tr><?php endforeach;?></tbody></table></section>
<?php elseif($page==='view'):?>
<section class="card narrow"><h1>User details</h1><?php foreach(['name'=>'Name','email'=>'Email','phone'=>'Phone','role'=>'Role','created_at'=>'Registered'] as $field=>$label):?><p><strong><?=e($label)?>:</strong> <?=e($target[$field])?></p><?php endforeach;?><a class="button" href="/?page=edit&id=<?=e($target['id'])?>">Edit details</a></section>
<?php elseif($page==='edit'):?>
<section class="card narrow"><h1>Edit user</h1><form method="post"><?=csrf()?><?php foreach(['name'=>'Name','email'=>'Email','phone'=>'Phone'] as $field=>$label):?><label><?=e($label)?><input name="<?=e($field)?>" type="<?=$field==='email' ? 'email' : 'text'?>" <?=$field==='phone' ? '' : 'required'?> maxlength="<?=$field==='email' ? 190 : ($field==='phone' ? 20 : 100)?>" value="<?=e($_POST[$field] ?? $target[$field])?>"></label><?php endforeach;?><button class="primary">Save changes</button></form></section>
<?php elseif($page==='delete'):?>
<section class="card narrow"><h1>Delete user?</h1><p>Delete <?=e($target['name'])?> (<?=e($target['email'])?>)? This cannot be undone.</p><form method="post"><?=csrf()?><button class="danger">Confirm delete</button> <a href="/?page=users">Cancel</a></form></section>
<?php endif;?></main></body></html>
