<?php

namespace App\Http\Requests\Api\V1\Mobile;

use App\Http\Requests\Api\V1\Customer\SendCustomerOtpRequest;

/** Validate mobile-app OTP delivery requests using the customer identity rules. */
class SendMobileOtpRequest extends SendCustomerOtpRequest
{
}
