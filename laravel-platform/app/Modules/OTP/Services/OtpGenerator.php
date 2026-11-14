<?php

namespace App\Modules\OTP\Services;

class OtpGenerator
{
    public function generateNumeric(int $digits = 6): string
    {
        $digits = max(4, min(8, $digits));
        $max = (10 ** $digits) - 1;

        return str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }
}
