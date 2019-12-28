<?php

namespace App\Traits;

use Carbon\Carbon;

trait DateRangeTrait {

    /**
     * @return mixed
     */
    public function dateRange() {
        $date['start'] = request()->get('start', Carbon::createFromFormat('Y-m-d H:i:s', config('crm.start_date'))->toDateTimeString());
        $date['end'] = request()->get('end', Carbon::now()->addUnitNoOverflow('hour', 24, 'day')->toDateTimeString());
        return $date;
    }
}