<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = \App\Models\User::query()->get(['id','name','email','username','is_active','is_admin']);
echo 'TOTAL: '.$users->count().PHP_EOL;
foreach ($users as $u) {
    echo json_encode($u->toArray()).PHP_EOL;
}

// Kiểm tra mật khẩu user/6666888
$target = $users->first(fn($u) => strtolower($u->email ?? '')==='user' || strtolower($u->username ?? '')==='user');
if ($target) {
    $check = \Illuminate\Support\Facades\Hash::check('6666888', $target->password);
    echo 'USER FOUND: '.$target->email.' password_check='.($check?'OK':'FAIL').PHP_EOL;
} else {
    echo 'USER "user" NOT FOUND in DB'.PHP_EOL;
}
echo 'Done.'.PHP_EOL;
