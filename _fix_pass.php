<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Hash;

$pass = '66668888';

foreach (['user', 'admin'] as $username) {
    $u = \App\Models\User::where('username', $username)->first();
    if (!$u) { echo "NO USER: $username\n"; continue; }
    $u->password = Hash::make($pass);
    $u->save();
    echo $username.' ('.$u->email.') -> password set to '.$pass.', check='.var_export(Hash::check($pass, $u->fresh()->password), true).PHP_EOL;
}
echo 'Done.'.PHP_EOL;
