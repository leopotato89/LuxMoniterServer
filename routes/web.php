<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| SPA (Vue) sở hữu mọi đường dẫn, TRỪ /api (để đường dẫn API sai vẫn trả 404 JSON)
| và /up (health check). Không loại trừ thì catch-all sẽ nuốt các URL API gõ sai
| và trả về HTML kèm 200 — client sẽ tưởng request thành công.
*/

Route::view('/{any?}', 'app')->where('any', '^(?!api\b|up$).*');
