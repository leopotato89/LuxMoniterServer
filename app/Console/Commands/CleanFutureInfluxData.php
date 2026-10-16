<?php

namespace App\Console\Commands;

use App\Services\InfluxService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('influx:clean-future {--serial= : Serial thiết bị (mặc định xóa toàn bộ)} {--from= : Mốc thời gian bắt đầu (mặc định là hiện tại)}') ]
#[Description('Xóa dữ liệu có timestamp ở tương lai trong InfluxDB do lỗi trôi giờ hệ thống/worker')]
class CleanFutureInfluxData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(InfluxService $influx): int
    {
        $serial = $this->option('serial');
        $fromOption = $this->option('from');

        $from = ! empty($fromOption) ? Carbon::parse($fromOption, 'UTC') : null;

        $target = ! empty($serial) ? "thiết bị {$serial}" : 'tất cả thiết bị';
        $this->info("Đang xóa dữ liệu tương lai cho {$target}...");

        $success = $influx->deleteFutureData($from, $serial);

        if ($success) {
            $this->info('Đã xóa thành công dữ liệu sai lệch ở tương lai và làm mới bộ nhớ đệm.');

            return self::SUCCESS;
        }

        $this->error('Không thể xóa dữ liệu từ InfluxDB. Vui lòng kiểm tra kết nối và token.');

        return self::FAILURE;
    }
}
